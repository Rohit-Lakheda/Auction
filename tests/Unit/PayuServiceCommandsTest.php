<?php

namespace Tests\Unit;

use App\Services\PayuService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayuServiceCommandsTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('PAYU_MERCHANT_KEY');
        putenv('PAYU_SALT');
        putenv('PAYU_MODE');

        parent::tearDown();
    }

    public function test_hash_for_command_matches_payu_formula(): void
    {
        putenv('PAYU_MERCHANT_KEY=key1');
        putenv('PAYU_SALT=salt1');

        $service = new PayuService;

        $expected = strtolower(hash('sha512', 'key1|cancel_transaction|payu123|salt1'));
        $this->assertSame($expected, $service->hashForCommand('cancel_transaction', 'payu123'));
    }

    public function test_cancel_transaction_posts_to_postservice_and_parses_success_json(): void
    {
        putenv('PAYU_MERCHANT_KEY=key1');
        putenv('PAYU_SALT=salt1');
        putenv('PAYU_MODE=test');

        Http::fake([
            'test.payu.in/*' => Http::response(
                '{"status":1,"msg":"Cancelled Request Queued","txn_update_id":"x"}',
                200,
                ['Content-Type' => 'application/json']
            ),
        ]);

        $service = new PayuService;
        $result = $service->cancelTransaction('payu-id-1', 'merchant-txn-1');

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['status']);

        Http::assertSent(function (Request $request): bool {
            return str_contains((string) $request->url(), 'test.payu.in/merchant/postservice.php')
                && $request['command'] === 'cancel_transaction'
                && $request['var1'] === 'payu-id-1'
                && $request['var2'] === 'merchant-txn-1';
        });
    }

    public function test_capture_transaction_posts_var3_amount(): void
    {
        putenv('PAYU_MERCHANT_KEY=key1');
        putenv('PAYU_SALT=salt1');
        putenv('PAYU_MODE=test');

        Http::fake([
            '*' => Http::response('{"status":1,"msg":"Capture Request Queued"}', 200),
        ]);

        $service = new PayuService;
        $result = $service->captureTransaction('15246574846', 'authorizeTransaction123', '99.50');

        $this->assertTrue($result['success']);

        Http::assertSent(function (Request $request): bool {
            return $request['command'] === 'capture_transaction'
                && $request['var3'] === '99.50';
        });
    }
}
