# Auditoría del sistema de traducciones

> **Fecha:** 2026-11-17 · **Base:** `7423d917` (post CLA-694) · **Modo:** solo lectura (sin cambios de código, sin acceso a `.env`).
> **Método:** mapeo del código por subagente de exploración + verificación manual puntual de los hallazgos 1, 2 (escritura del JSON) y 4. El resto proviene de lectura de código, no de ejecución. No se corrieron tests.

---

## 1. Resumen ejecutivo

Conviven **tres sistemas**:

1. **Traducción automática IA (Gemini)** de campos traducibles, con estados por locale y flujo de revisión humana.
2. **Metadata de medios IA** (`GenerateGalleryMediaMetadataJob`): camino separado, sin estados, sin caché, sin reintentos.
3. **i18n estático de UI** (`lang/` raíz + módulos).

El motor es razonablemente sólido tras CLA-694 (lotes por posición, fallos no cacheados, reintentos reales). Los problemas restantes están en los bordes: retraducción con locale de origen equivocado, carreras/lost updates, estados `failed` sin reintento automático e i18n fr/de incompleto.

---

## 2. Arquitectura

| Pieza | Ubicación | Rol |
|---|---|---|
| Campos traducibles | `spatie/laravel-translatable ^6.14`, `lara-zeus/spatie-translatable ^2.0` | JSON por locale + switcher en Filament |
| Trait `HasAiTranslations` | `Modules/Intelligence/Traits/HasAiTranslations.php` | Hooks `created`/`updated` → despacha job |
| `TranslateModelAttributesJob` | `Modules/Intelligence/Jobs/` | Traducción por atributo; `tries=3`, `backoff=[30,60,120]` |
| `GeminiService` | `Modules/Intelligence/Services/GeminiService.php` | Cliente proveedor, caché, lotes (`MAX_BATCH_SIZE=20`) |
| `GoogleServiceAccountAuthService` | idem | Token OAuth de service account (cacheado) |
| `TranslationStateService` + `TranslationState` | tabla `ai_translation_states` | Estado por `(type,id,attribute,locale)`, clave única |
| `GlossaryService` + `Glossary` | tabla `website_glossaries` | Glosario por sitio, cache 1 h, versión `crc32` |
| Comando | `intelligence:translate-pending [--include-missing] [--model=]` | Traducción masiva por lotes |
| UI revisión | `app/Filament/Clusters/Website/Pages/TranslationReviewPage.php` | Editar / aprobar / publicar / retraducir + activity log |

- **Locales:** destinos `nl/en/fr/de`, hardcodeados en 4 lugares (job, `SetPanelLocale`, `GenerateGalleryMediaMetadataJob`, `AdminPanelProvider:291`). `config/app.php`: `locale=en`, `fallback_locale=nl`.
- **Config:** `services.gemini.url`, `services.gemini.service_account_path`. `services.gemini.key` existe pero `GeminiService` no la usa. Modelo, timeout, reintentos y tamaño de lote **no son configurables** (constantes o hardcode).
- **Estados por locale:** `missing`, `machine`, `stale`, `failed`, `needs_review`, `reviewed`, `published`.

---

## 3. Funcionamiento

### Disparo automático

1. `created` → siempre despacha; `updated` → solo si cambió un atributo IA. Envío vía `DB::afterCommit`.
2. El locale de origen es `app()->getLocale()` (sale de `Accept-Language` vía `SetPanelLocale`).
3. Si cambió el texto origen, invalida derivados: `reviewed`/`published` → `needs_review`; el resto → `stale`.

### Job

1. Carga el modelo sin scope de sitio.
2. Por atributo calcula destinos = locales vacíos + `stale`/`failed`.
3. **Una llamada por atributo** (`translateAndDetect`) con todos los locales juntos.
4. Éxito → `machine` con hash de origen y versión de glosario. Vacío → `failed`. Excepción → `failed` en todos los locales pedidos.
5. Persiste el JSON completo con `DB::table()->update()`; en tablas FieldOps actualiza también la columna legacy `ai_translation_status` (`complete`/`pending`).
6. Relanza el primer fallo para que la cola reintente (excepto en cola `sync`).

