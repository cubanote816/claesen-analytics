# Handoff — CAFCA Intelligence Hub

> Estado global vivo del proyecto. Actualizar en cada cierre de ticket.
> Última actualización: 2026-07-07 — reordenado el menú lateral de Filament (User Management al final, grupo "Analyse & Intelligentie" ocultado) y encontrado un bug real de localización: `NavigationGroup::label(__(...))`/`NavigationItem::group('...')` evaluados como string plano en vez de closure quedan **congelados en el locale activo al bootear la app** (no en el del visitante), rompiendo el orden y duplicando grupos para cualquier locale ≠ el default. Corregido pasando closures (`fn () => __(...)`) en todo `AdminPanelProvider.php`. El resto sigue igual: membrete corporativo oficial en PDFs (`core::pdf.letterhead`), rebranding "Claesen Intelligence Hub" → "Claesen Outdoor Lighting Platform", bug de `MicrosoftGraphTransport` (logos embebidos sin `isInline`/`contentId`); segundo incidente de producción (`SESSION_DOMAIN`/Sanctum) resuelto; ElectricalBoard, Media (FileLibrary) y Client encontrados igual de rotos que Terrain/Structure/Luminaire; los 3 arreglados y verificados en vivo, sigue sin commitear.

---

## Estado actual

- **Sprint activo:** ninguno declarado. Trabajo de esta sesión no pasó por ticket Linear formal — es un "reto" exploratorio del usuario para portar `service.claesen-verlichting` (frontend legacy, antes apuntaba a `api-claesen-sport-app`) contra el backend real `Modules/FieldOps`. Documentado acá por su tamaño, pero **sin commitear todavía** en ninguno de los dos repos (`claesen_api_web_oficial` y `service.claesen-verlichting`).

### Sesión 2026-07-07 (cont.) — Reordenar menú lateral + bug real de labels de navegación congelados por locale (Done, sin ticket Linear formal)

**Contexto:** el usuario pidió mover "User Management" al final del menú lateral y eliminar la sección "Analyse & Intelligentie" (marcada con una X roja en una captura). Al implementar el reorden simple del array `->navigationGroups([...])` en `app/Providers/Filament/AdminPanelProvider.php`, el orden renderizado en el navegador real **no coincidía en absoluto** con el array — ni con el idioma inglés del navegador del usuario ni con ningún reordenamiento simple.

- **Causa raíz encontrada (no reportada por el usuario, investigada de oficio comparando `Filament\Navigation\NavigationManager::get()` línea por línea contra el HTML real):** `NavigationGroup::make('X')->label(__('navigation.groups.x'))` evalúa `__()` **una sola vez, en el momento en que `AdminPanelProvider::panel()` corre durante el boot de la app** — es decir, en el locale por defecto (`APP_LOCALE=nl`), **antes** de que `BrowserLocaleMiddleware` fije el locale real del visitante según su navegador (que en el caso del usuario es inglés). El label quedaba congelado en holandés ("Personeel & Prestaties", etc.), mientras que cada `Resource::getNavigationGroup()` evalúa `__(...)` de nuevo en cada request, ya con el locale correcto del visitante ("Workforce & Performance"). Como las dos strings no coinciden textualmente (salvo por casualidad, ej. "Mailing" es igual en ambos idiomas), el algoritmo de orden de Filament (`array_search` contra las labels registradas) fallaba en silencio para casi todos los grupos, cayendo a un empate que se resolvía por orden de descubrimiento — de ahí el orden aparentemente aleatorio. Bug pre-existente, invisible para visitantes con navegador en holandés (coincide con el locale de boot) pero real para cualquier otro idioma.
- **Bug hermano encontrado de paso:** los dos `NavigationItem` del sitio público (`website.v1_demo_link`/`safety_pwa_link`) usaban `->group('Content & Website')` con el string en inglés hardcodeado (no traducido) — en holandés esto creaba un **grupo "Content & Website" duplicado** (con esos 2 links sueltos) separado del grupo real "Inhoud & Website" (con los recursos traducidos).
- **Fix:** todo `->label(__(...))` y `->group('...')`/`NavigationItem::make(__(...))` en `AdminPanelProvider.php` reescrito como closure (`fn () => __(...)`) — `Filament\Navigation\NavigationGroup`/`NavigationItem` ya soportan `string|Closure` en estos métodos, y las closures se evalúan de forma diferida (`evaluate()`) en cada request, no en boot. Verificado con un test ad-hoc (no commiteado, descartado tras confirmar) inspeccionando `data-group-label` en el HTML real renderizado, en inglés y holandés — ambos ahora muestran 8 grupos únicos, sin duplicados, en el orden correcto.
- **Orden final del menú:** Workforce & Performance, Growth & Acquisition, Mailing, Safety & VCA, Content & Website, Intelligence Hub, Field Operations (Demo), **User Management (último)**.
- **"Analyse & Intelligentie" eliminado del menú:** único contenido era `Modules/Performance/Filament/Resources/ProjectInsightResource.php` — usuario confirmó ocultarlo del todo (`$shouldRegisterNavigation = false`), el recurso sigue existiendo y accesible por URL directa, no se borró código.
- **Pendiente:** ninguno — cambio verificado en ambos locales, sin tests automatizados existentes que cubrieran esto (se dejó sin test permanente por ser configuración de UI, no lógica de negocio).

### Sesión 2026-07-07 — Membrete corporativo oficial + rebranding + bug real de logos inline en Microsoft Graph (Done, sin ticket Linear formal)

**Contexto:** el usuario aprobó el PDF de inspecciones con el logo embebido (sesión anterior), pero pidió que el encabezado siga el papel membretado real de la empresa (compartió una imagen de referencia: logo + 3 columnas — KANTOOR/MAATSCHAPPELIJKE ZETEL/contacto-IBAN, separadas por líneas verticales). De paso reportó que un correo viejo (captura de un email real del 2026-06-25) tampoco tenía el logo y usaba el nombre "Claesen Intelligence Hub" — pidió cambiarlo a "Claesen Outdoor Lighting Platform" en todos lados.

- **Membrete oficial nuevo:** `Modules/Core/resources/views/pdf/letterhead.blade.php` — partial reutilizable (`@include('core::pdf.letterhead')`) con el logo (`brand-logo-light.png`, base64) + 3 columnas de texto (KANTOOR / MAATSCHAPPELIJKE ZETEL / contacto+IBAN+BIC+RPR) separadas por `border-left`, igual al papel membretado real. Datos: Redemptiestraat 35, 3740 Bilzen; Claesen BV, Benoit Jansenstraat 4, 2490 Balen; BTW BE 0413.993.228; IBAN BE80 2350 1008 4877; BIC GEBABEBB; RPR Antwerpen Afd. Turnhout; Reg 413.993.228/102611-23. `Modules/Safety/resources/views/pdf/inspection-report-nl.blade.php` ya lo usa — **cualquier PDF nuevo que se cree debe incluir este partial** (ver memoria `project_official_branding`), no reinventar el encabezado.
- **Rebranding completo "Claesen Intelligence Hub" → "Claesen Outdoor Lighting Platform"** en los únicos 7 archivos donde sobrevivía: `Modules/Safety/Emails/InspectionReminderMail.php`, `Modules/Performance/Emails/{ImmediateRiskAlertMail,WatchdogRiskReportMail}.php` (nombre del remitente `Address`), `Modules/Performance/resources/views/emails/immediate-alert.blade.php` (footer), `Modules/Mailing/resources/views/emails/campaign.blade.php` (footer), `Modules/Mailing/Mail/Transport/MicrosoftGraphTransport.php` + `Modules/Mailing/Services/MicrosoftGraphService.php` (comentarios de cabecera).
- **Mismo bug de logo roto (URL externa `https://www.claesen-verlichting.be/v1/assets/...`) encontrado en 4 emails más** que nunca se habían tocado: `inspection-reminder.blade.php` (Safety), `immediate-alert.blade.php` y `watchdog-report.blade.php` (Performance, tema oscuro → `brand-logo-dark.png`), `campaign.blade.php` (Mailing). Todos arreglados con el mismo patrón `$message->embed(public_path('img/brand-logo-{light,dark}.png'))` ya usado en la sesión anterior. Dos páginas web (no email) con el mismo link roto (`unsubscribe.blade.php`, `preferences.blade.php` de Mailing) arregladas con `asset()` en vez de `embed()` (son HTTP normal, no van por Mailable).
- **Bug real más profundo encontrado al verificar (no reportado por el usuario, descubierto de oficio):** `MicrosoftGraphTransport::getPayload()` mandaba **todo** adjunto (incluidos los embebidos via `$message->embed()`) como `#microsoft.graph.fileAttachment` plano, sin `isInline`/`contentId`. Microsoft Graph necesita esos dos campos explícitos para tratar un adjunto como imagen inline referenciada por `cid:` en el HTML — sin ellos, el logo llega como archivo adjunto descargable suelto y el `<img src="cid:...">` del cuerpo queda roto en el cliente de correo. **Esto significa que el fix de la sesión anterior (email de inspección con el logo embebido) probablemente tampoco se veía bien en el correo real** — el `render()` de prueba de esa sesión no lo detectó porque ese método reemplaza `cid:` por `data:` base64 solo para previsualización, sin pasar nunca por este transport real. Corregido: `$attachment->hasContentId()` → agrega `isInline: true` + `contentId` al payload de Graph. Test de regresión nuevo (`Modules/Mailing/tests/Unit/MicrosoftGraphTransportPayloadTest.php`, 2 casos: embebido vs adjunto normal).
- **Verificado:** Safety 124/124 ✅, render manual de `ImmediateRiskAlertMail`/`WatchdogRiskReportMail` sin errores con el logo embebido presente, Mailing 178/189 ✅ (11 fallos en `FollowUpTest` — dispatch de `ExecuteCampaignJob` para follow-ups automáticos, código no tocado por este cambio, confirmado no relacionado). PDF completo verificado visualmente (`Read` sobre el PDF generado) contra la imagen de referencia del membrete — coincide.
- **Confirmado por el usuario (2026-07-07):** logo inline visible correctamente en un correo real recibido tras el fix de `isInline`/`contentId` en `MicrosoftGraphTransport`. Incidente cerrado, sin pendientes.

### Sesión 2026-07-06 (cont.) — Incidente producción: login Azure OAuth roto tras deploy — SESSION_DOMAIN + gap de opcache (Done, resuelto en caliente en prod-priv-01, sin ticket Linear formal — incidente urgente)

**Contexto:** tras el deploy de esta sesión (release `20260706212219`), el usuario reportó que el login de Microsoft en `https://backoffice.claesen.local/login` redirigía a Azure y volvía, pero se quedaba pegado en `/login` (probó también email/password, mismo resultado). Investigado en vivo por SSH contra `bert@192.168.254.52` (prod-priv-01), con logs reales de nginx/Laravel — no simulado.

- **Causa raíz #1 — `SESSION_DOMAIN` desincronizado:** `/srv/www/claesen/shared/.env` tenía `SESSION_DOMAIN=".claesen-verlichting.be"` pero el nginx de este servidor (`/etc/nginx/conf.d/claesen.conf`) solo sirve `server_name backoffice.claesen.local` — un dominio completamente distinto. Por RFC 6265 el navegador descarta cualquier cookie cuyo `Domain` no coincida con el host actual, así que la cookie de sesión que Laravel fijaba tras el callback de Azure nunca se guardaba — confirmado con el log real de nginx (`/auth/microsoft/callback` → 302 → `/login` como invitado → cada `/heartbeat` siguiente devolvía 302). Fix: `SESSION_DOMAIN=null` (deja que Laravel use el host de la request actual).
- **Causa raíz #2, más profunda — gap de opcache en prod:** después del fix de arriba, `config:cache` + verificación con tinker mostraban `session.domain = NULL` correctamente, pero la cookie real seguía saliendo con `domain=.claesen-verlichting.be` (confirmado con `curl` directo contra el servidor). Motivo: `/etc/php/8.4/fpm/conf.d/10-opcache-prod.ini` tiene `opcache.validate_timestamps=0` — endurecimiento estándar de producción que hace que PHP-FPM **nunca vuelva a leer archivos en disco por su cuenta**. `config:cache` reescribe el archivo cacheado pero los workers de PHP-FPM siguen sirviendo el bytecode viejo hasta que se recarga el servicio. `sudo systemctl reload php8.4-fpm` (reload, no restart) resolvió — reverificado con `curl` real: la cookie ya no lleva atributo `domain`. Usuario confirmó login funcionando después de este paso.
- **Hallazgo colateral:** el log de Laravel tenía una entrada vieja (`2026-07-06 16:39:06`) de un intento manual de `php artisan opcache:clear` — comando que no existe en Laravel — probablemente alguien intentando resolver este mismo tipo de problema antes sin éxito. Confirmado que **`deploy.sh` (tanto la copia versionada en `infrastructure/scripts/` como la real en `/opt/claesen/scripts/` de prod-priv-01) ya hacía `sudo systemctl reload php8.4-fpm` correctamente en el paso 9**, para deploys completos — el gap real es que **ediciones manuales de `shared/.env` fuera de un deploy completo** (como el fix de hoy) no tenían ningún mecanismo formal que incluyera ese reload.
- **Fix de proceso aplicado (a pedido del usuario, "actualiza deploy script"):**
  - Nuevo `infrastructure/scripts/reload-config.sh` (y copiado a `/opt/claesen/scripts/` en prod, con backup del `deploy.sh` anterior guardado ahí mismo): `config:clear` + `config:cache` (como `www-data`, mismo patrón que `deploy.sh`) + `systemctl reload php8.4-fpm` en un solo paso, para usar cada vez que se edite `.env` a mano sin pasar por un deploy completo.
  - Comentario agregado en `deploy.sh` línea del reload (paso 9) explicando por qué es obligatorio y no debe quitarse (referencia a este incidente).
  - `docs/ai/commands-runbook.md` y `docs/ai/production-readiness.md` actualizados con la nota de `opcache.validate_timestamps=0` y el nuevo script.
- **Nota de acceso:** toda la investigación y el fix se hicieron con acceso SSH directo a `bert@192.168.254.52` (sudo disponible) — logs de nginx (`/var/log/nginx/claesen-access.log`, `claesen-error.log`), `storage/logs/laravel.log`, `redis-cli`, y curls directos contra el servidor (`--resolve backoffice.claesen.local:443:192.168.254.52`, ya que "location /" en nginx no permite `127.0.0.1`, solo rangos de red internos) para confirmar cada hipótesis con evidencia real antes de tocar nada.
- **Pendiente:** confirmar con el usuario si quiere un ticket Linear retroactivo para este incidente (se resolvió en caliente, sin pasar por el flujo formal, con aprobación explícita en cada paso).

### Sesión 2026-07-06 (cont.) — Incidente producción #2: `SESSION_DOMAIN` estático en conflicto real entre Filament y Safety PWA (Done, código + deploy, sin ticket Linear formal)

**Contexto:** después de resolver el incidente #1 de arriba (login Azure OAuth), el usuario probó enviar una inspección real desde la app Claesen Safety y obtuvo `POST https://backend.claesen-verlichting.be/api/v1/safety/inspections → 419`. Mismo síntoma (CSRF/sesión), pero causa distinta y más profunda: no era un simple valor mal puesto, era un **conflicto real** entre dos consumidores del mismo backend.

- **Diagnóstico:** confirmado en `.env` de producción: `FRONTEND_URL=https://service.claesen-verlichting.be` y `SANCTUM_STATEFUL_DOMAINS=service.claesen-verlichting.be`. La PWA Safety (y Claesen-Sport, mismo repo `service.claesen-verlichting`) corre en `service.claesen-verlichting.be` y llama a la API en `backend.claesen-verlichting.be` — dos subdominios que necesitan **compartir** cookie de sesión/CSRF (Sanctum SPA stateful), lo cual solo funciona con `SESSION_DOMAIN=".claesen-verlichting.be"`. Ese era justamente el valor original que el incidente #1 identificó como "roto" — no estaba mal, era necesario para Safety/Sport, pero es *incompatible* con Filament en `backoffice.claesen.local` (dominio sin relación alguna, ni mismo TLD). Un único `SESSION_DOMAIN` estático no puede servir a ambos consumidores a la vez.
- **Fix:** `Modules/Core/Http/Middleware/ResolveSessionCookieDomain.php` (nuevo) — resuelve `config('session.domain')` dinámicamente por `$request->getHost()` en cada request: `.claesen-verlichting.be` si el host termina en ese sufijo (Safety/Sport), `null` en cualquier otro caso (Filament). Registrado como middleware **global** (`$middleware->prepend(...)` en `bootstrap/app.php`) para que corra antes que cualquier middleware que fije cookies (`StartSession`, `VerifyCsrfToken`, el pipeline stateful de Sanctum).
- **Detalle técnico importante (por qué middleware y no `ServiceProvider::boot()`):** se intentó primero poner esta lógica en `CoreServiceProvider::boot()`, pero se descartó — `boot()` corre una sola vez por ciclo de vida de la `Application`, no por cada request despachado. En PHP-FPM real esto coincide (proceso nuevo por request), pero en tests de Feature (`$this->get()`) el mismo `$app` se reusa entre llamadas dentro del mismo test, así que un `boot()` nunca vería el host real de cada request simulado — imposible de testear correctamente y arquitectónicamente fragil. Middleware sí corre en cada despacho de request, en producción y en tests por igual.
- **Gotcha de testing encontrado:** `$this->get('/up', ['Host' => 'algo'])` (pasar el header `Host` por separado) **no** cambia `$request->getHost()` en los tests de Laravel — hay que pasar la URL completa: `$this->get('http://algo/up')`. Confirmado con un middleware de debug temporal antes de dar con esto.
- **Tests:** `Modules/Core/tests/Feature/ResolveSessionCookieDomainTest.php` (4 casos: `*.claesen-verlichting.be` → dominio compartido, `backoffice.claesen.local` y host no relacionado → sin dominio fijo). Core 39/39 ✅, Safety 124/124 ✅, FieldOps 221/221 ✅ (la primera corrida de FieldOps dio 180 fallos falsos por haber matado a mitad de camino una corrida anterior de la suite completa que dejó la DB de test en estado inconsistente — resuelto con `migrate:fresh` sobre la DB `testing` y reconfirmado limpio).
- **Corrección crítica encontrada al verificar el primer deploy:** el primer diseño (decidir por `$request->getHost()`) se desplegó, pero al verificar contra el log real (`claesen-access.log`) se descubrió que **el túnel/proxy que trae el tráfico público de la API reescribe el Host interno a `backoffice.claesen.local` antes de llegar a Laravel** — las requests reales de `/api/v1/safety/*` de la SPA aparecen en el vhost de `backoffice.claesen.local`, no en el de `backend.claesen-verlichting.be` (que además resultó ser un vhost vestigial sin tráfico real). Con ese diseño, el middleware nunca habría detectado el dominio correcto para el tráfico real — solo lo hubiera detectado en pruebas directas por IP/Host que no reflejan el proxy real. Corregido para reusar `Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::fromFrontend()` (Origin/Referer contra `SANCTUM_STATEFUL_DOMAINS`, lo único que sobrevive el proxy) en vez de Host. Test de regresión agregado exactamente para este caso (`test_host_rewritten_by_tunnel_still_resolves_by_referer`).
- **Deploy:** commiteado y desplegado a producción vía `deploy.sh` completo (no solo hotfix manual, ya que es un cambio de código real) — dos veces: la primera con el diseño por Host (luego corregido), la segunda con el fix real por Origin/Referer. Verificado con curl real contra prod (Referer simulando la SPA → cookie con `domain=.claesen-verlichting.be`; navegación normal de Filament → sin `domain`) **y confirmado por el usuario en vivo**: login Filament OAuth funciona y el submit de inspección desde Claesen Safety ya no da 419. Incidente cerrado.
- **Pendiente:** igual que el incidente #1, confirmar con el usuario si quiere tickets Linear retroactivos (CLA-232/233 usados como referencia tentativa en el código/tests, sin ticket real creado todavía).

### Sesión 2026-07-06 — Fix logo roto en email/PDF de inspecciones Safety (Done, commit `c63c8d4`, pusheado a `origin/main`)

**Contexto:** el usuario reportó, con captura de pantalla de un correo real recibido en Gmail, que el logo de la empresa en el header del email "Nieuw veiligheidsrapport" aparecía roto (ícono de imagen no cargada), y que el PDF adjunto —documento con carácter oficial— nunca tuvo logo. Pedido explícito: no tocar el diseño, solo agregar el logo. Fix acotado, sin ticket Linear formal (aprobado explícitamente por el usuario saltar el protocolo para este caso puntual).

- **Causa raíz del email:** `Modules/Safety/resources/views/emails/inspection-report.blade.php` apuntaba a una URL externa (`https://www.claesen-verlichting.be/v1/assets/brand-logo-light.png`) — el sitio público (repo Astro separado `cubanote816/website-claesen-v1`), no este backend. Esa ruta no resuelve.
- **Causa raíz del PDF:** `Modules/Safety/resources/views/pdf/inspection-report-nl.blade.php` — el `<div class="header">` nunca tuvo un `<img>`, solo el `<h1>` del título.
- **Fix email:** en vez de apuntar a una URL externa frágil, se usa `$message->embed(public_path('img/brand-logo-light.png'))` — feature nativa de `Illuminate\Mail\Message` que incrusta el logo como adjunto CID dentro del propio correo (`$message` se inyecta automáticamente en cualquier vista de un Mailable basado en `Content`, `Mailer::render()`/`Mailer::send()` lo resuelven). Más robusto que depender de un dominio externo o de `APP_URL` estar bien configurado en producción.
- **Fix PDF:** mismo logo (`public/img/brand-logo-light.png`, texto oscuro — el header de ambos documentos tiene fondo blanco, confirmado visualmente contra `brand-logo-dark.png` que es la variante de texto blanco) embebido en base64 dentro de `.header`, arriba del `<h1>` — mismo patrón ya usado en ese archivo para las fotos de evidencia (`base64_encode(file_get_contents(...))`). Ninguna otra línea de diseño tocada.
- **Verificado:** PDF de prueba generado con logo visible (`Barryvdh\DomPDF\Facade\Pdf::loadView` real contra una inspección de dev); `InspectionReportMail::render()` confirma el embed (Laravel convierte `cid:` a base64 solo para previsualización standalone — en un envío real vía `Mail::send()` queda como adjunto CID embebido). Suite completa de Safety: 124/124 tests ✅, 0 regresiones.
- **Archivos tocados:** solo los 2 blade templates. Sin migraciones, sin cambios de lógica de negocio, sin nuevo ticket Linear (decisión explícita del usuario para este fix puntual).

