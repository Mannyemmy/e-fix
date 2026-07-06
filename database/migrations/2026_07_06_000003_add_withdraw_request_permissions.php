<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddWithdrawRequestPermissions extends Migration
{
    /**
     * Adds the permissions needed to gate the new "Withdraw Requests"
     * admin menu/resource, following the same shape used for the
     * 'referral' / 'referral list' permission pair (see
     * ReferralPermissionSeeder): a parent permission plus a child
     * "<name> list" permission, assigned to the admin/demo_admin roles.
     */
    public function up()
    {
        $permissions = [
            ['name' => 'withdraw request', 'guard_name' => 'web', 'parent_id' => null],
            ['name' => 'withdraw request list', 'guard_name' => 'web', 'parent_id' => null],
            ['name' => 'withdraw request approve', 'guard_name' => 'web', 'parent_id' => null],
            ['name' => 'withdraw request reject', 'guard_name' => 'web', 'parent_id' => null],
        ];

        $now = now();
        $parentId = null;
        $insertedIds = [];

        foreach ($permissions as $perm) {
            $existing = DB::table('permissions')
                ->where('name', $perm['name'])
                ->where('guard_name', $perm['guard_name'])
                ->first();

            if ($existing) {
                $id = $existing->id;
            } else {
                $id = DB::table('permissions')->insertGetId([
                    'name' => $perm['name'],
                    'guard_name' => $perm['guard_name'],
                    'parent_id' => $perm['name'] === 'withdraw request' ? null : $parentId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($perm['name'] === 'withdraw request') {
                $parentId = $id;
            }

            $insertedIds[] = $id;
        }

        // Second pass to make sure the child permissions point at the
        // parent's id even if the parent row already existed and was
        // only resolved after some children were inserted above.
        DB::table('permissions')
            ->whereIn('name', ['withdraw request list', 'withdraw request approve', 'withdraw request reject'])
            ->where('guard_name', 'web')
            ->update(['parent_id' => $parentId]);

        $roleIds = DB::table('roles')
            ->whereIn('name', ['admin', 'demo_admin'])
            ->pluck('id', 'name');

        foreach ($roleIds as $roleId) {
            foreach ($insertedIds as $permissionId) {
                $exists = DB::table('role_has_permissions')
                    ->where('permission_id', $permissionId)
                    ->where('role_id', $roleId)
                    ->exists();

                if (!$exists) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ]);
                }
            }
        }
    }

    public function down()
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['withdraw request', 'withdraw request list', 'withdraw request approve', 'withdraw request reject'])
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
}
