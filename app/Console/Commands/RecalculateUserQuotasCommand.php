<?php

namespace App\Console\Commands;

use App\Enums\NodeType;
use App\Models\Node;
use App\Models\User;
use Illuminate\Console\Command;

class RecalculateUserQuotasCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nas:recalculate-quotas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate used_bytes for all users from their stored files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $updatedCount = 0;

        User::query()->chunkById(100, function ($users) use (&$updatedCount): void {
            foreach ($users as $user) {
                $actualBytes = (int) Node::withTrashed()
                    ->where('type', NodeType::File)
                    ->where('owner_id', $user->id)
                    ->sum('size_bytes');

                if ($user->used_bytes !== $actualBytes) {
                    $user->update(['used_bytes' => $actualBytes]);
                    $updatedCount++;
                }
            }
        });

        $this->info("Recalculated quotas. Updated {$updatedCount} user(s).");

        return self::SUCCESS;
    }
}
