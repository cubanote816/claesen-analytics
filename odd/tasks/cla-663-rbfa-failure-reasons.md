# CLA-663: el sync RBFA guarda el motivo por el que falla cada serie

## Specs

- S1: A discovery response that is not JSON, or JSON without `data.seriesRankings`, is a series failure whose reason includes the HTTP status, the content type and the first ~200 characters of the body with HTML tags stripped, entities decoded and whitespace collapsed. Example: an HTML Access Denied page with 200 yields a reason like `HTTP 200 text/html: Access Denied You don't have permission to access "http://datalake-prod2018.rbfa.be/graphql" on this server. Reference #18.…`. A JSON body with GraphQL `errors` includes the first error message (e.g. `PersistedQueryNotFound`).
- S2: An exception (RequestException after retries, ConnectionException) is a series failure whose reason is the exception class short name plus its message, truncated to ~200 chars.
- S3: When every series fails, the command's sync history `logs` still contains the failure reasons, grouped as distinct reasons with counts (e.g. `❌ 140 series failed: HTTP 200 text/html: Access Denied …`), before the existing "All RBFA discovery series failed." error; status stays `failed` and the exit code stays FAILURE.
- S4: When every series fails, exactly one `Log::warning` is written with the distinct reasons and counts (no per-series spam).
- S5: The partial-failure path keeps working and uses the new reasons; successful runs and all data writes (ClubPersister, reconcile, fill-only) are unchanged.
- S6: club-info enrichment failures with non-JSON bodies get the same reason format in whatever it reports today (do not change whether enrichment failure is fatal).
- The reason is free of secrets (no request headers are logged).

## Tasks

| # | Task | Estado | Commit |
|---|------|--------|--------|
| T1 | RED: tests por S# en `RbfaGraphQLSourceTest` y `SyncRbfaGraphqlCommandTest` | hecho | c9dfeccc |
| T2 | GREEN: `RbfaGraphqlSource` calcula el motivo; el comando agrupa y registra | hecho | c9dfeccc |
| T3 | Docs: runbook + handoff | hecho | c9dfeccc |
| T4 | Suite `Modules/Prospects/tests` (165 passed) + `git diff --check` | hecho | c9dfeccc |

## Log

- L1: Diagnóstico aprobado por el usuario: «Sí, en un ticket aparte» — que el sync marque una respuesta HTML o no JSON como error y guarde el código HTTP y el inicio de la respuesta de cada serie.