### Sesión 2026-07-06 (cont.) — ElectricalBoard, Media, Client: mismo patrón de servicios sin portar (sin commitear)

**Contexto:** continuación directa de la sesión de Terrain/Structure/Luminaire de abajo. El usuario siguió navegando la app real (no Playwright) y fue reportando crashes/404 uno por uno; cada uno resultó ser el mismo patrón ya visto: un `services/*.ts` entero todavía apuntando 100% al satélite viejo (`api-claesen-sport-app`), nunca portado a `Modules/FieldOps`. Ninguno de estos tres dominios había sido tocado en la sesión anterior.

- **ElectricalBoard — Done, verificado en vivo (create/update + lectura de detalle):**
  - `electrical-board.service.ts`: `createElectricalBoardBy{Terrain,Structure,Complex}Id` y `updateElectricalBoard` posteaban a rutas muertas (`/electrical-board/{terrain|structure|complex}/create/{id}`, `/electrical-board/{belong}/update/{id}`) con `Authorization: Bearer` de `localStorage` (no existe en este esquema de sesión-cookie). Reescritos contra el contrato real: `POST /fieldops/electrical-boards` / `PUT /fieldops/electrical-boards/{id}` con pivot (`structure_ids`/`terrain_ids`/`complex_ids`: `[id]`) en el body, verificado contra `StoreElectricalBoardRequest`/`UpdateElectricalBoardRequest`.
  - **Bug real más profundo, encontrado después de arreglar el create:** el usuario abrió el detalle del tablero recién creado y crasheó en `ElectricalBoardMediaTab.tsx:19` (`electricalBoard.idDecrypt.toString()` sobre `undefined`) — `idDecrypt` es un campo heredado del esquema de IDs encriptados del satélite viejo; el backend nuevo devuelve `id` numérico plano. Investigando se confirmó que **todo el camino de lectura de ElectricalBoard nunca se adaptó** (solo se había tocado creación/edición): `typeNameNl/En/Fr/Es` y `locationDescriptionNl/...` tampoco existen tal cual en la respuesta real (vienen anidados como `electrical_board_type.name.{nl,en,fr,de}` y `location_description.{nl,en,fr,de}`).
  - `adapters/electricalBoardAdapter.ts` — **ya existía pero era código muerto**: nunca se importaba desde ningún service/hook, y encima solo hacía spread de `response.data` asumiendo que ya venía en el shape plano legacy (no adaptaba nada realmente). Reescrito con el mismo patrón `flattenTranslatable` de los demás adaptadores, y conectado esta vez de verdad en los 6 puntos de `electrical-board.service.ts` (fetch by id, fetch-by-padre×3, create×3, update).
  - Verificado en vivo: crear tablero → abrir detalle → click en tab Media → sin crash, sin `idDecrypt`/`TypeError` en consola.

- **Media (galería de archivos, `FileLibrary`/`mediaLibrary/`) — Done, verificado en vivo (upload + lectura + render):**
  - `media.service.ts`: `uploadFiles`/`fetchGalleryFiles` apuntaban a un endpoint `structure-schema` que nunca existió en FieldOps. FieldOps no tiene "schema" por entidad — los adjuntos van genéricamente por `POST /fieldops/{modelType}/{modelId}/media` (`FieldOpsMediaController`, un archivo por request, campo `file`+`collection: photos|documents`) y se leen embebidos en el recurso padre (`GET /fieldops/{modelType}/{modelId}` → `.photos`/`.documents`, vía `HasMediaPayload`). Reescritos ambos contra el contrato real.
  - **Segundo bug, encontrado *después* de este primer fix** (mismo patrón que ElectricalBoard: arreglar el fetch expuso un bug de shape que antes era invisible porque la galería siempre estaba vacía): con datos reales fluyendo por primera vez, `FileLibrary.tsx:992` crasheó en blanco (`Cannot read properties of undefined (reading 'toLowerCase')`, `file.fileName` era `undefined`). Investigando se encontró que **el tipo declarado no coincide con lo que el JSX realmente lee**: `models/api/upload.model.ts::MediaResource` (satélite viejo: `name`/`url`/`type`) está desactualizado — `MediaLibrary.tsx`/`GalleryViewer.tsx` en realidad leen una interfaz distinta definida inline (`ExtendedMediaResource`: `fileName`/`mimeType`/`small`/`medium`/`big`), y ni siquiera son consistentes entre sí (`ImagePreview.tsx` lee `.url`/`.name`, no wireado a `GalleryViewer` de todas formas). `fetchGalleryFiles` ahora rellena **ambos** juegos de campos por compatibilidad (`adaptGalleryItem` en `media.service.ts`) en vez de intentar unificar/limpiar los tipos de `mediaLibrary/` (fuera de alcance de este fix puntual).
  - Verificado en vivo: subir foto real (PNG 1x1) → `fetchGalleryFiles` la devuelve con `fileName` poblado → el `.filter(file => file.fileName.toLowerCase()...)` exacto que crasheaba corre limpio con datos reales.

- **Client (`client.service.ts`, 793 líneas) — Done para el camino alcanzable desde la UI, verificado en vivo:**
  - Reportado por el usuario: `ClientService.fetchAllClientsFromAPI` → `GET /client` (404, vía `API_ENDPOINTS.client.base`, config de endpoints 100% satélite viejo). Mismo patrón: archivo entero nunca portado.
  - `fetchAllClientsFromAPI`: el satélite viejo devolvía los ~1170 clientes en una sola llamada (asunción horneada en toda la arquitectura de caché de 24h de este archivo: "cargar todo una vez, cachear, slicear client-side para infinite scroll"). `Modules/FieldOps` (`FoClientController::index`) pagina a 50 fijo sin `meta`/`total` en la respuesta. Fix: loop de páginas (`?page=N`) hasta recibir una página corta (< 50), agregando todo client-side — preserva la arquitectura de caché existente sin tocar el resto del archivo.
  - `getClientById`/`getClientProjects` (usados por la página de detalle de cliente, `useClientDetail.ts`) — mismo problema, apuntaban a `/client/{id}` y `/client/projects/{id}`. Reescritos contra `/fieldops/clients/{id}` y `/fieldops/complexes?client_id={id}` (un "proyecto" de cliente en FieldOps es un `Complex` real, sincronizado por FO-013 desde `relation_delivery` — `ComplexResource` ya devuelve un shape plano compatible con lo que lee `ProjectsList.tsx`, sin adaptador adicional necesario).
  - `searchClients`: su fallback a API (cuando no hay caché) pegaba a `/client/search/{term}`, endpoint que no existe en FieldOps (no soporta parámetro de búsqueda). Reemplazado por cargar todo (`getClientsOptimized`) + filtrar localmente, igual que el camino normal con caché.
  - `fetchClientCount`: mismo problema que `terrains/count` ya documentado abajo — sin endpoint de conteo dedicado en FieldOps. En vez de pegarle a una ruta muerta o recorrer ~24 páginas solo para un badge, ahora deriva el total de la caché de `ClientService` (0 si todavía no hay caché).
  - `adapters/clientAdapter.ts` nuevo (`adaptClient`/`adaptClientList`) — mapea `FoClientResource` (`{id,name,street,city,phone,email,language,complexes_count}`) al `Client` del frontend (`street`→`address`).
  - Verificado en vivo: 1170 clientes reales agregados correctamente vía paginación, detalle de cliente + su complejo/proyecto asociado resueltos, conteo derivado de caché sin request de red.

- **Patrón confirmado transversal a toda la sesión (y probablemente a más servicios todavía no tocados):** cada dominio de `service.claesen-verlichting` que todavía no se había abierto en esta sesión resultó estar **100% sin portar** contra `Modules/FieldOps` — no roto a medias, sino construido enteramente contra el satélite viejo. El patrón de diagnóstico que funcionó las 3 veces: (1) arreglar el endpoint/contrato de la función que causaba el error reportado, (2) verificar en vivo con datos reales — lo cual expone un segundo bug de *shape* que antes era invisible porque el fetch nunca devolvía datos (ver ElectricalBoard e igual con Media). Pendiente: barrido proactivo de `src/services/*.ts` por `API_ENDPOINTS.*`/`axios.` crudo (no `getAxiosInstance()`) antes de seguir encontrándolos uno por uno — ofrecido al usuario, todavía no confirmado.
- **Nota de entorno:** mismo patrón que la sesión anterior — usuario de prueba `playwright-test@claesen-verlichting.be` (rol `super_admin` esta vez, para probar creación de catálogos/tableros/clientes) creado y borrado varias veces vía tinker durante la sesión; tableros eléctricos y archivos de media de prueba también borrados. No queda nada de prueba en la BD de dev.

### Sesión 2026-07-05/06 — Port de `service.claesen-verlichting` contra `Modules/FieldOps` real (Terrain ✅, Structure ✅, Luminaire 🚧, sin commitear)

**Contexto:** el usuario señaló que `service.claesen-verlichting` (frontend legacy React, corriendo en `:5174`, repo separado en `/home/totti/service.claesen-verlichting`) fue construido contra el satélite viejo `api-claesen-sport-app` y nunca contra el backend real de `Modules/FieldOps`. Aclaración explícita del usuario: **no tocar `api-claesen-sport-app`**, es solo referencia de lectura. `/terrain` crasheaba (`Cannot read properties of undefined (reading 'typeNl')`) porque el frontend esperaba el shape plano legacy (`nameNl/nameEn/nameFr/nameEs`, con soporte `es`) y el backend nuevo devuelve objetos traducibles `{nl,en,fr,de}` (spatie/laravel-translatable, sin `es` — FO-008).

**Patrón de fix (igual en los 3 dominios):** capa de adaptadores en `src/adapters/*.ts` (`translatable.ts`, `catalogAdapter.ts`, `terrainAdapter.ts`, `structureAdapter.ts`, `luminaireAdapter.ts`) que traduce el shape real del backend al shape legacy plano que ya esperan las vistas — así se evita reescribir decenas de componentes, solo se arregla el punto donde los datos entran a la app (funciones de `src/services/*.ts`). Mismo patrón para endpoints: varios `services/*.ts` (terrain, structure, luminaire, electrical-board-type) todavía usaban rutas del satélite viejo con `Authorization: Bearer` de `localStorage` en vez de sesión por cookie (`getAxiosInstance()`).

- **Login (session-cookie) funcionando** — requirió mover `POST /auth/login/spa` de `Modules/Core/routes/api.php` a `routes/web.php` (necesita el middleware `web` para sesión/CSRF) + agregar `http://localhost:5174` a `config/cors.php`. Este cambio ya estaba parcialmente hecho al arrancar la sesión (diff sin commitear encontrado al reanudar).

- **Terrain — Done, verificado en navegador real (Playwright + Chromium, instalado solo en el scratchpad de la sesión):**
  - `adapters/terrainAdapter.ts` nuevo. Conectado en `complex.service.ts::fetchTerrainsByComplexId` y `terrain.service.ts::fetchTerrainById`.
  - Endpoint roto corregido: `/terrain-type` (viejo, sin `/fieldops`) → `/fieldops/terrain-types` (en `useTerrainTypes.ts` y `AddTerrainView.tsx`).
  - Bug real corregido: `useEditTerrainModal.tsx` enviaba `name` como string plano al `PUT /fieldops/terrains/{id}`; el backend espera `{nl: "..."}` (mismo formato que `addTerrain`, ya correcto).
  - Breadcrumbs (`TerrainBreadcrumb.tsx`, `StructureBreadcrumb.tsx`, `AddStructureView.tsx`) usaban `axios` crudo a endpoints viejos (`/complex/{id}`, `/terrain/{id}`) con Bearer de `localStorage` — reemplazados por `fetchComplexById`/`fetchTerrainById` ya correctos.
  - Verificado: `/terrain` ya no crashea, muestra terrenos reales con tipo traducido.

- **Structure — Done, verificado en navegador (lectura Y escritura):**
  - `adapters/structureAdapter.ts` nuevo. Conectado en `terrain.service.ts::fetchStructureByTerrainId` y `structure.service.ts::fetchStructureById`.
  - `structure.service.ts::createStructure`/`updateStructure` estaban **rotos de fábrica** (referenciaban `axios`/`BASE_URL` que ni siquiera estaban importados en el archivo — `ReferenceError` en cuanto se ejecutaban). Reescritos contra `POST/PUT /fieldops/structures` con el payload real (`structure_type_id`, `access_type_id`, `access_active`, `safety_type_id`, `safety_certified`, `info:{nl}`).
  - Verificado extremo a extremo: crear estructura (`POST` → 201) y editarla (`PUT` → 200, altura 10→20) con datos reales, 0 errores de página.

- **Luminaire — backend + adaptador + service reescritos y verificados por llamadas directas a la API real (dentro del navegador vía `import()` dinámico), pendiente de probar clicks reales en la UI del wizard:**
  - **Decisiones de producto tomadas con el usuario antes de implementar:**
    - Escala de luminaria en el canvas (`scale_x`/`scale_y`) — el backend nuevo no tenía estos campos (solo `frame_x`/`frame_y` de posición). El usuario pidió **agregarlos al backend** en vez de abandonar el feature.
    - "Crear frame personalizado tomando una foto" (`CameraCapture` → antes `/luminaire-frame-type/custom/{structureId}`, creaba frame+luminaria en un paso) — el usuario pidió **mantenerlo** (con miras a futuro poblado por IA de la posición de luces). Se implementó como creación de un `LuminaireFrameType` de catálogo asociado al usuario (`created_by_user_id`, columna que ya existía en el schema para este caso) — a diferencia del viejo, ahora solo crea el *tipo*, no la instancia+luminaria en un solo paso; el usuario continúa el flujo normal de alta eligiendo el tipo recién creado.
  - **Backend (`Modules/FieldOps`):**
    - Migración `2026_07_06_028_add_scale_to_fo_luminaires_table.php` (`scale_x`, `scale_y` nullable) + `Luminaire` model/`LuminaireResource`/`Store`+`UpdateLuminaireRequest` actualizados (se actualizan vía el `PUT` normal, sin endpoint nuevo).
    - Endpoint nuevo `POST /fieldops/luminaire-frame-types/custom` (`CatalogController::storeCustomLuminaireFrameType`, multipart `name`+`photo`, guarda en disco `public`).
    - `image` agregado a los objetos anidados `luminaire_type` (`LuminaireResource`) y `frame_type` (`LuminaireFrameResource`) — el dato ya existía en el catálogo pero no se exponía en la instancia, necesario para mostrar el ícono correcto en el canvas.
    - **Bug real encontrado y corregido** (no introducido por esta sesión, ya existía): `StructureResource.php` llamaba a `LuminaireType::getTranslations('name')` dentro de `luminaire_frames[].luminaires[].type`, pero `LuminaireType.name` es un string plano (no traducible, decisión Slice C) — crasheaba con 500 (`BadMethodCallException`) en cuanto una estructura tenía **al menos una luminaria real** (por eso nunca se había detectado: los tests y el uso previo siempre tenían frames vacíos). Fix + test de regresión (`test_show_includes_luminaire_type_name_when_frame_has_luminaires`) en `StructureCrudTest.php`.
    - Tests: `LuminaireCrudTest` (+2 casos de scale), `CustomLuminaireFrameTypeTest` (nuevo, 3 casos), `StructureCrudTest` (+1 regresión). FieldOps completo: 220/220 ✅.
  - **Frontend (`service.claesen-verlichting`):**
    - `adapters/luminaireAdapter.ts` nuevo — dos familias de shape: "catálogo/selección" (Group/Subgroup/TypeModel, preserva el anidado legacy tal cual para no tocar el wizard de alta `useLuminaireModal`/`useLuminaireData`/`LuminaireGroupList`/`useReplacementLuminaireModal`) e "instancia" (Luminaire/LuminaireFrame, shape plano nuevo y más simple).
    - **"Group" legacy no existe más como tabla** (fusionado en `fo_luminaire_subgroups.group_name`, Slice C) — se reconstruye como valor sintético derivado de los `group_name` únicos entre subgrupos reales; el wizard de 3 niveles (Group→Subgroup→Type) sigue funcionando sin tocarlo.
    - `luminaire.service.ts` reescrito completo (antes: 785 líneas, 100% de las funciones usaban `AuthUtils.getToken()`+endpoints del satélite viejo que no existen en `Modules/FieldOps`). Modules/FieldOps no tiene endpoint directo "luminarias de una estructura" (a diferencia del viejo) — se resuelve vía `Structure.luminaire_frames` (shape mínimo, solo en `show()`) + `GET /luminaire-frames/{id}` por cada frame para el detalle completo. Tampoco hay lookup "por posición" — se resuelve trayendo `GET /luminaire-frames/{frame}/luminaires` y filtrando client-side por `frame_position`.
    - Simplificados sin más ramas defensivas (ya no hace falta adivinar el shape, el adaptador garantiza uno solo): `FrameDetailsView.tsx` (`processedLuminaires`), `LuminaireFrameView.tsx`, `LuminaireInformationTab.tsx`.
    - `luminaire-frame-custom.service.ts` + `CameraCapture.tsx` + `SelectFrame.tsx::handleFrameCreated` reescritos contra el endpoint nuevo.
    - `getMaintenanceHistory`/`createMaintenanceRecord`/`getLuminaireModels`/`createLuminaireFrameType`/`getLuminaireDetails` (ID-based) eliminados de `luminaire.service.ts` — confirmado que no tenían consumidores vivos (los tabs Maintenance/Services de `LuminaireDetailPage` **ya estaban correctamente portados** de una sesión anterior, usan `maintenance.service.ts`/`customerService.service.ts`, no este archivo).
  - **Verificado (vía llamadas directas a los métodos del service dentro de una página real logueada, con datos reales):** catálogos (grupos sintéticos LED/HID, subgrupos, tipos filtrados), crear luminaria (`POST` → luminaria real creada), actualizar posición y escala (`PUT` → ambos confirmados), lookup por posición, y render del canvas (`GraphicalView`) con la luminaria creada mostrando su marcador — **0 errores de página**.
  - **Pendiente / no verificado:** flujo completo de clicks reales en el wizard de alta (`AddLuminaireModal`/`useLuminaireModal`) desde la UI (se verificó la lógica subyacente por llamada directa, no el click-through); drag-and-drop real en el canvas; `LuminaireDetailPage` por navegación de click real (la navegación por URL directa tuvo timeouts de Playwright, posible flakiness del entorno — la lógica de datos ya se confirmó correcta por separado).
  - **Fuera de alcance, explícitamente diferido:** ninguno — el usuario pidió el dominio completo. Lo que quedó pendiente es solo verificación adicional, no diseño.
- **Nota de entorno:** usuario de prueba temporal `playwright-test@claesen-verlichting.be` (rol `project_manager`) creado y borrado varias veces durante la sesión — no queda en la base de dev. Estructuras/luminarias de prueba (`Playwright test structure`, `SN-TEST-*`, etc.) también borradas.

- **Sesión 2026-07-05 (cont.) — catálogos, UX de campos traducibles, y 419 (Done, ver detalle completo en `CLAUDE.md`):**
  - `client_id` de `Complex` ahora inmutable vía API (`UpdateComplexRequest`) — el vínculo viene del sync CAFCA, nunca se reasigna a mano. De paso se encontró que el selector de cliente en `ComplexFormModal.tsx` solo cargaba los primeros 50 de 1167 clientes (por eso a veces se veía vacío) — resuelto reemplazándolo por texto de solo lectura.
  - 8 catálogos de FieldOps (`AccessType`, `ElectricalBoardType`, `SafetyType`, `StructureType`, `TerrainType`, `LuminaireFrameType`, `LuminaireSubgroup`, `LuminaireType`) portados del satélite viejo — estaban vacíos en dev, los selects de Terrain/Structure/Luminaire/ElectricalBoard no tenían nada para elegir. `php artisan db:seed --class="Modules\FieldOps\Database\Seeders\FieldOpsCatalogSeeder"` (idempotente).
  - Reemplazados los 4 inputs por-idioma (nl/en/fr/de) por un solo campo + auto-traducción IA (`HasAiTranslations` ya lo hacía automático, solo faltaba que la UI dejara de forzar los 4) — en 12 resources de Filament y en `TranslatableInput.tsx` de Claesen-Sport.
  - Bug real encontrado de paso: `ComplexesListPage.tsx`/`ClientsListPage.tsx` tenían un loop infinito de render ("Maximum update depth exceeded") por `data = []` inline en la destructuración de `useQuery` — corregido con una constante estable a nivel módulo.
  - Mapa satelital en `MapPicker.tsx` (Esri World Imagery, sin API key).
  - **Bug real de CSRF (419) en Claesen-Sport, dos causas combinadas:** cookies `Secure` por defecto corriendo sobre `http://localhost` (`SESSION_SECURE_COOKIE=false` en `.env` local) + axios no manda `X-XSRF-TOKEN` en requests cross-origin por defecto desde v1.6, y `localhost:5173`→`localhost:8000` son puertos distintos (`withXSRFToken: true` en `client.ts`). Confirmado por el usuario que ya funciona.
  - **Pendiente:** commitear (backend: 12 archivos Filament + seeders ya en `3bfd95f`; frontend: `TranslatableInput.tsx`, `client.ts`, `MapPicker.tsx`, `ComplexesListPage.tsx`, `ClientsListPage.tsx`, `ComplexFormModal.tsx`).
