# Mailing Platform — Documento Maestro

> Fuente de verdad del módulo Mailing. Leer al inicio de cada sesión del sprint.
> Creado: 2026-05-27 | Rama de trabajo: `feature/mailing-platform`

---

## Visión del producto

Construir una **plataforma de gestión de campañas** para Claesen Verlichting, no solo un sistema de envío de correos. El alcance cubre:

- Diseño y versionado de plantillas
- Workflow de aprobación antes del envío
- Envío seguro y medible vía Microsoft Graph (transporte inicial)
- Tracking de apertura, clic, rebote y baja
- Lista de supresión automática y manual
- Métricas centradas en clics y conversiones (no en aperturas)
- Headers de cumplimiento técnico (RFC 8058 / Gmail / Yahoo)
- Auditoría de quién hizo qué y cuándo
- Arquitectura preparada para migrar a ESP externo sin reescritura

**Principio rector:** el primer objetivo no es enviar más correos. Es enviar con visibilidad completa de lo que ocurre después del envío.

---

## Decisiones arquitectónicas fijadas

### Transporte de email

| Decisión | Detalle |
|---|---|
| **Fase actual** | Microsoft Graph (ya implementado, autenticado via Azure OAuth) |
| **Fase futura** | ESP externo (Resend / Postmark / Mailgun) cuando gerencia apruebe |
| **Cómo se migrará** | `MarketingCampaignInterface` ya existe — se añade un `ResendMailer` y se cambia config. El resto del sistema no toca. |
| **Transaccional vs. comercial** | Microsoft Graph para ambos en Fase 1. Separación de dominios en Fase futura. |

### Base de datos

**MySQL 8.4** — igual que el resto del proyecto. No se introduce PostgreSQL.

Razones:
- `mailing_messages` hace `JOIN` con `prospects_prospects` — misma DB es obligatorio.
- MySQL 8.4 tiene JSON funcional, CTEs y window functions suficientes para este volumen.
- Si `mailing_message_events` crece a decenas de millones de filas, se mueven a ClickHouse. No antes.

### Audiencias / Contactos

Los contactos **son** `Modules/Prospects`. No se construye un sistema paralelo de contactos.

- `Modules/Prospects` = fuente de verdad de audiencias (quiénes son, federación, región, idioma)
- `Modules/Mailing` = lógica de campaña (cómo los contactamos, qué pasó)

`mailing_messages` referencia `prospect_id`. No duplica datos del prospecto.

### Stack final

| Capa | Tecnología |
|---|---|
| Transporte | Microsoft Graph (Laravel `microsoft-graph` mailer) |
| Colas | Laravel Queues + Redis (ya en stack) |
| DB | MySQL 8.4 |
| Tracking | Pixel propio + redirect propio (rutas Mailing) |
| Rebotes | Parser NDR vía command Laravel programado |
| UI Admin | Filament V5 |
| Aperturas | Señal débil — no KPI principal (Apple MPP, proxies corporativos) |
| KPIs reales | Clics únicos, CTR, CTOR, bajas, quejas |

---

## Estado actual del módulo (auditoría)

### Lo que existe y funciona

| Componente | Archivo | Observaciones |
|---|---|---|
| `EmailTemplate` (name, subject, body) | `Modules/Mailing/Models/EmailTemplate.php` | Minimal, sin versión ni categoría |
| `MicrosoftGraphMailer` | `Modules/Mailing/Services/MicrosoftGraphMailer.php` | Operativo |
| `SaaSMailer` (stub) | `Modules/Mailing/Services/SaaSMailer.php` | Placeholder para ESP futuro |
| `MarketingCampaignInterface` | `App/Contracts/` | Patrón correcto |
| `UnsubscribeController` (web + API) | `Modules/Mailing/Http/Controllers/` | Token HMAC-SHA256 correcto |
| Filament resource `EmailTemplateResource` | `Modules/Mailing/Filament/` | Operativo |
| Vista unsubscribe | `Modules/Mailing/resources/views/` | Operativa |

