<?php

namespace App\Console\Commands;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Mail\AccessRequestReminderMail;
use App\Models\AccessRequest;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendAccessRequestRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nas:remind-access-requests {--days=3 : Number of days before reminding}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder notifications and emails for pending access requests older than X days, escalating to Admin if owner is inactive';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $threshold = now()->subDays($days);

        $pendingRequests = AccessRequest::query()
            ->with(['node', 'requester', 'owner'])
            ->where('status', RequestStatus::Pending->value)
            ->where('created_at', '<=', $threshold)
            ->where(function ($query) use ($threshold): void {
                $query->whereNull('last_reminded_at')
                    ->orWhere('last_reminded_at', '<=', $threshold);
            })
            ->get();

        $remindedCount = 0;

        foreach ($pendingRequests as $accessRequest) {
            if ($accessRequest->node === null || $accessRequest->node->trashed()) {
                continue;
            }

            $owner = $accessRequest->owner;
            $nodeName = $accessRequest->node->name;
            $requesterName = $accessRequest->requester?->name ?? 'ผู้ใช้';

            if ($owner !== null && $owner->is_active) {
                UserNotification::create([
                    'user_id' => $owner->id,
                    'type' => 'access_request_reminder',
                    'data' => [
                        'access_request_id' => $accessRequest->id,
                        'node_id' => $accessRequest->node_id,
                        'node_uuid' => $accessRequest->node->uuid,
                        'node_name' => $nodeName,
                        'requester_id' => $accessRequest->requester_id,
                        'requester_name' => $requesterName,
                        'message' => "[เตือนซ้ำ] คำขอเข้าถึง \"{$nodeName}\" จาก {$requesterName} ยังรอการพิจารณา",
                    ],
                    'created_at' => now(),
                ]);

                Mail::to($owner->email)->send(new AccessRequestReminderMail($accessRequest, false));
            } else {
                $admins = User::query()
                    ->where('role', UserRole::Admin)
                    ->where('is_active', true)
                    ->get();

                foreach ($admins as $admin) {
                    UserNotification::create([
                        'user_id' => $admin->id,
                        'type' => 'access_request_escalated',
                        'data' => [
                            'access_request_id' => $accessRequest->id,
                            'node_id' => $accessRequest->node_id,
                            'node_uuid' => $accessRequest->node->uuid,
                            'node_name' => $nodeName,
                            'requester_id' => $accessRequest->requester_id,
                            'requester_name' => $requesterName,
                            'escalated' => true,
                            'message' => "[ส่งต่อ Admin] คำขอเข้าถึง \"{$nodeName}\" จาก {$requesterName} (เจ้าของถูกปิดบัญชี)",
                        ],
                        'created_at' => now(),
                    ]);

                    Mail::to($admin->email)->send(new AccessRequestReminderMail($accessRequest, true));
                }
            }

            $accessRequest->update([
                'last_reminded_at' => now(),
            ]);

            $remindedCount++;
        }

        $this->info("Sent {$remindedCount} access request reminder(s).");

        return self::SUCCESS;
    }
}
