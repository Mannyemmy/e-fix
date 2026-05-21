<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferralPermissionSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            ['id' => 141, 'name' => 'referral-setting', 'guard_name' => 'web', 'parent_id' => null],
            ['id' => 142, 'name' => 'referral', 'guard_name' => 'web', 'parent_id' => null],
            ['id' => 143, 'name' => 'referral list', 'guard_name' => 'web', 'parent_id' => 142],
        ];

        foreach ($permissions as $perm) {
            $existing = DB::table('permissions')->where('id', $perm['id'])->orWhere(function ($q) use ($perm) {
                $q->where('name', $perm['name'])->where('guard_name', $perm['guard_name']);
            })->first();

            if (!$existing) {
                DB::table('permissions')->insert($perm);
            }
        }

        $adminRole = DB::table('roles')->where('name', 'admin')->value('id');
        $demoAdminRole = DB::table('roles')->where('name', 'demo_admin')->value('id');

        $newPermissionIds = [141, 142, 143];

        foreach ($newPermissionIds as $pid) {
            foreach ([$adminRole, $demoAdminRole] as $rid) {
                if ($rid) {
                    $exists = DB::table('role_has_permissions')
                        ->where('permission_id', $pid)
                        ->where('role_id', $rid)
                        ->exists();

                    if (!$exists) {
                        DB::table('role_has_permissions')->insert([
                            'permission_id' => $pid,
                            'role_id' => $rid,
                        ]);
                    }
                }
            }
        }

        $this->command->info('Referral permissions inserted and assigned to admin/demo_admin roles.');
    }
}
