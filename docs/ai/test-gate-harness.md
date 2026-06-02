# Test Gate Harness — CAFCA Intelligence Hub

> Arnés de testing obligatorio. Define qué tests se requieren, cuándo y cómo documentar el resultado.
> Última actualización: 2026-06-03 (TEST-GATE-001)

---

## Regla no negociable

**Ningún ticket puede pasar a Done sin tests automatizados proporcionales al riesgo, salvo waiver explícito documentado en el cierre.**

Esta regla tiene la misma jerarquía que el protocolo de tickets. El GO técnico no puede concederse si faltan los tests requeridos por este arnés y no existe waiver aprobado.

---

## Flujo por etapa

### Plan
- Identificar el tipo de cambio usando la matriz de abajo.
- Declarar en el plan: qué archivos de test se crearán o modificarán, qué casos cubrirán, qué fakes/mocks necesitan.
- Si el tipo de cambio no requiere tests formales, declarar el waiver y su justificación en el plan.

### Implementación
- Crear los tests junto con el código, no después.
- Usar fakes de framework (`Mail::fake()`, `Notification::fake()`, `Queue::fake()`) — nunca llamadas reales a servicios externos en tests.
- Gemini/IA siempre mockeada. Nunca llamadas reales a la API de Gemini en tests.

### Review
- Antes de evaluar arquitectura o correctitud, verificar el Test Gate:
  1. ¿Qué tipo de cambio es?
  2. ¿Qué tests exige este arnés?
  3. ¿Qué tests se añadieron?
  4. ¿Se ejecutaron? ¿Con qué resultado?
- Ver sección "Testing Gate" en `code-review-rubric.md`.

### Done
- Los tests deben pasar antes del GO técnico.
- El resultado debe estar documentado en el cierre usando la plantilla de abajo.
- Si hay waiver: debe estar documentado con formato estándar y aprobado por el auditor.

### Pre-producción
- `php artisan test --testsuite=Modules --filter=<Modulo>` debe pasar en staging.
- No hay deploy sin Test Gate pasado o waiver documentado.
- Ver `production-readiness.md`.

---

## Matriz de tests obligatorios por tipo de cambio

| Tipo de cambio | Tests mínimos requeridos | Herramienta |
|---|---|---|
| **Servicio puro** (lógica de negocio) | Unit o Feature del método principal; happy path + caso de error | PHPUnit |
| **API / Resource** | **Protegido:** 200 OK, 401 sin auth, 403 sin permiso, 422 input inválido. **Público:** 200 OK, 422 si acepta input, estructura JSON correcta, locale/fallback si devuelve campos traducibles | `$this->getJson()` / `postJson()` |
| **Job / Command / Scheduler** | Verificar efecto del job; command registrado; comportamiento con datos vacíos | `artisan()` / `Queue::fake()` / `Bus::fake()` |
| **Mail / Notification** | Verificar que se envía, a quién, con qué datos; nunca envío real | `Mail::fake()` / `Notification::fake()` |
| **Migración / Transformación de datos** | Test con datos representativos incluyendo casos legacy (nulls, strings, JSON existente) | `RefreshDatabase` + fixtures |
| **IA / Gemini** | Mock del servicio; verificar prompt construido, respuesta procesada, fallback en error | Mock de `GeminiService` |
| **i18n / Locales** | Verificar que todas las keys existen en todos los idiomas soportados; verificar resolución de locale vía middleware | Feature test con `Accept-Language` |
| **Observer / Event** | Verificar que el observer se dispara en el evento correcto; verificar que no se dispara cuando no debe (ej: `saveQuietly`) | `Event::fake()` o test funcional |
| **Filament Resource** | Verificar que las páginas renderizan sin error (Livewire test básico); cobertura manual si no es viable | Livewire test / waiver documental |
| **Frontend / Webhook** | Verificar que el job de notificación se despacha; el rebuild externo no se testea aquí | `Bus::fake()` / `Queue::fake()` |

---

## Criterios para waiver

Un waiver documenta por qué no se añaden tests automatizados. Es válido cuando:

- El cambio es **exclusivamente documental** (docs, lang files, config sin lógica).
- El cambio es **de Filament UI pura** donde los tests funcionales no son viables en este proyecto.
- El tipo de cambio tiene **cobertura indirecta suficiente** en tests existentes (documentar cuáles).
- El **costo de test supera claramente el riesgo** (ej: un campo cosmético en un template de email) y el auditor lo acepta.

### Formato de waiver

