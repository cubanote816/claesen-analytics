---
description:
---

Goal Description: Implementar la arquitectura core para un sistema BI RAG usando Laravel 12 y Filament V5.

Robust Async RAG: Procesamiento en segundo plano (Jobs) para evitar timeouts.

Legacy Data Integrity: Tipado estricto y trim automático para IDs de SQL Server.

Cost Efficiency: "Semantic Fingerprinting" (Hash MD5) para evitar llamadas redundantes a la API.

Filament V5 Compliance: Uso estricto de Schema y AdminPanelProvider para widgets.

User Review Required:

UX Change: Transición de "Click & Wait" a "Dispatch & Notify". La UI se actualizará automáticamente mediante polling.

1. Core Infrastructure (Cimientos Legacy)
   [NEW] App\Traits\Cafca\ReadOnlyTrait.php

Bloquea estrictamente save(), update(), delete(), create().

Lanza una LogicException si se intenta escribir en la BD Legacy.

[NEW] App\Models\Cafca\CafcaModel.php

Clase base abstracta para todos los modelos Legacy.

Define conexión: protected $connection = 'sqlsrv'.

Integrity Fix: Implementa un booted() o Accessor global que hace trim() automático a todos los atributos string (soluciona el padding CHAR(20) de SQL Server).

Strict Types: Fuerza public $incrementing = false y protected $keyType = 'string'.

2. Domain: Project Intelligence (AI & Sync)
   [NEW] App\Models\Cafca\Project.php

Modelo Legacy de solo lectura.

Relación: hasOne(ProjectInsight::class, 'project_id', 'id').

[NEW] App\Models\ProjectInsight.php

Tabla MySQL local ("El Cerebro").

Almacena: efficiency_score, ai_summary, y el campo crítico last_data_hash (Semantic Fingerprint).

[NEW] App\DTOs\ProjectAiPayload.php (Micro-Ajuste 1: DTO Pattern)

Clase responsable de la Higiene de Datos.

Recibe el modelo Project crudo.

Método toArray(): Elimina recursivamente claves con valor null, cadenas vacías, y campos de sistema (sys_created, uuid) para ahorrar tokens.

Método getHash(): Retorna el MD5 del array limpio para la comparación semántica.

[NEW] App\Services\GeminiService.php

Enfocado solo en el transporte (API Client).

Método generateProjectAudit(ProjectAiPayload $payload).

Maneja reintentos y logs de errores de la API.

[NEW] App\Jobs\AuditProjectJob.php

La unidad de trabajo (Worker). Implements Batchable.

Lógica:

Carga el Project (Legacy).

Instancia el ProjectAiPayload (limpieza).

Compara Payload->getHash() vs ProjectInsight->last_data_hash.

Si son iguales: Termina (Skip). Log: "Skipped by Semantic Cache".

Si son diferentes: Llama a GeminiService y actualiza ProjectInsight.

[NEW] App\Services\CafcaSyncService.php

Orquestador.

Método auditBatch(Collection $projectIds): Despacha el Bus::batch.

3. Filament V5 UI (Interfaz)
   [NEW] App\Filament\Resources\ProjectInsightResource.php

V5 Standard: Usa Filament\Schemas\Schema para definir el formulario (en lugar de Form).

Acción: Botón de cabecera "Run Audit" que dispara el Job Batch.

Micro-Ajuste 2: Polling:

En el método table(), añadir ->poll('5s').

Esto permite que la columna de "Estado" o "Score" se actualice sola mientras los Jobs terminan en segundo plano, mejorando la UX asíncrona.

4. Verification Plan (Pruebas)
   Unit Tests:

CafcaModel recorta espacios en blanco en IDs.

ProjectAiPayload elimina claves nulas correctamente.

ReadOnlyTrait lanza excepción al intentar escribir.

Feature Tests:

AuditProjectJob respeta el hash semántico (no llama a la API en la segunda ejecución).

Manual Verification:

Disparar una auditoría de 5 proyectos en Filament.

Verificar que la tabla se actualiza sola cada 5 segundos (Polling).

Módulo: ApiGateway (Preparado para Futuro)
Estado: Ready for Implementation (Phase 3).

Contrato de Integración (Manual):

Endpoint de Salida (Hub -> Sports App):

GET /api/v1/projects/{id}/financial-snapshot

Uso: Un botón en la App Deportiva que dice "Ver Estado Financiero". El operario lo pulsa y ve si el proyecto está pagado o en deuda, sin salir de su app.

Trigger: Manual (Usuario App Deportiva).

Endpoint de Entrada (Sports App -> Hub):

POST /api/v1/projects/{id}/audit-assets

Uso: Un botón en el Hub que dice "Importar Inventario Físico".

Lógica:

El Hub pide datos a la App Deportiva.

Si recibe datos (ej: 50 luminarias), los guarda en project_insights.external_data.

El Hub compara (50 físicas vs 40 presupuestadas) y genera una alerta visual.

Trigger: Manual (Project Manager en el Hub).

Verificar en logs que Gemini solo se invocó una vez por proyecto.