### Lo que existe en el módulo incorrecto (a mover en Fase 0)

| Componente | Dónde vive ahora | Dónde debe vivir |
|---|---|---|
| `ProspectMailCampaign` | `Modules/Prospects/Models/` | `Modules/Mailing/Models/Campaign` |
| `ProspectMailLog` | `Modules/Prospects/Models/` | `Modules/Mailing/Models/CampaignMessage` |
| `ExecuteMailingCampaignJob` | `Modules/Prospects/Jobs/` | `Modules/Mailing/Jobs/` |
| `ProspectMailCampaignResource` (Filament) | `Modules/Prospects/Filament/` | `Modules/Mailing/Filament/` |

### Gaps críticos (a construir en Fase 1)

| Gap | Riesgo actual |
|---|---|
| Sin `mailing_message_events` | El sistema es ciego tras el envío — no sabe si rebotó, abrió o reclamó |
| Sin `mailing_suppression_list` | Puede reenviar a quien marcó spam o rebotó duro |
| Sin headers `List-Unsubscribe` | Incumple requisitos Gmail/Yahoo para emisores medios |
| Sin tracking de apertura/clic | Sin datos de engagement |
| Sin workflow de aprobación | Cualquier usuario puede lanzar campaña masiva |
| `sleep(1)` en el job | 500 prospectos = ~8 min bloqueando un worker completo |

---

## Modelo de datos objetivo

### Tablas nuevas a crear

```sql
-- Plantillas con versión y categoría
mailing_templates
  id, name, subject, body (LONGTEXT),
  category ENUM('commercial','transactional'),
  variables JSON,          -- [{"key":"name","label":"Nombre del club","example":"KVC Westerlo"}]
  version TINYINT DEFAULT 1,
  parent_id (FK → mailing_templates, nullable),
  created_by (FK → users),
  updated_at, created_at

-- Campañas con workflow de estado
mailing_campaigns
  id, name, objective VARCHAR(100),
  template_id (FK → mailing_templates),
  subject_snapshot TEXT,
  body_snapshot LONGTEXT,
  audience_type ENUM('all_subscribed','segment','manual'),
  audience_filters JSON,   -- reglas del segmento dinámico
  status ENUM('draft','review','approved','sending','completed','failed','cancelled'),
  scheduled_at DATETIME nullable,
  timezone VARCHAR(50) DEFAULT 'Europe/Brussels',
  approved_by (FK → users, nullable),
  approved_at DATETIME nullable,
  created_by (FK → users),
  total_count INT DEFAULT 0,
  sent_count INT DEFAULT 0,
  failed_count INT DEFAULT 0,
  skipped_count INT DEFAULT 0,
  finished_at DATETIME nullable,
  created_at, updated_at

-- Un mensaje por destinatario por campaña
mailing_messages
  id, campaign_id (FK → mailing_campaigns),
  prospect_id (FK → prospects_prospects),
  email VARCHAR(255),
  status ENUM('queued','sent','delivered','failed','skipped'),
  tracking_token VARCHAR(64) UNIQUE,  -- para pixel y click redirect
  sent_at DATETIME nullable,
  created_at, updated_at

-- Eventos de entrega (append-only)
mailing_message_events
  id, message_id (FK → mailing_messages),
  event_type ENUM('sent','delivered','opened','clicked',
                  'bounced_hard','bounced_soft','complained','unsubscribed'),
  occurred_at DATETIME,
  metadata JSON,           -- {"link_url":"...","ip":"...","bounce_code":"550"}
  INDEX(message_id, event_type)
  INDEX(event_type, occurred_at)

-- Lista de supresión global
mailing_suppression_list
  id, email VARCHAR(255) UNIQUE,
  prospect_id (FK → prospects_prospects, nullable),
  reason ENUM('unsubscribed','hard_bounce','spam_complaint',
              'soft_bounce_limit','manual'),
  source_campaign_id (FK → mailing_campaigns, nullable),
  notes TEXT nullable,
  suppressed_at DATETIME,
  suppressed_by (FK → users, nullable)

-- Preferencias de categoría por contacto
mailing_contact_preferences
  id, prospect_id (FK → prospects_prospects) UNIQUE per category,
  category VARCHAR(100),   -- 'offers','newsletter','events'
  subscribed BOOLEAN DEFAULT TRUE,
  updated_at
```

