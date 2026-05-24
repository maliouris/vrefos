<?php

namespace App\Console\Commands;

use App\Services\SyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:sync-to-server')]
#[Description('Sync dirty local records to the remote server')]
class SyncToServer extends Command
{
    public function handle(SyncService $syncService): int
    {
        $syncService->sync();

        $this->info('Sync complete.');

        return self::SUCCESS;
    }
}
