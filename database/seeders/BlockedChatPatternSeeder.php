<?php

namespace Database\Seeders;

use App\Models\BlockedChatPattern;
use Illuminate\Database\Seeder;

class BlockedChatPatternSeeder extends Seeder
{
    public function run()
    {
        $patterns = [
            [
                'pattern' => 'give me your number',
                'pattern_type' => 'keyword',
                'description' => 'Prevents users from asking for phone numbers',
                'is_active' => true,
                'is_regex' => false,
            ],
            [
                'pattern' => 'send me your number',
                'pattern_type' => 'keyword',
                'description' => 'Prevents users from asking for phone numbers',
                'is_active' => true,
                'is_regex' => false,
            ],
            [
                'pattern' => 'my number is',
                'pattern_type' => 'keyword',
                'description' => 'Prevents sharing phone numbers',
                'is_active' => true,
                'is_regex' => false,
            ],
            [
                'pattern' => 'call me on',
                'pattern_type' => 'keyword',
                'description' => 'Prevents off-app contact sharing',
                'is_active' => true,
                'is_regex' => false,
            ],
            [
                'pattern' => 'whatsapp me',
                'pattern_type' => 'keyword',
                'description' => 'Prevents redirecting to WhatsApp',
                'is_active' => true,
                'is_regex' => false,
            ],
            [
                'pattern' => 'text me at',
                'pattern_type' => 'keyword',
                'description' => 'Prevents off-app contact sharing',
                'is_active' => true,
                'is_regex' => false,
            ],
            [
                'pattern' => 'contact me outside',
                'pattern_type' => 'keyword',
                'description' => 'Prevents users from going off-platform',
                'is_active' => true,
                'is_regex' => false,
            ],
            [
                'pattern' => 'pay me directly',
                'pattern_type' => 'keyword',
                'description' => 'Prevents off-platform payment solicitation',
                'is_active' => true,
                'is_regex' => false,
            ],
            [
                'pattern' => 'pay outside',
                'pattern_type' => 'keyword',
                'description' => 'Prevents off-platform payment solicitation',
                'is_active' => true,
                'is_regex' => false,
            ],
            [
                'pattern' => '\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b',
                'pattern_type' => 'regex',
                'description' => 'Blocks US/CA phone number format (XXX-XXX-XXXX)',
                'is_active' => true,
                'is_regex' => true,
            ],
            [
                'pattern' => '\+\d{1,3}\s?\d{6,14}',
                'pattern_type' => 'regex',
                'description' => 'Blocks international phone numbers with country code',
                'is_active' => true,
                'is_regex' => true,
            ],
        ];

        foreach ($patterns as $pattern) {
            BlockedChatPattern::firstOrCreate(
                ['pattern' => $pattern['pattern']],
                $pattern
            );
        }
    }
}