### Tablas a migrar desde Prospects (Fase 0)

```
prospect_mail_campaigns  →  mailing_campaigns   (con renaming de columnas)
prospect_mail_logs       →  mailing_messages     (con renaming de columnas)
```

---

## Fases de desarrollo

### Fase 0 — Consolidación arquitectónica

**Objetivo:** limpiar deuda técnica antes de construir encima. Cero funcionalidad nueva.

| Ticket | Título | Tipo | Prioridad |
|---|---|---|---|
| MAI-001 | Mover ProspectMailCampaign → Mailing module | Refactor | Urgente |
| MAI-002 | Mover ProspectMailLog → Mailing module | Refactor | Urgente |
| MAI-003 | Mover ExecuteMailingCampaignJob → Mailing + fix sleep(1) | Refactor | Urgente |
| MAI-004 | Mover Filament Campaign resources → Mailing | Refactor | Alta |
| MAI-005 | Actualizar referencias en Prospects — no romper compatibilidad | Chore | Alta |

### Fase 1 — MVP Robusto

**Objetivo:** cumplimiento técnico, visibilidad de eventos, supresión automática, aprobación antes de enviar.

| Ticket | Título | Tipo | Prioridad | Depende de |
|---|---|---|---|---|
| MAI-006 | config/config.php — constantes del módulo | Chore | Alta | — |
| MAI-007 | Migración mailing_suppression_list | Migration | Alta | — |
| MAI-008 | SuppressionList model + SuppressionService | Feature | Alta | MAI-007 |
| MAI-009 | Migración mailing_message_events | Migration | Alta | Fase 0 |
| MAI-010 | MessageEvent model + enum MessageEventType | Feature | Alta | MAI-009 |
| MAI-011 | EmailTemplate — añadir category, variables, version | Feature | Alta | MAI-006 |
| MAI-012 | Campaign workflow — estados + approved_by | Feature | Alta | Fase 0 |
| MAI-013 | Tracking pixel apertura (GET /mailing/track/open/{token}) | Feature | Alta | MAI-009, MAI-010 |
| MAI-014 | Tracking click redirect (GET /mailing/track/click/{token}/{hash}) | Feature | Alta | MAI-009, MAI-010 |
| MAI-015 | Headers List-Unsubscribe + List-Unsubscribe-Post en ProspectCampaignMail | Compliance | Urgente | — |
| MAI-016 | NDR/bounce parser command (rebotes automáticos a suppression) | Feature | Media | MAI-008 |
| MAI-017 | CampaignPolicy — RBAC por recurso | Security | Alta | Fase 0 |
| MAI-018 | Filament — Campaign management (create, review, approve, view) | Feature | Alta | MAI-012, MAI-017 |
| MAI-019 | Filament — Dashboard métricas básicas (widget) | Feature | Media | MAI-009, MAI-010 |
| MAI-020 | Feature tests Fase 1 | Test | Alta | MAI-006 a MAI-018 |

### Fase 2 — Automatización (backlog)

| Ticket | Título | Tipo |
|---|---|---|
| MAI-021 | Segmentos dinámicos basados en eventos | Feature |
| MAI-022 | A/B testing de asunto (split + winner automático por CTR) | Feature |
| MAI-023 | Follow-up automático por comportamiento | Feature |
| MAI-024 | Programación por franja horaria (Europe/Brussels) | Feature |
| MAI-025 | Página de preferencias de categoría | Feature |
| MAI-026 | Webhook handler para ESP externo (cuando se adopte) | Feature |
| MAI-027 | Alertas de entregabilidad (queja > 0.08%, rebote > 5%) | Feature |

