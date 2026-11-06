@props(['show' => false])

@if ($show)
    <div x-data x-init="setTimeout(() => $wire.dismissFinished(), 8000)" class="mb-4">
        <x-mary-alert title="Action finished." icon="o-check-circle" class="alert-success">
            <x-slot:actions>
                <x-mary-button label="Undo" icon="o-arrow-uturn-left" class="btn-sm" wire:click="undoFinish" />
            </x-slot:actions>
        </x-mary-alert>
    </div>
@endif