- **FO-012 / CLA-226 y FO-013 / CLA-227 (Done, 2026-07-05, sin commitear todavía):** a partir de un pedido del usuario de que "la opción de crear un cliente no debería existir" en `Claesen-Sport`, se auditó el satélite viejo (`api-claesen-sport-app`) y se confirmó que ni `Client` ni `Complex` tuvieron nunca una vía de creación manual intencional (`ComplexController::store()` viejo nunca se registró en rutas, con un `//TODO importar desde cafca` explícito). Se construyó el bridge real desde el ERP para ambos, ver detalle completo en `CLAUDE.md` (sección FieldOps). Resumen:
  - `fieldops:sync-clients-from-relations`: 1167 clientes reales importados desde `MirrorRelation` (filtro `tp_customer=1`). De paso se corrigieron 2 bugs reales en `SyncMirrorDataService::syncRelations()` (`phone` 100% vacío, `language` sin decodificar — este último afecta también al Offer Simulator vía `MirrorRelation::getIsNlAttribute()`).
  - `fieldops:sync-complexes-from-relation-deliveries`: 887 complejos reales importados desde un mirror nuevo (`MirrorRelationDelivery`, tabla CAFCA `relation_delivery`) + geocoding automático (Google Geocoding API, solo primera vez, nunca pisa coordenadas manuales) — 883/887 con coordenadas reales.
  - Creación manual deshabilitada en Filament (`FoClientResource`/`ComplexResource`, `canCreate(): false`) y en `Claesen-Sport` (botón "+" quitado de `ClientsListPage`/`ComplexesListPage`) — edición sigue habilitada en ambos lados.
  - **Pendiente de confirmar con el negocio:** el mapeo de `relation.language` (1/2/3/4 → nl/fr/en/de) es una inferencia por distribución de datos, no una confirmación real del ERP.
  - **Pendiente:** la Google Geocoding API key está restringida por IP en Google Cloud Console a la IP de este entorno dev (`169.155.241.57`) — si se corre este sync desde producción hay que agregar esa IP también. Google Cloud project: `gen-lang-client-0849598291` (mismo proyecto que ya usa `GEMINI_API_KEY` para IA).
  - **Sin commitear todavía** — tickets creados en Linear como `In Progress`, pendiente commit dedicado + mover a `Done`.
  - **Nota de git status:** en el mismo working tree hay cambios sin commitear de una sesión anterior sin relación con este ticket (login SPA `Modules/Core/routes/*.php`, sidebar-scroll/session-expired-modal en `AdminPanelProvider.php`, etc.) — se aisló con `git stash` para no mezclarlos en este commit; quedan en el stash a la espera de su propio ticket/commit.
- **Merge `issue-dashboard` → `main` (2026-07-05):** trae CLA-219 (fix "can be closed" prematuro + dark mode), CLA-221 (fila clicable en Billing Control), CLA-222 (scroll de sidebar persistente), CLA-223/224 (Offer Simulator restringido y oculto del menú) y CLA-225 (heartbeat real de SessionKeeper). CLA-208 ya estaba en `main` desde antes (mergeado por otra vía) — el cherry-pick `f830b2d` de `issue-dashboard` quedó reconciliado sin duplicar nada. Merge hecho en un worktree temporal (`git worktree add ... -b temp-main-merge main`) porque `main` ya estaba checked-out en `claesen_api_web_oficial` con cambios sin commitear ajenos a esta sesión (trabajo en curso de FieldOps sync-from-relations) — no se tocó ese worktree, solo se movió el ref `main` al terminar. Conflictos resueltos: `AdminPanelProvider.php` (conservar las 3 vistas del mismo render hook `BODY_END`: `sidebar-scroll-restore`, `session-expired-modal`, `session-keeper`) y este mismo `handoff.md`.
- **Contexto previo a este merge (mismo día, merge anterior):** FO-009 recién cerrado, dashboard Safety CLA-215/216/217 recién mergeado desde `issue-dashboard`, CLA-208 recién mergeado desde `issues-0001`.
- **Merge `issue-dashboard` → `main` (anterior, mismo día):** trae CLA-215 (títulos claros + selector de periodo 7/30/90 días en las gráficas de Safety), CLA-216 (tabla "Recent Safety Inspections" limitada a 5 filas) y CLA-217 (el detalle operativo de Safety — stats, tendencia diaria, tabla de inspecciones — se ocultó del dashboard general y se movió a la página `Inspections`; el dashboard general solo conserva `SafetyAdoptionOverviewWidget`, señal ejecutiva `super_admin`-only). Conflicto real en el merge: `Modules/FieldOps/Routes/api.php` (ambas ramas agregaban rutas nuevas en el mismo punto — FO-009 sumó rutas de Mantenimiento, `issue-dashboard` sumó rutas de catálogo FO-010; se conservaron ambos bloques, no eran mutuamente excluyentes) y este mismo `handoff.md` (se conservaron ambas narrativas en orden cronológico).
- **Rama actual:** `main`, **pusheado a `origin/main`** (`61c7f62..afd5e43`, commit FO-009). El deploy en producción corre migraciones y `config:cache` automáticamente (confirmado por el usuario) — no se requiere acción manual post-push.
- **FO-009 / CLA-213 (Done, commit `afd5e43` — 2026-07-04):** Slice G, dominio de Mantenimiento de luminarias. Hallazgo clave de esta sesión: el spike FO-007 había auditado la rama `master` del satélite viejo, pero la producción real corre sobre `origin/deploy`, que tiene un dominio mucho más rico (relación polimórfica Luminaire/ElectricalBoard, incidencias/emergencias, subdominio completo de "reportado por cliente"). Implementado:
  - **Migraciones:** `create_fo_maintenance_types_table`, `create_fo_maintenance_records_table`.
  - **Modelos:** `FoMaintenanceType` (catálogo traducible, `code` string en vez de IDs hardcodeados) y `FoMaintenanceRecord` (polimórfico `maintainable_type`/`maintainable_id` vía `morphs()`, apunta a `Luminaire` o `ElectricalBoard`).
  - **`employee_id`** string, referencia blanda a `employees.id` (mismo patrón que `Safety\Inspection::incident_worker_id`), sin FK de BD.
  - **Cliente-reportado:** columnas reales (`client_id` FK a `fo_clients`, `priority`, `contact_person`, `contact_phone`, `location_details`, `reported_by_client`) en vez de JSON enterrado como en el sistema viejo.
  - **Un solo controller polimórfico** (`MaintenanceRecordController`) en vez de los dos duplicados del sistema viejo — mismo principio que `FieldOpsMediaController` (FO-005).
  - **API:** rutas anidadas por equipo (`/luminaires/{id}/maintenance-records`, `/electrical-boards/{id}/maintenance-records`) + `/maintenance-records/{id}` CRUD + stats correctivo + subdominio cliente-reportado completo (store/pending/statistics/resolve) + catálogo `/maintenance-types`.
  - **Filament:** catálogo `FoMaintenanceTypeResource` (super_admin only) + `FoMaintenanceRecordResource` (super_admin/admin, select dependiente tipo→equipo).
  - **Excluido a propósito:** `ScheduledMaintenanceService` y `Task` del sistema viejo — CRUD genérico sin evolución real en 12+ meses de historial, sin relación de FK entre sí, a diferencia de `MaintenanceServicesHistory` (6+ commits de desarrollo sustancial). Decisión tomada con el usuario antes de implementar.
  - **Bug real encontrado por el propio smoke test de esta sesión:** el closure `afterStateUpdated`/`options` del Select dependiente en Filament V5 requiere el type-hint `Filament\Schemas\Components\Utilities\Get`, no `Filament\Forms\Get` (namespace de V3/V4) — sin el fix, la página `/fo-maintenance-records/create` tiraba 500. Detectado porque se agregó un test de renderizado real de las páginas Filament (no solo API), no solo verificación de schema en tinker.
  - **Tests:** 21 tests API nuevos (`MaintenanceRecordCrudTest`, `ClientReportedMaintenanceTest`) + 1 test de renderizado Filament (`FoMaintenanceFilamentTest`, 5 asserts, cubre index/create/edit de ambos resources). FieldOps completo: 187/187 ✅. Suite completa: 45 fallos preexistentes (mismo número ya documentado — Mailing/Website/Safety, sin relación con este ticket), 0 regresiones nuevas.
  - **FO-006 (cutover) ya no bloqueado** por la parte de Mantenimiento cubierta aquí — si el cutover necesita mantenimiento *programado* a futuro (`ScheduledMaintenanceService`), es un ticket nuevo a abrir antes de cerrar C.6b.
- **CLA-215/216/217 (Done, dashboard Safety, mergeados a `main` en esta sesión):** el Dashboard general de Filament mostraba, vía auto-discovery, tanto señal ejecutiva como detalle operativo de Safety mezclados sin selector de rango. CLA-215: se agregó selector de periodo (Yesterday/7/30/90 días) a `SafetyAdoptionOverviewWidget` y `SafetyStatsWidget` (recalcula valores + sparkline), títulos ahora incluyen siempre el periodo mostrado, y se agregó `InspectionsTrendChartWidget` (gráfica de línea real con heading/description explícitos y filtro nativo de Filament `ChartWidget`). CLA-216: `LatestInspectionsWidget` limitado a 5 filas (antes 10). CLA-217: `SafetyStatsWidget`, `InspectionsTrendChartWidget` y `LatestInspectionsWidget` se ocultan del dashboard general vía `canView()` (mismo patrón que `EmployeePerformanceChartWidget` de Performance) y se registran explícitamente en `ListInspections` — el dashboard general queda solo con `SafetyAdoptionOverviewWidget`. Test de regresión nuevo: `tests/Feature/DashboardWidgetPlacementTest.php` (sin cobertura previa de esta lógica de `canView()`).
- **FO-010 / CLA-214 (Done, commit `36eef56`, mergeado a `main` en esta sesión):** al planear el port del frontend de instalaciones a `Claesen-Sport`, se encontró que solo `GET /terrain-types` existía como catálogo — faltaban listados para `StructureType`, `AccessType`, `SafetyType`, `ElectricalBoardType`, `LuminaireFrameType`, `LuminaireType`, `LuminaireSubgroup` (necesarios para poblar selects en los formularios de alta). Implementado `CatalogController` (mismo patrón que `TerrainController@types`) + 7 `Resources` + 7 rutas GET + 8 tests nuevos (`CatalogEndpointsTest`), 181/181 FieldOps ✅. Se trabajó en la rama `fieldops-catalog-endpoints`/`issue-dashboard` (worktree `claesen_api_dashboard_safety`, puerto 8001) porque `main` ya estaba checked-out en `claesen_api_web_oficial` (puerto 8000); GO confirmado y mergeado en esta sesión, ya no bloqueado.
- **Port frontend de instalaciones a `Claesen-Sport` (sin commitear, sin probar en navegador):** se construyó el dominio completo Complex→Terrain→Structure→(ElectricalBoard | LuminaireFrame→Luminaire) + adjuntos, consumiendo el contrato real de `Modules/FieldOps` (no la app legacy `service.claesen-verlichting`, que sirvió solo de referencia de UX). Detalle en memoria `project_fieldops_sport_port`. Verificado: `npm run build` limpio, contrato confirmado con curl+token real contra el backend, dev server transforma todos los módulos sin error — **no se hizo el recorrido visual en navegador** (sin herramienta de screenshot/browser disponible en el entorno). Fuera de alcance (sin API en Core): clientes, customer service, empleados, mantenimiento (bloqueado por `FO-009`, ya resuelto).
- **Aclaración de infraestructura local — dos worktrees del mismo repo corriendo Sail en paralelo:** `claesen_api_web_oficial` (rama `main`) → puerto **8000**; `claesen_api_dashboard_safety` (rama `issue-dashboard`, ya mergeada) → puerto **8001**. Comparten la misma base de datos de dev. Con este merge, los endpoints de catálogo de FO-010 y los widgets de Safety (CLA-215/216/217) ya están en `main` — confirmar con el usuario si `Claesen-Sport/.env` (`VITE_API_URL`) debe seguir apuntando a 8000 u 8001.
- **Incidente menor (sesión FO-010):** durante la verificación se mató por error un proceso `vite` en el puerto 5173 que ya estaba corriendo antes de esa sesión (no arrancado por el asistente) — se avisó al usuario, no se confirmó su origen.
- **Rama `issue-dashboard` (mergeada a `main` en esta sesión):** traía además `f38c6f6..3c66c5f` (10 commits: FO-008, FO-004, FO-003, FO-005, FO-007 + docs + label "(Demo)"), ya presentes en `main` de antes.
- **CLA-224 (Done, commit `4c8d63a`, rama `issue-dashboard`):** siguiendo CLA-223, el usuario pidio sacar Offer Simulator del menu de navegacion en TODOS los entornos (no solo produccion) porque "parece codigo muerto". Implementado `shouldRegisterNavigation() -> false` (siempre) en `OfferSimulator.php` + test `test_navigation_item_is_never_registered`. **Interpretacion aplicada:** se oculta el item del menu, el codigo/pagina NO se borra ni se revierte de main — sigue alcanzable por URL directa para super_admin fuera de produccion (mismo gate de CLA-223).
- **CLA-225 (Done, commit `77af174`, rama `issue-dashboard`):** el usuario vio el confirm() nativo de Livewire ("This page has expired") en `/inspections` y pregunto si esto ya estaba resuelto. Causa raiz real: `SessionKeeper` (commit original `7da9494`, 2026-05-01) creo `HeartbeatController`+ruta `core.heartbeat` con la intencion de mantener la sesion viva, pero el JS nunca llego a llamarla — solo trackeaba actividad del navegador para un auto-logout silencioso por inactividad, sin tocar nunca la sesion real del servidor. Con `SESSION_LIFETIME=120min` y una sesion de trabajo de mas de 2h, la sesion de PHP expiraba de verdad aunque el usuario estuviera activo. Fix: segundo `setInterval` (60s) en `session-keeper.blade.php` que hace `fetch(route('core.heartbeat'))` solo si hubo actividad reciente (no contradice el auto-logout por inactividad). Test `HeartbeatTest` (2 casos).
- **CLA-208 (Done originalmente en rama `claesen_sport`, portado a `issue-dashboard` en commit `f830b2d`):** modal de sesion expirada personalizado (reemplaza el confirm() nativo de Livewire ante HTTP 419) ya existia resuelto en otra rama (commit `94b9ff9`) pero nunca se habia mergeado a `main`/`issue-dashboard`. Se trajo via cherry-pick tras confirmar con el usuario que "esto ya lo habiamos personalizado en otra rama". Conflicto de merge en `AdminPanelProvider.php` resuelto conservando ambas vistas del mismo render hook `BODY_END` (esta y `sidebar-scroll-restore` de CLA-222). **Nota importante:** CLA-208 mejora la experiencia cuando la sesion YA expiro (modal branded en vez de dialogo feo), pero no evitaba que expirara — por eso hizo falta ademas CLA-225 (el heartbeat real) para atacar la causa de fondo.
- **CLA-223 (Done, commit `1c8ad60`, rama `issue-dashboard`):** el usuario senalo que Offer Simulator (`Modules/Intelligence/Filament/Pages/OfferSimulator.php`) esta muy crudo y no deberia estar expuesto tal como esta en `main` (ya mergeado hace tiempo, solo con badge "DEMO", sin restriccion real de acceso). Se le pregunto que tan fuerte queria la restriccion (ocultar del panel / condicionar por env / revertir el feature de main) — no hubo respuesta a tiempo a la primera pregunta, asi que se implemento la opcion mas conservadora (canAccess() -&gt; super_admin) como primer commit; el usuario luego pidio explicitamente la opcion "condicionar por env" y se reemplazo por: `canAccess()` bloquea por completo si `app()->environment('production')`, y fuera de produccion exige `super_admin`. El feature **no se revirtio de main** — sigue existiendo el codigo, solo cambia quien/donde puede acceder. Test `OfferSimulatorAccessTest` (6 casos). Suite Intelligence completa: 138 tests / 271 assertions.
- **CLA-222 (Done, rama `issue-dashboard`):** el sidebar del panel Filament perdía la posicion de scroll al navegar entre paginas — a pesar de tener `->spa()` habilitado (`app/Providers/Filament/AdminPanelProvider.php`), el `<nav class="fi-sidebar-nav">` de Filament no esta envuelto en `@persist()` (a diferencia del topbar), asi que el nodo se reconstruye en cada transicion SPA y su `scrollTop` vuelve a 0. Limitacion conocida de Filament, no configuracion incorrecta del proyecto. Fix: script nuevo (`Modules/Core/resources/views/filament/sidebar-scroll-restore.blade.php`, registrado via `PanelsRenderHook::BODY_END` en `AdminPanelProvider::register()`, mismo patron ya usado por `Modules/Prospects` para su boton flotante) que guarda `scrollTop` en `sessionStorage` en `livewire:navigate` y lo restaura en `livewire:navigated`. **Primer intento sin delay no funciono** (el usuario confirmo que no se mantenia la posicion) — causa: el propio `scroll-sidebar.js` de Filament usa un `setTimeout(..., 10)` antes de tocar el scroll porque el layout (grupos colapsables) no esta asentado inmediatamente despues del swap; nuestro restore corria sincrono y el `scrollTop` se clampeaba a 0. Fix aplicado: mismo delay de 10ms + un segundo intento a 100ms como red de seguridad. **Confirmado funcionando por el usuario** tras el ajuste. No se toca `vendor/filament` en ningun momento.
- **CLA-221 (rama `issue-dashboard`):** cada fila de la tabla reutilizable de alertas (`Modules/Intelligence/resources/views/filament/pages/billing-control-table.blade.php`, usada en las 4 secciones de Billing Control) ahora es completamente clicable (`wire:click="openModal(...)"` en el `<tr>`) y abre el modal de detalle — antes solo el boton pequeno "Details" (ahora eliminado por redundante) lo hacia. El link "Insights" usa `@click.stop` y los 5 botones de flujo (Review/Confirm/Dismiss/Resolve/Reopen) usan `wire:click.stop` para no disparar el modal al mismo tiempo que su propia accion.
- **CLA-219 (Done, commit `5089df0`, rama `issue-dashboard`):** el usuario vio "Month July 2026 can be closed" en Billing Control el día 4 de julio y reportó que era engañoso — el mes recién empezaba. Causa: `$canClose` en `billing-control.blade.php` solo miraba conteo de alertas (`critical_open`/`high_open`/`confirmed_open`/`blocker`), sin comparar el período seleccionado contra la fecha actual. Con casi ninguna actividad de facturación aún, los contadores estaban en cero → falso positivo de "listo para cerrar". Fix: `getMaandafsluitingData()` (`MonthlyBillingControlPage.php`) ahora calcula `period_ended` (`endOfMonth()->isPast()`, Europe/Brussels); `$canClose` lo exige además de los conteos; se agregó un tercer estado neutro gris ("Month in progress"/"De maand loopt nog") para mes en curso o futuro. Test nuevo `BillingControlPeriodEndedTest` (4 casos). Suite Intelligence completa: 132 tests / 265 assertions, sin regresiones. Esta página **no cierra el mes realmente** (eso ocurre en CAFCA/ERP externo) — es solo un indicador de estado, pero el mensaje ya no es engañoso. Fuera de alcance (mencionado en el ticket, no implementado): tracking de "última corrida de Guardian por período" — mejora futura opcional si se retoma. **Follow-up (mismo ticket, commit separado):** el estado neutro se implementó primero con `dark:bg-gray-800/40` — variante de opacidad nunca usada antes en el archivo, Tailwind JIT no la compiló, así que en dark mode el banner se veía blanco (caía al valor de modo claro `bg-gray-50`). Corregido reusando la paleta azul informativa ya establecida en el mismo archivo (línea ~293: `bg-blue-50 dark:bg-blue-900/10`) + `npm run build` para recompilar assets.
- **Auditoria FieldOps vs `api-claesen-sport-app` (2026-07-03):** el usuario pidió comparar `Modules/FieldOps` contra el API satelite anterior (`/home/totti/api-claesen-sport-app`) antes de un push a producción, para confirmar que no falta nada y no se duplica código. Hallazgo clave: `fo_admin` ya estaba mezclado en `main` y en `origin/main` (no era evitable, ya estaba pusheado) — se acordó con el usuario dejarlo, pero marcar el menú "Field Operations" como **"(Demo)"** en `lang/en,nl/navigation.php` (clave `navigation.groups.field_operations`) hasta que los gaps se cierren y se confirme el cutover. Gaps reales encontrados (ElectricalBoard, Access/Safety de estructura, adjuntos de archivos/Media, cutover de Sport, dominio de mantenimiento) — **no** son gaps: `LuminaireGroup` colapsado a `group_name` string y `ComplexZoomLevel` colapsado a `zoom` único en `Complex`, ambos son decisiones de diseño explícitas ya documentadas en `project_fieldops_sprint` memory / Slice C.
  - **Tickets Linear** (equipo Claesen): `CLA-206`/FO-008 (✅ Done), `CLA-207`/FO-004 (✅ Done), `CLA-209`/FO-003 (✅ Done), `CLA-210`/FO-005 (✅ Done), `CLA-212`/FO-007 (✅ Done, spike, ver abajo), `CLA-213`/FO-009 (✅ Done, commit `afd5e43`, ver entrada de sesión más abajo), `FO-006` (Slice C.6b cutover, ⬜ Todo, ya no bloqueado por la parte de Mantenimiento cubierta en FO-009).
  - **Orden de trabajo acordado:** FO-008 → FO-004 → FO-003 → FO-005 → FO-007 → FO-009 → **FO-006**.
