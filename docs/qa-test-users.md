# Usuarios de prueba (QA) — local y testing

> **Solo local/testing.** Estas cuentas las crea `core:qa-reset-environment`, un comando que se
> niega a ejecutarse fuera de los entornos `local`/`testing`. Las contraseñas están en el propio
> código del comando (`Modules/Core/Console/Commands/QaResetEnvironmentCommand.php`) — no son
> secretos reales y **nunca** deben existir en staging ni en producción.
> Si el código y este documento se contradicen, manda el código.

## Cómo se crean

```bash
php artisan migrate:fresh --force
php artisan core:qa-reset-environment --skip-cafca-sync
```

- `--skip-cafca-sync` es para cuando **no hay acceso al SQL Server del ERP** (fuera de la LAN de
  oficina). Con el flag se omite el sync de Cafca y, por tanto, no se crea `qa.cliente@…`
  (depende de que exista un `FoClient`).
- `migrate:fresh` **borra las sesiones**.
- El comando asume una BD recién migrada: el seeder base **no es idempotente**. Si falla con
  `Duplicate entry 'bert.Bertels@claesen-verlichting.be'`, es que la BD ya tenía datos → vuelve a
  hacer `migrate:fresh`.

## Las cuentas

| Email | Contraseña | Organización | Rol | Backoffice `admin` | Backoffice `bertels` |
|---|---|---|---|---|---|
| `qa.backoffice@claesen-verlichting.test` | `QaBackoffice123!` | Claesen Verlichting | `super_admin` | ✅ | ✅ |
| `qa.bertels@electro-bertels.test` | `QaBertels123!` | **Electro Bertels** | `super_admin` | ✅ | ✅ |
| `qa.tecnico@claesen-verlichting.test` | `QaTecnico123!` | Claesen Verlichting | `technician` | ⚠️ solo *My Work Orders* | ❌ |
| `qa.cliente@claesen-verlichting.test` | `QaCliente123!` | Claesen Verlichting | `client` | ❌ | ❌ |
| `qa.cliente2@claesen-verlichting.test` | `QaCliente2123!` | Claesen Verlichting | `client` | ❌ | ❌ |

`qa.cliente2` queda vinculado al cliente de aislamiento "QA Tenant B Sportclub"; `qa.cliente`
necesita el sync de Cafca para existir (sin él, el comando avisa y lo omite).

## MFA — el código llega a Mailpit, no al correo real

`super_admin`/`admin` tienen MFA obligatorio: en el primer login el panel pide configurarlo. Elige
el proveedor **por email** y **el código aparece en Mailpit** (<http://localhost:8027>). No hace
falta app de autenticación.

> ⚠️ **Gotcha que cuesta media hora:** el código solo llega si el mailer por defecto apunta a
> Mailpit. Un `.env` copiado de un checkout que apunta al tenant real trae
> `MAIL_MAILER=microsoft-graph`, y entonces **nada** aparece en Mailpit (ni MFA, ni reset de
> contraseña, ni los correos de Website/Mailing). Para local:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
```

Después: `php artisan config:clear` (el contenedor de Sail suele reiniciarse solo al detectar el
cambio de `.env`).

## URLs del stack aislado (CLA-603 / multiorg)

| Qué | URL |
|---|---|
| Backoffice Claesen (`admin`) | <http://localhost:8001> |
| Backoffice Electro Bertels (`bertels`) | <http://localhost:8001/bertels> |
| Mailpit | <http://localhost:8027> |
| MySQL | `localhost:3309` |

Puertos a propósito distintos de los del checkout principal (8000/3308) para no chocar.

## Límites conocidos (no son bugs de estas cuentas)

1. **El panel `bertels` exige `super_admin`.** `User::canAccessPanel()` no admite ningún otro rol
   ahí hasta P6 (ADR D10, "regla de hierro"). Por eso el usuario QA de Bertels es `super_admin`:
   con `admin` o `viewer` no entraría *en absoluto*, y parecería roto en vez de restringido.
2. **Con `organizations.enforce=false` (el default) los dos `super_admin` entran en los dos
   paneles.** La separación real "un usuario de Bertels solo ve Bertels" llega en P5/P7.
3. **Rate limiting del flujo de contraseña:** la solicitud de reset son 2 intentos por minuto por
   IP+componente y el reset 2 por email (`rateLimit(2)` de Filament, no una decisión propia). Si
   repites el flujo a mano muy seguido, "no cambia de pantalla" — es correcto. `php artisan
   cache:clear` entre pasadas.
4. Los 3 usuarios demo del seeder base (`bert.Bertels@`, `bert.kenis@`, `orelvys.cuellar@` con
   `@claesen-verlichting.be`, contraseña `password`) **no tienen rol**, así que no pueden entrar a
   ningún panel.

## Relacionado

- `docs/auth-role-app-login-matrix.md` — qué rol entra a qué app (fuente de verdad).
- `docs/ai/commands-runbook.md` — el comando QA y el resto de comandos Artisan.
- Tickets: CLA-603 (usuario QA de Bertels), CLA-331/CLA-330 (aislamiento de tenant), CLA-363 (matriz de login).
