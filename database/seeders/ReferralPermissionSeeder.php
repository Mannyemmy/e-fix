<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferralPermissionSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
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
    }
}