- **FO-007 / CLA-212 (Done — spike, sin código, commit N/A):** investigación sobre si el dominio de Mantenimiento de luminarias del sistema anterior (`TypeMaintenance`, `MaintenanceServicesHistory`, `ScheduledMaintenanceService`, `Task`) es funcionalidad real en uso o código abandonado. Evidencia en el código de Sport: 6+ commits de desarrollo iterativo sustancial en `MaintenanceServicesHistory` (soporte para "servicios reportados por clientes", estadísticas, mantenimiento por frame/posición, tracking de emergencias) — no parecía un prototipo. **El usuario confirmó directamente que está vivo en producción.** Conclusión: NO se cierra como "no aplica" — se abrió `CLA-213`/`FO-009` (Slice G), planificado e implementado en la misma sesión (ver entrada de sesión FO-009 más abajo, commit `afd5e43`).
- **FO-005 / CLA-210 (Done, commit `f80e0cb`):** adjuntos de fotos/PDFs para Complex/Terrain/Structure/ElectricalBoard. Hallazgo importante: `Modules/Safety` **no usa** `spatie/laravel-medialibrary` (usa columnas planas + streaming manual + Gate policies) — se adoptó el patrón real que sí usan `Website\Project`/`Cafca\Employee`, pero forzando el disco **privado** `local` (mismo `storage_path('app/private')` de Safety) en vez del disco público por defecto de la librería. Trait compartido `HasFieldOpsMedia` (colecciones `photos`/`documents`, conversión `thumb`) — requirió resolver colisión de métodos con `InteractsWithMedia` vía `insteadof` en los 4 modelos. 1 controller genérico `FieldOpsMediaController` (no 4 duplicados). No se implementó `SchemaComment` ni upload chunked (sin evidencia de necesidad real). 17 tests nuevos, 165/165 FieldOps ✅. Suite completa: 0 regresiones.
- **FO-003 / CLA-209 (Done, commit `603baf7`):** dominio completo de Electrical Board — catálogo `ElectricalBoardType` (mismo patrón `super_admin` only) + entidad `ElectricalBoard` (`lat`/`lng`, `location_description` traducible) con **3 pivots reales** (`fo_complex_electrical_board`, `fo_electrical_board_terrain`, `fo_electrical_board_structure`, todos `cascadeOnDelete`) — a diferencia de Access/Safety (1:1, denormalizado), un cuadro eléctrico puede compartirse entre varios complejos/terrenos/estructuras, así que aquí sí aplica tabla pivot. CRUD API completo bajo `v1/fieldops/electrical-boards` con sync triple-caso para las 3 relaciones. No se duplicó la encriptación de IDs ni el filtro polimórfico `belong=complex|terrain|structure` del controller anterior. 25 tests nuevos, 148/148 FieldOps ✅. Suite completa: 0 regresiones (45 fallos preexistentes, mismos de siempre).
- **FO-004 / CLA-207 (Done, commit `4f6d1c5`):** reemplaza `external_safety_id`/`external_access_id` (placeholders sin FK, sin datos reales) por catálogos reales `AccessType`/`SafetyType` (mismo patrón que `StructureType`/`TerrainType`: traducible, `HasAiTranslations`, `super_admin` only) + 2 columnas booleanas en `fo_structures` (`access_active`, `safety_certified`). Denormalizado como columnas planas en vez de tablas de instancia separadas (`Access`/`Safety` del sistema anterior) porque la relación es 1:1 por estructura, nunca reutilizada — mismo precedente que `LuminaireGroup`. `StructureController` actualizado para eager-load `accessType`/`safetyType` en los 4 puntos (index/show/store/update, consistente con la regla de shape). 8 tests nuevos, 123/123 FieldOps ✅.
- **FO-008 / CLA-206 (Done, commit `6a831e9`):** los FormRequests de FieldOps (`Store`/`Update` Terrain, Structure, Luminaire) validaban locale `nl,en,fr,es`, pero `TranslateModelAttributesJob::LOCALES` y los formularios Filament ya usaban `nl,en,fr,de`. El locale `es` nunca se traducía ni se mostraba; `de` era rechazado por la API. Unificado a `nl,en,fr,de` en 6 FormRequests + 2 tests ajustados. 117/117 tests FieldOps ✅.
- **Deuda técnica detectada (no de esta sesión, no bloqueante):** 45 tests fallando en `Mailing` (AbTesting, DispatchScheduled, FollowUp) y `Website` (ConsultationEmail, ConsultationReminder, GalleryMetadataJob, MigrationJsonConversion, StaticSitePublish, WorkDetails — varios por columnas faltantes como `work_story`, `simulation_hash`) más `Safety\InspectionAuthStoreIndexTest` (3, ya documentado). Ningún ticket abierto para esto todavía — señalarlo si se retoma alguno de esos módulos.
- **CLA-208 (Done, commit `94b9ff9`, mergeado a `main` en esta sesión desde `issues-0001`):** Livewire dispara un `confirm()` nativo del navegador (sin marca) ante cualquier respuesta HTTP 419 (sesión/CSRF expirado) en el panel Filament — comportamiento propio del vendor (`vendor/livewire/livewire/dist/livewire.js`), no código nuestro. Se intercepta vía `Livewire.hook('request', ({fail}) => fail(({status, preventDefault}) => {...}))`: `preventDefault()` es el mismo flag que gobierna el `confirm()` nativo (confirmado leyendo el vendor JS línea por línea), así que no hay carrera entre ambos. Se muestra en su lugar un modal branded (logo, colores Claesen, copy NL/EN) — nueva vista `Modules/Core/resources/views/filament/session-expired-modal.blade.php`, enganchada al `PanelsRenderHook::BODY_END` ya existente en `AdminPanelProvider.php` (junto a `session-keeper` y `floating-mailing-button`). Sin cambios de backend/PHP lógico, sin migraciones.
  - **Verificación:** no había herramienta de automatización de navegador en el entorno — se instaló Playwright (Chromium) en el scratchpad de la sesión (no en el repo) y se armó un test end-to-end real: usuario temporal → login → tabla con búsqueda Livewire → se truncó `sessions` para simular expiración real → la request a `/livewire/update` devolvió 419 → sin diálogo nativo → modal visible → botón "Reload" navega a `/login`. Usuario temporal borrado al cerrar.
  - **Gotcha encontrado:** la primera pasada del modal se veía sin el fondo cyan del botón — clases Tailwind arbitrarias nuevas (`bg-[#00aeef]`, etc.) no existen en `public/build/` hasta correr `npm run build` (mismo gotcha ya documentado más abajo, sesión EMP-021). Se corrigió recompilando; no requiere paso extra en producción (`deploy.sh` ya corre `npm ci && npm run build` en cada deploy).
- **Merge `fo_admin` → `main` (2026-07-03, commit `39e2d78`):** se completó un merge que había quedado atascado en una sesión anterior (rama de scratch `fo_admin_merge_tmp` en el worktree `/home/totti/claesen_api_dashboard_safety`, con 5 conflictos sin resolver y basada en un `main` de 56 commits de antigüedad). Se abortó ese intento viejo y se rehizo el merge directo en `main` sobre la punta actual. Trae FO-ADM-001→007 y FO-AI-001: Filament Resources V5 completos para `FoClient`, `Complex`+`Terrain` (relation manager), `Structure`, `Luminaire`+`LuminaireFrame`, y catálogos (`StructureType`, `TerrainType`, `LuminaireType`, `LuminaireSubgroup`, `LuminaireFrameType` — estos últimos solo `super_admin`); nuevo grupo de navegación "Field Operations"; `HasAiTranslations` en 5 modelos FieldOps traducibles.
  - **Conflictos resueltos:** `StructureType.php`/`TerrainType.php` (combinar `getAiTranslatableAttributes()` de fo_admin + `ai_translation_status` en fillable de main, ambos cambios compatibles); `lang/en,nl/navigation.php` (combinar clave `mailing` de main + `field_operations` de fo_admin); `Modules/Safety/resources/views/emails/inspection-report.blade.php` (conflicto add/add real — se mantuvo la versión de `main`, que ya tiene el rediseño CLA-177/178; la de `fo_admin` era una versión pre-rediseño y obsoleta).
  - **Tests:** `--testsuite=Modules --filter="FieldOps|Safety"` → 233 passed, 3 failed. Las 3 fallas son preexistentes y ya documentadas más abajo (`78327ae` — migración `present_workers.*` de `users` a `employees`, afecta `InspectionAuthStoreIndexTest`), no las causó este merge — confirmado comparando el archivo de test y `AuthController.php` byte a byte contra `main` pre-merge (sin diferencias).
  - **Pendiente:** documentar FO-ADM-001→007 / FO-AI-001 en Linear si no existen ya como tickets (no se encontró un ticket "Field Operations" al buscar); son commits ya escritos por una sesión anterior, no generados en esta.
- **Último hito código:** `24b326e` (2026-07-03) — CLA-205: `project_manager` ya no tiene acceso al panel Filament (backoffice). `User::hasPanelAccess()` es la fuente única de verdad; `EnsurePanelAccess` (nuevo middleware, registrado tras `EnsurePasswordIsSet` en `AdminPanelProvider`) redirige a `/auth/no-access` — página de bienvenida propia en vez del 403 nativo de Filament — cuando el rol no tiene acceso. Se quitaron las 2 excepciones que aún permitían `project_manager` en `MonthlyBillingControlPage` y `ProjectIntelligenceDetail` (bloqueo total, sin excepciones). Se creó usuario de prueba `pm-test@claesen-analytics.com` (rol `project_manager`) para validación manual — **borrado de la base de dev tras la verificación** (mismo día).
- **Bug encontrado y corregido en el mismo ticket:** la ruta de logout de Filament (`filament.admin.auth.logout`) vive dentro de las rutas del panel, así que `EnsurePanelAccess` la bloqueaba también, dejando al usuario sin forma de cerrar sesión desde la página de bienvenida. Fix: el middleware deja pasar cualquier ruta con nombre `filament.*.auth.logout` antes de evaluar `hasPanelAccess()`. Cubierto por test de regresión.
- **Hito previo (sprint FieldOps / Hours per Project):** `c770e0c` (2026-07-03) — EMP-029 / CLA-204: badge distinto ("No contract yet — work in progress", bold/uppercase/borde) para proyectos con horas trabajadas pero sin contrato formal (solo facturables vía estimate), separado del badge normal de vacío de facturación (EMP-028). Verificado con tinker (4 proyectos `no_contract`, 41 `overdue`) — **sin confirmación visual explícita en navegador**, el auditor pasó a cerrar la rama antes de revisarlo en pantalla.
- **Sprint Hours per Project (EMP-025→029) — resumen:** arrancó de un bug reportado por el auditor (`/projects-worked-hours-page` vacío) y escaló a una serie de mejoras relacionadas:
  - EMP-025 (`dccff22`): fix raíz — `getProjectsWithInvoiceInfo()` usaba SQL Server en vivo en vez del mirror MySQL.
  - EMP-026 (`698686e`): sincronizar `date_start`/`date_end` al mirror (columna "Start date" quedaba vacía).
  - EMP-027 (`b4d1fa1`): ordenamiento por columna + fix nombre real del proyecto (`descr` vs `name`=cliente) + fix hover de headers.
  - EMP-028 (`cc1e0be`): badge de vacío de facturación reusando la lógica de `MonthlyBillingGuardianService` (Billing Control) — mismo umbral configurable y misma exclusión de proyectos no facturables.
  - EMP-029 (`c770e0c`): diferenciar el caso "sin contrato, trabajo en curso" (mayor riesgo) del vacío normal.
- **Hito previo:** `698686e` (2026-07-03) — EMP-026 / CLA-201: `intelligence_mirror_projects` ahora sincroniza `date_start`/`date_end` (migración + `MirrorProject` fillable/casts + `SyncMirrorDataService::syncProjects()`). Backfill corrido en dev invocando `syncProjects()` puntualmente (el `syncAll()` completo es pesado, no se esperó esta sesión) — conviene correr `intelligence:sync-mirror` completo en producción en el próximo ciclo normal de sync.
- **Hito previo:** `dccff22` (2026-07-03) — EMP-025 / CLA-200: `ProjectRepository::getProjectsWithInvoiceInfo()` migrado de `Cafca\Project` (SQL Server en vivo) a `MirrorProject`+`MirrorInvoice` (MySQL). `/projects-worked-hours-page` mostraba siempre "No active projects" porque la excepción de conexión SQL Server se tragaba en un try/catch. Mismo patrón de bug que `08b7453`, que había corregido `find()`/`getProjectsByIds()` pero dejó este método afuera.
- **Hito previo:** `93de5d5` (2026-07-02) — EMP-024 / CLA-199: tendencia de 12 meses en el tab Hours de EmployeeResource.
- **Hito previo:** `4ded7c2` (2026-07-02) — EMP-023 / CLA-198: gráficos Laden/Werf/Transport (donut Day, stacked bar Week/Month) migrados desde claesen_hours.
- **Hito previo:** `2384783` (2026-07-02) — EMP-022 / CLA-197: API — quitar Cost/Revenue/Margin a nivel de empleado individual (2 endpoints live + 4 Resources huérfanos limpiados).
- **Hito previo:** `c1ec7b5` (2026-07-02) — EMP-021 / CLA-196: Day/Week Overview — quitar Cost/Revenue/Margin a nivel de empleado individual (decisión de producto, estándar de industria).
- **Hito previo:** `4c74126` (2026-07-02) — EMP-020 / CLA-195: Week Stats — fix Daily breakdown vacío sin mensaje (`empty()` de PHP no detecta Collection vacía, faltaba `->all()`).
- **Hito previo:** `7f419d0` (2026-07-02) — EMP-019 / CLA-194: Week/Day Stats — breadcrumb correcto según origen (Hours Dashboard vs. tab Hours de Employee), vía parámetro `from=employee|dashboard`.
- **Hito previo:** `1fe2ecd` (2026-07-02) — EMP-018 / CLA-193: Hours sub-nav de EmployeeResource — breadcrumb refleja el mes en vez del genérico "View".
- **Hito previo:** `7243f16` (2026-07-02) — EMP-017 / CLA-192: breadcrumb jerárquico real (Hours Dashboard > Mes > Semana > Día) en Month/Week/Day Stats, vía `getBreadcrumbs()` nativo de Filament.
- **Hito previo:** `3eb0a7c` (2026-07-02) — EMP-016 / CLA-191: Day Stats — fila completa de "Projects today" navegable a detalle de proyecto.
- **Hito previo:** `357abbb` (2026-07-02) — EMP-015 / CLA-190: Week Stats — fila completa de "Projects this week" navegable a detalle de proyecto.
- **Hito previo:** `4abc866` (2026-07-01) — EMP-014 / CLA-189: Week Stats — fila completa del Daily breakdown navegable.
- **Hito previo:** `1872576` (2026-07-01) — EMP-013 / CLA-188: Month Stats — fix Target no prorrateado en semanas de borde de mes + fila navegable.
- **Hito previo:** `45cf1c7` (2026-07-01) — EMP-012 / CLA-187: Hours Dashboard — fila completa del listado de empleados navegable.
- **Hito previo:** `fc06a8b` (2026-07-01) — EMP-011 / CLA-186: EmployeeHoursSummaryWidget con selector de mes + estado vacío sin horas.
- **Hito previo:** `069792d` (2026-07-01) — EMP-008/009/010 / CLA-183/184/185: Hours Dashboard sin límite top-10, fix gráfica Monthly Hours Trend, filtro unificado de temporalidad.
- **Último hito infra:** `667416a` (2026-06-27) — CORS corregido en nginx producción, deploy script endurecido, todos los scripts de servidor versionados en `infrastructure/`. Release activa: `20260627170653`.
- **Próximo paso:** sin ticket activo, definir con auditor.

**Referencia — app previa `claesen_hours`:** `/home/totti/claesen_hours` (React + TypeScript + Vite, accesible en este mismo entorno WSL) es la app que el auditor viene integrando/migrando a este backoffice Filament. Antes de asumir para qué servía un endpoint del módulo Employee sin consumidor Filament conocido, conviene revisar ahí primero (`src/services/`, `src/private/features/employees/`) en vez de adivinar — así se encontró el objetivo real de `getEmployeeTimeStats()` para EMP-023/024.

**Nota de entorno local — recompilar assets tras clases Tailwind nuevas:** `public/build/` (gitignored) es un artefacto de Vite/Tailwind que no se regenera solo. Si agregás una clase de Tailwind que nadie usaba antes (ej. `grid-cols-3` en EMP-021, 2026-07-02) y el bundle local quedó compilado antes de ese cambio, la clase simplemente no existe en el CSS servido — se ve como cards apiladas en vez de en fila, sin ningún error en consola. Correr `npm run build` (o `npm run dev` en watch) después de cambios de layout resuelve esto. **No es un riesgo de producción**: `infrastructure/scripts/deploy.sh` ya corre `npm ci && npm run build` en cada deploy (paso 5/10) — el problema es exclusivamente de sesiones de desarrollo local donde el bundle no se refrescó.

### Sesión 2026-07-03 — Core: CLA-205 — bloqueo de panel Filament para project_manager + página de bienvenida ✅ Done

**Commit:** `24b326e`

**Contexto:** pedido directo del auditor — `project_manager` no debe poder usar el backoffice Filament (usan las PWA de Safety/FieldOps). El código tenía una inconsistencia: `canAccessPanel()` solo miraba `is_active` (ningún rol bloqueaba el panel), pero además 2 páginas (`MonthlyBillingControlPage`, `ProjectIntelligenceDetail`) daban acceso explícito a `project_manager` vía `canAccess()`. Se acordó con el auditor bloqueo total (sin excepciones) y que el login siga funcionando pero termine en una página de bienvenida propia, no en el 403 nativo de Filament.

**Diseño:** mismo patrón que `EnsurePasswordIsSet` (ya existente para el flujo de activación) — `canAccessPanel()` no cambia (login sigue funcionando para cualquier `is_active`), y el gate real ocurre en un middleware nuevo dentro del stack del panel:
- `User::hasPanelAccess()` — fuente única de verdad (`false` si `hasRole('project_manager')`).
- `EnsurePanelAccess` middleware — si el usuario no tiene `hasPanelAccess()`, redirige a `auth.no-access` (ruta web fuera del panel, mismo patrón que `/auth/setup-password`). Registrado en `AdminPanelProvider` justo después de `EnsurePasswordIsSet`.
- `NoPanelAccessController` + vista `core::auth.no-access` — página de bienvenida (NL): "cuenta registrada pero sin acceso", con botón de logout. Si el usuario sí tiene acceso, redirige al panel en vez de mostrar la página.
- Quitado `project_manager` de `hasAnyRole([...])` en `MonthlyBillingControlPage` y `ProjectIntelligenceDetail`.

**Bug encontrado en pruebas del auditor y corregido en el mismo ticket:** el botón "Afmelden" (logout) no devolvía al login. Causa: la ruta de logout de Filament (`filament.admin.auth.logout`) está registrada dentro de las rutas del panel, así que pasaba por el mismo `EnsurePanelAccess` y quedaba atrapada en el redirect a `/auth/no-access` antes de llegar al `LogoutController` — el usuario nunca cerraba sesión. Fix: el middleware deja pasar cualquier ruta con nombre `filament.*.auth.logout` antes de evaluar `hasPanelAccess()`.

**Tests:** `Modules/Core/tests/Feature/PanelAccessTest.php` (6 tests nuevos, incluye regresión del logout) + suite completa de Core en verde (31 tests). Probado manualmente en navegador por el auditor con usuario de prueba `pm-test@claesen-analytics.com` (rol `project_manager`, password `Password123!`) — usuario borrado de la base de dev tras la verificación.

**Ticket Linear:** CLA-205, creado y cerrado en la misma sesión (no pertenecía a ningún sprint declarado).

### Sesión 2026-07-02 — Employee module: EMP-014→024 — filas navegables, breadcrumb jerárquico, fix Daily breakdown, quitar € individual (UI + API), gráficos migrados de claesen_hours ✅ Done

**Commits:**

| Hash | Ticket | Descripción |
|------|--------|-------------|
| `4abc866` | EMP-014 · CLA-189 | Week Stats: fila completa del Daily breakdown navegable a `EmployeeDayStats` |
| `357abbb` | EMP-015 · CLA-190 | Week Stats: fila completa de "Projects this week" navegable a `ProjectIntelligenceDetail` |
| `3eb0a7c` | EMP-016 · CLA-191 | Day Stats: fila completa de "Projects today" navegable a `ProjectIntelligenceDetail` |
| `7243f16` | EMP-017 · CLA-192 | Breadcrumb jerárquico real (Hours Dashboard > Mes > Semana > Día) en las 3 páginas |
| `1fe2ecd` | EMP-018 · CLA-193 | Hours sub-nav de EmployeeResource — breadcrumb refleja el mes en vez del genérico "View" |
| `7f419d0` | EMP-019 · CLA-194 | Week/Day Stats — breadcrumb correcto según origen (`from=employee\|dashboard`) |
| `4c74126` | EMP-020 · CLA-195 | Week Stats — fix Daily breakdown vacío sin mensaje (`empty()` no detecta Collection vacía) |
| `c1ec7b5` | EMP-021 · CLA-196 | Day/Week Overview — quitar Cost/Revenue/Margin a nivel de empleado individual (UI) |
| `2384783` | EMP-022 · CLA-197 | API — quitar Cost/Revenue/Margin a nivel de empleado individual (capa API) |
| `4ded7c2` | EMP-023 · CLA-198 | Gráficos Laden/Werf/Transport en Day/Week/Month Overview (migrado de `claesen_hours`) |
| `93de5d5` | EMP-024 · CLA-199 | Tendencia de 12 meses en el tab Hours de `EmployeeResource` (migrado de `claesen_hours`) |

**Contexto EMP-023/024 — migración desde `claesen_hours`:** Investigando qué consumía `getEmployeeTimeStats()` (hallazgo de EMP-022), se encontró que existía una app React previa (`/home/totti/claesen_hours`, accesible en este mismo entorno WSL) que el auditor viene integrando al backoffice Filament. Se leyó el código real de esa app (no se asumió nada) para entender el objetivo de negocio: detectar empleados que registran muchas horas de "Transport"/"Laden" (carga) en vez de "Werf" (trabajo real en obra), mostrándoselo gráficamente a gerencia (y a veces al empleado). Esa feature específica (`LaborHoursCard.tsx`, `DailyLaborHoursChart.tsx`) usaba `labor_hours` — dato que YA estaba integrado en los blades Filament desde EMP-008→020, solo faltaba visualizarlo como gráfico en vez de números/barras simples.

