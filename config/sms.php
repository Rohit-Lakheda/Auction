<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bulk SMS (MyToday / bulkpush.mytoday.com)
    |--------------------------------------------------------------------------
    | Set SMS_BULK_ENABLED=true in .env and fill credentials to send real OTPs.
    | When disabled, mobile OTP flows still work locally by returning the OTP in API responses (registration) or flash (debug).
    */

    'enabled' => env('SMS_BULK_ENABLED', false),

    'url' => env('SMS_BULK_URL', 'https://bulkpush.mytoday.com/BulkSms/SingleMsgApi'),

    'feed_id' => env('SMS_BULK_FEED_ID'),

    'username' => env('SMS_BULK_USERNAME'),

    'password' => env('SMS_BULK_PASSWORD'),

    'template_id' => env('SMS_BULK_TEMPLATE_ID'),

    'sender_id' => env('SMS_BULK_SENDER_ID', 'IRINNH'),

    /*
     * DLT: entity id from your registered principal / telemarketer (often required for delivery in India).
     */
    'entity_id' => env('SMS_BULK_ENTITY_ID'),

    /*
     * Optional: only sent when set (e.g. 0 = sync, 1 = async). Leave unset if unsure.
     */
    'async' => env('SMS_BULK_ASYNC'),

    /*
     * Use {otp} placeholder. Newlines are preserved in the SMS body.
     */
    'text_template' => env('SMS_BULK_TEXT_TEMPLATE', "Dear User,\nYour OTP for IRINN Membership Application mobile verification is {otp} This OTP is valid for 10 minutes\nIRINN/NIXI"),

];
