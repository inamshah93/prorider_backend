<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'manage-cities',
            'assign-riders',
            'verify-payments',
            'view-analytics',
            'manage-riders',
            'manage-vendors',
            'manage-staff',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $rolePermissions = [
            'SuperAdmin' => $permissions,
            'OperationsManager' => ['manage-cities', 'assign-riders', 'manage-riders', 'manage-vendors', 'view-analytics'],
            'FinanceUser' => ['verify-payments', 'view-analytics'],
            'Merchant' => [],
            'Rider' => [],
            'Customer' => [],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }
    }
}
