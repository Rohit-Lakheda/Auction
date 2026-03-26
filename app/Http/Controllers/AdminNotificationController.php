<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class AdminNotificationController extends Controller
{
    public function index(Request $request)
    {
        if (! Schema::hasTable('admin_messages') || ! Schema::hasTable('admin_message_recipients')) {
            return view('admin.notifications', [
                'users' => collect(),
                'threads' => collect(),
                'stats' => ['total_users' => 0, 'blocked_users' => 0, 'defaulted_users' => 0],
                'setupMissing' => true,
            ]);
        }

        $users = DB::table('users')
            ->where('role', 'user')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_blocked', 'default_count']);

        $threads = DB::table('admin_messages as m')
            ->leftJoin('admin_message_recipients as r', 'r.message_id', '=', 'm.id')
            ->leftJoin('admin_message_replies as ar', 'ar.message_id', '=', 'm.id')
            ->leftJoin('users as cu', 'cu.id', '=', 'm.created_by')
            ->selectRaw('m.id, m.subject, m.message, m.attachment_path, m.created_at, cu.name as created_by_name, cu.role as created_by_role, COUNT(DISTINCT r.user_id) as recipient_count, COUNT(DISTINCT ar.id) as reply_count')
            ->groupBy('m.id', 'm.subject', 'm.message', 'm.attachment_path', 'm.created_at', 'cu.name', 'cu.role')
            ->orderByDesc('m.created_at')
            ->limit(100)
            ->get();

        $stats = [
            'total_users' => $users->count(),
            'blocked_users' => $users->where('is_blocked', 1)->count(),
            'defaulted_users' => $users->where('default_count', '>', 0)->count(),
        ];

        return view('admin.notifications', compact('users', 'threads', 'stats') + ['setupMissing' => false]);
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'selected_users' => ['required', 'array', 'min:1'],
            'selected_users.*' => ['integer', 'min:1'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('admin-messages', 'public');
        }

        DB::transaction(function () use ($validated, $attachmentPath, $request): void {
            $messageId = DB::table('admin_messages')->insertGetId([
                'subject' => trim((string) ($validated['subject'] ?? '')),
                'message' => trim((string) $validated['message']),
                'attachment_path' => $attachmentPath,
                'created_by' => (int) $request->session()->get('user_id'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $users = DB::table('users')
                ->whereIn('id', $validated['selected_users'])
                ->where('role', 'user')
                ->get(['id', 'email', 'name']);

            foreach ($users as $user) {
                DB::table('admin_message_recipients')->insert([
                    'message_id' => $messageId,
                    'user_id' => (int) $user->id,
                    'email_sent_at' => now(),
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if (Schema::hasTable('notifications')) {
                    DB::table('notifications')->insert([
                        'user_id' => (int) $user->id,
                        'message' => (string) $validated['message'],
                        'read_status' => 0,
                        'created_at' => now(),
                    ]);
                }

                try {
                    Mail::raw((string) $validated['message'], function ($m) use ($user, $validated, $attachmentPath): void {
                        $m->to($user->email)->subject((string) (($validated['subject'] ?? '') !== '' ? $validated['subject'] : 'Message from Auction Admin'));
                        if ($attachmentPath) {
                            $m->attach(storage_path('app/public/' . $attachmentPath));
                        }
                    });
                } catch (\Throwable) {
                    // Continue for other recipients even if one mail fails.
                }
            }
        });

        return redirect()->route('admin.notifications')->with('success', 'Message sent to selected users.');
    }

    public function showThread(Request $request, int $id)
    {
        $thread = DB::table('admin_messages')->where('id', $id)->first();
        if (! $thread) {
            return redirect()->route('admin.notifications')->withErrors(['thread' => 'Message thread not found.']);
        }
        $creator = DB::table('users')->where('id', (int) ($thread->created_by ?? 0))->first(['id', 'name', 'role', 'email']);

        $recipients = DB::table('admin_message_recipients as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.message_id', $id)
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.email', 'r.is_read', 'r.last_read_at']);

        $replies = DB::table('admin_message_replies')
            ->where('message_id', $id)
            ->orderBy('created_at')
            ->get();

        return view('admin.notification-thread', compact('thread', 'recipients', 'replies', 'creator'));
    }

    public function reply(Request $request, int $id)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        $thread = DB::table('admin_messages')->where('id', $id)->first();
        if (! $thread) {
            return redirect()->route('admin.notifications')->withErrors(['thread' => 'Message thread not found.']);
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('admin-messages-replies', 'public');
        }

        DB::table('admin_message_replies')->insert([
            'message_id' => $id,
            'sender_role' => 'admin',
            'sender_user_id' => (int) $request->session()->get('user_id'),
            'message' => (string) $request->input('message'),
            'attachment_path' => $attachmentPath,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recipients = DB::table('admin_message_recipients as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.message_id', $id)
            ->get(['u.id', 'u.email']);

        foreach ($recipients as $recipient) {
            DB::table('admin_message_recipients')
                ->where('message_id', $id)
                ->where('user_id', $recipient->id)
                ->update(['is_read' => 0, 'updated_at' => now()]);

            if (Schema::hasTable('notifications')) {
                DB::table('notifications')->insert([
                    'user_id' => (int) $recipient->id,
                    'message' => (string) $request->input('message'),
                    'read_status' => 0,
                    'created_at' => now(),
                ]);
            }

            try {
                Mail::raw((string) $request->input('message'), function ($m) use ($recipient, $thread, $attachmentPath): void {
                    $m->to($recipient->email)->subject('Reply: ' . ((string) ($thread->subject ?: 'Message from Auction Admin')));
                    if ($attachmentPath) {
                        $m->attach(storage_path('app/public/' . $attachmentPath));
                    }
                });
            } catch (\Throwable) {
                // Ignore email errors per recipient.
            }
        }

        return redirect()->route('admin.notifications.thread', ['id' => $id])->with('success', 'Reply sent successfully.');
    }
}

