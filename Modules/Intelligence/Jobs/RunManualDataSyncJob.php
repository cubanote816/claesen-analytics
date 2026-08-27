<?php

declare(strict_types=1);

namespace Modules\Intelligence\Jobs;

use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\User;
use Modules\Intelligence\Services\ManualDataSyncService;

/**
 * CLA-439 — corre uno de los sync manuales (empleados/clientes/complejos/todo)
 * disparados desde "Mirror Sync Status", en cola para no bloquear el request
 * HTTP del botón (el chain clients/complexes puede tardar varios minutos
 * contra SQL Server real, mismo hallazgo que el schedule nocturno).
 *
 * Lock global (no por-tarea): dos manuales concurrentes sobre CLIENTES es el
 * caso real que rompió durante la verificación de CLA-439 (constraint único
 * en fo_clients.relation_id) — más simple y seguro serializar las 4 tareas
 * entre sí que sincronizar el lock caso por caso para un botón de uso
 * infrecuente.
 */
class RunManualDataSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Lock atómico real (mutex) — funciona con cualquier driver de cache.
     */
    private const LOCK_KEY = 'fieldops-manual-sync-lock';

    /**
     * Flag de solo-lectura para la UI ("¿hay un sync corriendo?"). Separado
     * del lock atómico de arriba porque `Cache::lock()` con el driver
     * `database` (usado en dev) guarda el lock en una tabla propia
     * (`cache_locks`), invisible para un `Cache::has()` normal — el flag es
     * un valor de cache plano, legible con cualquier driver.
     */
    public const RUNNING_FLAG_KEY = 'fieldops-manual-sync-running';

    private const LOCK_SECONDS = 600;

    public function __construct(
        public readonly string $task,
        public readonly ?int $triggeredByUserId,
    ) {}

    public function handle(ManualDataSyncService $service): void
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        if (! $lock->get()) {
            return;
        }

        Cache::put(self::RUNNING_FLAG_KEY, true, self::LOCK_SECONDS);

        try {
            match ($this->task) {
                'employees' => $service->syncEmployees(),
                'clients' => $service->syncClients(),
                'complexes' => $service->syncComplexes(),
                'all' => $service->syncAll(),
            };

            $this->notifyUser(
                title: __('intelligence::mirror_sync.notifications.manual_sync_done_title'),
                body: __('intelligence::mirror_sync.notifications.manual_sync_done_body', ['task' => $this->taskLabel()]),
                success: true,
            );
        } catch (\Throwable $e) {
            report($e);

            $this->notifyUser(
                title: __('intelligence::mirror_sync.notifications.manual_sync_failed_title'),
                body: $e->getMessage(),
                success: false,
            );
        } finally {
            Cache::forget(self::RUNNING_FLAG_KEY);
            $lock->release();
        }
    }

    private function taskLabel(): string
    {
        return __('intelligence::mirror_sync.tasks.' . $this->task);
    }

    private function notifyUser(string $title, string $body, bool $success): void
    {
        if ($this->triggeredByUserId === null) {
            return;
        }

        $user = User::find($this->triggeredByUserId);

        if ($user === null) {
            return;
        }

        $notification = Notification::make()->title($title)->body($body);
        $success ? $notification->success() : $notification->danger();
        $notification->sendToDatabase($user);
    }
}
