Implementation Plan: Core Infrastructure & Async RAG
Goal Description
Implement the core architecture for a BI RAG system using Laravel 12 and Filament V5, focusing on async processing, legacy data integrity, and cost efficiency.

User Review Required
IMPORTANT

This plan follows the strict specific requirements for Legacy Read-Only access and Filament V5 schemas.

Proposed Changes
Core Infrastructure (Step 1)
[NEW]
ReadOnlyTrait.php
Strictly blocks
save()
,
update()
,
delete()
,
create()
.
Throws LogicException on write attempts.
[NEW]
CafcaModel.php
Abstract base class for legacy models.
Connection: sqlsrv.
Integrity Fix: Automatic trim() for string attributes.
Strict Types: incrementing = false, keyType = 'string'.
Domain: Project Intelligence (Step 2)
[NEW]
Project.php
Legacy Read-Only Model.
Relation: hasOne(ProjectInsight::class).
[NEW]
ProjectInsight.php
Local MySQL table.
Stores efficiency_score, ai_summary, last_data_hash.
[NEW]
ProjectAiPayload.php
Data Hygiene DTO.
Sanitizes input (removes nulls).
Generates semantic hash.
[NEW]
GeminiService.php
Transport layer for API.
[NEW]
AuditProjectJob.php
Batchable Job.
Checks semantic hash before calling API.
[NEW]
CafcaSyncService.php
Orchestrator for batch auditing.
Employee Module (Corporate Directory)
[NEW]
Employee.php
Connection: sqlsrv (Legacy).
Read-Only (ReadOnlyTrait).
Timestamps disabled.
[NEW]
EmployeeResource.php
Infolist: "Business Card" layout (Name, Mobile, Email, Address).
Table: Name (with job function), Combined Address, Toggleable Contact/Status columns.
Strict V5 Standards: Use Schema, avoid Form.
Filament V5 UI (Step 3)
[NEW]
ProjectInsightResource.php
Uses Filament\Schemas\Schema.
Polling for updates.
Verification Plan
Automated Tests
Unit tests for ReadOnlyTrait and
CafcaModel
trim logic.
Feature tests for AuditProjectJob hash logic.
Manual Verification
Check Filament UI for polling and correct data display.
