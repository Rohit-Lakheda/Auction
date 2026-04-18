<?php

return [
    /*
    | Bid placement uses PayU hosted pre-authorization when true (requires MID pre-auth enabled).
    */
    'bid_preauth_enabled' => filter_var(env('PAYU_BID_PREAUTH_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
];
