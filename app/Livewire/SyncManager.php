<?php

namespace App\Livewire;

use App\Services\SyncService;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Invisible background component embedded in the authenticated layout.
 * Listens for network-reconnect signals (dispatched via Alpine.js) and
 * runs a sync. Only actually syncs when running inside NativePHP.
 */
class SyncManager extends Component
{
    #[On('triggerSync')]
    public function triggerSync(SyncService $syncService): void
    {
        $syncService->sync();
    }

    public function render(): string
    {
        return <<<'HTML'
        <div></div>
        HTML;
    }
}
