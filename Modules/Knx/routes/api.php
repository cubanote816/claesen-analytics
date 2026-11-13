<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\SetLocaleFromHeader;
use Modules\Knx\Http\Controllers\AcceptanceTestController;
use Modules\Knx\Http\Controllers\Auth\AuthController;
use Modules\Knx\Http\Controllers\Auth\SessionController;
use Modules\Knx\Http\Controllers\BoardController;
use Modules\Knx\Http\Controllers\ChatGptConnectionController;
use Modules\Knx\Http\Controllers\ClientController;
use Modules\Knx\Http\Controllers\ConflictController;
use Modules\Knx\Http\Controllers\DashboardController;
use Modules\Knx\Http\Controllers\DocumentController;
use Modules\Knx\Http\Controllers\EmployeeController;
use Modules\Knx\Http\Controllers\EventStreamController;
use Modules\Knx\Http\Controllers\Field\FieldBoardController;
use Modules\Knx\Http\Controllers\Field\FieldDeviceController;
use Modules\Knx\Http\Controllers\Field\FieldIssueController;
use Modules\Knx\Http\Controllers\Field\FieldProjectController;
use Modules\Knx\Http\Controllers\Field\FieldSessionController;
use Modules\Knx\Http\Controllers\Field\FieldTodayController;
use Modules\Knx\Http\Controllers\Field\FieldVisitController;
use Modules\Knx\Http\Controllers\FieldNotificationController;
use Modules\Knx\Http\Controllers\FunctionSpecController;
use Modules\Knx\Http\Controllers\IntentController;
use Modules\Knx\Http\Controllers\NotificationPreferenceController;
use Modules\Knx\Http\Controllers\OfficeIssueController;
use Modules\Knx\Http\Controllers\PlanAiAuthorizationController;
use Modules\Knx\Http\Controllers\PlanAnalysisController;
use Modules\Knx\Http\Controllers\PlanCleanProfileController;
use Modules\Knx\Http\Controllers\PlanMarkerController;
use Modules\Knx\Http\Controllers\PlanningController;
use Modules\Knx\Http\Controllers\PlanRoomController;
use Modules\Knx\Http\Controllers\ProjectController;
use Modules\Knx\Http\Controllers\ReportController;
use Modules\Knx\Http\Controllers\RoomController;
use Modules\Knx\Http\Controllers\ZoneController;
use Modules\Knx\Http\Middleware\EnsureKnxApp;

/*
 * KNX installation API — the contract consumed by Kantoor (office) and Veld
 * (field). See docs/BACKEND-API.md and docs/BACKEND-API-ZONES.md in the
 * electro-bertels-kantoor repo.
 *
 * Everything lives under /api/v1/knx (this provider's `api` prefix plus the
 * v1/knx below) and, except the session endpoints, behind `auth:sanctum` and
 * `organization:electro-bertels` so no Claesen token can reach it.
 */

