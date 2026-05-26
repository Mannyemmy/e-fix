<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\ReferralCode;
use App\Models\ReferredUser;

class SimulateReferral extends Command
{
    protected $signature = 'referral:simulate {referrer_email?} {referred_email?}';
    protected $description = 'Simulate a referral by creating a ReferredUser record between two users';

    public function handle()
    {
        $referrerEmail = $this->argument('referrer_email') ?? $this->ask('Enter referrer email', 'efeogheneotega9@gmail.com');
        $referredEmail = $this->argument('referred_email') ?? $this->ask('Enter referred user email');

        $referrer = User::where('email', $referrerEmail)->first();
        if (!$referrer) {
            $this->error("Referrer not found: $referrerEmail");
            return 1;
        }

        $referred = User::where('email', $referredEmail)->first();
        if (!$referred) {
            $this->error("Referred user not found: $referredEmail");
            return 1;
        }

        if ($referrer->id === $referred->id) {
            $this->error('Cannot refer yourself');
            return 1;
        }

        $refCode = ReferralCode::firstOrCreate(
            ['user_id' => $referrer->id],
            ['code' => strtoupper(($referrer->first_name ?: 'user') . '-' . substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 4))]
        );

        $existing = ReferredUser::where('referrer_id', $referrer->id)
            ->where('referred_user_id', $referred->id)
            ->exists();

        if ($existing) {
            $this->warn("Referral already exists between {$referrer->email} and {$referred->email}");
            return 0;
        }

        ReferredUser::create([
            'referrer_id' => $referrer->id,
            'referred_user_id' => $referred->id,
            'referral_code' => $refCode->code,
        ]);

        $refCode->increment('total_referred');

        $this->info("Referral created successfully!");
        $this->table(
            ['', 'ID', 'Name', 'Email'],
            [
                ['Referrer', $referrer->id, $referrer->display_name, $referrer->email],
                ['Referred', $referred->id, $referred->display_name, $referred->email],
            ]
        );
        $this->line("Referral code used: {$refCode->code}");

        return 0;
    }
}