**EMP-023 (CLA-198) — detalle:**
- Reutiliza el patrón Chart.js ya establecido en `employee-hours-dashboard.blade.php` (EMP-009): CDN dinámico vía Alpine + `wire:ignore`, sin librerías nuevas (no se portó `recharts`, que es específico de React).
- Cero cambios de backend — Day/Week/Month ya calculaban `labor_hours` (Laden/Werf/Transport) por período.
- `employee-day-stats.blade.php`: donut chart del día, arriba de las barras de progreso existentes (se mantienen).
- `employee-week-stats.blade.php`: bar chart apilado por día (7 barras), arriba de la lista navegable existente (EMP-014, intacta).
- `employee-month-stats.blade.php`: bar chart apilado por semana (hasta 5 barras), arriba de la tabla navegable existente (EMP-013, intacta).

**EMP-024 (CLA-199) — detalle:**
- De todo lo que devuelve `getEmployeeTimeStats()`, solo el trend de 12 meses (línea, horas totales por mes) era genuinamente nuevo — el resto (perfil personal, resumen semanal/mensual, proyectos activos) ya existe en Details tab / Month Stats / Hours Dashboard; se decidió **no duplicarlo**.
- `EmployeeTimeService::getYearlyHoursTrend(string $employeeId)`: nuevo método público, delega en el `getYearlyTrend()` privado ya existente (sin duplicar lógica, ya sin € tras EMP-022).
- Se agrega como sección nueva al final del tab **Hours** (`EmployeeHoursPage.php`) — no como página nueva — porque es donde ya vive el contexto de horas de ese empleado.

**EMP-022 (CLA-197) — detalle:**
- Continúa EMP-021 aplicando la misma decisión a la capa API. Confirmado con el auditor: sin consumidor real conectado hoy (port de hace 3 días, cero referencias internas, sin docs/Postman/OpenAPI) — mi mención previa de "PWA FieldOps" como consumidor sospechoso en EMP-021 era una suposición **incorrecta**: `Modules/FieldOps/` es infraestructura física (luminarias/complejos/terrenos), no tiene relación con horas de empleados.
- **Endpoint 1** — `GET /api/v1/employees/{id}/stats/{periodType}`: `EmployeeStatsController` + `DailyStatsResource`/`WeeklyStatsResource`/`MonthlyPeriodStatsResource`/`PeriodStatsResource` (este último no estaba en el hallazgo original de EMP-021, mismo patrón, encontrado durante la implementación) + sus 4 DTOs correspondientes.
- **Endpoint 2** — `GET /api/v1/employees/{id}` y `/time/stats`: `EmployeeTimeService::getEmployeeTimeStats()` (endpoint completo no identificado en EMP-021) + `StatsCalculator::getDailyHours/getWeeklyHours` + 4 métodos privados (`getMonthlyHours`, `getYearlyHours`, `getYearlyTrend`, `getPreviousMonthStats`) + el bloque `projects[].financial`/`labor_details[].cost,revenue` dentro de `getEmployeeTimeStats()`.
- Confirmado sin overlap con los blades Filament ya corregidos (EMP-014→021) — usan métodos distintos (`getSpecificDayStats`/`getSpecificWeekStats`/`getMonthWeeksStats`), que **quedan intactos a propósito** (mismo alcance ya decidido en EMP-021).
- Limpieza adicional de 4 Resources huérfanos sin controller (`EmployeeRankingResource`, `EmployeeRankingItemResource`, `EmployeeProjectProductivityResource`, `YearlyHoursResource`).
- **Descartado correctamente:** `ProjectEfficiencyResource` — también huérfano, pero sus campos (`cost_performance_index`, `revenue_per_hour`) son a nivel *proyecto*, no empleado individual — permitido por el estándar de industria, no se toca.
- **Verificación:** curl + token Sanctum real (no Selenium, es API pura) contra ambos endpoints — `success:true`, cero campos de dinero en las respuestas, estructura completa y coherente.

**EMP-021 (CLA-196) — detalle:**
- **Decisión de producto** (no bug): siguiendo el estándar de industria para field-service labor tracking, cifras en € (Cost/Revenue/Margin) deben existir solo a nivel proyecto/cuadrilla agregado (semanal/mensual), nunca por empleado individual y por día — no es accionable a esa granularidad y genera sensibilidad laboral (percepción de vigilancia/rating individual, relevante en Bélgica con works councils). Se sustituye por % de utilización, que ya existía como `achievement_percentage` ("% of target").
- `employee-day-stats.blade.php`: quitada card "Margin" (grid 4→3 cards: Total hours+%, Approved, Distance) y filas Cost/Revenue de "Labor breakdown".
- `employee-week-stats.blade.php`: quitada card "Revenue" (grid 4→3 cards: Total hours+%, Days worked, Distance).
- Auditado y confirmado limpio sin cambios: Month Stats, tab Hours de `EmployeeResource`, Hours Dashboard, `EmployeeHoursSummaryWidget` — nunca mostraron € por empleado individual.
- **No se toca** `EmployeeTimeService.php` (sigue calculando `financial.*` internamente para `getSpecificDayStats`/`getSpecificWeekStats`, solo deja de renderizarse en estas 2 blades — decisión final, no cambia en EMP-022) — la capa API sí se resuelve, ver **EMP-022** abajo.

**EMP-020 (CLA-195) — detalle:**
- Bug encontrado por el auditor navegando `/employee-week-stats?...&start_date=2026-05-18&end_date=2026-05-24`: sección "Daily breakdown" completamente en blanco (sin mensaje) cuando no hay horas esa semana, mientras "Projects this week" sí mostraba "No projects found." correctamente.
- Causa: `EmployeeTimeService::getSpecificWeekStats()` devolvía `daily_breakdown` como `Collection` (`->values()` sin `->all()` final), mientras `projects` en la misma función ya terminaba en `->all()` (array plano). `empty()` de PHP **siempre es `false` para objetos** — el `@if(empty($dailyBreakdown))` del blade nunca disparaba, cayendo directo a un `@foreach` sobre una Collection vacía que no renderiza nada ni mensaje de fallback.
- Fix: una línea — `->all()` agregado a la cadena, igual patrón que `projects` en la misma función. Cero cambios de blade (el mensaje "No hours found." ya existía, solo era inalcanzable).

**EMP-017 (CLA-192) — detalle:**
- Override de `getBreadcrumbs(): array` (mecanismo nativo de `Filament\Pages\Page`, panel ya tiene `hasBreadcrumbs()` en `true` por defecto, sin uso previo en el proyecto) en `EmployeeMonthStats.php`, `EmployeeWeekStats.php`, `EmployeeDayStats.php`.
- Trail: `Hours Dashboard → {Empleado} — {Mes Año} → {rango semana} → {día actual}` — cada segmento excepto el último es clickeable y salta directo a ese nivel (antes solo había un link "volver un nivel").
- Se eliminó el link "Back to..." (flecha) de las 3 páginas — redundante una vez que el breadcrumb cubre lo mismo y permite saltos de más de un nivel.
- Reutiliza 100% las `getUrl()` ya existentes en los blades; cero rutas/recursos nuevos.

**EMP-018 (CLA-193) — detalle:**
- Mismo problema, otra causa: `EmployeeHoursPage.php` (tab "Hours" de `EmployeeResource`, `/employees/{id}/hours`) sobreescribía `getTitle()` pero no `getBreadcrumb()` — hook **separado** y propio de `Filament\Resources\Pages\ViewRecord` (`vendor/filament/filament/src/Resources/Pages/ViewRecord.php:48`), cuyo default es el string genérico `"View"`. Breadcrumb mostraba `Employees › {Nombre} › View` en vez del mes visible.
- Fix: `getBreadcrumb()` ahora devuelve el mismo mes/año que ya se mostraba en `getSubheading()` (lógica extraída a helper privado `getMonthLabel()`, sin duplicar código).
- **Limitación documentada en EMP-018 y resuelta en EMP-019** (ver abajo).

**EMP-019 (CLA-194) — detalle:**
- Resuelve la divergencia dejada pendiente en EMP-018: Week/Day Stats son páginas compartidas por dos flujos — (1) Hours Dashboard > Month > Week > Day (standalone, EMP-017) y (2) EmployeeResource > tab Hours (`EmployeeHoursPage`) > Week > Day. El breadcrumb de Week/Day asumía siempre el flujo (1).
- **Solución elegida (buena práctica estándar para este caso):** parámetro de contexto `from=employee|dashboard` por querystring — stateless, sobrevive refresh/back-button/links directos, consistente con el resto del módulo (todo ya viaja por query params: `employee_id`, `start_date`, `month`). Se descartaron: Referrer HTTP (frágil, no sobrevive `wire:navigate`) y stack de navegación en sesión (inconsistente con la arquitectura URL-driven existente).
- `EmployeeWeekStats`/`EmployeeDayStats`: nuevas constantes `FROM_EMPLOYEE`/`FROM_DASHBOARD`, prop `$from` leída en `mount()` (default `dashboard`, 100% retrocompatible), `getBreadcrumbs()` bifurca según `$from`.
- Con `from=employee`: trail `Employees → {Nombre} → {Mes vía EmployeeHoursPage::getUrl()} → {semana} → {día}` — el nivel "Mes" enlaza al tab del recurso, no a `EmployeeMonthStats` (que en este flujo nunca se visita).
- `from` propagado por querystring en: link de semana en `employee-hours-page.blade.php`, flechas prev/next de Week y Day, y el link Day→Week. `EmployeeMonthStats.php` y `employee-hours-dashboard.blade.php` no se tocan — fuera del árbol "employee".
- Cero páginas/recursos nuevos; mismas `getUrl()` ya existentes.

**EMP-015/EMP-016 — detalle (mismo patrón, dos páginas):**
- Archivos: `employee-week-stats.blade.php` (sección "Projects this week") y `employee-day-stats.blade.php` (sección "Projects today").
- Reutiliza el patrón de fila-navegable ya existente (`x-data` + `x-on:click="Livewire.navigate(...)"` con guard `$event.target.closest('a')`) — mismo patrón de EMP-012/013/014, sin código nuevo de patrón.
- Link destino: `\Modules\Intelligence\Filament\Pages\ProjectIntelligenceDetail::getProjectUrl($project['id'])` — vista de detalle de proyecto operativa canónica (ya usada en `ProjectResource.php:142` y `billing-control.blade.php`). El helper hace `trim()` del id internamente.
- Sin cambios de backend: `$project['id']` ya venía en los arrays de `EmployeeTimeService::getSpecificWeekStats()` y `getSpecificDayStats()`, solo no se usaba en los blades.
- Descartado como destino: `Modules\Performance\Filament\Resources\ProjectResource` (no tiene página `view` registrada) y `ProjectInsightResource` (capa de IA/insight, no vista operativa — documentado en el propio código de `ProjectIntelligenceDetail`).

**Verificación (EMP-014 a EMP-024, sesión completa):** Selenium real contra Chrome vía Selenium Grid del stack Sail, login con usuario `super_admin` sembrado localmente (`admin@claesen-analytics.com`). Confirmado en Day: breadcrumb `["Hours Dashboard","Junuzovic Kemal — May 2026","04/05 – 10/05/2026","Mon 4/05"]`; click en crumb "Hours Dashboard" salta 3 niveles directo a `/employee-hours-dashboard`; click en crumb de mes navega directo a `/employee-month-stats?...`; old back-links confirmados ausentes en las 3 páginas. Confirmado en `/employees/170/hours`: breadcrumb `["Employees","Junuzovic Kemal","July 2026"]` por defecto y `["Employees","Junuzovic Kemal","May 2026"]` con `?month=2026-05`; click en fila de semana sigue navegando correctamente a `EmployeeWeekStats` (sin regresión). Confirmado flujo `from=employee` completo: Hours tab → click semana (link con `from=employee`) → Week breadcrumb `["Employees","Junuzovic Kemal","May 2026","04/05 – 10/05/2026"]` → click día → Day breadcrumb `["Employees","Junuzovic Kemal","May 2026","04/05 – 10/05/2026","Mon 4/05"]` → click crumb de mes vuelve correctamente a `/employees/170/hours?month=2026-05` (el tab, no `EmployeeMonthStats`). Confirmado que el flujo dashboard sin `from=` no cambió: `["Hours Dashboard","Junuzovic Kemal — May 2026","04/05 – 10/05/2026"]`, idéntico a EMP-017. Confirmado fix EMP-020: `/employee-week-stats?...&start_date=2026-05-18&end_date=2026-05-24` renderiza `"No hours found."` en Daily breakdown (antes: HTML confirmaba `@foreach` vacío sin mensaje); regresión OK en semana con datos (04/05–10/05, 5 filas siguen renderizando igual). Confirmado fix EMP-021 sobre las mismas fechas de la captura original (Day 11/05, Week 11/05–17/05): cero € en el HTML, sin "Margin"/"Cost"/"Revenue", grids de 3 cards, sin errores. Confirmado fix EMP-022 con curl + token Sanctum: `GET /api/v1/employees/170/stats/current-week` y `current-month` — `success:true`, cero `total_cost`/`total_sales`/`totalCost`/`totalSales`; `GET /api/v1/employees/170` — `success:true`, cero `costs`/`revenue`/`profit`/`financial`/`total_cost`/`total_revenue`/`transport_cost`/`transport_revenue`, estructura completa (`employee`, `time_stats.*`, `last_two_weeks`, `previous_month`, `projects`) intacta. Confirmado EMP-023: donut canvas en Day (04/05/2026), stacked-bar canvas en Week (04/05–10/05) con lista navegable EMP-014 intacta debajo (5 links), stacked-bar canvas en Month (May 2026) con tabla navegable EMP-013 intacta debajo (5 links) — sin errores en las 3. Confirmado EMP-024: canvas de trend presente en `/employees/170/hours?month=2026-05`, sin errores, sin regresión de campos €.

**Nota de entorno (recurrente, no bloqueante, no parte de ningún cambio):** el harness de verificación local requiere `SESSION_SECURE_COOKIE=false` temporal en `.env` porque el default de Laravel (`true`) exige HTTPS para la cookie de sesión, y la verificación corre por HTTP dentro de la red Docker de Sail. Se revierte inmediatamente después de cada verificación; no afecta producción (allí corre bajo HTTPS).

**Verificación (los 3 tickets):** Selenium real contra Chrome vía Selenium Grid del stack Sail, login con usuario `super_admin` sembrado localmente (`admin@claesen-analytics.com`), navegación real a `/employee-week-stats?...` y `/employee-day-stats?...`. Confirmado en ambas páginas: click en cualquier punto de la fila (no solo el nombre) navega correctamente; sin regresión en Daily breakdown; proyecto de detalle carga sin error (invoices, costs, alertas).

**Nota de entorno (no bloqueante, no parte del cambio):** el harness de verificación local requirió `SESSION_SECURE_COOKIE=false` temporal en `.env` porque el default de Laravel (`true`) exige HTTPS para la cookie de sesión, y la verificación corre por HTTP dentro de la red Docker de Sail. Revertido inmediatamente después de cada verificación; no afecta producción (allí sí corre bajo HTTPS).

### Sesión 2026-07-01 — Employee module: Hours Dashboard (listado, gráfica, filtro), widget dashboard ✅ Done

**Commits:**

| Hash | Tickets | Descripción |
|------|---------|-------------|
| `069792d` | EMP-008/009/010 · CLA-183/184/185 | Hours Dashboard: listado completo de empleados, fix gráfica, filtro unificado de temporalidad |
| `fc06a8b` | EMP-011 · CLA-186 | EmployeeHoursSummaryWidget: selector de mes + estado vacío |
| `45cf1c7` | EMP-012 · CLA-187 | Hours Dashboard: fila completa del listado de empleados navegable |
| `1872576` | EMP-013 · CLA-188 | Month Stats: fix Target no prorrateado en semanas de borde de mes + fila navegable |

**EMP-008 (CLA-183) — Listado completo, sin límite top-10:**
- `EmployeeDashboardRankingService::getTopEmployees()` — quitado `->take(10)`; ahora devuelve todos los empleados activos (`tracks_hours=true`), ordenados por horas desc.
- Afecta también al endpoint público `GET /api/v1/employees/rankings` (mismo servicio compartido) — decisión aprobada explícitamente.
- Sección renombrada "Top Employee Rankings" → "Employees".

**EMP-009 (CLA-184) — Fix gráfica Monthly Hours Trend (aparecía en blanco):**
- `window.Chart` nunca se cargaba en esta página → Chart.js se inyecta dinámicamente vía CDN.
- Bug real de Carbon: `Carbon::now()->endOfMonth()` (día 31) + `subMonth()` repetido produce overflow (31-jul − 1 mes → 1-jul, no 30-jun), duplicando un mes y perdiendo otro. Fix: iterar con base `startOfMonth()`.
- Primer pintado dependía de `requestAnimationFrame` no determinista → `animation: false`.

**EMP-010 (CLA-185) — Filtro unificado de temporalidad:**
- `EmployeeHoursDashboard.php` — un solo estado de periodo (`periodPreset`, `periodYear`, `customStartDate`, `customEndDate`, todos `#[Url]`) + `applyFilter()` recalcula gráfica y tabla juntas.
- Presets: Q1, Q2, Q3, Q4, H1 (Jan-Jun), H2 (Jul-Dec), año completo, rango personalizado.
- `EmployeeDashboardRankingService::getDashboardData()` — firma `?string $year` → `?string $startDate, ?string $endDate`; `getMonthlyHoursTrend()` genera buckets dentro del rango real (no fijo a 12 meses).
- **Bug preexistente corregido:** `getDashboardData($year)` ignoraba `$year`, siempre usaba los últimos 12 meses desde hoy. `total_working_days` también estaba fijo al año calendario completo.
- `EmployeeDashboardController` mantiene compatibilidad con `?year=` además de aceptar `?start_date=&end_date=`.

**EMP-011 (CLA-186) — EmployeeHoursSummaryWidget (dashboard admin):**
- Selector de mes (`type=month`, solo granularidad mensual) en vez de mes fijo.
- Nuevo flag `hasHoursLogged`; cuando no hay horas registradas ese mes, ya no muestra "Top 3" con empleados a 0h — mensaje explícito en su lugar.

**EMP-012 (CLA-187) — Hours Dashboard: fila completa navegable:**
- La tabla de empleados solo tenía el link en el nombre; ahora toda la fila navega a `EmployeeMonthStats` (click en cualquier celda), vía `Livewire.navigate()` + Alpine, con guard para no duplicar navegación si el click cae sobre el `<a>` del nombre.

**EMP-013 (CLA-188) — Month Stats: fix Target prorrateado + fila navegable:**
- **Bug encontrado durante investigación** (no reportado inicialmente): `EmployeeTimeService::getMonthWeeksStats()` mostraba el Target semanal completo (ej. 40h) incluso en la primera/última semana del mes, cuando esa semana solo tiene 1-2 días hábiles reales dentro del mes visible (el resto cae en el mes adyacente) — producía % de cumplimiento engañoso. Corregido a `(targetWeeklyHours/5)*workDays`, igual que el resto de métodos del servicio (`getMonthlyHours`, `getSpecificWeekStats`, etc.) ya hacían.
- Ejemplo verificado: semana 27/04–03/05 vista desde mayo 2026 — antes Target=40h, ahora Target=8h (1 día hábil real en mayo).
- **Decisión del auditor:** el rango de fechas mostrado en semanas de borde (ej. "27/04 – 03/05" en vista de mayo) se deja sin recortar — el cálculo de horas ya es correcto, y mostrar el rango real de la semana ayuda a la navegación.
- Misma mejora de UX que EMP-012: fila completa de la tabla "Weeks" navega a `EmployeeWeekStats` al hacer click en cualquier celda.

**Verificación:** Selenium (login real vía cookie de sesión inyectada + capturas de pantalla) para las 4 tickets. Tests del módulo Employee: 44/44 verdes (sin regresiones; no había tests previos para el widget).

**Deuda / pendiente:** ninguna abierta por estos tickets.

### SAF-PWA-001 / CLA-170 ✅ Done

**Commits:** `d958759` (impl) + `6cf8179` (fix tests) | **Fecha:** 2026-06-23

**Cambio:** `ProjectController::index()` eliminó try/catch SQL Server y fallback DEV-001/DEV-002.
Ahora consulta `intelligence_mirror_projects` con `leftJoin` a `intelligence_mirror_relations` → añade `relation_name: string|null` al contrato (aditivo, no breaking).

**Tests:** 5 casos — con/sin relación, inactivo excluido, mirror vacío, no-import-Cafca.

**Riesgo operativo documentado:** frescura de proyectos depende del job de sync del mirror. Si el sync falla, el listado de la PWA queda desactualizado.

---

### SAF-NNN — Email reminder semanal a project_managers inactivos ✅ Done

**Commits:** `ff79b73` (impl) + `9600825` (URL PWA) + `6cf8179` (fix tests) | **Fecha:** 2026-06-23

**Archivos creados:**
- `Modules/Safety/Services/InspectionReminderService.php` — user-centric, `withTrashed()`, gracia 7 días, boundary `>= 30`
- `Modules/Safety/Emails/InspectionReminderMail.php`
- `Modules/Safety/resources/views/emails/inspection-reminder.blade.php` — NL, dos ramas de copy
- `Modules/Safety/Console/NotifyInactiveManagersCommand.php` — `safety:notify-inactive-managers [--days] [--dry-run]`
- `Modules/Safety/tests/Feature/NotifyInactiveManagersCommandTest.php` — 9 tests / 21 assertions ✅

**Schedule:** lunes 09:00 + `withoutOverlapping()` (sin colisión con `CheckSafetyComplianceCommand` en 08:00).

**Deploy:** requiere `SAFETY_PWA_URL=https://service.claesen-verlichting.be/` en `.env` de producción.

---

### Deudas técnicas Safety — pendientes de ticket

