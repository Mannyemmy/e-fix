<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add user_login notification type constant (idempotent)
        DB::table('constants')->insertOrIgnore([
            'type'  => 'notification_type',
            'value' => 'user_login',
            'name'  => 'User Login',
        ]);

        // Improve register template subject if it still has the old generic value
        $registerTemplate = DB::table('notification_templates')
            ->where('type', 'register')
            ->whereNull('deleted_at')
            ->first();

        if ($registerTemplate) {
            DB::table('notification_template_content_mapping')
                ->where('template_id', $registerTemplate->id)
                ->where('language', 'en')
                ->where('subject', 'Register') // only update if still the default
                ->update([
                    'subject'              => 'Welcome to {{ company_name }}!',
                    'notification_message' => 'Welcome! Your registration was successful.',
                    'template_detail'      => '
            <p>Dear [[ user_name ]],</p>
            <p>&nbsp;</p>
            <p>Welcome to <strong>[[ company_name ]]</strong>! We are thrilled to have you on board.</p>
            <p>&nbsp;</p>
            <p>Your registration was successful. You can now sign in and start exploring our services.</p>
            <p>&nbsp;</p>
            <p>If you have any questions or need assistance, our support team is always here to help.</p>
            <p>&nbsp;</p>
            <p>Best regards,</p>
            <p>[[ logged_in_user_fullname ]],</p>
            <p>[[ logged_in_user_role ]],</p>
            <p>[[ company_name ]]</p>
            <p>[[ company_contact_info ]]</p>
            ',
                ]);
        }

        // Create user_login notification template if it does not exist
        $exists = DB::table('notification_templates')
            ->where('type', 'user_login')
            ->whereNull('deleted_at')
            ->exists();

        if (!$exists) {
            $templateId = DB::table('notification_templates')->insertGetId([
                'type'       => 'user_login',
                'name'       => 'user_login',
                'label'      => 'Login Notification',
                'status'     => 1,
                'to'         => '["user","provider","handyman"]',
                'channels'   => json_encode(['IS_MAIL' => '1', 'PUSH_NOTIFICATION' => '0']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('notification_template_content_mapping')->insert([
                'template_id'          => $templateId,
                'language'             => 'en',
                'notification_link'    => '',
                'notification_message' => 'New sign-in to your account.',
                'status'               => 1,
                'subject'              => 'New Sign-In to Your [[ company_name ]] Account',
                'template_detail'      => '
            <p>Hi [[ user_name ]],</p>
            <p>&nbsp;</p>
            <p>We noticed a new sign-in to your <strong>[[ company_name ]]</strong> account on <strong>[[ datetime ]]</strong>.</p>
            <p>&nbsp;</p>
            <p>If this was you, no further action is needed.</p>
            <p>&nbsp;</p>
            <p>If you did not sign in, please contact our support team immediately and change your password.</p>
            <p>&nbsp;</p>
            <p>Best regards,</p>
            <p>[[ logged_in_user_fullname ]],</p>
            <p>[[ logged_in_user_role ]],</p>
            <p>[[ company_name ]]</p>
            <p>[[ company_contact_info ]]</p>
            ',
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        }
    }

    public function down(): void
    {
        $template = DB::table('notification_templates')
            ->where('type', 'user_login')
            ->first();

        if ($template) {
            DB::table('notification_template_content_mapping')
                ->where('template_id', $template->id)
                ->delete();
            DB::table('notification_templates')
                ->where('id', $template->id)
                ->delete();
        }

        DB::table('constants')
            ->where('type', 'notification_type')
            ->where('value', 'user_login')
            ->delete();
    }
};
