@props([
    'show' => false,
    'title' => 'Delete?',
    'message' => null,
    'confirm',
    'cancel' => "\$set('showDeleteModal', false)",
    'confirmLabel' => 'Delete',
    'blocked' => false,
])

{{-- Shared confirmation dialog for destructive actions. `confirm` / `cancel` are
     Livewire action expressions. A `blocked` dialog only offers Close. --}}
@if ($show)
    <div class="fixed inset-0 bg-black/50 z-40 flex items-center justify-center" role="dialog" aria-modal="true" aria-label="{{ $title }}" wire:click.self="{{ $cancel }}" @keydown.escape.window="$wire.{{ $cancel }}">
        <div class="bg-base-100 rounded-lg p-6 max-w-md w-full mx-4 shadow-lg">
            <h3 class="text-lg font-bold mb-4">{{ $title }}</h3>

            @if ($message)
                <p class="mb-4">{{ $message }}</p>
            @endif

            {{ $slot }}

            <div class="flex gap-2">
                @if ($blocked)
                    <button type="button" class="btn btn-ghost flex-1" wire:click="{{ $cancel }}">Close</button>
                @else
                    <button type="button" class="btn btn-ghost flex-1" wire:click="{{ $cancel }}">Cancel</button>
                    <button type="button" class="btn btn-error flex-1" wire:click="{{ $confirm }}" wire:loading.attr="disabled" wire:target="{{ $confirm }}">{{ $confirmLabel }}</button>
                @endif
            </div>
        </div>
    </div>
@endif
