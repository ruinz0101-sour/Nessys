<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupStaleChunksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nas:cleanup-chunks {--hours=24 : Delete .part files older than this many hours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove abandoned temporary chunk upload (.part) files older than the configured age';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = max(0, (int) $this->option('hours'));
        $cutoffTimestamp = now()->subHours($hours)->getTimestamp();
        $disk = Storage::disk('local');

        if (! $disk->exists('chunks')) {
            $this->info('No temporary chunks directory found.');

            return self::SUCCESS;
        }

        $deletedCount = 0;
        $reclaimedBytes = 0;

        foreach ($disk->allFiles('chunks') as $relativePath) {
            if (! str_ends_with($relativePath, '.part')) {
                continue;
            }

            $fullPath = $disk->path($relativePath);
            if (! is_file($fullPath)) {
                continue;
            }

            $lastModified = @filemtime($fullPath);
            if ($hours > 0 && $lastModified !== false && $lastModified > $cutoffTimestamp) {
                continue;
            }

            $size = (int) (@filesize($fullPath) ?: 0);
            if (@unlink($fullPath)) {
                $deletedCount++;
                $reclaimedBytes += $size;
            }
        }

        $reclaimedMb = number_format($reclaimedBytes / 1048576, 2);
        $this->info("Cleaned up {$deletedCount} stale chunk file(s), reclaimed {$reclaimedMb} MB.");

        return self::SUCCESS;
    }
}
