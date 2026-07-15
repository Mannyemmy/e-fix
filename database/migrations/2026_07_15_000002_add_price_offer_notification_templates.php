<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function definitions(): array
    {
        return [
            [
                'type' => 'price_offer_sent',
                'label' => 'Price Offer Sent',
                'to' => '["user"]',
                'message' => 'A provider sent you a price offer in chat.',
                'subject' => 'You have a new price offer',
                'body' => '<p>Dear [[ user_name ]],</p><p>&nbsp;</p><p>You have received a new price offer for your booking. Open the chat to review and respond.</p><p>&nbsp;</p><p>[[ company_name ]]</p><p>[[ company_contact_info ]]</p>',
            ],
            [
                'type' => 'price_offer_countered',
                'label' => 'Price Offer Countered',
                'to' => '["provider"]',
                'message' => 'The customer sent you a counter price offer in chat.',
                'subject' => 'You have a new counter offer',
                'body' => '<p>Hello,</p><p>&nbsp;</p><p>The customer sent a counter price offer for a booking. Open the chat to review and respond.</p><p>&nbsp;</p><p>[[ company_name ]]</p><p>[[ company_contact_info ]]</p>',
            ],
            [
                'type' => 'price_offer_accepted_by_customer',
                'label' => 'Price Offer Accepted By Customer',
                'to' => '["provider"]',
                'message' => 'Your price offer was accepted.',
                'subject' => 'Your price offer was accepted',
                'body' => '<p>Hello,</p><p>&nbsp;</p><p>The customer accepted your price offer. You can now proceed with the booking.</p><p>&nbsp;</p><p>[[ company_name ]]</p><p>[[ company_contact_info ]]</p>',
            ],
            [
                'type' => 'price_offer_accepted_by_provider',
                'label' => 'Price Offer Accepted By Provider',
                'to' => '["user"]',
                'message' => 'The provider accepted your counter offer.',
                'subject' => 'Your counter offer was accepted',
                'body' => '<p>Dear [[ user_name ]],</p><p>&nbsp;</p><p>The provider accepted your counter offer. You can now pay for the booking.</p><p>&nbsp;</p><p>[[ company_name ]]</p><p>[[ company_contact_info ]]</p>',
            ],
            [
                'type' => 'price_offer_declined_by_customer',
                'label' => 'Price Offer Declined By Customer',
                'to' => '["provider"]',
                'message' => 'Your price offer was declined.',
                'subject' => 'Your price offer was declined',
                'body' => '<p>Hello,</p><p>&nbsp;</p><p>The customer declined your price offer. You can send a new offer from the chat.</p><p>&nbsp;</p><p>[[ company_name ]]</p><p>[[ company_contact_info ]]</p>',
            ],
            [
                'type' => 'price_offer_declined_by_provider',
                'label' => 'Price Offer Declined By Provider',
                'to' => '["user"]',
                'message' => 'The provider declined your counter offer.',
                'subject' => 'Your counter offer was declined',
                'body' => '<p>Dear [[ user_name ]],</p><p>&nbsp;</p><p>The provider declined your counter offer. You can send a new offer from the chat.</p><p>&nbsp;</p><p>[[ company_name ]]</p><p>[[ company_contact_info ]]</p>',
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->definitions() as $def) {
            DB::table('constants')->insertOrIgnore([
                'type' => 'notification_type',
                'value' => $def['type'],
                'name' => $def['label'],
            ]);

            $exists = DB::table('notification_templates')
                ->where('type', $def['type'])
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                continue;
            }

            $templateId = DB::table('notification_templates')->insertGetId([
                'type' => $def['type'],
                'name' => $def['type'],
                'label' => $def['label'],
                'status' => 1,
                'to' => $def['to'],
                'channels' => json_encode(['IS_MAIL' => '1', 'PUSH_NOTIFICATION' => '1']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('notification_template_content_mapping')->insert([
                'template_id' => $templateId,
                'language' => 'en',
                'notification_link' => '',
                'notification_message' => $def['message'],
                'status' => 1,
                'subject' => $def['subject'],
                'template_detail' => $def['body'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->definitions() as $def) {
            $template = DB::table('notification_templates')->where('type', $def['type'])->first();

            if ($template) {
                DB::table('notification_template_content_mapping')->where('template_id', $template->id)->delete();
                DB::table('notification_templates')->where('id', $template->id)->delete();
            }

            DB::table('constants')->where('type', 'notification_type')->where('value', $def['type'])->delete();
        }
    }
};
