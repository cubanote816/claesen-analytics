<?php

use Illuminate\Support\Facades\Route;

/*
 * KNX installation API — the contract consumed by Kantoor (office) and Veld
 * (field). See docs/BACKEND-API.md and docs/BACKEND-API-ZONES.md in the
 * electro-bertels-kantoor repo.
 *
 * Everything is prefixed /api/v1/knx (this provider's prefix plus the v1/knx
 * below) and, except the login route, behind both `auth:sanctum` and
 * `organization:electro-bertels` so no Claesen token can reach it.
 *
 * K0 (CLA-604) only wires the group; the endpoints land in K1-K9.
 */

Route::prefix('v1/knx')->name('knx.')->group(function (): void {
    // K1 will add: POST auth/login, POST auth/refresh, POST auth/logout.

    Route::middleware(['auth:sanctum', 'organization:electro-bertels'])->group(function (): void {
        // K1: GET me/session
        // K2: clients, projects (+stats)
        // K3: projects/{code}/devices|activity
        // K4: notifications (+ack)
        // K5: technicians, planning
        // K6: conflicts
        // K7: zones
        // K8: documents, reports
        // K9: functions, tests
    });
});
