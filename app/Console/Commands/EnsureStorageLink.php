<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * The web root (public_html) is a separate directory from this app
 * (see .cpanel.yml), so php artisan storage:link's usual public/storage
 * target isn't what the browser actually reads — production serves out
 * of /home/elitccyv/public_html/storage instead. Deploys recreate that
 * symlink, but it can go stale between deploys (e.g. a deploy aborting
 * partway through), which shows up as broken images. Runs on a schedule
 * as a safety net so it self-heals instead of waiting for the next deploy.
 */
class EnsureStorageLink extends Command
{
    protected $signature = 'storage:ensure-link';

    protected $description = 'Verify the public_html/storage symlink points at storage/app/public, and recreate it if missing or broken';

    private const LINK = '/home/elitccyv/public_html/storage';

    public function handle(): int
    {
        if (!is_dir(dirname(self::LINK))) {
            return self::SUCCESS;
        }

        $target = storage_path('app/public');

        if (is_link(self::LINK) && readlink(self::LINK) === $target) {
            return self::SUCCESS;
        }

        if (file_exists(self::LINK) && !is_link(self::LINK)) {
            $this->error(self::LINK . ' exists as a real directory, not a symlink — leaving it alone, check it by hand.');
            Log::warning('[StorageLink] ' . self::LINK . ' is a real directory instead of a symlink; left untouched.');
            return self::FAILURE;
        }

        if (is_link(self::LINK) || file_exists(self::LINK)) {
            unlink(self::LINK);
        }

        symlink($target, self::LINK);

        $this->info('Recreated ' . self::LINK . ' -> ' . $target);
        Log::warning('[StorageLink] Recreated broken/missing symlink ' . self::LINK . ' -> ' . $target);

        return self::SUCCESS;
    }
}