### Fase 3 — Inteligencia Comercial (backlog)

| Ticket | Título | Tipo |
|---|---|---|
| MAI-031 | Engagement scoring 0-100 por prospecto | Feature |
| MAI-032 | Segmento automático "prospectos fríos" | Feature |
| MAI-033 | Recomendación de franja horaria por región/idioma | Feature |
| MAI-034 | Predicción de riesgo de baja (señales de desengagement) | Feature |
| MAI-035 | Atribución de campaña → proyecto Cafca (revenue attribution) | Feature |
| MAI-036 | Reporte ejecutivo consolidado | Feature |

---

## Tickets Fase 0 — Alcance detallado

---

### MAI-001 — Mover ProspectMailCampaign → Modules/Mailing

**Tipo:** Refactor | **Prioridad:** Urgente | **Depende de:** —

#### Alcance
Mover el modelo y migración de `ProspectMailCampaign` a `Modules/Mailing`.

#### Cambios
- Crear `Modules/Mailing/Models/Campaign.php` (renombrado de `ProspectMailCampaign`)
- Crear migración que renombra tabla `prospect_mail_campaigns` → `mailing_campaigns`
- Renombrar columna `user_id` → `created_by` para consistencia con el schema objetivo
- Actualizar namespace y relaciones

#### Criterios de aceptación
- `\Modules\Mailing\Models\Campaign` existe y persiste en `mailing_campaigns`
- La relación `Campaign::messages()` → `CampaignMessage` funciona (después de MAI-002)
- Todas las referencias a `ProspectMailCampaign` en `ExecuteMailingCampaignJob` actualizadas

---

### MAI-002 — Mover ProspectMailLog → Modules/Mailing

**Tipo:** Refactor | **Prioridad:** Urgente | **Depende de:** MAI-001

#### Alcance
Mover el modelo y migración de `ProspectMailLog` a `Modules/Mailing`.

#### Cambios
- Crear `Modules/Mailing/Models/CampaignMessage.php`
- Crear migración que renombra `prospect_mail_logs` → `mailing_messages`
- Añadir columna `tracking_token VARCHAR(64) UNIQUE` (generado en creación)
- Actualizar columna `status` para incluir `'queued'` y `'delivered'`

#### Criterios de aceptación
- `\Modules\Mailing\Models\CampaignMessage` existe y persiste en `mailing_messages`
- `tracking_token` se genera automáticamente en el boot del modelo
- La relación `CampaignMessage::events()` → `MessageEvent` está declarada (usable en MAI-010)

---

### MAI-003 — Mover ExecuteMailingCampaignJob → Mailing + fix sleep(1)

**Tipo:** Refactor | **Prioridad:** Urgente | **Depende de:** MAI-001, MAI-002

#### Alcance
Mover el job al módulo correcto y reemplazar el `sleep(1)` bloqueante.

#### Cambios
- Mover a `Modules/Mailing/Jobs/ExecuteCampaignJob.php`
- Reemplazar `sleep(1)` por throttle configurable vía `config('mailing.send_delay_ms')` usando `usleep()`
- Actualizar todos los namespaces internos al job
- Actualizar el binding en `MailingServiceProvider`

#### Criterios de aceptación
- El job corre bajo `Modules\Mailing\Jobs`
- No hay `sleep()` en el loop — se usa `usleep(config('mailing.send_delay_ms', 500) * 1000)`
- Con 500 prospectos el worker no bloquea más de lo configurado

---

### MAI-004 — Mover Filament Campaign resources → Mailing

**Tipo:** Refactor | **Prioridad:** Alta | **Depende de:** MAI-001, MAI-002

