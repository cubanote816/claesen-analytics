Walkthrough - Role Management & Employee Module
Overview
This update implements a robust Role-Based Access Control (RBAC) system using spatie/laravel-permission and refines the Employee module with proper address synchronization and UI improvements. This ensures security and data completeness.

1. Role Management System (RBAC)
   We implemented a Native Filament V5 solution for roles, avoiding fragile wrapper packages like filament-shield while keeping the powerful spatie/laravel-permission engine.

Changes Implemented
Engine: Installed spatie/laravel-permission.
User Model: Added HasRoles trait to App\Models\User.
Role Resource: Created a custom
RoleResource
to manage Roles and Permissions directly from the admin panel.
Role Policy: Implemented a strict
RolePolicy
that only allows users with the admin role to manage roles.
Permissions: Seeded initial permissions: view_employees, update_employees, manage_roles.
How to Use
Go to Settings > Roles.
Create a new role (e.g., Manager).
Assign permissions using the Checkbox List.
Assign the role to a user (via Database/Tinker for now, or build a UserResource next).
Code Highlight: Role Policy
public function viewAny(User $user): bool
{
return $user->hasRole('admin');
} 2. Employee Module Enhancements
Address Synchronization Fix
The "Address" column was empty because the local database lacked specific address fields.

Schema Update: Added street, zip, city, country columns to employees table.
Sync Logic: Updated
EmployeeDTO
and
CafcaSyncService
to map and upsert these fields individually from the legacy system.
UI Improvements
Default Avatar: Added a fallback image for employees without a photo.
Status Column: Fixed the status display by mapping the correct model attribute (is_active).
Verification
Role Management
Log in as Admin (User ID 1).
Navigate to Settings > Roles.
Edit the admin role.
Verify you can toggle permissions.
Employee Sync
Go to Employees.
Click Sync Employees.
Verify that Address column now populates with data (e.g., "Street, Zip, City, Country").