```
WAIVER — [TICKET-ID] / CLA-[N]
Motivo: [una línea — por qué no hay tests automatizados]
Riesgo residual: [qué podría fallar y cómo se detectaría]
Cobertura alternativa: [smoke test manual / revisión visual / test existente que cubre]
Aprobado por: [nombre del auditor en el GO técnico]
```

### Quién aprueba
El waiver debe ser aceptado explícitamente por el auditor en el GO técnico. Sin esa aceptación, el waiver no es válido.

---

## Reglas específicas por tecnología

### IA / Gemini
- Nunca llamadas reales a la API de Gemini en tests.
- Siempre usar mock del `GeminiService` o fake de la respuesta HTTP.
- Testear: construcción del prompt, procesamiento de la respuesta, comportamiento cuando Gemini devuelve vacío o estructura parcial, comportamiento cuando Gemini lanza excepción.

### Mail
- Siempre `Mail::fake()` en tests.
- Verificar con `Mail::assertSent(MiMailable::class, fn($mail) => $mail->hasTo('...'))`.
- Nunca `Mail::mailer('microsoft-graph')` activo en tests.

### Notifications
- Siempre `Notification::fake()` en tests.
- Verificar con `Notification::assertSentTo($user, MiNotificacion::class)`.

### Commands
- Usar `$this->artisan('nombre:comando', ['--opcion' => valor])`.
- Verificar código de salida (`assertExitCode(0)`).
- Verificar efectos en DB (`assertDatabaseHas` / `assertDatabaseMissing`).

### Migraciones
- Incluir datos representativos: nulls, strings legacy, JSON existente, valores límite.
- Probar `migrate` y `migrate:rollback` en DB de test.
- Si la migración transforma datos (ej: varchar → JSON), incluir fixture con los dos formatos de entrada.

### Scheduler
- Verificar que el command está registrado: `$this->artisan('schedule:list')` contiene el nombre del command.
- Si la integración del schedule no es testeable, documentar como waiver documental con smoke test alternativo.

### Observers / Events
- Verificar que `saveQuietly()` no dispara el observer.
- Verificar que el observer solo actúa en los modelos/colecciones correctos.

---

## Plantilla de cierre de ticket con Test Gate

```
## Test Gate — [TICKET-ID] / CLA-[N]

### Tests añadidos
- [ ] Archivo: `...` — casos: ...
- [ ] Archivo: `...` — casos: ...

### Tests ejecutados
```bash
php artisan test --filter=...
```

### Resultado
PASS | FAIL — [N tests, N assertions, N ms]

### Waiver (si aplica)
WAIVER — [TICKET-ID] / CLA-[N]
Motivo: ...
Riesgo residual: ...
Cobertura alternativa: ...
Aprobado por: [pendiente de GO técnico]
```

---

## Lecciones del sprint WEB-008 → WEB-011

Este sprint fue implementado y auditado manualmente sin tests automatizados. Los patrones críticos identificados que requieren cobertura en futuras iteraciones:

| Patrón | Riesgo | Test recomendado |
|---|---|---|
| Middleware `SetPanelLocale` + `BrowserLocaleMiddleware` | Locale incorrecto silencioso | Feature test con `Accept-Language: nl/fr/de/en` y verificar `app()->getLocale()` |
| `HasAiTranslations` con `['nl','en','fr','de']` | Locale faltante, texto vacío en producción | Unit test del trait con mock de Gemini; verificar 4 locales rellenos |
| `GenerateGalleryMediaMetadataJob` — claim atómico + finally | Metadata incompleta, strings legacy no promovidos como fuente, Gemini fail sin guardar vacío, frontend sin notify en algún camino | Feature test: media guardada → job mockeando Gemini → `custom_properties` rellenos; test de Gemini fail → metadata vacía + notify igual disparado |
| `ConsultationService` + `DB::afterCommit()` + `Mail::fake()` | Correo enviado aunque transacción haga rollback | Feature test: POST /consultations → `Mail::assertSent(NewConsultationRequestMail::class)` |
| `ProcessConsultationRemindersCommand` — doble procesamiento | Notificación duplicada en retry | Test: reminder `remind_at` en pasado → artisan → 1 actividad, status `completed` |
| `updateQuietly()` en `ConsultationService::updateStatus()` | Double logging si se revierte a `update()` | Test: llamar `updateStatus()` → exactamente 1 `ConsultationActivity` de tipo `status_change` |
| Migración varchar → JSON con `JSON_VALID()` | Corrupción de datos legacy | Test de migración con fixture: string plano, JSON válido existente, null |
| `resolveLocaleValue()` en `ProjectResource` | API devuelve null en lugar de fallback | Unit test: array con/sin locale solicitado, con/sin nl, con/sin en |
