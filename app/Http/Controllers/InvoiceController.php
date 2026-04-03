<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Dompdf\Dompdf;
use Dompdf\Options;

class InvoiceController extends Controller
{
    public function auction(Request $request, int $auctionId)
    {
        $userId = (int) $request->session()->get('user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $auction = DB::table('auctions')
            ->where('id', $auctionId)
            ->where('winner_user_id', $userId)
            ->whereIn('status', ['closed', 'completed'])
            ->first();
        if (! $auction) {
            return redirect()->route('user.won-auctions')->with('error', 'auction_not_found');
        }

        $user = DB::table('users')->where('id', $userId)->first();
        $payment = DB::table('payment_transactions')
            ->where('auction_id', $auctionId)
            ->where('user_id', $userId)
            ->where('status', 'success')
            ->latest('created_at')
            ->first();
        if (! $payment) {
            return redirect()->route('user.won-auctions')->with('error', 'invoice_available_after_payment');
        }

        $invoiceData = [
            'invoice_no' => 'AUC-' . date('Ymd') . '-' . $auctionId,
            'issued_at' => now()->format('d-M-Y H:i'),
            'customer_name' => $user->name ?? 'Customer',
            'customer_email' => $user->email ?? '',
            'item_name' => $auction->title,
            'amount' => (float) $auction->final_price,
            'transaction_id' => $payment->transaction_id ?? '-',
            'payu_id' => $payment->payu_transaction_id ?? '-',
        ];
        $registrationRef = $this->registrationReferenceForUser($userId);
        $paymentRef = (string) ($payment->transaction_id ?? ('AUCTION_' . $auctionId));
        $downloadName = $this->invoiceFileName($registrationRef, 'auction_payment', $paymentRef);
        $serverPath = $this->invoiceServerPath($registrationRef, 'auction_payment', $paymentRef);

        return $this->renderPdf(
            view('invoices.auction', ['data' => $invoiceData])->render(),
            $downloadName,
            $serverPath
        );
    }

    public function registration(Request $request)
    {
        $userId = (int) $request->session()->get('user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $registration = DB::selectOne(
            "SELECT r.*, u.name FROM registration r
            INNER JOIN users u ON r.email = u.email
            WHERE u.id = ? AND r.payment_status = 'success'
            ORDER BY r.id DESC LIMIT 1",
            [$userId]
        );
        if (! $registration) {
            return redirect()->route('user.profile')->with('error', 'registration_not_found');
        }

        $invoiceData = [
            'invoice_no' => 'REG-' . date('Ymd') . '-' . ($registration->registration_id ?? $userId),
            'issued_at' => $registration->payment_date ? date('d-M-Y H:i', strtotime((string) $registration->payment_date)) : now()->format('d-M-Y H:i'),
            'customer_name' => $registration->full_name ?? $registration->name ?? 'Customer',
            'customer_email' => $registration->email ?? '',
            'registration_id' => $registration->registration_id ?? '-',
            'registration_type' => ucfirst((string) ($registration->registration_type ?? 'standard')),
            'amount' => (float) ($registration->payment_amount ?? 0),
            'transaction_id' => $registration->payment_transaction_id ?? '-',
        ];
        $registrationRef = (string) ($registration->registration_id ?? ('USER_' . $userId));
        $paymentRef = (string) ($registration->payment_transaction_id ?? $registrationRef);
        $downloadName = $this->invoiceFileName($registrationRef, 'registration_payment', $paymentRef);
        $serverPath = $this->invoiceServerPath($registrationRef, 'registration_payment', $paymentRef);

        return $this->renderPdf(
            view('invoices.registration', ['data' => $invoiceData])->render(),
            $downloadName,
            $serverPath
        );
    }

    public function adminRegistration(Request $request, int $userId)
    {
        $registration = DB::selectOne(
            "SELECT r.*, u.name FROM registration r
            INNER JOIN users u ON r.email = u.email
            WHERE u.id = ? AND r.payment_status = 'success'
            ORDER BY r.id DESC LIMIT 1",
            [$userId]
        );
        if (! $registration) {
            return redirect()->route('admin.users.show', ['id' => $userId])->withErrors(['invoice' => 'Registration invoice not found.']);
        }

        $invoiceData = [
            'invoice_no' => 'REG-' . date('Ymd') . '-' . ($registration->registration_id ?? $userId),
            'issued_at' => $registration->payment_date ? date('d-M-Y H:i', strtotime((string) $registration->payment_date)) : now()->format('d-M-Y H:i'),
            'customer_name' => $registration->full_name ?? $registration->name ?? 'Customer',
            'customer_email' => $registration->email ?? '',
            'registration_id' => $registration->registration_id ?? '-',
            'registration_type' => ucfirst((string) ($registration->registration_type ?? 'standard')),
            'amount' => (float) ($registration->payment_amount ?? 0),
            'transaction_id' => $registration->payment_transaction_id ?? '-',
        ];
        $registrationRef = (string) ($registration->registration_id ?? ('USER_' . $userId));
        $paymentRef = (string) ($registration->payment_transaction_id ?? $registrationRef);
        $downloadName = $this->invoiceFileName($registrationRef, 'registration_payment', $paymentRef);
        $serverPath = $this->invoiceServerPath($registrationRef, 'registration_payment', $paymentRef);

        return $this->renderPdf(
            view('invoices.registration', ['data' => $invoiceData])->render(),
            $downloadName,
            $serverPath
        );
    }

    public function adminAuction(Request $request, int $userId, int $auctionId)
    {
        $auction = DB::table('auctions')
            ->where('id', $auctionId)
            ->where('winner_user_id', $userId)
            ->whereIn('status', ['closed', 'completed'])
            ->first();
        if (! $auction) {
            return redirect()->route('admin.users.show', ['id' => $userId])->withErrors(['invoice' => 'Auction invoice not found.']);
        }

        $user = DB::table('users')->where('id', $userId)->first();
        $payment = DB::table('payment_transactions')
            ->where('auction_id', $auctionId)
            ->where('user_id', $userId)
            ->where('status', 'success')
            ->latest('created_at')
            ->first();
        if (! $payment) {
            return redirect()->route('admin.users.show', ['id' => $userId])->withErrors(['invoice' => 'Invoice is available only after successful payment.']);
        }

        $invoiceData = [
            'invoice_no' => 'AUC-' . date('Ymd') . '-' . $auctionId,
            'issued_at' => now()->format('d-M-Y H:i'),
            'customer_name' => $user->name ?? 'Customer',
            'customer_email' => $user->email ?? '',
            'item_name' => $auction->title,
            'amount' => (float) $auction->final_price,
            'transaction_id' => $payment->transaction_id ?? '-',
            'payu_id' => $payment->payu_transaction_id ?? '-',
        ];
        $registrationRef = $this->registrationReferenceForUser($userId);
        $paymentRef = (string) ($payment->transaction_id ?? ('AUCTION_' . $auctionId));
        $downloadName = $this->invoiceFileName($registrationRef, 'auction_payment', $paymentRef);
        $serverPath = $this->invoiceServerPath($registrationRef, 'auction_payment', $paymentRef);

        return $this->renderPdf(
            view('invoices.auction', ['data' => $invoiceData])->render(),
            $downloadName,
            $serverPath
        );
    }

    private function renderPdf(string $html, string $filename, ?string $serverPath = null)
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $output = $dompdf->output();

        if ($serverPath !== null) {
            $dir = dirname($serverPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($serverPath, $output);
        }

        return response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function registrationReferenceForUser(int $userId): string
    {
        $row = DB::table('users as u')
            ->leftJoin('registration as r', 'r.email', '=', 'u.email')
            ->where('u.id', $userId)
            ->select('r.registration_id')
            ->first();

        return (string) ($row->registration_id ?? ('USER_' . $userId));
    }

    private function invoiceFileName(string $registrationRef, string $paymentType, string $paymentRef): string
    {
        $safeRef = Str::slug($paymentRef, '_');
        return "{$registrationRef}_{$paymentType}_{$safeRef}.pdf";
    }

    private function invoiceServerPath(string $registrationRef, string $paymentType, string $paymentRef): string
    {
        $baseDir = storage_path('app/invoices/' . $registrationRef);
        return $baseDir . DIRECTORY_SEPARATOR . $this->invoiceFileName($registrationRef, $paymentType, $paymentRef);
    }
}
