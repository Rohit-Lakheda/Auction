<?php

namespace App\Http\Controllers;

use App\Services\PayuService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PaymentController extends Controller
{
    public function initiateWalletTopup(Request $request, PayuService $payu)
    {
        $userId = (int) $request->session()->get('user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $amount = (float) $validated['amount'];
        $transactionId = 'WALLET_' . time() . '_' . random_int(1000, 9999);
        DB::table('wallet_topups')->insert([
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = DB::table('users')->where('id', $userId)->first();
        $paymentData = [
            'key' => env('PAYU_MERCHANT_KEY'),
            'txnid' => $transactionId,
            'amount' => number_format($amount, 2, '.', ''),
            'productinfo' => 'Wallet Top-up',
            'firstname' => $user->name ?? 'User',
            'email' => $user->email ?? '',
            'phone' => DB::table('registration')->where('email', $user->email ?? '')->value('mobile') ?? '9999999999',
            'surl' => route('payu.wallet.success'),
            'furl' => route('payu.wallet.failure'),
            'udf1' => 'WALLET_TOPUP',
            'udf2' => (string) $userId,
            'udf3' => $transactionId,
            'udf4' => '',
            'udf5' => '',
        ];
        $paymentData['hash'] = $payu->generateHash($paymentData);

        return view('payments.redirect', ['paymentUrl' => $payu->paymentUrl(), 'paymentData' => $paymentData]);
    }

    public function initiateAuctionPayment(Request $request, int $auctionId, PayuService $payu)
    {
        $userId = (int) $request->session()->get('user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $auction = DB::table('auctions')
            ->where('id', $auctionId)
            ->where('winner_user_id', $userId)
            ->where('status', 'closed')
            ->first();
        if (! $auction) {
            return redirect()->route('user.auctions.index', ['view' => 'won'])->with('error', 'Auction not found or you do not have access.');
        }
        if (($auction->payment_status ?? 'pending') === 'paid') {
            return redirect()->route('user.auctions.index', ['view' => 'won'])->with('error', 'This auction is already paid.');
        }

        $lockedEmd = (float) (DB::table('auction_participants')
            ->where('auction_id', $auctionId)
            ->where('user_id', $userId)
            ->value('locked_emd_amount') ?? 0);
        $payableAmount = max(0, (float) $auction->final_price - $lockedEmd);

        $transactionId = 'TXN' . time() . random_int(1000, 9999);
        DB::table('payment_transactions')->insert([
            'transaction_id' => $transactionId,
            'auction_id' => $auctionId,
            'user_id' => $userId,
            'amount' => $payableAmount,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = DB::table('users')->where('id', $userId)->first();
        $paymentData = [
            'key' => env('PAYU_MERCHANT_KEY'),
            'txnid' => $transactionId,
            'amount' => number_format((float) $payableAmount, 2, '.', ''),
            'productinfo' => 'Auction Payment - ' . $auction->title,
            'firstname' => $user->name ?? 'User',
            'email' => $user->email ?? '',
            'phone' => DB::table('registration')->where('email', $user->email ?? '')->value('mobile') ?? '9999999999',
            'surl' => route('payu.auction.success'),
            'furl' => route('payu.auction.failure'),
            'udf1' => 'AUCTION_' . $auctionId,
            'udf2' => (string) $userId,
            'udf3' => '',
            'udf4' => '',
            'udf5' => '',
        ];
        $paymentData['hash'] = $payu->generateHash($paymentData);

        return view('payments.redirect', ['paymentUrl' => $payu->paymentUrl(), 'paymentData' => $paymentData]);
    }

    public function initiateAuctionParticipationPayment(Request $request, int $auctionId, PayuService $payu)
    {
        $userId = (int) $request->session()->get('user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $auction = DB::table('auctions')
            ->where('id', $auctionId)
            ->where('status', 'active')
            ->first();
        if (! $auction) {
            return redirect()->route('user.auctions.index')->with('bid_error', 'Auction is not active.');
        }
        if (! \Illuminate\Support\Facades\Schema::hasTable('auction_participants')) {
            return redirect()->route('user.auctions.show', ['auctionId' => $auctionId])->with('bid_error', 'Participation feature is unavailable.');
        }

        $alreadyJoined = DB::table('auction_participants')
            ->where('auction_id', $auctionId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
        if ($alreadyJoined) {
            return redirect()->route('user.auctions.show', ['auctionId' => $auctionId])->with('bid_success', 'You have already joined this auction.');
        }

        $fee = (float) ($auction->emd_amount ?? 0);
        if ($fee <= 0) {
            $fee = 1.00;
        }

        $transactionId = 'PART_' . time() . random_int(1000, 9999);
        DB::table('payment_transactions')->insert([
            'transaction_id' => $transactionId,
            'auction_id' => $auctionId,
            'user_id' => $userId,
            'amount' => $fee,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = DB::table('users')->where('id', $userId)->first();
        $paymentData = [
            'key' => env('PAYU_MERCHANT_KEY'),
            'txnid' => $transactionId,
            'amount' => number_format($fee, 2, '.', ''),
            'productinfo' => 'Participation Fee - ' . $auction->title,
            'firstname' => $user->name ?? 'User',
            'email' => $user->email ?? '',
            'phone' => DB::table('registration')->where('email', $user->email ?? '')->value('mobile') ?? '9999999999',
            'surl' => route('payu.auction.success'),
            'furl' => route('payu.auction.failure'),
            'udf1' => 'PARTICIPATION_' . $auctionId,
            'udf2' => (string) $userId,
            'udf3' => '',
            'udf4' => '',
            'udf5' => '',
        ];
        $paymentData['hash'] = $payu->generateHash($paymentData);

        return view('payments.redirect', ['paymentUrl' => $payu->paymentUrl(), 'paymentData' => $paymentData]);
    }

    public function auctionSuccess(Request $request, PayuService $payu)
    {
        $data = array_merge($request->query(), $request->post());
        if (! $payu->verifyHash($data) || ($data['status'] ?? '') !== 'success') {
            return redirect()->route('user.auctions.index', ['view' => 'won'])->with('error', 'Payment could not be verified. Please contact support if the amount was debited.');
        }

        $txnid = (string) ($data['txnid'] ?? '');
        preg_match('/(AUCTION|PARTICIPATION)_(\d+)/', (string) ($data['udf1'] ?? ''), $m);
        $flowType = (string) ($m[1] ?? 'AUCTION');
        $auctionId = (int) ($m[1] ?? 0);
        if (isset($m[2])) {
            $auctionId = (int) $m[2];
        }
        $userId = (int) ($data['udf2'] ?? 0);

        DB::transaction(function () use ($txnid, $data, $auctionId, $userId, $flowType): void {
            $existingTx = DB::table('payment_transactions')
                ->where('transaction_id', $txnid)
                ->lockForUpdate()
                ->first();
            if (! $existingTx) {
                Log::warning('Payment callback received for unknown transaction.', ['transaction_id' => $txnid, 'flow' => $flowType]);
                return;
            }
            if (($existingTx->status ?? '') === 'success') {
                // Idempotent callback retry from gateway.
                return;
            }

            DB::table('payment_transactions')
                ->where('transaction_id', $txnid)
                ->update([
                    'status' => 'success',
                    'payu_transaction_id' => $data['mihpayid'] ?? null,
                    'response_message' => 'Payment successful',
                    'response_data' => json_encode($data),
                    'updated_at' => now(),
                ]);

            if ($flowType === 'PARTICIPATION') {
                if (Schema::hasTable('auction_participants')) {
                    $exists = DB::table('auction_participants')
                        ->where('auction_id', $auctionId)
                        ->where('user_id', $userId)
                        ->exists();
                    if (! $exists) {
                        DB::table('auction_participants')->insert([
                            'auction_id' => $auctionId,
                            'user_id' => $userId,
                            'emd_locked' => 0,
                            'locked_emd_amount' => 0,
                            'status' => 'active',
                            'joined_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        DB::table('auction_participants')
                            ->where('auction_id', $auctionId)
                            ->where('user_id', $userId)
                            ->update(['status' => 'active', 'updated_at' => now()]);
                    }
                }
                return;
            }

            $auctionUpdate = ['status' => 'completed', 'payment_status' => 'paid'];
            if (Schema::hasColumn('auctions', 'payment_date')) {
                $auctionUpdate['payment_date'] = now();
            }

            $updated = DB::table('auctions')
                ->where('id', $auctionId)
                ->where('winner_user_id', $userId)
                ->update($auctionUpdate + ['updated_at' => now()]);
            if ($updated === 0) {
                DB::table('payment_transactions')
                    ->where('transaction_id', $txnid)
                    ->update([
                        'response_message' => 'Payment captured; auction state not updated (winner mismatch).',
                        'updated_at' => now(),
                    ]);
                Log::warning('Auction payment callback mismatch.', [
                    'transaction_id' => $txnid,
                    'auction_id' => $auctionId,
                    'user_id' => $userId,
                ]);
            }

            if (Schema::hasTable('auction_participants')) {
                DB::table('auction_participants')
                    ->where('auction_id', $auctionId)
                    ->where('user_id', $userId)
                    ->update(['status' => 'winner', 'emd_locked' => 0, 'updated_at' => now()]);
            }
        });

        if ($flowType === 'PARTICIPATION') {
            return redirect()->route('user.auctions.show', ['auctionId' => $auctionId])
                ->with('bid_success', 'Participation payment successful. You can now place bids.');
        }
        return redirect()->route('user.auctions.index', ['view' => 'won'])->with('payment', 'success');
    }

    public function auctionFailure(Request $request)
    {
        $data = array_merge($request->query(), $request->post());
        $txnid = (string) ($data['txnid'] ?? '');
        if ($txnid !== '') {
            DB::table('payment_transactions')
                ->where('transaction_id', $txnid)
                ->update([
                    'status' => 'failed',
                    'response_message' => (string) ($data['error_Message'] ?? $data['error'] ?? 'Payment failed'),
                    'response_data' => json_encode($data),
                    'updated_at' => now(),
                ]);
        }
        preg_match('/(AUCTION|PARTICIPATION)_(\d+)/', (string) ($data['udf1'] ?? ''), $m);
        $flowType = (string) ($m[1] ?? 'AUCTION');
        $auctionId = (int) ($m[2] ?? 0);
        if ($flowType === 'PARTICIPATION' && $auctionId > 0) {
            return redirect()->route('user.auctions.show', ['auctionId' => $auctionId])->with('bid_error', 'Participation payment failed.');
        }
        return redirect()->route('user.auctions.index', ['view' => 'won'])->with('error', 'Payment failed. You can try again from your won auctions.');
    }

    public function initiateRegistrationPayment(Request $request, PayuService $payu)
    {
        $reg = $request->session()->get('pending_registration');
        if (! is_array($reg)) {
            return redirect()->route('register')->withErrors(['registration' => 'No registration data found.']);
        }
        if (! empty($reg['expires_at']) && strtotime((string) $reg['expires_at']) < time()) {
            $request->session()->forget('pending_registration');
            return redirect()->route('register')->withErrors(['registration' => 'Registration session expired. Please register again.']);
        }

        $amount = (float) (DB::table('settings')->where('setting_key', 'registration_amount')->value('setting_value') ?? 500);
        $transactionId = 'REG_' . time() . '_' . uniqid();
        $registrationId = 'REG' . strtoupper(substr(md5((string) microtime(true)), 0, 10));

        DB::table('pending_registrations')->insert([
            'transaction_id' => $transactionId,
            'registration_id' => $registrationId,
            'registration_type' => $reg['registration_type'],
            'full_name' => $reg['full_name'],
            'date_of_birth' => $reg['date_of_birth'],
            'pan_card_number' => $reg['pan_card_number'],
            'email' => $reg['email'],
            'mobile' => $reg['mobile'],
            'pan_verification_data' => json_encode($request->session()->get('pan_verification_data_' . md5($reg['pan_card_number']), [])),
            'expires_at' => now()->addMinutes((int) env('REGISTRATION_PAYMENT_EXPIRY_MINUTES', 30)),
            'created_at' => now(),
        ]);
        DB::table('registration_payments')->insert([
            'transaction_id' => $transactionId,
            'registration_id' => $registrationId,
            'amount' => $amount,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentData = [
            'key' => env('PAYU_MERCHANT_KEY'),
            'txnid' => $transactionId,
            'amount' => number_format($amount, 2, '.', ''),
            'productinfo' => 'Registration Fee - ' . $registrationId,
            'firstname' => $reg['full_name'],
            'email' => $reg['email'],
            'phone' => $reg['mobile'],
            'surl' => route('payu.registration.success'),
            'furl' => route('payu.registration.failure'),
            'udf1' => 'REGISTRATION',
            'udf2' => $registrationId,
            'udf3' => $transactionId,
            'udf4' => '',
            'udf5' => '',
        ];
        $paymentData['hash'] = $payu->generateHash($paymentData);

        return view('payments.redirect', ['paymentUrl' => $payu->paymentUrl(), 'paymentData' => $paymentData]);
    }

    public function registrationSuccess(Request $request, PayuService $payu)
    {
        $data = array_merge($request->query(), $request->post());
        if (! $payu->verifyHash($data) || ($data['status'] ?? '') !== 'success') {
            return redirect()->route('register')->withErrors(['payment' => 'Payment verification failed.']);
        }

        $transactionId = (string) ($data['udf3'] ?? '');
        $registrationId = (string) ($data['udf2'] ?? '');
        $amount = (float) ($data['amount'] ?? 0);
        $welcomeEmailContext = null;

        DB::transaction(function () use ($transactionId, $registrationId, $amount, $data, &$welcomeEmailContext): void {
            $pending = DB::table('pending_registrations')
                ->where('transaction_id', $transactionId)
                ->where('registration_id', $registrationId)
                ->where('expires_at', '>', now())
                ->first();
            if (! $pending) {
                throw new \RuntimeException('Pending registration not found.');
            }
            $exists = DB::table('users')->where('email', $pending->email)->exists();
            if ($exists) {
                throw new \RuntimeException('Email already registered.');
            }

            $tempPassword = bin2hex(random_bytes(8));
            $resetToken = bin2hex(random_bytes(32));

            DB::table('registration')->insert([
                'registration_id' => $registrationId,
                'registration_type' => $pending->registration_type,
                'full_name' => $pending->full_name,
                'date_of_birth' => $pending->date_of_birth,
                'pan_card_number' => $pending->pan_card_number,
                'email' => $pending->email,
                'mobile' => $pending->mobile,
                'pan_verification_data' => $pending->pan_verification_data,
                'payment_status' => 'success',
                'payment_transaction_id' => $transactionId,
                'payment_amount' => $amount,
                'payment_response' => json_encode($data),
                'payment_date' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $userInsert = [
                'name' => $pending->full_name,
                'email' => $pending->email,
                'password' => Hash::make($tempPassword),
                'password_reset_token' => $resetToken,
                'password_reset_expires' => now()->addDays(7),
                'role' => 'user',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('users', 'must_reset_password')) {
                $userInsert['must_reset_password'] = 1;
            }
            DB::table('users')->insert($userInsert);

            DB::table('registration_payments')->where('transaction_id', $transactionId)->update([
                'status' => 'success',
                'payu_transaction_id' => $data['mihpayid'] ?? null,
                'payu_response' => json_encode($data),
                'updated_at' => now(),
            ]);
            DB::table('pending_registrations')->where('transaction_id', $transactionId)->delete();

            $welcomeEmailContext = [
                'email' => (string) $pending->email,
                'registration_id' => $registrationId,
                'temp_password' => $tempPassword,
                'reset_url' => route('password.reset.form', ['token' => $resetToken]),
            ];
        });

        if (is_array($welcomeEmailContext)) {
            $this->sendRegistrationSuccessEmail($welcomeEmailContext);
        }

        return redirect()->route('login')->with('status', 'Registration payment successful. Please check email for credentials.');
    }

    public function registrationFailure(Request $request)
    {
        $data = array_merge($request->query(), $request->post());
        $transactionId = (string) ($data['udf3'] ?? '');
        if ($transactionId !== '') {
            DB::table('registration_payments')->where('transaction_id', $transactionId)->update([
                'status' => 'failed',
                'payu_response' => json_encode($data),
                'updated_at' => now(),
            ]);
        }
        return redirect()->route('register')
            ->withErrors(['payment' => 'Payment failed. Please try again.'])
            ->with('payment_retry_available', true);
    }

    public function walletSuccess(Request $request, PayuService $payu, WalletService $walletService)
    {
        $data = array_merge($request->query(), $request->post());
        if (! $payu->verifyHash($data) || ($data['status'] ?? '') !== 'success') {
            return redirect()->route('wallet.index')->with('error', 'wallet_payment_verification_failed');
        }

        $transactionId = (string) ($data['udf3'] ?? $data['txnid'] ?? '');
        DB::transaction(function () use ($transactionId, $data, $walletService): void {
            $topup = DB::table('wallet_topups')->where('transaction_id', $transactionId)->lockForUpdate()->first();
            if (! $topup) {
                throw new \RuntimeException('Wallet top-up transaction not found.');
            }

            if ($topup->status !== 'success') {
                DB::table('wallet_topups')->where('id', $topup->id)->update([
                    'status' => 'success',
                    'gateway_transaction_id' => $data['mihpayid'] ?? null,
                    'gateway_response' => json_encode($data),
                    'updated_at' => now(),
                ]);
                $walletService->creditDeposit((int) $topup->user_id, (float) $topup->amount, $transactionId, 'success');
            }
        });

        return redirect()->route('wallet.index')->with('success', 'Wallet credited successfully.');
    }

    public function walletFailure(Request $request)
    {
        $data = array_merge($request->query(), $request->post());
        $transactionId = (string) ($data['udf3'] ?? $data['txnid'] ?? '');

        if ($transactionId !== '') {
            DB::table('wallet_topups')->where('transaction_id', $transactionId)->update([
                'status' => 'failed',
                'gateway_response' => json_encode($data),
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('wallet.index')->with('error', 'wallet_topup_failed');
    }

    private function sendRegistrationSuccessEmail(array $context): void
    {
        try {
            $this->applyActiveAdminEmailSettings();
            Mail::mailer('smtp')->raw(
                "Welcome to Auction Portal.\nRegistration ID: {$context['registration_id']}\nEmail: {$context['email']}\nTemporary password: {$context['temp_password']}\nReset link: {$context['reset_url']}",
                static function ($message) use ($context): void {
                    $message->to($context['email'])->subject('Registration Successful');
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Registration success email failed to send.', [
                'email' => $context['email'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function applyActiveAdminEmailSettings(): void
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('email_settings')) {
                return;
            }

            $settings = DB::table('email_settings')
                ->where('is_active', 1)
                ->latest('updated_at')
                ->first();

            if (! $settings) {
                return;
            }

            $encryption = strtolower(trim((string) ($settings->encryption ?? '')));
            $smtpScheme = match ($encryption) {
                'ssl' => 'smtps',
                'tls', '' => 'smtp',
                default => 'smtp',
            };

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => (string) ($settings->smtp_host ?? config('mail.mailers.smtp.host')),
                'mail.mailers.smtp.port' => (int) ($settings->smtp_port ?? config('mail.mailers.smtp.port')),
                'mail.mailers.smtp.username' => (string) ($settings->smtp_username ?? config('mail.mailers.smtp.username')),
                'mail.mailers.smtp.password' => (string) ($settings->smtp_password ?? config('mail.mailers.smtp.password')),
                'mail.mailers.smtp.scheme' => $smtpScheme,
                'mail.mailers.smtp.timeout' => 10,
                'mail.from.address' => (string) ($settings->from_email ?? config('mail.from.address')),
                'mail.from.name' => (string) ($settings->from_name ?? config('mail.from.name')),
            ]);

            Mail::purge('smtp');
        } catch (\Throwable) {
            // Keep default mail configuration if dynamic settings are unavailable.
        }
    }
}