| Deuda | Descripción | Prioridad |
|-------|-------------|-----------|
| **SAF-DEBT-001** | ✅ Done `80f0385` — `MirrorRelation::$incrementing = false`. Workaround `DB::table()` en `ProjectControllerTest` revertido; `BillingGuardianOverdueTest` también se beneficia. | — |
| **SAF-DEBT-002** | Congelar tiempo en tests de frontera de `NotifyInactiveManagersCommandTest` — casos 3, 4, 5 usan `Carbon::now()->subDays(N)` sin `Carbon::setTestNow()`. En condiciones normales pasan, pero pueden ser flaky si el test cruza medianoche o en CI con reloj rápido. | Baja |

### SAF-019 — Payload fingerprint (idempotency hash) 🚧 Commit aprobado, cierre pendiente

**Commit:** `19b7cf1` | **Fecha:** 2026-06-21

**Archivos modificados:**
- `Modules/Safety/Models/Inspection.php` — `payload_hash` en `$fillable`
- `Modules/Safety/Http/Requests/StoreInspectionRequest.php` — `answers.*.question_id`: `integer` + `distinct` + `Rule::exists` scoped a `checklist_id`; `withValidator()` rechaza fotos huérfanas y keys no-numéricas (422)
- `Modules/Safety/Http/Controllers/InspectionController.php` — `canonicalPayload()` + `computeHash()` + `idempotentResponse()`; SHA-256 computado post-validación pre-transacción; `UniqueConstraintViolationException` capturada solo para `safety_inspections_user_idempotency_unique`
- `Modules/Safety/database/migrations/2026_06_21_120000_add_payload_hash_to_safety_inspections.php` — `payload_hash VARCHAR(64) NULL` ✅ aplicada

**Comportamiento de `payload_hash = NULL` (registros legacy):**
> Devuelve 200 para preservar compatibilidad con registros anteriores a SAF-019. **No verifica igualdad del payload** — un payload diferente con la misma `idempotency_key` también recibirá 200 si el registro es legacy. Este comportamiento es deliberado y está documentado en `idempotentResponse()`.

**Pendiente antes de cierre:**
1. Crear ticket en Linear: `SAF: fix 5 pre-existing test failures after employees worker migration` — `78327ae` cambió `present_workers.*` de `exists:users,id` a `exists:employees,id` sin actualizar tests. Afecta: `InspectionAuthStoreIndexTest` (3), `InspectionPhotoStorageFailureTest` (2).
2. Confirmar que CI acepta o excluye justificadamente los 5 fallos (no son regresiones de SAF-019 — verificado con `git stash`).
3. Rotación de API key de Linear verificada externamente (401/403 key antigua, nueva key almacenada como secreto, no expuesta en código/logs/Git).

---

### Sesión 2026-06-30 — Employee module: tests, mirror fix, cache, EmployeeResource ✅ Done

**Commits (esta sesión):**

| Hash | Descripción |
|------|-------------|
| `1a88873` | fix(Employee): Livewire dispatch + Alpine window event for chart data |
| `b230f2f` | feat(Employee): EmployeeHoursSummaryWidget — top-3 + stats en dashboard |
| `407d396` | fix(Employee): top-3 cards layout (flex) + Tailwind @source Modules scan |
| `37ab1ff` | fix(Employee): FQCN `\Carbon\Carbon` en employee-month-stats blade |
| `4b8feec` | fix(Employee): wire:navigate en todos los links internos (SPA navigation) |
| `1e157a0` | test(Employee): 44 tests — auth, rankings, time stats, MirrorLabor enrichment |
| `08b7453` | fix(Employee): ProjectRepository.find/getProjectsByIds → MirrorProject (MySQL) |
| `eb74d5a` | feat(EMP-A): cache rankings — `Cache::remember()` con TTL adaptativo (30min actual / 6h histórico) |
| `f59f19a` | feat(EMP-B): EmployeeResource migrado a módulo Employee + Hours sub-nav tab |
| `626d15b` | docs(handoff): 2026-06-30 — cache rankings + EmployeeResource → Employee module |

**Smoke test browser (2026-06-30): ✅ aceptado**
- Sub-nav 4 tabs visible (Details / Edit / AI Performance / Hours)
- Tab Hours carga `/{record}/hours`, muestra cards totals + tabla semanal con % consecución
- Navegación prev/next mes operativa
- Sin errores 500

**Cambios relevantes:**

**Tests (1e157a0 + 08b7453):**
- 44 tests en `Modules/Employee/tests/Feature/` — todos verdes, sin mocks, contra mirror real
- `ProjectRepository::find()` y `getProjectsByIds()` usan `MirrorProject` (MySQL `intelligence_mirror_projects`) — eliminada dependencia sqlsrv en endpoints day/week stats

**Cache rankings (eb74d5a):**
- `EmployeeDashboardRankingService::getTopEmployees()` envuelve cómputo en `Cache::remember()`
- TTL adaptativo: rangos históricos (fin < inicio de mes actual) → 6h; mes en curso → 30 min
- Subconjuntos con `$employeeIds` explícito bypass cache (espacio de claves ilimitado)
- Cache key: `'employee.rankings.' . md5($startDate . $endDate)`

**EmployeeResource → módulo Employee (f59f19a):**
- `Modules/Cafca/Filament/Resources/EmployeeResource.php` **eliminado** — evita conflicto de rutas
- `Modules/Employee/Filament/Resources/EmployeeResource.php` — propietario canónico de todas las rutas `/employees/*`
- Las 5 páginas Cafca (List, Create, View, Edit, EmployeeAnalytics) y `EmployeesTable` ahora referencian el nuevo resource
- `Modules/Employee/Filament/Resources/Employees/Pages/EmployeeAnalytics.php` — copia en namespace Employee
- `Modules/Employee/Filament/Resources/Employees/Pages/EmployeeHoursPage.php` — nueva sub-nav "Hours" (`/{record}/hours`)
- Vista blade `employee-hours-page.blade.php` — resumen mes (laden/werf/transport/km) + tabla semanal con % consecución
- Sub-nav de 4 tabs por empleado: **Details | Edit | AI Performance | Hours**
- `ViewEmployee::getHeaderActions()` — botón "View Hours" actualizado para apuntar a `EmployeeHoursPage::getUrl()`
- `Performance\ProjectInsightResource` — referencia a `EmployeeResource::getUrl()` actualizada al nuevo namespace

---

### Sprint EMP — Estabilización /employees 🚧 En curso

**Issues creados:** 2026-06-21. Orden aprobado: EMP-001 → EMP-004 → EMP-002 → EMP-005 → EMP-003 → EMP-006 → EMP-007

| Ticket | Linear | Título | Archivo principal | Depende de | Estado |
|--------|--------|--------|-----------------|-----------|--------|
| EMP-001 | CLA-162 | Retirar alerta Watchdog falsa | `EmployeeInfolist.php:71-96` | — | ✅ Done `39c1e07` |
| EMP-004 | CLA-163 | Eliminar botón "View archives" | `employee-project-timeline.blade.php:124` | — | ✅ Done `5f0ec35` |
| EMP-002 | CLA-164 | `uren_per_week` → estado unknown | `EmployeePerformanceService.php` + infolists | — | ✅ Done `ef513c7` |
| EMP-005 | CLA-165 | Eliminar llamada duplicada Livewire | `EmployeeProjectTimeline.php` | — | ✅ Done `bc9ff40` |
| EMP-003 | CLA-166 | Diferenciar 3 estados ERP/datos | `EmployeeProjectTimeline.php` + blade | EMP-005 | ✅ Done `176da75` |
| EMP-006 | CLA-167 | Locale configurable prompt Gemini | `TechnicianAnalysisService.php:56` | — | ✅ Done `8d5c27a` |
| EMP-007 | CLA-168 | Auditoría permisos Analytics | `EmployeeAnalytics.php` (solo lectura) | EMP-002, EMP-003 | ✅ Done (Status Quo) |

**Decisiones del auditor para este sprint:**
- EMP-001: no eliminar claves de traducción, verificar uso global primero
- EMP-002: `null` (no `0`) cuando `uren_per_week <= 0`; sin dependencia de EMP-003
- EMP-003: captura `\Throwable` para conexión caída; re-throw si no es error de conexión/PDO; `hasHistory` en `mount()` una sola vez
- EMP-005: verificar con `DB::connection('sqlsrv')->enableQueryLog()` sobre la conexión correcta
- EMP-006: sin migración `insight_locale`; locale canónico nl/en con fallback nl
- EMP-007: discovery puro; si exige código → ticket EMP-007b separado

**NO GO explícito del auditor para este sprint:**
- Leaderboard, anomaly detection individual, coste por empleado, scheduler IA, QR con token de sesión
- Compliance Safety en perfil (pendiente confirmar relaciones worker/employee)
- Certificaciones, disponibilidad: requieren discovery previo

**Pendiente producción (CLA-161):**
```bash
php artisan migrate
php artisan mailing:backfill-preference-snapshots  # dry-run primero
php artisan mailing:backfill-preference-snapshots --apply
# reiniciar workers
```

### MAI-PREF-001 / CLA-161 — Enforcement de Category Preferences ✅ Done

| Archivo | Cambio | Estado |
|---------|--------|--------|
| `Mailing/database/migrations/2026_06_20_000014_*` | `preference_category` en `email_templates` | ✅ |
| `Mailing/database/migrations/2026_06_20_000015_*` | `template_category_snapshot` + `preference_category_snapshot` en `mailing_campaigns` | ✅ |
| `Mailing/Models/EmailTemplate.php` | `preference_category` en `$fillable` + `booted()` saving hook | ✅ |
| `Mailing/Models/Campaign.php` | `buildSnapshotFrom()` + guard en `transitionTo(APPROVED)` | ✅ |
| `Mailing/Jobs/ExecuteCampaignJob.php` | `assertValidSnapshots()` + skip order correcto + sin fallback a mutable template | ✅ |
| `app/Contracts/MarketingCampaignInterface.php` | `bool $isCommercial = true` propagado | ✅ |
| `Mailing/Emails/ProspectCampaignMail.php` | `List-Unsubscribe` headers condicionales | ✅ |
| `Mailing/Services/MicrosoftGraphMailer.php` | Firma actualizada con `isCommercial` | ✅ |
| `Mailing/Services/SaaSMailer.php` | Firma actualizada | ✅ |
| `Mailing/Filament/.../EmailTemplateForm.php` | Select `preference_category` visible solo para COMMERCIAL | ✅ |
| `Mailing/Filament/.../CampaignForm.php` | `afterStateUpdated` usa `buildSnapshotFrom()` + Hidden fields para nuevos snapshots | ✅ |
| `Mailing/Console/BackfillPreferenceSnapshotsCommand.php` | Nuevo — dry-run por defecto, `--apply` para commit | ✅ |
| `Mailing/Providers/MailingServiceProvider.php` | Registra BackfillPreferenceSnapshotsCommand | ✅ |
| `Mailing/database/seeders/Led2027HighConversionTemplatesSeeder.php` | `preference_category => 'offers'` en los 3 templates | ✅ |
| `Mailing/database/factories/CampaignFactory.php` | Defaults con snapshots + estados `commercial()`, `transactional()`, `withoutSnapshots()` | ✅ |
| `Mailing/database/factories/EmailTemplateFactory.php` | `preference_category` default + estados `asOffers()`, `asNewsletter()`, `asEvents()`, `transactional()` | ✅ |
| `Mailing/lang/en/resource.php` + `nl/resource.php` | Claves `preference_category`, `preference_category_helper`, `template_invalid_pref_category` | ✅ |
| `Mailing/tests/Feature/CategoryPreferenceEnforcementTest.php` | Nuevo — 20 tests | ✅ |
| `Mailing/tests/Feature/BackfillPreferenceSnapshotsCommandTest.php` | Nuevo — 9 tests | ✅ |
| `Mailing/tests/Feature/ListUnsubscribeTest.php` | +3 tests `isCommercial` | ✅ |
| `Mailing/tests/Feature/CampaignWorkflowTest.php` | 2 tests con template vinculado para `transitionTo(APPROVED)` | ✅ |
| `docs/ai/known-risks.md` | Risk "enforcement" cerrado | ✅ |

**Secuencia de deploy (producción):**
```bash
# 1. Parar workers antes de migrate
# 2. php artisan migrate
# 3. php artisan mailing:backfill-preference-snapshots  (dry-run, revisar output)
# 4. php artisan mailing:backfill-preference-snapshots --apply
# 5. Reiniciar workers
```

**Tests verificados (2026-06-20):** 77 passed / 134 assertions — CategoryPreferenceEnforcement (22), BackfillPreferenceSnapshots (9), ListUnsubscribe (12+2 skip), CampaignWorkflow (20), ExecuteCampaignJobCounter (14). ✅ GO técnico aprobado.

### SAF-017→022 — Soft Delete Seguro de Inspecciones ✅ Done

| Ticket | Título | Commit | Estado |
|--------|--------|--------|--------|
| SAF-017 | SoftDeletes trait en Inspection model | `08f5f4a` | ✅ Done |
| SAF-018 | InspectionPolicy — delete/restore/forceDelete | `08f5f4a` | ✅ Done |
| SAF-019 | Filament — Archiveren action + RestoreAction + TrashedFilter | `08f5f4a` | ✅ Done |
| SAF-020 | getEloquentQuery() withoutGlobalScope SoftDeletingScope | `08f5f4a` | ✅ Done |
| SAF-021 | API: route model binding devuelve 404 para deleted | `08f5f4a` | ✅ Done |
| SAF-022 | Tests InspectionSoftDeleteTest (11 tests) + fix InspectionPhotoStorageFailureTest | `08f5f4a`/`34e88fd` | ✅ Done |

**Fix pre-existente documentado (34e88fd):**
- `Queue::fake()` → `Bus::fake()` (inline, no en setUp) — `Queue::assertDispatched()` no existe en Laravel 12
- `"photos[{id}]" => file` → `'photos' => [id => file]` — brackets literales no activan `hasFile("photos.N")`; array anidado sí
- Mockery mock vía `Storage::set()` para simular fallo de disco (path-based tricks inútiles en Docker/root)

### BI-PROJ — Vista de Águila

| Ticket | Título | Commit | Estado |
|--------|--------|--------|--------|
| BI-PROJ-01 | Auditoría ProjectInsightResource — confirma nueva page en Intelligence | (docblock en clase) | ✅ Done |
| BI-PROJ-02 | `ProjectIntelligenceDetail` page MVP — `/project-detail/{projectId}` | `7db83ae` | ✅ Done — GO |
| BI-PROJ-03 | Wire "Projectdetails openen" button en billing-control modal | `a0e1007` | ✅ Done |
| BI-PROJ-04 | Quitar class_exists guard, importar BillingAlert directo | `3af5fc8` | ✅ Done |
| BI-2B-UX-09 | Billing Control — secciones por pregunta de negocio (tabs eliminadas) | `8f20e3f` | ✅ Done |
| BI-2B-UX-10 | Desactivar partial_payment + DismissPartialPaymentAlerts command | `8f20e3f` | ✅ Done |
| BI-2B-UX-13 | Docs actualizados — partial_payment eliminado, secciones documentadas | `8f20e3f` | ✅ Done |
| BI-2B-UX-14 | Maandstatus card arriba — absorbe banner rojo, KPI grid | (este commit) | ✅ Done |
| BI-2B-UX-15 | Vervallen facturen — top 10 + "Toon alle" + summary stats | (este commit) | ✅ Done |
| BI-2B-UX-16 | Compact empty states (40–60px, single line) — Afgesloten + Creditnota's | (este commit) | ✅ Done |
| BI-2B-UX-17 | Quick nav anchors + section IDs | (este commit) | ✅ Done |

### Sprint BI — Estado

| Sprint | Estado | Aprobación |
|--------|--------|------------|
| Sprint 0 — Integración BI→main | ✅ Done — PR #4 mergeado | ✅ Auditor GO |
| Sprint 1 — Mirrors + bi_config | ✅ Done — PR #5 mergeado a `main` (`558ec32`) | ✅ Auditor GO |
| Sprint 2 — Motor financiero | ⬜ Todo | ✅ (no requiere auditor gate) |
| Sprint 2B — Monthly Billing Guardian | ✅ **COMPLETADO** — BI-050→062 todos Done — pendiente PR | ✅ GO con **Auditor Gate en BI-052/053/054** |
| Sprint 3 — UI simulador | ⬜ Todo | ✅ (no requiere auditor gate) |
| Sprint 4 — Métricas | ⬜ Todo | ✅ (no requiere auditor gate) |

### Sprint 2B — Tickets

| Ticket | Título | Commit | Estado |
|--------|--------|--------|--------|
| BI-050 | Migración `intelligence_billing_alerts` + modelo | `5ba0ec7` | ✅ Done |
| BI-051 | `MonthlyBillingGuardianService` — estructura + §4.4.1 rerun policy | `4b262b7` | ✅ Done |
| BI-052 | Regla `missing_customer_invoice` — **Gate APPROVED** | `a3004b8`+`4490bcc` | ✅ Done |
| BI-053 | Reglas `overdue_receivable`+`partial_payment` — **Gate APPROVED** | `610dff7` | ✅ Done |
| BI-054 | Regla `unbilled_followup_cost` — costes followup no facturados — **Gate APPROVED** | `108f928` | ✅ Done |
| BI-055 | Reglas `billing_gap`+`credit_note`+`closed_with_balance` (sin gate) | `fdfaf66` | ✅ Done |
| BI-056 | Comando `intelligence:billing-guardian` — 4 opciones + output tabla | `f7803f3` | ✅ Done |
| BI-057 | Scheduler mensual (día 2, 07:00 Brussels, `--previous-month`) | `f7803f3` | ✅ Done |
| BI-058 | `MonthlyBillingControlPage` Filament V5 — KPIs + 5 tabs + Run Guardian | `d020ac2` | ✅ Done |
| BI-059 | Workflow open→review→confirmed|dismissed→resolved | `4b7ac2e` | ✅ Done |
| BI-060 | Reglas Guardian en `BiConfigPage` | ✅ ya en BI-019/052 | ✅ Done |
| BI-061 | Tests — suite completa 95 tests / 200 assertions | `37cdf8b` | ✅ Done |
| BI-062 | Documentación `docs/bi-monthly-billing-guardian.md` | `37cdf8b` | ✅ Done |
| BI-2B-UX-01/04/05 | Quick wins UX — status labels NL, Bedrag contextual, tab Maandafsluiting, banner link, recomendación expandible, KPI sublabels+tooltips, notificaciones orientativas, modal Guardian texto | `757e982` | ✅ Done |
| BI-2B-UX-03 | Columna Project con nombre + cliente + link ProjectInsights (no N+1, no broken links) | `0953245` | ✅ Done |
| BI-2B-UX-02 | Modal "Meer details" — contexto completo + evidence legible + aviso ámbar | `a7a0a61` | ✅ Done |
| BI-2B-UX-06/07/08 | Documentación UX — user-guide (9 pasos, Bevestigd≠Opgelost, Bedrag tabla, Projectinzichten) + data-sources (Wat betekent het Bedrag?) + technical doc (UX contextual, auditor gate) | `d7ab67a` | ✅ Done |

### BI-052 — Auditor Gate: APPROVED (2026-06-13)

**Regla:** `missing_customer_invoice` dispara cuando:
- hay actividad económica en el mes,
- activity_cost > €500 (`min_activity_amount`, comparador estricto `>`),
- no existe invoice no-CN en ese mes,
- el proyecto tiene contrato o estimate vinculado.

**Decisiones aprobadas por el auditor:**
- Comparador estricto: `>` threshold. Exactamente €500 NO dispara (fijado por tests).
- `CN%` no cuenta como factura válida.
- `amount_activity_cost` contiene costes detectados en mirror_costs.
- `amount_estimated` solo se rellena con `contract_price` confiable; sin contrato → NULL.
- Horas/workdocs solos no disparan por ahora.
- Config renombrada: `min_activity_amount` para esta regla; `min_cost_amount` reservado para `unbilled_followup_cost` (BI-054).

**Evidencia del gate (datos reales mayo 2026, dry-run):**
- Caso A: P20250063 Limburg Diepenbeek — €20.642,84, 120 días sin factura
- Caso B: P20250054 Gemeente Heuvelland — €9.925,18 + 193,34h, 120 días
- Caso C (edge): P20260026 De Raedt Ivan — €2.110,43, 31 días
- Caso N: P20260024 Balteau — €9.016,05 PERO facturado en mayo → excluido ✓
- Caso L: sin fila real en €500,00 exacto — comportamiento fijado por tests (500,00 no dispara / 500,01 dispara)
- **Hallazgo demo:** P20260029 vs P20260030 (ambos Derriks, €5.600) — uno facturado, otro no → alerta correcta. Caso ideal para demo interna del módulo.

### BI-053 — Auditor Gate: APPROVED (2026-06-13)

**Regla `overdue_receivable`:** dispara cuando `fl_paid=false`, no es CN%, `date_expiration < hoy`, y saldo abierto `(total_price − total_paid) > min_amount` (€500, estricto `>`). Severity: >60 días vencida → critical, si no → high (frontera 60/61 fijada por tests).

**Regla `partial_payment`:** dispara cuando `fl_paid=false`, `total_paid > 0`, saldo > min_amount, y **aún no vencida** (o sin fecha). Severity: medium.

**Decisiones aprobadas:**
- Exclusión mutua por `date_expiration`: al vencer, la parcial pasa a overdue — nunca doble alerta.
- Umbral compartido `min_amount` (€500) para ambas reglas.
- Semántica snapshot: saldo que sigue abierto re-alerta el periodo siguiente (dedup_key incluye periodo) — intencional.
- `fl_paid=true` excluye siempre (el bit manda sobre el cálculo).
- Schema: `total_price`/`total_paid` añadidos al mirror; sync ampliado a "6 meses O fl_paid=0" (mirror: 113 → 130 facturas; la impagada más vieja es de 2009).

