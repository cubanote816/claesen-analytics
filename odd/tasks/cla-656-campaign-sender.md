# CLA-656 — Dedicated campaign sender and safe bounce parsing

Branch `fix/cla-656-campaign-sender` (worktree `.worktrees/cla-656`, from `origin/main` c19d692).
Context: `mailing:parse-bounces` fails every 30 min with Graph 404 because `MAILING_NDR_INBOX` defaults to `bounces@claesen-verlichting.be`, which does not exist. NDRs return to the SENDER mailbox (Graph cannot set a Return-Path), today `offerte@`, a customer-facing mailbox. The shared mailbox `sport@claesen-verlichting.be` (display "Bert Bertels (Claesen Outdoor Lighting)", hidden from the address list) was created on 2026-11-06 as the dedicated campaign sender. Read access for the app on `sport@` (Exchange RBAC for Applications) waits for a management decision; this ticket is code only.

## Specs

- S1: Campaign emails (`ProspectCampaignMail`) are sent From `mailing.from_address` / `mailing.from_name`, configured by `MAILING_FROM_ADDRESS` / `MAILING_FROM_NAME`, which fall back to `mail.from.address` / `mail.from.name` when unset, so today's behaviour is unchanged until production sets them. Every other application email keeps its current sender.
- S2: Campaign emails carry a Reply-To from `mailing.reply_to` (`MAILING_REPLY_TO`), falling back to `mail.from.address`, so human replies reach a monitored mailbox and not the bounce mailbox.
- S3: `mailing.ndr_inbox` (`MAILING_NDR_INBOX`) falls back to the campaign sender `mailing.from_address`, because bounces return to the sender.
- S4: `mailing:parse-bounces` marks a message as read ONLY when it is recognised as a bounce (an address could be extracted / it is classified as an NDR). Non-bounce messages stay unread and untouched, so pointing the parser at a mailbox people read never hides their mail. Behaviour for recognised bounces is unchanged.
- S5: The Graph transport sends from the From address of the message (already the case); a test pins that a campaign is posted to `users/{mailing.from_address}/sendMail` with the configured Reply-To in the payload.
- S6: Existing behaviour pinned: other mails' senders, BounceParser and NDR correlation tests, List-Unsubscribe headers.

## Tasks

| ID | Specs | Route | Commit |
|----|-------|-------|--------|
| T1 | S1, S2, S3, S5 | worker, test-first | implemented, uncommitted |
| T2 | S4, S6 | worker, test-first | implemented, uncommitted |
| T3 | docs (production-readiness env vars, handoff, CLAUDE.md) | parent | — |

## Log

- L1 (user, 2026-11-06): "vamos a implementarlo siempre que al sincronizarlos la proxima vez no se pisen los cambios CLA-654 (espera tu GO) y los bounces con 404."
- L2 (user, 2026-11-06): "ok continuamos con la implemantacion mientra que la gerencia decide" (management decides on Exchange read access; code proceeds).
- L3: Production facts (read-only server audit 2026-11-06): `mail.from.address` = `mailing.from_address` = offerte@claesen-verlichting.be; `mailing.ndr_inbox` = bounces@ (config default, mailbox does not exist: Graph 404 ErrorInvalidUser); app has only Mail.Send. The Reply-To recipient (Bert Bertels or offerte@) is a management decision, hence configurable.
- L4 (worker): T1+T2 implemented test-first (CampaignSenderTest, NdrCorrelationTest); module suite run, see handoff.
- L5 (S4 amended): a message that is not recognised as a bounce is no longer just left in place. It is MOVED, still unread, to the `mailing.ndr_unprocessed_folder` folder (`MAILING_NDR_UNPROCESSED_FOLDER`, default `Mailing - not processed`; created if missing, resolved once per run) and logged at info level. This stops non-bounce mail (e.g. out-of-office replies) from filling the unread batch and starving real NDRs. A move failure is logged and the run continues. Recognised bounces are unchanged. Needs Mail.ReadWrite on the mailbox.
