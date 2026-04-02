Employee Synchronization Walkthrough
This document outlines the implementation of the "Smart Sync" system for technician data between the legacy SQL Server (SAP/ERP) and the local MySQL database.

🚀 Key Features
Manual Sync: A new button "Sincronizar SAP/ERP" in the Employee list.
Automated Sync: A daily cron job (scheduled task) configured at 04:00 AM.
Smart Logic: Uses the ts_modif timestamp from SQL Server to only pull records that have changed, minimizing database load.
Dutch Localization: All notifications and labels are in Dutch (NL).
🛠️ Components

1. Synchronization Service
   The logic resides in App\Services\Cafca\EmployeeSyncService. It maps field segments (Contact, Address, Employment Dates) and performs an updateOrCreate operation.

2. Header Action in Filament
   The
   ListEmployees
   page now includes a dedicated button:

Action::make('sync')
->label(\_\_('employees/resource.actions.sync.label'))
->icon('heroicon-m-arrow-path')
... 3. Console Command & Schedule
You can trigger the sync manually from the terminal inside Sail:

./vendor/bin/sail artisan app:sync-employees
The schedule is registered in
bootstrap/app.php
:

->withSchedule(function (Schedule $schedule) {
$schedule->command('app:sync-employees')->dailyAt('04:00');
})
✅ Verification Results
First Run: Discovered and created 52 technician records in MySQL.
Subsequent Runs: Identified that 0 records were modified, correctly skipping updates.
UI Feedback: Filament displays a notification: "Synchronisatie voltooid: X nieuw, Y bijgewerkt."

---

This document outlines the implementation of the technician management system, featuring "Smart Sync" from SAP/ERP and professional avatar management.

🚀 Key Features
Smart Synchronization: Pulls technician data from legacy SQL Server into local MySQL.
Delta-Sync: Uses ts_modif timestamp to only process changed records.
Automated: Scheduled to run daily at 04:00 AM.
Manual: Button available in the Employee list UI.
MediaLibrary Integration: Uses spatie/laravel-medialibrary for professional asset management.
Supports image editing, optimization, and flexible storage.
Dutch (NL) Localization: Complete UI and notification support in Dutch.
🛠️ Implementation Details

1. Synchronization Service (
   EmployeeSyncService
   )
   Correctly handles complex mapping from SQL Server (SAP/ERP):

Identity: Maps
id
, name.
Function: Maps legacy functie.
Birth Date: Maps legacy birthday.
Contact: Prioritizes mobile, falls back to tel.
Employment: Maps in_dienst and uit_dienst. 2. Media Management
We transitioned from a basic avatar_path column to a dedicated Media Library:

Model:
Employee.php
implements HasMedia.
UI:
EmployeeForm.php
uses SpatieMediaLibraryFileUpload. 3. Filament UI Integration
The
ListEmployees
page features the sync action:

Action::make('sync')
->label(\_\_('employees/resource.actions.sync.label'))
->icon('heroicon-m-arrow-path')
->color('info')
...
✅ Verification Results
Data Accuracy: Verified via Tinker that birth_date and mobile are pulled correctly from production SQL Server.
Performance: Smart sync correctly identifies changed records (52 created initially, 0 updated on subsequent runs if no changes exist in source).
Media Persistence: Avatars are correctly associated with records via the media table and displayed in the UI.
Terminal Commands
To trigger manual sync:

./vendor/bin/sail artisan app:sync-employees