### GeminiService

- Temperatura 0.2, respuesta JSON. Lanza `GeminiProviderException` ante: sin token, error de transporte, HTTP fallido, sin texto, no-JSON.
- Caché solo de resultados completos: `ai-trans:<sha256(text, locales, context, glossary, version)>`, 7 días.
- `translateBatch`: un locale por llamada, ítems numerados por posición, filtra hits de caché antes.

### Comando masivo

Selecciona `stale`/`failed` (+ `missing` con `--include-missing`), agrupa por modelo/atributo/locale, chunks de 20 → `translateBatch` → `saveQuietly`.

### Revisión humana

`TranslationReviewPage`: badges, filtro, acciones edit/approve/publish/retranslate, auditoría en activity log. Scope por `site_id`.

### Consumidores

- **FieldOps (10 modelos):** `AccessType`, `ElectricalBoard`, `ElectricalBoardType`, `FoMaintenanceType`, `Luminaire`, `SafetyType`, `Structure`, `StructureType`, `Terrain`, `TerrainType`.
- **Website:** `Project` (8 atributos), `LegalDocument`.
- **Solo Spatie, sin IA:** `Announcement`, `ProjectCategory`; en `Project`, `content` y `seo_tags` son traducibles pero no IA.

---

## 4. Hallazgos

### 🔴 Alta

1. **Retraducir usa el locale de origen equivocado** *(verificado)*. `TranslationReviewPage.php:276-282` despacha con `app()->getLocale()` (idioma del panel del revisor), no con `$record->source_locale` (que sí se usa dos líneas antes para el estado). Revisor en `en` sobre contenido `nl` ⇒ traduce desde la versión inglesa; si el destino coincide con el locale del panel, traduce la traducción sobre sí misma.
2. **Lost update / carreras** *(código verificado, impacto inferido)*. El job lee el modelo al inicio y al final reescribe todo el JSON de cada atributo (`Job:184-196`); un guardado del editor en el medio se pierde. No hay `ShouldBeUnique` / `WithoutOverlapping`; cada guardado encola otro job → dos jobs paralelos y doble costo en Gemini. El `saveQuietly` del comando tiene el mismo riesgo.
3. **Timeouts vs. cola** *(inferido)*. Llamadas secuenciales por atributo (`Project` = 8), `Http::post` sin timeout explícito (30 s por defecto), job sin `$timeout`, y `retry_after=90`. En el peor caso el job supera 90 s, se re-ejecuta y duplica costo.
4. **Los `failed` nunca se reintentan solos** *(verificado: `translate-pending` no está en ningún schedule)*. Sin `failed()` en el job; tras 3 intentos queda `failed` hasta otro guardado o corrida manual. Los `failed` de FieldOps no son visibles en la UI (`site_id = null`).

### 🟠 Media

5. **Aprobar traducciones vacías**: "approve" visible en `failed`/`missing`/`stale` (`TranslationReviewPage:152-156`) y `applyTransition` no valida que exista valor.
6. **El modo lote aplana saltos de línea**: `GeminiService` (~`:190`) reemplaza `\r\n` por espacios → los documentos legales multi-párrafo pierden formato vía comando; el job no. Inconsistencia entre caminos.
7. **Prompt injection**: el texto de usuarios CMS/FieldOps se interpola crudo en el prompt (`:253-258`); el glosario también. Sin `maxOutputTokens` → lotes de 20 textos largos pueden truncarse.
8. **Endpoint hardcodeado**: sin `GEMINI_API_URL` cae a una URL fija `us-central1` con project ID y modelo `gemini-2.5-flash` (`:19`). `handoff.md:136` registra tests FieldOps haciendo llamadas reales a Gemini, lo que contradice `testing-checklists.md:236` ("Gemini always mocked").
9. **Estado legacy inconsistente**: el job escribe `complete`/`pending`, una migración FieldOps escribe `'completed'`; la API expone `translation_status` (legacy), desalineado de los estados por locale; el comando nunca actualiza la columna legacy.
10. **Ambigüedad del locale origen**: el trait usa el locale del request; el comando usa `source_locale ?? fallback_locale`. Guardar con panel en `en` etiqueta como origen `en` aunque el contenido sea `nl`.
11. **La invalidación solo mira el locale del request**: editar el texto `nl` con el panel en `en` no marca derivados como `stale`.

