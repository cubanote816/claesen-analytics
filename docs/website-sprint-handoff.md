# Website Sprint — Handoff Document

> Rama: `website` | Inicio: 2026-05-28 | Estado: en curso

---

## Contexto del sprint

El módulo `Modules/Website` provee el backend API para el sitio público de Claesen Verlichting.
El frontend es un sitio Astro en `cubanote816/website-claesen-v1` que consume esta API.

El flujo completo es:
```
Admin edita proyecto en Filament
  → ProjectObserver / MediaObserver
  → NotifyAstroFrontendJob (queue)
  → POST https://api.github.com/repos/cubanote816/website-claesen-v1/dispatches
  → GitHub Actions repository_dispatch (backend_update)
  → npm run sync:prod   ← descarga imágenes de la API → public/v1-media/
  → npm run build:prod  ← compila Astro con datos estáticos
  → LFTP mirror         ← sube dist/ al servidor SFTP del frontend
```

---

## Bugs corregidos en este sprint

### WEB-001 / CLA-90 — `132f98c`
**Archivo:** `Modules/Website/Jobs/NotifyAstroFrontendJob.php`
- `event_type` cambiado de `update_portfolio` → `backend_update`
- El Action nunca se activaba automáticamente antes de este fix

### WEB-002 / CLA-91 — `141c3ab`
**Archivo:** `Modules/Website/Repositories/EloquentProjectRepository.php`
- `is_published` (columna inexistente) → `published`
- Eliminado filtro `published_at <= now()` (published_at no está en fillable, siempre NULL)
- Orden cambiado a `order_index` (consistente con scope del modelo)
- Semántica aprobada (opción A): `published = true` es condición suficiente

### WEB-003 + WEB-004 / CLA-92 + CLA-93 — ⏳ pendiente push
**Archivo:** `.github/workflows/deploy.yml` (repo `website-claesen-v1`)

**WEB-003:** `cp -r public/v1-media dist/v1-media` creaba `dist/v1-media/v1-media/` (directorio dentro de directorio).
Fix: `cp -r public/v1-media/. dist/v1-media/` (copia el contenido, no el directorio).

**WEB-004:** El bloque LFTP mezclaba sintaxis bash:
- `set sftp:chmod-ignore yes` → variable inexistente en LFTP
- `mkdir -p ... || true` → `true` no es comando LFTP

Fix: separar en 3 invocaciones `lftp -c "..."` independientes. El `|| true` y `|| echo` quedan en bash, fuera del bloque LFTP.

Commit pusheado al repo frontend: `7b2b28f` (main de `cubanote816/website-claesen-v1`).

### WEB-005 + WEB-006 / CLA-94 + CLA-95 — `2868699`
**Archivo:** `Modules/Website/Models/Project.php`

**WEB-005:** Conversiones de media ahora todas en WebP:
- `thumb`: 300×200, WebP q85
- `optimized`: 1200×1200, WebP q80 (sin cambios)
- `gallery`: 1200×800, WebP q80 (antes sin formato → JPEG/PNG original)

**WEB-006:** Atributos API:
- `api_featured_image_url`: devuelve URL de conversión `optimized` (WebP) con fallback al original
- `api_gallery`: añadida clave `optimized` con URL WebP (requerida por `sync-content.js`: `img.optimized || img.url`)

### WEB-007 / CLA-96 — `90cc01b`
**Archivo:** `Modules/Website/Console/RegenerateProjectMediaCommand.php`

Nuevo Artisan command para regenerar conversiones WebP en media ya existente:
```bash
php artisan website:regenerate-media
php artisan website:regenerate-media --collection=gallery
php artisan website:regenerate-media --collection=featured_image
php artisan website:regenerate-media --project=<id>
```

**Ejecutar en producción después del deploy** para que el API empiece a servir URLs WebP reales.

---

## Arquitectura del módulo

```
Modules/Website/
├── App/
│   ├── Enums/ProjectCategory.php   (SPORT, INDUSTRIAL, PUBLIC)
│   ├── Http/                        (legacy, no rutas activas)
│   └── Observers/                   (ConsultationRequestObserver)
├── Console/
│   └── RegenerateProjectMediaCommand.php   ← creado en WEB-007
├── Contracts/
│   ├── ProjectRepositoryInterface.php
│   └── MessageRepositoryInterface.php
├── DTOs/
│   ├── ProjectData.php
│   └── MessageData.php
├── Http/Controllers/
│   ├── ProjectController.php    ← rutas activas (usa PortfolioService)
│   ├── WebsiteController.php    ← usa repositorio (sin rutas activas hoy)
│   └── ConsultationController.php
├── Jobs/
│   └── NotifyAstroFrontendJob.php   ← corregido WEB-001
├── Models/
│   ├── Project.php              ← corregido WEB-005/006
│   ├── Message.php
│   └── ConsultationRequest.php  (+ Activity, Reminder, Notification)
├── Observers/
│   ├── ProjectObserver.php      (dispara el webhook)
│   └── MediaObserver.php        (dispara el webhook en cambios de media)
├── Providers/
│   └── WebsiteServiceProvider.php   ← registra command WEB-007
├── Repositories/
│   └── EloquentProjectRepository.php   ← corregido WEB-002
├── Routes/
│   └── api.php                  (prefix: v1/website)
└── Services/
    ├── WebsiteService.php
    ├── PortfolioService.php      ← servicio activo para proyectos
    └── ConsultationService.php
```

### Rutas activas

```
GET  /v1/website/            → status
GET  /v1/website/projects    → listado paginado (filtros: category, year, featured)
GET  /v1/website/projects/categories
GET  /v1/website/projects/years
GET  /v1/website/projects/{slug}
POST /v1/website/consultations
```

---

## Variables de entorno relevantes

```env
GITHUB_ACTION_WEBHOOK_URL=https://api.github.com/repos/cubanote816/website-claesen-v1/dispatches
GITHUB_ACTION_TOKEN=<token con scope repo + workflow>
IMAGE_DRIVER=imagick
QUEUE_CONNECTION=sync   # cambiar a redis en producción para no bloquear requests
MEDIA_DISK=public
```

---

## Dependencias externas

| Sistema | Propósito |
|---------|-----------|
| `cubanote816/website-claesen-v1` | Repo frontend Astro, consume la API |
| GitHub Actions `deploy.yml` | CI/CD: sync → build → SFTP upload |
| Servidor SFTP del hosting | Destino final del frontend compilado |
| `spatie/laravel-medialibrary ^11` | Gestión de media + conversiones WebP |
| `imagick` (PHP extension) | Motor de conversión de imágenes |

---

## Checklist de deploy a producción

- [ ] Ejecutar `php artisan website:regenerate-media` (backfill WebP)
- [ ] Verificar que `QUEUE_CONNECTION` procesa las conversiones (sync OK en dev, redis en prod)
- [ ] Confirmar que las URLs en `api_gallery` apuntan a archivos `.webp` reales en disco
- [ ] Push del `deploy.yml` corregido al repo frontend (requiere scope `workflow` en token)
- [ ] Trigger manual del Action en GitHub para verificar que el deploy corre sin errores de quota ni path duplication
