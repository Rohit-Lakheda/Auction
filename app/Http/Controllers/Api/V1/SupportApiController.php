<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SupportApiController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $userId = (int) $request->session()->get('user_id');
        if (! $userId) {
            return $this->fail('Unauthenticated.', 401);
        }
        if (! Schema::hasTable('support_tickets')) {
            return $this->ok([]);
        }
        $rows = DB::table('support_tickets')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
        return $this->ok($rows);
    }

    public function store(Request $request)
    {
        $userId = (int) $request->session()->get('user_id');
        if (! $userId) {
            return $this->fail('Unauthenticated.', 401);
        }
        if (! Schema::hasTable('support_tickets')) {
            return $this->fail('Support module unavailable.', 500);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', 'in:low,normal,high'],
            'category' => ['nullable', 'string', 'max:50'],
        ]);

        $id = DB::table('support_tickets')->insertGetId([
            'user_id' => $userId,
            'subject' => trim((string) $validated['subject']),
            'message' => trim((string) $validated['message']),
            'status' => 'open',
            'priority' => (string) ($validated['priority'] ?? 'normal'),
            'category' => trim((string) ($validated['category'] ?? '')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->ok(['ticket_id' => $id], 'Ticket created.', 201);
    }
}