**Evidencia del gate (datos reales, dry-run — 32 overdue: 20 critical / 12 high):**
- Caso A: F25260007 TC Tenkie — €65.867,48, 286 días, critical
- Caso B: F25260201 Happy Waregem — €33.903,52, 12 días, high (severity distinta)
- Caso C (edge): F21220158 K.F.C. St-Job — €550,55, justo sobre umbral
- Caso N: F24250178 — €420,93 ≤ €500 → excluido ✓
- Caso L: sin fila real en €500,00 — fijado por tests (500,00 no / 500,01 sí)
- Partial real hoy: 0 (todas las parciales ya vencieron → overdue, exclusión mutua correcta)

### BI-054 — Auditor Gate: APPROVED (2026-06-13)

**Regla:** `unbilled_followup_cost` dispara cuando el total de costes con `invoiced=false` en el período, agrupado por proyecto, supera `min_cost_amount` (€500, estricto `>`).

**Decisiones aprobadas:**
- **Evaluación a nivel proyecto** (no por ítem individual): `SUM(cost_price × quantity) > min_cost_amount`. Aprobado explícitamente porque detecta acumulación de costes pequeños no facturados que suman riesgo operativo real.
- Comparador estricto `>`: exactamente €500 NO dispara.
- Campo fuente: `intelligence_mirror_costs.invoiced = false` → mapea a `followup_cost.already_invoiced` del ERP.
- Solo suma costes `uninvoiced`; los `invoiced=true` del mismo proyecto no entran.
- Threshold configurable: `billing_guardian_rules.min_cost_amount` (reservado para esta regla, separado de `min_activity_amount` de BI-052).
- Severity tiers: `medium ≤ €10k`, `high > €10k`. No `critical` por ahora — observar datos reales antes de añadir tier adicional.
- evidence_json: `{ count_items, total_amount, cost_types[] }`.
- recommendation: holandés, texto claro con ref proyecto + instrucción CAFCA.

**Desviación aprobada del spec original:**
> Auditor approved project-level aggregation instead of per-item threshold because multiple small uninvoiced costs on the same project represent a real billing risk.

**Tests:** 15 pasados / 26 assertions (BillingGuardianUnbilledCostTest.php). Commit `108f928`.

### Sprint 1 — Tickets (todos ✅)

| Ticket | Título | Commit | Estado |
|--------|--------|--------|--------|
| BI-010 | `contract_price`, `type`, `state` → `intelligence_mirror_projects` | `5002265` | ✅ Done |
| BI-011 | `invoiced` (boolean) → `intelligence_mirror_costs` + sync (`already_invoiced`) | `f8383fd` | ✅ Done |
| BI-012 | `relation_id`, `date_expiration`, `fl_paid` → `intelligence_mirror_invoices` + sync | `7984209` | ✅ Done |
| BI-013 | `intelligence_mirror_estimate_calc` — factores MAMO (6.677 filas 1:1) | `358cbe5` | ✅ Done |
| BI-014 | `intelligence_mirror_project_links` (1.658 filas) + fix composite-key save | `ec89fcc`+`a0b8604` | ✅ Done |
| BI-015 | `intelligence_mirror_project_results` — 45 filas validadas, profit_percent decimal(10,4) | `eb1ae6a` | ✅ Done |
| BI-016 | `intelligence_mirror_workdocs` — 1.782 filas validadas | `e86255a` | ✅ Done |
| BI-017 | `intelligence_bi_config` + seeder 5 entradas (firstOrCreate) | `a118d92` | ✅ Done |
| BI-018 | `BiConfigService` — get/set/dot-notation/cache 1h + invalidación | `04c35b2` | ✅ Done |
| BI-019 | `BiConfigPage` Filament V5 — 5 secciones, super_admin only | `3280d83` | ✅ Done |
| BI-020 | Labor sync window — respeta `labor_sync_schedule`, ventanas que cruzan medianoche | `9740181` | ✅ Done |
| BI-021 | Tests Intelligence — 27 tests / 61 assertions (3 archivos Feature) | `b2b6d8f` | ✅ Done |
| BI-022 | Fix N+1 `syncProjects` — batch whereIn por chunk; colgado → 1.14s | `c46db98` | ✅ Done |

### Hallazgos clave Sprint 1 (para el auditor)

- **BI-011:** campo correcto es `followup_cost.already_invoiced` (12.735 true / 190 false). `invoice` bit es flag de tipo, NO estado de facturación. `fl_booked_to_invoice` tiene 1 sola fila.
- **BI-015:** `profit_percent` requiere `decimal(10,4)` — caso real P20180031 NMBS: 11.852% (cost €920, invoiced €110.005). `rpt_project_results.invoiced` es importe float €, no boolean.
- **BI-016:** `workdoc.fl_invoice=1` en 75% de filas → flag de tipo facturable. `fl_paid=1` en solo 1 fila — no es señal fiable aún. `fl_needinvoiced` descartado (9 filas).
- **BI-014 fix:** `updateOrCreate` con PK compuesta generaba `UPDATE WHERE id IS NULL` — bug latente que habría fallado en el primer re-sync de producción. Detectado por los tests de BI-021.
- **Mirrors poblados:** projects 127 (contract_price/type/state OK, zipcode 126/127), project_results 45/45, workdocs 1.782/1.782, relations 3.259, estimate_items 144.051.

**Documento maestro:** `docs/bi-sprint-plan.md`
**Rama Sprint 1:** `feature/bi-sprint1-data` | Sprint 2B → desde `main` tras merge de Sprint 1

### Estado de ramas feature

| Rama | Estado vs `main` |
|------|-----------------|
| `feature/mailing` | ✅ Ya en main |
| `Safety_Inspections` | ✅ Ya en main |
| `feature/static-site-publish` | ✅ Mergeada `ff11888` |
| `feature/website-work-details` | ✅ Mergeada `1169646` |

### Work Details / In Action — tickets mergeados (2026-06-05)