### 🟡 Baja

12. **Código muerto/duplicado**: `GeminiService::fallbackResponse` sin uso; `detected_locale` ignorado; `LOCALES` repetido en 4 lugares; `TranslationState.php:18` referencia una inexistente `TranslationStatusService`.
13. **El job solo captura `\Exception`**: un `TypeError`/`Error` evita el registro de `failed`.
14. **Mensajes de error en inglés hardcodeados** guardados en BD y mostrados en un panel multilingüe (`Job:157`, `Command:140`).
15. **`GenerateGalleryMediaMetadataJob`** se traga todos los errores; sin reintento ni test.

### i18n estático

- `lang/de` y `lang/fr` solo tienen `documentation`, `navigation`, `website`; faltan `activity_log`, `employees`, `permissions`, `roles`, `users`.
- `de/website.php` y `fr/website.php` no tienen `media_slots`, `page_publication_review` ni `translation_review.statuses` → usuarios de/fr ven literalmente "machine" / "needs_review".
- Casi todos los módulos tienen solo `en/nl` (Prospects agrega `fr`), pero el panel ofrece los 4 idiomas.
- Existe `lang/nl.json`, no existe `en.json`.

---

## 5. Tests

- **Cubierto:**
  - `Modules/Intelligence/tests/Feature/TranslationEngineTest.php` (CLA-611): estados, no pisar revisadas, glosario, caché, tope de lote, resume del comando.
  - `Modules/Intelligence/tests/Feature/TranslationEngineReliabilityTest.php` (CLA-694, 12 tests): servicio real contra HTTP fake, posición en lotes, fallos no cacheados, reintentos.
  - `TranslateModelAttributesJobTest`, `Modules/FieldOps/tests/Feature/AiTranslationTest.php`, `Modules/Website/tests/Feature/TranslationReviewPageTest.php`.
- **Huecos:**
  - Retraducir con origen ≠ locale del panel (hallazgo 1).
  - Aprobar filas vacías (hallazgo 5).
  - Concurrencia / lost update (hallazgo 2).
  - Texto multilínea en modo lote (hallazgo 6).
  - `generateMediaMetadata`.
  - Scheduling del comando.

**Documentación existente:** reglas del motor en `docs/ai/module-contracts.md:331-375`. `handoff.md:136` deja pendiente revisar manualmente las traducciones ya generadas por `translate-pending` (posiblemente vacías o cruzadas).

---

## 6. No verificable en esta auditoría

- Valores de `.env` (driver de cola, store de caché, `GEMINI_API_URL`) y timeout del worker en producción.
- Existencia de una UI de Glosario en `Modules/Website` (búsqueda truncada).
- Paridad de claves dentro de `en` vs. `nl`.
- `tests/Feature/WebsiteClusterTranslationsTest.php` (no leído).

---

## 7. Orden de corrección sugerido

1. **#1** — retraducir con `source_locale` (chico, testeable).
2. **#2 + #3** — lock / `ShouldBeUnique` por modelo, `$timeout` del job y timeout HTTP explícito.
3. **#4** — programar `translate-pending` con límite de costo o implementar `failed()`; visibilidad de `failed` en FieldOps.
4. **#5 + #6** — bloquear aprobación vacía; preservar saltos de línea en modo lote.
5. **i18n** — completar fr/de y centralizar los locales en config.