#### Alcance
Mover `ProspectMailCampaignResource` y `ProspectMailLogResource` de Prospects a Mailing.

#### Cambios
- Mover a `Modules/Mailing/Filament/Resources/`
- Renombrar a `CampaignResource` y `CampaignMessageResource`
- Actualizar tabla objetivo y relaciones
- Registrar en `MailingServiceProvider` (desregistrar de `ProspectsServiceProvider`)

#### Criterios de aceptación
- Las páginas de campaña cargan desde el panel Filament bajo el módulo Mailing
- No hay referencias a estos recursos en el módulo Prospects

---

### MAI-005 — Actualizar referencias en Prospects

**Tipo:** Chore | **Prioridad:** Alta | **Depende de:** MAI-001, MAI-002, MAI-003, MAI-004

#### Alcance
Eliminar toda referencia a los modelos movidos dentro de `Modules/Prospects`.

#### Cambios
- Limpiar imports de `ProspectMailCampaign` y `ProspectMailLog` en cualquier archivo de Prospects
- Verificar que `Prospect` no tenga relaciones a modelos de Mailing (las relaciones van en la dirección Mailing → Prospects, no al revés)
- Eliminar archivos del módulo Prospects que ya no corresponden

#### Criterios de aceptación
- `grep -r "ProspectMailCampaign\|ProspectMailLog\|ExecuteMailingCampaignJob" Modules/Prospects/` retorna vacío
- `php artisan test` pasa sin errores tras la limpieza

---

## Tickets Fase 1 — Alcance detallado

---

### MAI-006 — config/config.php — constantes del módulo

**Tipo:** Chore | **Prioridad:** Alta | **Depende de:** —

#### Cambios

```php
return [
    'send_delay_ms'        => env('MAILING_SEND_DELAY_MS', 500),
    'bounce_soft_limit'    => 3,
    'spam_rate_alert'      => 0.0008,
    'hard_bounce_alert'    => 0.05,
    'unsubscribe_domain'   => env('MAILING_UNSUBSCRIBE_DOMAIN', 'claesen-verlichting.be'),
    'tracking_domain'      => env('MAILING_TRACKING_DOMAIN', env('APP_URL')),
    'from_address'         => env('MAIL_FROM_ADDRESS', 'info@claesen-verlichting.be'),
    'from_name'            => env('MAIL_FROM_NAME', 'Claesen Verlichting'),
];
```

#### Criterios de aceptación
- `config('mailing.send_delay_ms')` retorna `500` por defecto
- `config('mailing.spam_rate_alert')` retorna `0.0008`

---

### MAI-007 — Migración mailing_suppression_list

**Tipo:** Migration | **Prioridad:** Alta | **Depende de:** —

#### Schema

