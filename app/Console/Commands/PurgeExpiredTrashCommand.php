<?php

namespace App\Console\Commands;

use App\Enums\NodeType;
use App\Models\Node;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeExpiredTrashCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nas:purge-trash';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently delete trashed nodes whose purge_at timestamp has passed';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expiredNodes = Node::onlyTrashed()
            ->whereNotNull('purge_at')
            ->where('purge_at', '<=', now())
            ->get();

        $purgedCount = 0;

        foreach ($expiredNodes as $node) {
            if (! Node::withTrashed()->whereKey($node->id)->exists()) {
                continue;
            }

            $purgedCount += $this->purgeNodeRecursively($node);
        }

        $this->info("Purged {$purgedCount} expired node(s).");

        return self::SUCCESS;
    }

    protected function purgeNodeRecursively(Node $node): int
    {
        $count = 0;

        $children = Node::withTrashed()
            ->where('parent_id', $node->id)
            ->get();

        foreach ($children as $child) {
            $count += $this->purgeNodeRecursively($child);
        }

        if ($node->type === NodeType::File) {
            if ($node->storage_path !== null && Storage::disk('local')->exists($node->storage_path)) {
                Storage::disk('local')->delete($node->storage_path);
            }

            if ($node->size_bytes > 0) {
                $owner = User::find($node->owner_id);
                if ($owner !== null) {
                    $owner->update([
                        'used_bytes' => max(0, $owner->used_bytes - $node->size_bytes),
                    ]);
                }
            }
        }

        $node->forceDelete();

        return $count + 1;
    }
}