| WEB | CLA | Título | Commit | Estado |
|-----|-----|--------|--------|--------|
| WEB-012 | CLA-133 | `work_story`, `challenge`, `solution`, `result` + `detail_gallery` collection | `7f7f4f9` | ✅ Done |
| WEB-013 | CLA-134 | Translations NL + EN — Work Details section | `020a5f3` | ✅ Done |
| WEB-014 | CLA-135 | Filament — Work Details / In Action section | `b4c4ab4` | ✅ Done |
| WEB-015 | CLA-136 | API Resource — expose Work Details + `detail_gallery` | `a1aa7e4` | ✅ Done |
| WEB-016 | CLA-137 | Feature tests — Work Details / In Action | `76360c4` | ✅ Done |
| Fix | — | Comentario erróneo locale `de` — Gemini traduce nl/en/fr/**de** | `2d6c882` | ✅ Done |

Merge commit: `1169646` — resolución de conflictos en `ProjectResource.php` y `Project.php`:
- `work_story/challenge/solution/result` usan `resolveLocaleValue()` (consistente con WEB-008)
- `detail_gallery` caption/alt también usan `resolveLocaleValue()` (feature branch dejaba valores raw)
- `getAiTranslatableAttributes()` incluye `client` (HEAD) + los 4 campos Work Details

### Sprint Static Site Auto-Publish — tickets mergeados (2026-06-05)

| WEB | CLA | Título | Commit | Estado |
|-----|-----|--------|--------|--------|
| WEB-017 | CLA-138 | `config/static_site.php` — foundation config | `4285b72` | ✅ Done |
| WEB-018 | CLA-139 | `PublicationState` — migration, model, enum | `fbfdafc` | ✅ Done |
| WEB-019 | CLA-140 | `StaticSitePublicationService` + `WebhookResult` + job stub | `5458047` | ✅ Done |
| WEB-020 | CLA-141 | `TriggerStaticSiteRebuildJob` — debounce + retry | `0c7c51c` | ✅ Done |
| WEB-021 | CLA-142 | Wire observers → `StaticSitePublicationService` | `fb5bb05` | ✅ Done |
| WEB-022+023 | CLA-143+144 | Filament publication widget + botón manual + traducciones | `9cf47f9` | ✅ Done |
| WEB-024 | CLA-145 | Node.js webhook receiver (`scripts/astro-rebuild/`) | `2c34a3f` | ✅ Done |
| WEB-025 | CLA-146 | Feature tests — static site publication (Laravel + Node) | `057f1bf` | ✅ Done |
| Fix | — | GalleryMetadataJobTest: aserción + enable flag corregidos | `2e8732d` | ✅ Done |

Merge commit: `ff11888` (PR #3)

### Arquitectura del pipeline

```
Admin guarda proyecto / media
  → ProjectObserver / MediaObserver
  → StaticSitePublicationService::requestRebuild()
      → PublicationState::markPending()          (MySQL)
      → TriggerStaticSiteRebuildJob::dispatch()  (debounce + dispatch_key)
          → StaticSitePublicationService::sendWebhook()
              → POST /rebuild  (HMAC-SHA256, anti-replay 300s)
              → 202 = solicitud aceptada; build corre async en frontend
              → GET /health    = estado real del build

Frontend: Node.js webhook-receiver.mjs en 192.168.60.20
  → responde 202 inmediatamente
  → npm run build -- --outDir releases/<YYYYMMDDTHHmmss>/
  → rename(2) → swap atómico del symlink current
```

### API pública Website — URLs operativas

| Método | URL | Descripción |
|--------|-----|-------------|
| `GET` | `/v1/website/projects` | Listado paginado (`?category`, `?year`, `?featured`, `?per_page`) |
| `GET` | `/v1/website/projects/{slug}` | Detalle completo — incluye `work_story/challenge/solution/result/detail_gallery` |
| `GET` | `/v1/website/projects/categories` | Enum de categorías |
| `GET` | `/v1/website/projects/years` | Años con proyectos publicados |

Locale resuelto por `Accept-Language` vía `SetPanelLocale` middleware (nl/en/fr/de).

### Riesgos pendientes antes de producción

1. `STATIC_SITE_REBUILD_ENABLED=false` por defecto — activar explícitamente en .env de producción
2. ~~Ghost migration `add_work_details_to_website_projects_table`~~ — resuelto: `feature/website-work-details` mergeado en `main`
3. Permisos de escritura de `astro-deploy` sobre `WEBHOOK_RELEASES_DIR` y `WEBHOOK_PROJECT_DIR`
4. Configurar `tries`/`backoff` de `TriggerStaticSiteRebuildJob` antes de activar con Redis en producción
5. Proyectos publicados en producción sin `work_story/challenge/solution/result` rellenos — la API devuelve `null`; requiere que editores rellenen en Filament o se lance auto-traducción Gemini

### Tests ejecutados en verificación previa al PR

- Laravel: 51/51 ✅ (módulo Website completo)
- Node.js: 16/16 ✅ (HMAC, health, deploy, failed build, concurrent builds, pruning)
- Secret scan: limpio

## Reglas de arranque persistentes

Las reglas de arranque de Antigravity viven en:

- `AGENTS.md` — reglas del repositorio (leído automáticamente por agentes compatibles)
- `.agents/rules/00-project-startup.md` — protocolo completo de arranque

Todo agente debe leer estos archivos antes de cualquier acción.

---

## Módulos activos

| Módulo | Estado | Rama | Documento específico |
|--------|--------|------|---------------------|
| **Mailing** | ✅ Fase 0+1+2 completadas / Fase 3 en Backlog | `feature/mailing` | `docs/Mailing/mailing-platform-master.md` |
| **Website** | ✅ WEB-001→025 mergeados en `main` (incl. Work Details + Static Site) | `main` | `docs/website-sprint-handoff.md` |
| **Safety** | ✅ Sprint completado (SAF-001 a SAF-022) + Fase 1A Adopción PWA completada | `main` | `docs/safety-sprint-linear-tickets.md` |
| **Performance** | 🚧 ~85% | `main` | Ver `CLAUDE.md` |
| **Intelligence / BI** | ✅ Sprint 1 ✅ Sprint 2B — PR #6 pendiente merge; BI-PROJ-02 ✅ (Vista de Águila) | `feature/bi-project-intelligence-detail` | `docs/bi-sprint-plan.md` |
| **Prospects** | 🚧 ~80% (PROS-BUG-001+002 cerrados, FAB mailing operativo, sync dashboard exception feed) | `main` | Ver `CLAUDE.md` |
| **Cafca** | ✅ ~90% | `main` | Ver `CLAUDE.md` |
| **Core** | ✅ ~99% | `main` | Ver `CLAUDE.md` |

---

## Cambios recientes — UX / Bugs (2026-06-07)

| Ticket | Linear | Título | Commits | Estado |
|--------|--------|--------|---------|--------|
| MAI-UX-002 | CLA-143 | Improve campaign content snapshot preview | `fac901f` | ✅ Done |
| MAI-TEST-001 | CLA-144 | Fix 68 failing Mailing feature tests + 2 production bugs | `2bdd181` | ✅ Done |
| MAI-UX-003 | CLA-145 | Campaign view: accordion → modal "View full content" | — | ✅ Done |
| PROS-UX-001 | CLA-146 | Prospects: contextual mailing FAB para selección | `285a8f3`, `c5c65b9` | ✅ Done |
| PROS-BUG-002 | CLA-147 | Fix FAB sync + posición fixed (scope, page-select, interval) | `3d8a7b9`→`ca581e1` | ✅ Done |
| PROS-UX-003 | CLA-148 | Sync Dashboard: Aandacht vereist exception feed | `a95a42f` | ✅ Done |
| PROS-UX-002 | — | Compact mailing FAB on mobile (circular icon+badge on ≤640px) | `8a9cc51` | ✅ Done |


---

## Backlog Mailing — completado

| Ticket | Linear | Título | Commit | Estado |
|--------|--------|--------|--------|--------|
| MAI-BUG-001 | CLA-133 | Approve visible en campañas terminales | `c837782` | ✅ Done |
| MAI-BUG-002 | CLA-134 | Contadores dobles + FAILED + OAuth null cacheado | `6189e47` | ✅ Done |
| MAI-BUG-003 | CLA-135 | Submit visible en campañas terminales | `50c3a93` | ✅ Done |
| MAI-BUG-004 | CLA-136 | Ruta unsubscribe incorrecta bloqueaba envío | `fe0638a` | ✅ Done |
| MAI-BUG-005 | CLA-137 | Cancel visible en estados terminales (listado) | `10c6324` | ✅ Done |
| MAI-BUG-006 | CLA-139 | Cancel/Approve/Submit en detalle campaña | `db8605d` | ✅ Done |
| MAI-UX-001 | CLA-138 | Campaign engagement detail view | `51fa208` | ✅ Done |

## Hoja de ruta — prioridades

| Prioridad | Ticket | Linear | Título | Estado |
|-----------|--------|--------|--------|--------|
| **1** | BI-000 | — | Sprint BI — Sprint 0: integración + PR #4 | ✅ Done |
| **2** | BI-010→022 | — | Sprint BI — Sprint 1: mirrors + bi_config | ✅ Done — pendiente GO + merge |
| **3** | BI-050→062 | — | Sprint BI — Sprint 2B: Monthly Billing Guardian | ⬜ Desbloqueado tras merge |
| 4 | OPS-MAI-001 | CLA-140 | Mailing production readiness validation | ⬜ Todo |
| 5 | — | — | Website backfill media (`website:regenerate-media`) + validar deploy frontend | Operativo |
| 6 | — | — | Prospects CRM — calidad de datos, filtros, segmentos | 🚧 ~78% |
| 7 | — | — | Performance / Watchdog — impacto financiero si gerencia lo prioriza | 🚧 ~85% |
| Bloqueado | Mailing Fase 3 | MAI-031→036 | Scoring, predicciones, IA | ⏸ Hasta 4–6 sem datos reales |

---

## Flujo de deploy a producción

### Topología de red

```
Internet → sbapu03 (192.168.60.10) nginx edge
               └─ proxy_pass 127.0.0.1:9443
                    └─ autossh túnel inverso → prod-priv-01 (192.168.254.52):443
                         └─ nginx local → PHP-FPM 8.4 → Laravel
```

CORS gestionado en sbapu03 nginx (cors-map.conf + proxy_hide_header). No en Laravel HandleCors.

### Resumen deploy

```
git push origin main
       ↓
GitHub Actions — "Build Laravel release" (composer --no-dev, npm build, tar.gz → GitHub Releases 'production-latest')
       ↓
ssh bert@192.168.254.52
bash /opt/claesen/scripts/deploy.sh [branch]
```

Deploy al servidor es **manual** — CI construye el artefacto, bert decide cuándo activarlo.

### Lo que hace deploy.sh (`/opt/claesen/scripts/deploy.sh`)

Capistrano-style: releases dir + `current` symlink + shared `.env` y `storage`.

1. Descarga `release.tar.gz` de GitHub Releases (`gh release download production-latest`)
2. Extrae en `/srv/www/claesen/releases/<timestamp>`
3. Symlinks: `shared/.env` → `releases/<ts>/.env`, `shared/storage` → `releases/<ts>/storage`
4. `chown bert:www-data shared/.env && chmod 640` — www-data puede leer .env como grupo
5. `composer install --no-dev`
6. `npm ci && npm run build` (assets)
7. `php artisan migrate --force`
8. `php artisan optimize:clear && filament:upgrade && optimize`
9. `sudo chown -R www-data:www-data releases/<ts>` + `chmod -R 775`
10. `sudo -u www-data php artisan config:cache` — genera config.php legible por www-data
11. `sudo rm -rf current && sudo ln -s releases/<ts> current`
12. `sudo systemctl reload php8.4-fpm`
13. `supervisorctl restart claesen-worker:* claesen-scheduler`

### Scripts de servidor (versionados en `infrastructure/`)

| Script | Ruta producción | Propósito |
|--------|-----------------|-----------|
| `deploy.sh` | `/opt/claesen/scripts/deploy.sh` | Deploy principal (ver arriba) |
| `backup-mysql.sh` | `/opt/claesen/scripts/backup-mysql.sh` | mysqldump con `--no-tablespaces` (sin PROCESS privilege) |
| `backup-files.sh` | `/opt/claesen/scripts/backup-files.sh` | Restic backup `/srv/www/claesen`, nginx, ssl |
| `backup-all.sh` | `/opt/claesen/scripts/backup-all.sh` | Orquesta backup-mysql + backup-files + ntfy notify |
| `monitor.sh` | `/opt/claesen/scripts/monitor.sh` | Checks servicios, disco (>85%), RAM (<10% libre) |
| `notify.sh` | `/opt/claesen/scripts/notify.sh` | Push ntfy.sh — wrapper de notificaciones |

Config env en `/etc/claesen-backup.env` y `/etc/claesen-notify.env` (permisos `root:bert 640`).
Backups en `/var/backups/claesen/` (permisos `root:bert 770`).

### Nginx sbapu03 (versionado en `infrastructure/nginx/sbapu03/`)

| Archivo | Propósito |
|---------|-----------|
| `cors-map.conf` | `map $http_origin $cors_allowed_origin` — allowlist de 4 orígenes + localhost:5173 |
| `backend.claesen-verlichting.be.conf` | Proxy + CORS edge: OPTIONS→204, proxy_hide_header, add_header always, proxy_redirect |

### Notas operativas

- `.env` en producción: `bert:www-data 640` — www-data lee por grupo; bert puede editar directamente.
- Si deploy falla después de step 11 (symlink): `php artisan up` desde `/srv/www/claesen/current`.
- `gh` CLI autenticado como `cubanote816` en `/home/bert/.config/gh/hosts.yml`.
- Para editar nginx en sbapu03: `sudo tee /etc/nginx/sites-available/<archivo>` + `sudo nginx -t && sudo nginx -s reload` (ambos NOPASSWD para bert).

---

## Bloqueantes actuales

- **MAI-026** — Webhook handler ESP externo: bloqueado por decisión de gerencia. No tocar.
- **Mailing Fase 3** (MAI-031 a MAI-036) — bloqueada hasta 4–6 semanas de datos reales en producción.
- **Backfill Website media** — `php artisan website:regenerate-media` pendiente de ejecutar en producción.

Ver `docs/ai/known-risks.md` para el detalle completo.

---

## Próximos pasos recomendados

1. **Sprint BI — Sprint 0** (ahora, rama `feature/bi-foundation`):
   ```
   git checkout -b feature/bi-foundation
   git cherry-pick 8d563e8 a8eedcf 5796a32
   # verificar no-colisión de las 6 migraciones con main
   php artisan test --testsuite=Modules --filter=Intelligence
   ```
2. **Deploy Website en producción:**
   - `php artisan migrate` (columnas `work_story/challenge/solution/result` + tabla `publication_states`)
   - Instalar receiver Node.js en 192.168.60.20 (`scripts/astro-rebuild/README.md`)
   - Configurar `.env`: `STATIC_SITE_REBUILD_ENABLED=true`, `STATIC_SITE_WEBHOOK_SECRET`, `STATIC_SITE_WEBHOOK_URL`, `STATIC_SITE_HEALTH_URL`
   - Firewall: puerto 9000 solo desde 192.168.60.10
2. **Website backfill media:** ejecutar `php artisan website:regenerate-media` en producción (pendiente desde WEB-007).
3. **Rellenar Work Details en Filament:** `work_story/challenge/solution/result` vacíos en proyectos publicados — editores o trigger Gemini manual.
4. **Mailing Fase 3:** esperar datos reales de campañas en producción antes de iniciar MAI-031.
5. **Performance:** continuar mejoras de insights y Watchdog según prioridad.
6. **Prospects:** completar CRM y campañas email (~75%).

---

## Cambios recientes

| Fecha | Ticket | Acción |
|-------|--------|--------|
| 2026-07-04 | FO-009 / CLA-213 | Done — dominio de Mantenimiento de luminarias: catálogo `FoMaintenanceType` (`code` en vez de IDs hardcodeados) + `FoMaintenanceRecord` polimórfico (Luminaire\|ElectricalBoard) + subdominio cliente-reportado con columnas reales. `ScheduledMaintenanceService`/`Task` excluidos a propósito. 22 tests nuevos, 187/187 FieldOps. Commit `afd5e43`. |
| 2026-07-04 | FO-007 / CLA-212 | Done (spike) — confirmado que el dominio de Mantenimiento de luminarias del sistema anterior está vivo en producción (confirmación directa del usuario). No se cierra como N/A. Abierto `CLA-213`/FO-009 (Slice G), implementado el mismo día (ver fila de arriba). |
| 2026-07-04 | FO-005 / CLA-210 | Done — adjuntos de fotos/PDFs (spatie/laravel-medialibrary, disco privado `local`) para Complex/Terrain/Structure/ElectricalBoard vía trait `HasFieldOpsMedia` + controller genérico `FieldOpsMediaController`. Filament `SpatieMediaLibraryFileUpload`. 17 tests nuevos, 165/165 FieldOps. Commit `f80e0cb`. |
| 2026-07-04 | FO-003 / CLA-209 | Done — dominio Electrical Board completo: catálogo `ElectricalBoardType` + entidad `ElectricalBoard` con 3 pivots reales (`fo_complex_electrical_board`, `fo_electrical_board_terrain`, `fo_electrical_board_structure`, cascadeOnDelete). CRUD API + Filament. 25 tests nuevos, 148/148 FieldOps. Commit `603baf7`. |
| 2026-07-04 | FO-004 / CLA-207 | Done — catálogos `AccessType`/`SafetyType` + columnas `access_active`/`safety_certified` en `fo_structures`, reemplazando `external_safety_id`/`external_access_id`. Denormalizado (relación 1:1), no tablas de instancia separadas. 8 tests nuevos, 123/123 FieldOps. Commit `4f6d1c5`. |
| 2026-07-03 | FO-008 / CLA-206 | Done — unificar locale `es`→`de` en 6 FormRequests de FieldOps (Terrain/Structure/Luminaire Store+Update), consistente con `TranslateModelAttributesJob` y formularios Filament. 2 tests ajustados. 117/117 tests FieldOps. Commit `6a831e9`. |
| 2026-06-29 | fix(perf) | Done — `EmployeePerformanceService` + `EmployeeInfolist`: todas las queries `Labor` (sqlsrv → `followup_labor_analytical`) reemplazadas por `MirrorLabor` (`intelligence_mirror_labor`) y `MirrorProject` (`intelligence_mirror_projects`). Afecta: `getShortTrend`, `getStatsForPeriod`, `getDailyStats`, `hasAnyLaborHistory`, `getComparativeRanking`, `getTeamPosition`, `getTemporalProjectDetails`, `active_projects_summary`. `categorizeLaborEntry` desacoplado del tipo `Labor` (duck typing). Commit `8ef70ce`. |
| 2026-06-29 | SAF reminder | Verificado — `safety:notify-inactive-managers` listo para producción. 9/9 tests ✅. `SAFETY_PWA_URL` confirmado en `.env` producción. Scheduler: lunes 09:00 `withoutOverlapping()`. Recomendado `--dry-run` antes del primer lunes. |
| 2026-06-28 | CLA-182 | Done — `POST /api/v1/auth/change-password` para cuentas locales (microsoft_id null). 403 para cuentas Microsoft. Valida `current_password` vía Hash::check. Revoca todos los tokens Sanctum excepto el actual. `GET /api/v1/me` añade `auth_provider: "local"\|"microsoft"` para que el frontend sepa si mostrar Beveiliging. 6 tests / 12 assertions ✅. Commit `32ae7fe`. Nota test: `auth()->forgetGuards()` entre requests secuenciales para evitar cache de RequestGuard entre requests del mismo test. |
| 2026-06-28 | CLA-178 | Done — Rediseño emails Safety: `inspection-report.blade.php` (tabla, inline styles, logo, banda azul/roja por tipo, hero, badge, firma) + `inspection-reminder.blade.php` (mismo patrón, banda ámbar, alert box, CTA). Commits `74fef44` + `bdf77c4`. Fix colateral: nota indicativa CTOR en `CampaignMetricsWidget` + clave `ctor_note` en lang EN/NL. Commit `11cca98`. 116/116 tests Safety ✅ (3 fallos preexistentes Safety-auth no relacionados). |
| 2026-06-28 | i18n | Done — Auditoría y corrección completa de strings de UI en backoffice (Bloques A→D). Eliminados todos los strings en español, todos los ternarios `$nl ? ... : ...`, y todos los labels NL/EN hardcodeados. Ahora toda la UI usa `__()` con `app()->getLocale()`. Nuevos ficheros: `Modules/Intelligence/lang/{nl,en}/{billing,offer_simulator,bi_config}.php`, `Modules/Performance/lang/{nl,en}/projects.php`. Actualizados: `lang/{nl,en}/navigation.php` (12 grupos sidebar), `Modules/Safety/lang/{nl,en}/inspections.php` (columnas, tipos, badges, acciones, secciones). PHP afectado: `SafetyAdoptionOverviewWidget`, `InspectionResource`, `MonthlyBillingControlPage`, `OfferSimulator`, `BiConfigPage`, `ProjectResource` + 7 resources para grupos de navegación. Commit `d70f318`. 216/216 tests ✅ (31 fallos preexistentes en Mailing/Safety-auth/Website no relacionados). |
| 2026-06-27 | INFRA | Done — CORS corregido en sbapu03 nginx: `cors-map.conf` + `proxy_hide_header` eliminan duplicados ACAO; preflight OPTIONS→204 sin hit PHP. `MissingAppKeyException` resuelto: `shared/.env` → `bert:www-data 640`; `deploy.sh` corre `sudo -u www-data config:cache` post-chown. `mysqldump` saneado (`--no-tablespaces`, sin `--events`). 3 deploys limpios consecutivos. Scripts versionados en `infrastructure/`. Commit `667416a`. |
| 2026-06-27 | CLA-181 | Done — Migración global auth browser-first: Sanctum SPA cookie session. `statefulApi()` + CORS `supports_credentials=true` + Safety login/logout/me por cookie HttpOnly sin token + OAuth callback sin Bearer en URL + `loginSpa()` en Core para SPAs + `EnsureSafetyAccess` desacoplado de token ability para sesión + `logout()` tolerante a `TransientToken` + `localhost:5173` en stateful domains + `.env.example` documentado. Bearer legacy intacto para FieldOps/Sport. 251/251 tests ✅. Commit `80e3f1e`. Pendiente frontend: `GET /sanctum/csrf-cookie` antes de POST login, `withCredentials: true`, retirar `localStorage.auth_token`. `SESSION_DOMAIN` y `SESSION_SAME_SITE=none` en `.env` producción si frontend es cross-site. |
| 2026-06-24 | CLA-174 | Done — `project_address_text` (Projectadres) añadido al mirror y al endpoint Safety projects. Batch-load desde `txt.txt` vía `project.project_address = txt.txt_id`. Normalización null si vacío/whitespace. Contrato: `{id, name, descr, project_address_text, relation_name}`. 7 tests / 19 assertions. Commit `526b0b8`. Backfill: `php artisan intelligence:sync-mirror` post-deploy. |
| 2026-06-24 | FO-002 / CLA-173 | Done — `project.descr` añadido al mirror (migración + sync) y al endpoint Safety projects. Contrato: `{id, name, descr, relation_name}`. 5 tests / 15 assertions. Commit `50fc4eb`. |
| 2026-06-24 | FO-001 / CLA-172 | Done — Filament admin FieldOps (FoClientResource, TerrainTypeResource, StructureTypeResource), TranslateModelAttributesJob (Gemini nl/en/fr/de, ai_translation_status), SetLocaleFromHeader middleware en rutas v1/fieldops/*. 6 tests / 14 assertions. Commit `78e66df`. |
| 2026-06-23 | C.6a | Done — `GET /complexes?client_id=X` y `GET /structures?terrain_id=X`. Ambos filtros con `when()` + `whereHas()`. 5 tests nuevos / 15 assertions. 112/270 total FieldOps. Commit `b8b0205`. Desbloquea C.6b (frontend cutover). |
| 2026-06-23 | C.5 | Done — LuminaireFrame CRUD (structure_ids triple-case) + Luminaire CRUD (serial_number unique, frame_position auto-recalculado al cambiar frame, cross-validate type↔subgroup, info locale-merge). 35 tests / 95 assertions. 107/255 total FieldOps. Commit `e4452cf`. |
| 2026-06-23 | C.4 | Done — Structure CRUD. terrain_ids triple-case explícito (`absent→no-op / null→detach / array→sync`) usando `$request->has()`. info locale-merge. external_*_id como bridge opaco. 28 tests / 59 assertions. 72/160 con C.2+C.3+C.4. Commit `b2ff1c4`. |
| 2026-06-23 | C.3 | Done — Terrain CRUD (GET/POST/PUT/PATCH/DELETE). Locale validation `array:nl,en,fr,es`. Update merge parcial de traducciones. `complex_id` inmutable en update. 24 tests / 54 assertions. Commit `fbfaf6d`. |
| 2026-06-23 | C.2 | Done — Complex CRUD (POST/PUT/PATCH/DELETE) + RouteServiceProvider fix + factories + 20 tests. Flakiness de arranque documentada. Próximo: C.3 auditor gate. |
| 2026-06-23 | SAF-ADOPT / CLA-169 | Done — Fase 1A Adopción PWA completada. Rollups diarios con `project_id='GLOBAL'`, denominador `enabled_users` anclado estrictamente a los roles del middleware `EnsureSafetyAccess` (project_manager, super_admin, admin). Feature tests funcionales implementados validando el endpoint completo y previniendo duplicidad en `idempotency_key`. Commit `43089fb`. |
| 2026-06-22 | CLA-168 | Done — EMP-007: Discovery auditoría permisos cerrado. Decisión de negocio: Status Quo. El acceso a `EmployeeAnalytics` se restringe a `super_admin` y `admin` porque los insights IA y burnout son datos muy sensibles. No se modifica código ni se abre a managers/empleados sin separar antes datos operativos de sensibles. Sin commit de código. |
| 2026-06-22 | CLA-164 | Done — EMP-002: `calculateAchievementRate()` devuelve `null` (no `0%`) cuando `uren_per_week` es `null` o `<= 0`. `getDailyStats()` sin fallback `?? 0`. `aggregateStats()` docblock explicita baseline 7,6h vs contrato. Widget Stats: stat gris `Niet berekenbaar` cuando rate null; stat semanal usa clave `compliance_operational` (`Basis 7,6u`). Chart widget: línea target omitida cuando `uren_per_week` es null/0. Traducciones NL+EN (`achievement_unknown`, `compliance_operational`). Test sin `RefreshDatabase` (seam en memoria, determinista). 7 archivos, 8 tests / 15 aserciones ✅. Commit `ef513c7`. |
| 2026-06-22 | CLA-167 | Done — EMP-006: locale configurable para prompt Gemini en `TechnicianAnalysisService`. Config `performance.ai_insight_locale` (nl/en, fallback nl). Cache key v2 (`md5`). Prompt completo NL/EN sin texto en español. `PERFORMANCE_AI_LOCALE` en `.env.example`. 4 archivos, 15 tests / 59 aserciones ✅. Commit `8d5c27a`. |
| 2026-06-22 | CLA-166 | Done — EMP-003: diferenciar estados `erp_unavailable`, `no_period_activity`, `no_history` y `ready` en `EmployeeProjectTimeline`. Clasificador SQLSTATE (clase 08, HYT00/HYT01/IM002/IM014 + fallback mensajes). `hasAnyLaborHistory()` en `EmployeePerformanceService` para mockability. Blade 3 paneles PHP `@if`. 6 archivos, 11 tests / 50 assertions ✅. Smoke visual ✅. Commit `176da75`. |
| 2026-06-22 | CLA-165 | Done — EMP-005: caché `#[Locked] $cachedProjects` elimina segunda query SQL Server en `render()`. 2 archivos (componente + test). 4 tests/15 assertions ✅. Smoke visual ✅. Commit `bc9ff40`. |
| 2026-06-22 | CLA-163 | Done — EMP-004: eliminar botón "View archives" de `employee-project-timeline.blade.php:124`. 1 archivo, 1 línea. view:cache ✅. Smoke visual ✅ (sesión legítima Filament). Commit `5f0ec35`. |
| 2026-06-22 | CLA-162 | Done — EMP-001: eliminar alerta Watchdog falsa de `EmployeeInfolist.php:70-96`. 1 archivo, bloque eliminado. Commit `39c1e07`. |
| 2026-06-20 | CLA-161 | Done — MAI-PREF-001 enforce category preferences. Commits `80660d6`+`02a143d`. 77 tests / 134 assertions. Deploy pendiente: `migrate` + `mailing:backfill-preference-snapshots --apply`. |
| 2026-06-16 | CLA-159 | Done — Author audit metadata en `safety_questions`: migración FK `created_by_user_id`/`updated_by_user_id`, QuestionObserver, relaciones en modelo, API `show`/`active` devuelven `created_by`/`updated_by {id,name}`. 7 tests nuevos. Commit `a096243`. |
| 2026-06-16 | CLA-160 | Done — `safety:backfill-question-authors --apply` ejecutado en producción. 15 preguntas → Orelvys, 3 → Bert (creadas el 14-jun), 17 → updated_by Bert, ID 18 updated_by=null. Commits `7fc4a03`+`2d6938f`. |
| 2026-06-13 | BI-011→022 | Sprint 1 completado en una sesión: 12 tickets + 1 fix colateral. Mirrors nuevos (estimate_calc, project_links, project_results, workdocs), bi_config + service + página Filament, ventana labor sync, 27 tests, fix N+1. Todos los commits en `feature/bi-sprint1-data`. |
| 2026-06-13 | BI-010 | `contract_price`, `type`, `state` añadidos a `intelligence_mirror_projects`. Migración `2026_06_13_100000` aplicada. Sync completo pendiente (SQL Server no alcanzable desde Docker al momento del commit). Commit `5002265` en `feature/bi-sprint1-data`. |
| 2026-06-13 | BI-000 | PR #4 mergeado — `feature/bi-foundation` → `main`. Cherry-pick `8d563e8`+`a8eedcf` aplicados. 6 migraciones `2026_05_27_*` en main. Sail validado (migrate, sync --relations 3.259, sync --estimates 144.051). |
| 2026-06-13 | BI-PLAN | Done — Plan Sprint BI completado y aprobado por auditor. Sprint 0+1+2B GO. Auditor Gate formalizado en BI-052/053/054 con 5-ejemplo obligatorio. Documento: `docs/bi-sprint-plan.md`. |
| 2026-06-12 | OPS | Done — Fix GitHub Actions deploy workflow (5 bugs: actions versions @v4, PHP 8.3→8.4, .env.example `\nMAILING_DRIVER`, sqlite touch, CACHE/SESSION array, rsync self-copy). Fix deploy.sh (cd APP_DIR, artisan down \|\| true, sha256 verify, filament --no-interaction, php artisan optimize). Release `production-latest` operativa. |
| 2026-06-09 | Mailing | Done — One-time unsubscribe links (renders success immediately if already unsubscribed) and Livewire real-time auto-polling (5s) for campaign list, recipients table, and metrics widget. Verified with passing tests. |
| 2026-06-09 | Mailing | Done — Log and display 'Unsubscribed' status (Uitgeschreven) for unsubscribed or suppressed (unsubscribed) recipients instead of displaying 'Skipped (No email)'. Verified with tests passing in Sail. |
| 2026-06-09 | CORE-BUG-003 / CLA-153 | Done — Fix ProjectInsight namespace import in ProjectInsightSeeder and push all local commits to remote origin main. |
| 2026-06-09 | CORE-BUG-002 / CLA-152 | Done — Optimize login layout (reduce margins) and display the attempted Microsoft email address in the access denied error at the top of the login form using AUTH_LOGIN_FORM_BEFORE hook. |
| 2026-06-09 | PROS-UX-003 / CLA-148 | Done — Replace Sync Dashboard recent activity feed with exception-based Aandacht vereist section, retry action, and healthy empty state. Verified with Sail Prospects tests passing. |
| 2026-06-09 | CORE-BUG-001 / CLA-151 | Done — Render Microsoft login errors in custom login view. Verified locally. |
| 2026-06-09 | WEB-BUG-002 / CLA-150 | Done — Make website projects JSON migration idempotent by wrapping table and column alter statements in schema checks. Verified with tests passing. |
| 2026-06-09 | WEB-BUG-001 / CLA-149 | Done — Remove CAST(AS JSON) from website_projects migration to avoid syntax errors on MariaDB. Verified with tests passing. |
| 2026-06-06 | MAI-TEST-001 / CLA-144 | Done — Fix 68 failing Mailing tests: EmailTemplateFactory (new), ProspectFactory (new + afterCreating), CampaignMessageFactory fixes, EmailTemplate/MessageEvent/Prospect model fixes, CheckDeliverabilityAlertsCommand production bugs (`[$alert,$created]`→`wasRecentlyCreated`, resilient role query), SelectAbWinnerCommand GROUP BY, DeliverabilityAlertTest/SchemaFoundationTest fixes — `2bdd181` |
| 2026-06-06 | MAI-UX-002 / CLA-143 | Done — Campaign content snapshot preview: subject + plain-text preview visible sin accordion; Full Content expandible — `fac901f` |
| 2026-06-06 | MAI-BUG-007 / CLA-142 | Done — ONLY_FULL_GROUP_BY crash en CampaignMetricsWidget (chronological global scope) — `742c4f6` |
| 2026-06-06 | MAI-CONTENT-001 / CLA-141 | Done — Seeder LED 2027 templates (3 plantillas comerciales NL) — `0f79447` |
| 2026-06-06 | MAI-BUG-005 / CLA-137 | Done — Cancel action hidden on terminal campaigns (canTransitionTo guard) — `10c6324` |
| 2026-06-06 | MAI-BUG-004 / CLA-136 | Done — One-click unsubscribe route incorrecto (`mailing.unsubscribe.oneclick` → `api.mailing.unsubscribe.oneclick`) — `fe0638a`. Confirmado: Sent 2 / Failed 0 |
| 2026-06-06 | MAI-BUG-003 | Submit button visible on non-draft campaigns for super_admin — `50c3a93` |
| 2026-06-06 | MAI-BUG-002 | Campaign send accounting fixed — double count, completed-when-all-fail, null token cached — `6189e47` |
| 2026-06-06 | MAI-BUG-001 | Approve button visible on terminal campaigns for super_admin — `c837782` |
| 2026-06-06 | PROS-BUG-001 / CLA-133 | Bug FAB mailing cerrado — 3 causas raíz: `$selectedTableRecords` no limpiado en PHP al cambiar tab; FAB saltaba Alpine `mountAction()` (PHP siempre recibía `[]`); `livewire:update` no existe en Livewire 3 — commits `85a9100` `69246d6` `e5c22d9` |
| 2026-06-05 | verificación | API pública `/v1/website/projects/{slug}` confirma `work_story/challenge/solution/result/detail_gallery` operativos |
| 2026-06-05 | WEB-012→016 | Merge `feature/website-work-details` → `main` — Work Details / In Action — `1169646` |
| 2026-06-05 | Fix | Comentario erróneo locale `de` corregido — `2d6c882` |
| 2026-06-05 | WEB-017→025 | Merge `feature/static-site-publish` → `main` (PR #3) — Static site pipeline — `ff11888` |
| 2026-06-03 | TEST-GATE-001 | Arnés obligatorio de testing — commits `0278d05` `92199c3` |
| 2026-06-03 | WEB-011 / CLA-111 | Seguimiento Consultation Requests — commits `2b500b1` `569c2c0` |
| 2026-06-02 | WEB-010 / CLA-110 | Email transaccional Consultation Requests — commit `0588594` |
| 2026-06-02 | WEB-009 / CLA-109 | IA caption/alt galería portfolio — commits `f3d57c8` `112aef8` `5c1c972` |
| 2026-06-02 | WEB-008 / CLA-108 | Base multidioma portfolio nl/en/fr/de — commits `28e19aa` `80865c8` `9a626cd` |
| 2026-06-02 | DOCS-AI-003 / CLA-107 | Verificación arranque persistente Antigravity — commit `0ad1529` |
| 2026-06-02 | DOCS-AI-002 / CLA-106 | Creado `AGENTS.md` + `.agents/rules/00-project-startup.md` — reglas de arranque persistentes |
| 2026-06-02 | DOCS-AI-001 / CLA-105 | Creado sistema AI harnesses en `docs/ai/` + `handoff.md` raíz |
| 2026-05-30 | MAI-030 / CLA-105 | Cerrada Fase 2 Mailing — documentación y preparación PR |
| 2026-05-30 | MAI-027 / CLA-3b20265 | Alertas de entregabilidad — hard bounce + spam complaint |
| 2026-05-30 | MAI-023 / CLA-5699c75 | Follow-up automático por comportamiento |
| 2026-05-30 | MAI-022 / CLA-79270f7 | A/B testing de asunto — split + winner automático por CTR |
| 2026-05-30 | MAI-025 / CLA-7b00685 | Página de preferencias de categoría |

---

## Verificación de arranque persistente Antigravity

- Fecha: 2026-06-02
- Ticket: DOCS-AI-003 / CLA-107
- Resultado: OK

Se verificó que una nueva sesión de Antigravity lee y aplica correctamente:

1. `CLAUDE.md`
2. `handoff.md`
3. `docs/ai/README.md`
4. `AGENTS.md`
5. `.agents/rules/00-project-startup.md`

Reglas confirmadas activas:

- Sin ticket Linear activo → sin edición de archivos.
- Sin plan aprobado → sin implementación.
- Sin GO técnico → no se marca Done.
- No se leen ni copian secretos.
- Mailing Fase 3 bloqueada hasta 4–6 semanas de datos reales en producción.
- MAI-026 bloqueado hasta decisión de gerencia.
- SQL Server legacy / Cafca sigue siendo ReadOnly.

Próximo paso: definir el próximo ticket Linear antes de iniciar cualquier trabajo nuevo.

---

## Cómo reanudar una sesión

```
Lee CLAUDE.md, handoff.md y docs/ai/README.md.
Luego lee el documento específico del módulo activo.

Para Mailing: docs/Mailing/mailing-platform-master.md
Para Website: docs/website-sprint-handoff.md
Para Safety:  docs/safety-sprint-linear-tickets.md
```

Ver `docs/ai/prompt-templates.md` para prompts de reanudación listos para copiar.