Route::prefix('v1/knx')->name('knx.')->middleware(SetLocaleFromHeader::class)->group(function (): void {
    // K1 — session. Login/refresh are unauthenticated by definition and throttled
    // like the rest of the app's logins; logout needs a token to revoke.
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('auth.login');

    Route::post('auth/refresh', [AuthController::class, 'refresh'])
        ->middleware('throttle:10,1')
        ->name('auth.refresh');

    Route::post('auth/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('auth.logout');

    // Shared reads: one payload, both apps. Kept deliberately small — anything that
    // mutates office data stays in the office group.
    Route::middleware(['auth:sanctum', 'organization:electro-bertels', EnsureKnxApp::class.':'.EnsureKnxApp::ANY])
        ->group(function (): void {
            Route::get('zones', [ZoneController::class, 'index'])->name('zones.index');
            Route::get('zones/{id}', [ZoneController::class, 'show'])->name('zones.show');
        });

    // Veld: same tenant and same token machinery, a different app. The guard is what
    // stops a technician's token from reading the office API and vice versa.
    Route::middleware(['auth:sanctum', 'organization:electro-bertels', EnsureKnxApp::class.':'.EnsureKnxApp::FIELD])
        ->prefix('field')
        ->name('field.')
        ->group(function (): void {
            Route::get('session', [FieldSessionController::class, 'show'])->name('session');
            Route::get('today', [FieldTodayController::class, 'index'])->name('today');

            // V11.b — the project the app caches to work offline. `{code}` is
            // constrained for the same reason as the office routes: it must never
            // swallow the nested paths below it.
            Route::get('projects/{code}', [FieldProjectController::class, 'show'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.show');
            Route::get('projects/{code}/plans', [FieldProjectController::class, 'plans'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.plans');

            // KNX-47 — el plano real offline: la ficha de cada habitación y el modelo
            // confirmado de una página del plano.
            Route::get('projects/{code}/rooms', [FieldProjectController::class, 'rooms'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.rooms');
            // KNX-25 — los cuadros reales del proyecto, para que el registro no use los
            // de la maqueta.
            Route::get('projects/{code}/boards', [FieldProjectController::class, 'boards'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.boards');
            Route::get('projects/{code}/plans/{documentId}/model', [FieldProjectController::class, 'planModel'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.plans.model');

            // V11.f — the project's devices. The card's progress and the plan's
            // per-room counts come from here, and it answers the same shape the
            // registration does, so the app has one device shape and not two.
            Route::get('projects/{code}/devices', [FieldProjectController::class, 'devices'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.devices.index');

            // KNX-55 — las intenciones vigentes del proyecto, para leerlas sin red. El
            // campo las lee; escribir desde obra sigue pendiente de decisión.
            Route::get('projects/{code}/intents', [FieldProjectController::class, 'intents'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.intents.index');

            // V11.c — registering an apparatus from site. Idempotent by the app's own
            // `clientId`, so this is a POST the client may safely repeat.
            Route::post('projects/{code}/devices', [FieldDeviceController::class, 'store'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.devices.store');

            // KNX-53 — alta de cuadro y de módulo desde obra. Idempotentes por `clientId`
            // y nacen `unreviewed`: la oficina confirma.
            Route::post('projects/{code}/boards', [FieldBoardController::class, 'store'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.boards.store');
            Route::post('projects/{code}/boards/{boardId}/modules', [FieldBoardController::class, 'storeModule'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.boards.modules.store');

            // V11.d — an incident reported from site. Also idempotent by `clientId`.
            Route::post('projects/{code}/issues', [FieldIssueController::class, 'store'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.issues.store');

            // V11.e — closing a visit, in one of the three phases. Idempotent too.
            Route::post('projects/{code}/visits', [FieldVisitController::class, 'store'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.visits.store');
        });

    // Signed downloads live outside the authenticated group on purpose: an `<a href>`
    // from the browser cannot carry a bearer token. The signature IS the credential,
    // and it expires (see DocumentResource / ExportRecordResource).
    Route::middleware('signed')->group(function (): void {
        Route::get('documents/{id}/download', [DocumentController::class, 'download'])->name('documents.download');
        Route::get('exports/{id}/download', [ReportController::class, 'download'])->name('exports.download');
    });

    Route::middleware(['auth:sanctum', 'organization:electro-bertels', EnsureKnxApp::class.':'.EnsureKnxApp::OFFICE])
        ->group(function (): void {
            Route::get('me/session', [SessionController::class, 'show'])->name('me.session');

            // KNX-54 — las preferencias de aviso de la persona autenticada.
            Route::get('me/notification-preferences', [NotificationPreferenceController::class, 'show'])
                ->name('me.notification-preferences.show');
            Route::put('me/notification-preferences', [NotificationPreferenceController::class, 'update'])
                ->name('me.notification-preferences.update');

            // K7b — the dashboard aggregate (its inputs all exist now).
            Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard.show');

            // K2 — clients and projects.
            Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
            Route::get('clients/{id}', [ClientController::class, 'show'])->name('clients.show');
            Route::post('clients', [ClientController::class, 'store'])->name('clients.store');

            Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
            Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
            // {code} is a string ("C1618"), not a numeric id: constrain it so it can
            // never swallow the nested routes below (stats, devices, activity...).
            Route::get('projects/{code}', [ProjectController::class, 'show'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.show');
            // KNX-51 — edit the header (name, lead, deadline, site address).
            Route::patch('projects/{code}', [ProjectController::class, 'update'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.update');
            Route::get('projects/{code}/stats', [ProjectController::class, 'stats'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.stats');

            // KNX-44 — the canonical rooms of a project, with their aliases and their
            // zone. `PATCH /rooms/{id}` has no project in the URL: it resolves through
            // the room's project and the organization.
            Route::get('projects/{code}/rooms', [RoomController::class, 'index'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.rooms.index');
            Route::post('projects/{code}/rooms', [RoomController::class, 'store'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.rooms.store');
            Route::patch('rooms/{id}', [RoomController::class, 'update'])->name('rooms.update');

            // KNX-46 — la ficha de habitación: todo lo que la oficina muestra al hacer clic.
            Route::get('projects/{code}/rooms/{id}/sheet', [RoomController::class, 'sheet'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.rooms.sheet');

            // K3 — the dossier of a project.
            Route::get('projects/{code}/devices', [ProjectController::class, 'devices'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.devices');
            Route::get('projects/{code}/activity', [ProjectController::class, 'activity'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.activity');
            // KNX-3 — the cabinets, their modules, channels and links. Its board id is
            // the same string a plan marker's `boardId` carries.
            Route::get('projects/{code}/boards', [BoardController::class, 'index'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.boards');
            // KNX-52 — el alta y la edición de cuadros y módulos desde oficina. Toda
            // escritura deja `origin`/`reviewState`/`updatedBy` para que una
            // reimportación no pise lo editado a mano.
            Route::post('projects/{code}/boards', [BoardController::class, 'store'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.boards.store');
            Route::patch('projects/{code}/boards/{boardId}', [BoardController::class, 'update'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.boards.update');
            Route::post('projects/{code}/boards/{boardId}/modules', [BoardController::class, 'storeModule'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.boards.modules.store');
            Route::patch('projects/{code}/boards/{boardId}/modules/{slot}', [BoardController::class, 'updateModule'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.boards.modules.update');
            Route::delete('projects/{code}/boards/{boardId}/modules/{slot}', [BoardController::class, 'destroyModule'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.boards.modules.destroy');
            Route::put('projects/{code}/boards/{boardId}/modules/{slot}/channels/{channel}/links', [BoardController::class, 'updateLinks'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.boards.modules.links.update');
            Route::post('projects/{code}/boards/{boardId}/confirm', [BoardController::class, 'confirm'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.boards.confirm');
            Route::post('projects/{code}/boards/{boardId}/modules/{slot}/confirm', [BoardController::class, 'confirmModule'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.boards.modules.confirm');
            // V11.e — the closures signed off from site. The office is the reader: the
            // field app only ever sends them.
            Route::get('projects/{code}/visits', [ProjectController::class, 'visits'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.visits');
            // K4 — the field inbox. ack-all is declared first: it would otherwise be
            // a candidate match for {id} in a stricter route model binding setup.
            Route::post('notifications/ack-all', [FieldNotificationController::class, 'ackAll'])->name('notifications.ack-all');
            Route::get('notifications', [FieldNotificationController::class, 'index'])->name('notifications.index');
            Route::post('notifications/{id}/ack', [FieldNotificationController::class, 'ack'])->name('notifications.ack');
            // K5 — technicians and planning.
            Route::get('technicians', [PlanningController::class, 'technicians'])->name('technicians.index');
            // KNX-3 — the office people the project form can pick as `leadEmployeeId`.
            // `/technicians` is field-only, which is why a lead selector had no data.
            Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');

            Route::get('planning', [PlanningController::class, 'index'])->name('planning.index');
            Route::put('planning', [PlanningController::class, 'store'])->name('planning.store');
            Route::delete('planning', [PlanningController::class, 'destroy'])->name('planning.destroy');
            // K6 — the Conflictencentrum.
            Route::get('conflicts', [ConflictController::class, 'index'])->name('conflicts.index');
            Route::get('conflicts/{id}', [ConflictController::class, 'show'])->name('conflicts.show');
            Route::patch('conflicts/{id}', [ConflictController::class, 'update'])->name('conflicts.update');
            // KNX-56 — la oficina también abre incidencias (por ejemplo, la llamada del
            // cliente tras la entrega). `kind: malfunction` abre el ciclo validado.
            Route::post('projects/{code}/issues', [OfficeIssueController::class, 'store'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.issues.store');
            // KNX-55 — la intención por elemento: qué debe hacer un módulo, un canal o un
            // aparato. Es un upsert por la identidad del elemento, así que el front no
            // arrastra un id entre llamadas.
            Route::get('projects/{code}/intents', [IntentController::class, 'index'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.intents.index');
            Route::put('projects/{code}/intents', [IntentController::class, 'update'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.intents.update');
            Route::get('intents/{id}/versions', [IntentController::class, 'versions'])
                ->name('intents.versions');
            // K7 — readiness writes. The reads live in the shared group below: Veld uses
            // the same payload (its contract says so), but only the office changes checks.
            Route::patch('zones/{id}/checks/{key}', [ZoneController::class, 'updateCheck'])->name('zones.checks.update');
            // K8 — documents and reports. The upload (KNX-3) lives beside the reads so
            // the office has one shape for a document, create and list alike.
            Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
            Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
            Route::get('documents/{id}', [DocumentController::class, 'show'])->name('documents.show');
            // KNX-47 — corregir la planta de un plano ya subido, sin subir otra revisión.
            Route::patch('documents/{id}', [DocumentController::class, 'update'])->name('documents.update');

            // KNX-3 — the boards placed on a revision. The set belongs to the document,
            // so both routes resolve the document inside the project (`{code}`).
            Route::get('projects/{code}/plans/{documentId}/markers', [PlanMarkerController::class, 'show'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.plans.markers.show');
            Route::put('projects/{code}/plans/{documentId}/markers', [PlanMarkerController::class, 'update'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.plans.markers.update');

            // KNX-45 — the room model of a revision, per page. Same anchoring as the
            // markers: the set belongs to the document and the page.
            Route::get('projects/{code}/plans/{documentId}/rooms', [PlanRoomController::class, 'show'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.plans.rooms.show');
            Route::put('projects/{code}/plans/{documentId}/rooms', [PlanRoomController::class, 'update'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.plans.rooms.update');

            // KNX-48 — the clean-plan profile (group → role) of a revision page.
            Route::get('projects/{code}/plans/{documentId}/clean-profile', [PlanCleanProfileController::class, 'show'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.plans.clean-profile.show');
            Route::put('projects/{code}/plans/{documentId}/clean-profile', [PlanCleanProfileController::class, 'update'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.plans.clean-profile.update');

            // KNX-48 (plano IA) — per-document, per-provider authorization to send a plan
            // to an AI provider. No authorization = the document is never sent.
            Route::get('projects/{code}/plans/{documentId}/ai-authorizations', [PlanAiAuthorizationController::class, 'index'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.plans.ai-authorizations.index');
            Route::put('projects/{code}/plans/{documentId}/ai-authorizations/{provider}', [PlanAiAuthorizationController::class, 'update'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->where('provider', '[a-z0-9_-]+')
                ->name('projects.plans.ai-authorizations.update');
            Route::delete('projects/{code}/plans/{documentId}/ai-authorizations/{provider}', [PlanAiAuthorizationController::class, 'destroy'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->where('provider', '[a-z0-9_-]+')
                ->name('projects.plans.ai-authorizations.destroy');
            // "Sign in with ChatGPT" (prueba): each employee connects their OWN account.
            Route::get('chatgpt/connection', [ChatGptConnectionController::class, 'show'])
                ->name('chatgpt.connection.show');
            Route::post('chatgpt/connection/start', [ChatGptConnectionController::class, 'start'])
                ->name('chatgpt.connection.start');
            Route::post('chatgpt/connection/callback', [ChatGptConnectionController::class, 'callback'])
                ->name('chatgpt.connection.callback');
            Route::delete('chatgpt/connection', [ChatGptConnectionController::class, 'destroy'])
                ->name('chatgpt.connection.destroy');
            // KNX-48 (plano IA) — the AI proposal of a page: POST runs ONE authorized
            // provider and stores the validated proposal; GET reads the latest one.
            Route::get('projects/{code}/plans/{documentId}/analyses', [PlanAnalysisController::class, 'show'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.plans.analyses.show');
            Route::post('projects/{code}/plans/{documentId}/analyses', [PlanAnalysisController::class, 'store'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.plans.analyses.store');

            Route::get('reports/exports', [ReportController::class, 'index'])->name('reports.exports');
            Route::post('reports', [ReportController::class, 'store'])->name('reports.store');
            // K10 — server-sent events (optional per §6; polling keeps working without it).
            Route::get('events', [EventStreamController::class, 'stream'])->name('events.stream');

            // K9 — functional specs and acceptance tests.
            Route::get('projects/{code}/functions', [FunctionSpecController::class, 'index'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.functions');
            Route::patch('functions/{id}', [FunctionSpecController::class, 'update'])->name('functions.update');

            Route::get('projects/{code}/tests', [AcceptanceTestController::class, 'index'])
                ->where('code', '[A-Za-z0-9._-]+')
                ->name('projects.tests');
            Route::patch('tests/{id}', [AcceptanceTestController::class, 'update'])->name('tests.update');
        });
});