```php
Schema::create('mailing_suppression_list', function (Blueprint $table) {
    $table->id();
    $table->string('email')->unique();
    $table->foreignId('prospect_id')->nullable()->constrained('prospects_prospects')->nullOnDelete();
    $table->enum('reason', ['unsubscribed','hard_bounce','spam_complaint','soft_bounce_limit','manual']);
    $table->foreignId('source_campaign_id')->nullable()->constrained('mailing_campaigns')->nullOnDelete();
    $table->text('notes')->nullable();
    $table->timestamp('suppressed_at');
    $table->foreignId('suppressed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

#### Criterios de aceptación
- La tabla existe con todos los campos e índices
- `email` tiene constraint `UNIQUE`

---

### MAI-008 — SuppressionList model + SuppressionService

**Tipo:** Feature | **Prioridad:** Alta | **Depende de:** MAI-007

#### Cambios
- `Modules/Mailing/Models/SuppressionEntry.php`
- `Modules/Mailing/Services/SuppressionService.php` con métodos:
  - `suppress(string $email, string $reason, ?int $prospectId, ?int $campaignId, ?int $userId, ?string $notes): void`
  - `isSuppressed(string $email): bool`
  - `getReason(string $email): ?string`
- Verificación en `ExecuteCampaignJob` antes de enviar: si `isSuppressed($email)` → log como `skipped` con motivo

#### Criterios de aceptación
- `SuppressionService::isSuppressed('test@example.com')` retorna `bool`
- El job omite automáticamente emails suprimidos
- Un email suprimido por `spam_complaint` no puede eliminarse de la lista sin nota obligatoria

---

### MAI-009 — Migración mailing_message_events

**Tipo:** Migration | **Prioridad:** Alta | **Depende de:** Fase 0

#### Schema

```php
Schema::create('mailing_message_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('message_id')->constrained('mailing_messages')->cascadeOnDelete();
    $table->enum('event_type', [
        'sent','delivered','opened','clicked',
        'bounced_hard','bounced_soft','complained','unsubscribed'
    ]);
    $table->timestamp('occurred_at');
    $table->json('metadata')->nullable();
    $table->index(['message_id', 'event_type']);
    $table->index(['event_type', 'occurred_at']);
});
```

#### Criterios de aceptación
- Tabla con índices compuestos correctos
- No tiene `updated_at` (es append-only)

---

### MAI-010 — MessageEvent model + enum

**Tipo:** Feature | **Prioridad:** Alta | **Depende de:** MAI-009

#### Cambios
- `Modules/Mailing/Models/MessageEvent.php` — modelo Eloquent, sin timestamps de updated
- `Modules/Mailing/Enums/MessageEventType.php` — backed enum con labels en NL/EN
- Relación `CampaignMessage::events()` → `hasMany(MessageEvent::class)`
- Helper en `CampaignMessage`: `hasEvent(MessageEventType $type): bool`

#### Criterios de aceptación
- `MessageEventType::BOUNCED_HARD->label('nl')` retorna `'Hard bounce'`
- `$message->hasEvent(MessageEventType::CLICKED)` retorna bool
- Los eventos se guardan en orden cronológico por `occurred_at`

---

### MAI-015 — Headers List-Unsubscribe + List-Unsubscribe-Post

**Tipo:** Compliance | **Prioridad:** Urgente | **Depende de:** —

#### Alcance
Añadir los headers de baja en un clic a `ProspectCampaignMail`.

#### Cambios en `ProspectCampaignMail::build()` o `envelope()`

```php
->withSymfonyMessage(function (Email $email) use ($unsubscribeUrl, $mailtoUnsubscribe) {
    $email->getHeaders()
        ->addTextHeader('List-Unsubscribe', "<{$unsubscribeUrl}>, <{$mailtoUnsubscribe}>")
        ->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
})
```

Donde:
- `$unsubscribeUrl` = URL HTTPS del endpoint de baja (ya existe)
- `$mailtoUnsubscribe` = `mailto:afmelden@claesen-verlichting.be?subject=afmelden`

#### Criterios de aceptación
- Los headers aparecen en el raw source del email enviado
- El endpoint de baja acepta requests POST automáticos (RFC 8058 one-click)
- Se verifica con un cliente de correo que soporta one-click unsubscribe

---

### MAI-012 — Campaign workflow — estados + aprobación

**Tipo:** Feature | **Prioridad:** Alta | **Depende de:** Fase 0

#### Alcance
Nadie puede lanzar una campaña sin que un `admin` o `campaign_manager` la apruebe.

#### Cambios
- Añadir a `mailing_campaigns`: `status`, `approved_by`, `approved_at`
- `CampaignStatus` backed enum: `draft|review|approved|sending|completed|failed|cancelled`
- Transiciones permitidas:
  - `draft → review` (cualquier marketer)
  - `review → approved` (solo admin/campaign_manager)
  - `review → draft` (rechazo con nota)
  - `approved → sending` (el job al ejecutar)
  - `sending → completed|failed`
  - `* → cancelled` (solo admin)
- El job verifica `status === approved` antes de empezar. Si no, lanza excepción.

#### Criterios de aceptación
- Una campaña en `draft` no puede ponerse en `sending` directamente
- `Campaign::canBeApprovedBy(User $user): bool` existe
- Intentar enviar una campaña no aprobada lanza `\DomainException`

---

### MAI-013 — Tracking pixel apertura

**Tipo:** Feature | **Prioridad:** Alta | **Depende de:** MAI-009, MAI-010

#### Ruta
`GET /mailing/track/open/{token}.gif`

#### Comportamiento
1. Buscar `CampaignMessage` por `tracking_token`
2. Si existe y no tiene evento `opened`: crear `MessageEvent` con `event_type = opened`, `occurred_at = now()`, `metadata = {user_agent, ip}`
3. Retornar GIF transparente 1×1 con headers `Cache-Control: no-store`

#### Criterios de aceptación
- Token inválido retorna GIF igualmente (no error 404 — no romper la experiencia)
- Aperturas duplicadas en < 30 segundos del mismo IP se ignoran (Apple MPP mitigation)
- El evento se guarda con `metadata.user_agent` completo

---

### MAI-014 — Tracking click redirect

**Tipo:** Feature | **Prioridad:** Alta | **Depende de:** MAI-009, MAI-010

#### Ruta
`GET /mailing/track/click/{token}/{linkHash}`

#### Comportamiento
1. Buscar `CampaignMessage` por `tracking_token`
2. Resolver URL original desde `linkHash` (tabla `mailing_tracked_links` o JSON en template)
3. Crear `MessageEvent` con `event_type = clicked`, `metadata = {link_url, ip, user_agent}`
4. Redirect 302 a la URL original

#### Cambios adicionales
- Al construir el email en `ExecuteCampaignJob`, reemplazar URLs del body por URLs redirigidas
- Tabla auxiliar `mailing_tracked_links (id, campaign_id, original_url, hash, created_at)`

#### Criterios de aceptación
- El redirect llega a la URL original en < 200ms
- El evento `clicked` se registra correctamente
- Un hash inválido hace redirect a `config('app.url')` sin error 500

---

### MAI-017 — CampaignPolicy — RBAC por recurso

**Tipo:** Security | **Prioridad:** Alta | **Depende de:** Fase 0

#### Roles y permisos

| Acción | `super_admin` | `admin` | `campaign_manager` | `marketer` | `viewer` |
|---|---|---|---|---|---|
| Crear campaña | ✅ | ✅ | ✅ | ✅ | ❌ |
| Editar borrador | ✅ | ✅ | ✅ | solo propias | ❌ |
| Enviar a revisión | ✅ | ✅ | ✅ | ✅ | ❌ |
| Aprobar campaña | ✅ | ✅ | ✅ | ❌ | ❌ |
| Cancelar campaña | ✅ | ✅ | ❌ | ❌ | ❌ |
| Ver métricas | ✅ | ✅ | ✅ | ✅ | ✅ |
| Gestionar supresión | ✅ | ✅ | ❌ | ❌ | ❌ |

#### Criterios de aceptación
- Un `marketer` no puede aprobar su propia campaña
- `Gate::authorize('approve', $campaign)` lanza 403 para `marketer`
- Tests de policy para cada combinación crítica

---

### MAI-019 — Dashboard métricas básicas (Filament widget)

**Tipo:** Feature | **Prioridad:** Media | **Depende de:** MAI-009, MAI-010

#### Métricas a mostrar por campaña

| Métrica | Fuente | Nota |
|---|---|---|
| Enviados | `mailing_messages.status = sent` | |
| Entregados | `message_events.event_type = delivered` | |
| Rebotes duros | `message_events.event_type = bounced_hard` | Alerta si > 5% |
| Rebotes suaves | `message_events.event_type = bounced_soft` | |
| Aperturas únicas | `message_events.event_type = opened` DISTINCT message_id | Señal débil — mostrar con asterisco |
| Clics únicos | `message_events.event_type = clicked` DISTINCT message_id | KPI principal |
| CTR | clics únicos / enviados | |
| CTOR | clics únicos / aperturas únicas | |
| Bajas | `message_events.event_type = unsubscribed` | |
| Quejas | `message_events.event_type = complained` | Alerta si > 0.08% |

#### Criterios de aceptación
- Las aperturas se muestran con nota "Señal indicativa — Apple MPP puede inflar este valor"
- Si spam rate > 0.08%: badge rojo con alerta visible
- Si hard bounce > 5%: badge rojo con alerta visible

---

### MAI-020 — Feature tests Fase 1

**Tipo:** Test | **Prioridad:** Alta | **Depende de:** MAI-006 a MAI-019

#### Cobertura mínima

| Área | Tests |
|---|---|
| Suppression | `isSuppressed()`, `suppress()`, verificación en job |
| Eventos | Pixel apertura guarda evento, duplicado en < 30s ignorado |
| Click tracking | Redirect correcto, evento guardado |
| Workflow campaña | draft→review→approved, rechazo, no puede enviar sin aprobación |
| Policy | marketer no aprueba, viewer no crea |
| List-Unsubscribe | Header presente en email enviado |

---

## Reglas no negociables

1. **Transporte es intercambiable.** Nunca llamar `MicrosoftGraphMailer` directamente — siempre via `MarketingCampaignInterface`.
2. **`mailing_message_events` es append-only.** No se editan eventos ya registrados. El historial es inmutable.
3. **Supresión es permanente para `spam_complaint` y `hard_bounce`.** Solo un `super_admin` puede levantar una supresión con nota obligatoria y auditoría.
4. **Sin aprobación, sin envío.** El job debe verificar `status === approved` como primera instrucción.
5. **Aperturas no son KPI.** Nunca usar `open_rate` como criterio de éxito de campaña en reportes o automatizaciones. Usar siempre CTR o CTOR.
6. **List-Unsubscribe en todo correo comercial.** Sin excepción. Los correos transaccionales están exentos, los comerciales no.
7. **Módulos Mailing no importa clases internas de Prospects.** Solo referencia `prospect_id` (FK) y el modelo `Prospect` para joins. Nunca lógica de negocio de Prospects.

---

## Flujo de trabajo con Claude (mismo protocolo que Safety)

1. Mover issue Linear a **In Progress**.
2. Presentar plan del ticket: alcance, archivos, tests.
3. **Esperar aprobación** antes de editar archivos.
4. Implementar solo el ticket activo.
5. Ejecutar tests relevantes.
6. Presentar diff/resumen + criterios cubiertos.
7. **Esperar GO técnico.**
8. Commit dedicado: `MAI-XXX / CLA-YY: resumen corto`
9. Mostrar hash del commit.
10. Marcar Linear como **Done**.
11. **No avanzar** sin confirmación explícita.

### Cómo reanudar una sesión nueva

```
"Continuamos con MAI-00X / CLA-Y. Lee CLAUDE.md y docs/Mailing/mailing-platform-master.md."
```

---

## Historial de decisiones

| Fecha | Decisión | Razón |
|---|---|---|
| 2026-05-27 | Microsoft Graph como transporte inicial | Convencer a gerencia con valor demostrado antes de pagar ESP externo |
| 2026-05-27 | MySQL 8.4 (no PostgreSQL) | Cross-join con prospects_prospects; escala insuficiente para justificar segundo motor |
| 2026-05-27 | Clics > Aperturas como KPI | Apple MPP, proxies corporativos de Outlook invalidan open rate como señal fuerte |
| 2026-05-27 | Audiencias = Prospects (no sistema paralelo) | Los contactos ya existen y están sincronizados desde federaciones deportivas |
