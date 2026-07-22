<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\FieldOps\Enums\MaintenanceWorkOrderStatus;
use Modules\FieldOps\Http\Requests\CloseMaintenanceWorkOrderRequest;
use Modules\FieldOps\Http\Requests\ReturnMaintenanceWorkOrderRequest;
use Modules\FieldOps\Http\Requests\StoreMaintenanceWorkOrderRequest;
use Modules\FieldOps\Http\Requests\SubmitMaintenanceWorkOrderRequest;
use Modules\FieldOps\Http\Requests\TransitionMaintenanceWorkOrderRequest;
use Modules\FieldOps\Http\Resources\MaintenanceWorkOrderResource;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Services\MaintenanceWorkOrderService;

class MaintenanceWorkOrderController extends Controller
{
    private const RELATIONS = [
        'maintainable', 'maintenanceType', 'client', 'assignedEmployee', 'assignedBy',
        'returnedBy', 'events.actor', 'maintenanceRecord',
    ];

    public function __construct(private readonly MaintenanceWorkOrderService $service) {}

    public function assigned(): JsonResponse
    {
        $user = request()->user();
        $query = FoMaintenanceWorkOrder::query()
            ->with(self::RELATIONS)
            ->whereNotIn('status', [MaintenanceWorkOrderStatus::COMPLETED->value, MaintenanceWorkOrderStatus::CANCELLED->value])
            ->latest('scheduled_for');

        if (! $user->hasAnyRole(['super_admin', 'admin'])) {
            $query->where('assigned_employee_id', $user->employee_id ?: '__unlinked__');
        }

        $orders = $query->get();
        $this->loadEquipmentContext($orders);

        return response()->json(['success' => true, 'data' => MaintenanceWorkOrderResource::collection($orders)]);
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
