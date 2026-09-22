<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\FieldOps\Http\Requests\CloseMaintenanceWorkOrderRequest;
use Modules\FieldOps\Http\Requests\ExecuteMaintenanceWorkOrderRequest;
use Modules\FieldOps\Http\Requests\ReturnMaintenanceWorkOrderRequest;
use Modules\FieldOps\Http\Requests\StoreMaintenanceWorkOrderRequest;
use Modules\FieldOps\Http\Requests\SubmitMaintenanceWorkOrderRequest;
use Modules\FieldOps\Http\Requests\TransitionMaintenanceWorkOrderRequest;
use Modules\FieldOps\Http\Requests\WorkOrderQueryRequest;
use Modules\FieldOps\Http\Resources\MaintenanceWorkOrderResource;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Services\MaintenanceWorkOrderService;
use Modules\FieldOps\Services\WorkOrderReportingService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaintenanceWorkOrderController extends Controller
{
    private const RELATIONS = [
        'maintainable', 'maintenanceType', 'client', 'assignedEmployee', 'assignedBy',
        'returnedBy', 'events.actor', 'maintenanceRecord',
    ];

    public function __construct(
        private readonly MaintenanceWorkOrderService $service,
        private readonly WorkOrderReportingService $reporting,
    ) {}

    public function assigned(WorkOrderQueryRequest $request): JsonResponse
    {
        $orders = $this->reporting
            ->query($request->user(), $request->filters(), WorkOrderReportingService::BUCKET_OPEN)
            ->with(self::RELATIONS)
            ->latest('scheduled_for')
            ->get();

        $this->loadEquipmentContext($orders);

        return response()->json(['success' => true, 'data' => MaintenanceWorkOrderResource::collection($orders)]);
    }

    /**
     * Closed work orders. Paginated since CLA-578: the response keeps "data" as
     * a plain array, so existing consumers that ignore "meta" keep working.
     */
    public function history(WorkOrderQueryRequest $request): JsonResponse
    {
        $orders = $this->reporting
            ->query($request->user(), $request->filters(), WorkOrderReportingService::BUCKET_CLOSED)
            ->with(self::RELATIONS)
            ->latest('scheduled_for')
            ->paginate($request->perPage())
            ->withQueryString();

        $this->loadEquipmentContext($orders->getCollection());

        return MaintenanceWorkOrderResource::collection($orders)
            ->additional(['success' => true])
            ->response();
    }

    /**
     * Aggregates for the Reports screen (CLA-578). Exposes nothing beyond what
     * history() already returns for the same caller.
     */
    public function stats(WorkOrderQueryRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->reporting->stats($request->user(), $request->filters()),
        ]);
    }

    /**
     * CSV export of the same rows history() would return, streamed in chunks so
     * a wide date range does not build the whole file in memory.
     */
    public function export(WorkOrderQueryRequest $request): StreamedResponse
    {
        $query = $this->reporting
            ->query($request->user(), $request->filters(), WorkOrderReportingService::BUCKET_CLOSED)
            ->with(['maintenanceType', 'client', 'maintainable'])
            ->latest('scheduled_for');

        $filename = 'werkorders-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, $this->reporting->exportHeadings());

            $query->chunkById(500, function (EloquentCollection $orders) use ($handle): void {
                foreach ($orders as $order) {
                    fputcsv($handle, $this->reporting->exportRow($order));
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function show(FoMaintenanceWorkOrder $workOrder): JsonResponse
    {
        $this->authorizeWorkerOrPlanner($workOrder);

        $workOrder->load(self::RELATIONS);
        $this->loadEquipmentContext(new EloquentCollection([$workOrder]));

        return response()->json(['success' => true, 'data' => new MaintenanceWorkOrderResource($workOrder)]);
    }

    public function storeForLuminaire(StoreMaintenanceWorkOrderRequest $request, Luminaire $luminaire): JsonResponse
    {
        return $this->store($request, $luminaire::class, $luminaire->id);
    }

    public function storeForElectricalBoard(StoreMaintenanceWorkOrderRequest $request, ElectricalBoard $electricalBoard): JsonResponse
    {
        return $this->store($request, $electricalBoard::class, $electricalBoard->id);
    }

    public function executeForLuminaire(ExecuteMaintenanceWorkOrderRequest $request, Luminaire $luminaire): JsonResponse
    {
        return $this->execute($request, $luminaire::class, $luminaire->id);
    }

    public function executeForElectricalBoard(ExecuteMaintenanceWorkOrderRequest $request, ElectricalBoard $electricalBoard): JsonResponse
    {
        return $this->execute($request, $electricalBoard::class, $electricalBoard->id);
    }

    public function start(TransitionMaintenanceWorkOrderRequest $request, FoMaintenanceWorkOrder $workOrder): JsonResponse
    {
        return $this->response($this->service->start($workOrder, $request->user()->id));
    }

    public function submit(SubmitMaintenanceWorkOrderRequest $request, FoMaintenanceWorkOrder $workOrder): JsonResponse
    {
        return $this->response($this->service->submit($workOrder, $request->validated(), $request->user()->id));
    }

    public function returnForCorrection(ReturnMaintenanceWorkOrderRequest $request, FoMaintenanceWorkOrder $workOrder): JsonResponse
    {
        return $this->response($this->service->returnForCorrection(
            $workOrder,
            $request->user()->id,
            $request->validated('return_reason'),
        ));
    }

    public function validateAndClose(CloseMaintenanceWorkOrderRequest $request, FoMaintenanceWorkOrder $workOrder): JsonResponse
    {
        return $this->response($this->service->close($workOrder, $request->user()->id));
    }

    public function overrideAndClose(CloseMaintenanceWorkOrderRequest $request, FoMaintenanceWorkOrder $workOrder): JsonResponse
    {
        return $this->response($this->service->close($workOrder, $request->user()->id, $request->validated('override_reason')));
    }

    private function store(StoreMaintenanceWorkOrderRequest $request, string $type, int $id): JsonResponse
    {
        $order = $this->service->create(array_merge($request->validated(), [
            'maintainable_type' => $type,
            'maintainable_id' => $id,
        ]), $request->user()->id);

        return $this->response($order, 201);
    }

    private function execute(ExecuteMaintenanceWorkOrderRequest $request, string $type, int $id): JsonResponse
    {
        $employeeId = $request->user()->employee_id;
        if (! $employeeId) {
            throw ValidationException::withMessages([
                'employee_id' => __('fieldops::resource.work_orders.validation.assignee_requires_user'),
            ]);
        }

        $order = $this->service->createAndClose(array_merge($request->validated(), [
            'maintainable_type' => $type,
            'maintainable_id' => $id,
        ]), $request->user()->id, $employeeId);

        return $this->response($order, 201);
    }

    private function response(FoMaintenanceWorkOrder $order, int $status = 200): JsonResponse
    {
        $order->load(self::RELATIONS);
        $this->loadEquipmentContext(new EloquentCollection([$order]));

        return response()->json([
            'success' => true,
            'data' => new MaintenanceWorkOrderResource($order),
        ], $status);
    }

    private function loadEquipmentContext(EloquentCollection $orders): void
    {
        $orders->loadMorph('maintainable', [
            Luminaire::class => [
                'luminaireType',
                'luminaireFrame.structures.terrains.complex.client',
            ],
            ElectricalBoard::class => [
                'electricalBoardType',
                'complexes.client',
                'terrains.complex.client',
                'structures.terrains.complex.client',
            ],
        ]);
    }

    private function authorizeWorkerOrPlanner(FoMaintenanceWorkOrder $order): void
    {
        $user = request()->user();
        abort_unless($user->hasAnyRole(['super_admin', 'admin']) || ($user->employee_id && $user->employee_id === $order->assigned_employee_id), 403);
    }
}
