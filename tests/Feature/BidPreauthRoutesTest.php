<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BidPreauthRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_bid_preauth_failure_route_redirects_without_error_when_no_hold(): void
    {
        $response = $this->call('POST', '/payu/bid-preauth/failure', [
            'txnid' => 'UNKNOWN_TXN',
            'udf1' => 'BID_PREAUTH_99',
            'error_Message' => 'User cancelled',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('bid_error');
    }
}
