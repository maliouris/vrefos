<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold">Notifications</h1>
    </div>

    <x-notification-permission-banner :status="$permissionStatus" />

    @if (session('success'))
        <x-mary-alert title="{{ session('success') }}" class="alert-success mb-4" />
    @endif

    <x-mary-card>
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold">Messages</h3>
            <x-mary-button label="Add" icon="o-plus" wire:click="openCreate" class="btn-primary btn-sm" />
        </div>

        @forelse ($notifications as $notification)
            <div
                class="flex items-center justify-between gap-4 border-b py-3 last:border-b-0 cursor-pointer hover:bg-base-200 transition-colors p-3 -m-3"
                wire:click="openEdit({{ $notification->id }})"
            >
                <div class="flex-1 min-w-0">
                    <div class="font-medium truncate">{{ $notification->title }}</div>
                    @if (filled($notification->description))
                        <div class="text-sm opacity-70 truncate">{{ $notification->description }}</div>
                    @endif
                </div>
                <x-mary-icon name="o-chevron-right" class="text-base-content/30 shrink-0" />
            </div>
        @empty
            <p class="text-sm opacity-70">No messages yet.</p>
        @endforelse
    </x-mary-card>

    <x-mary-modal wire:model="showModal" title="{{ $editingId ? 'Edit message' : 'New message' }}">
        <x-mary-form wire:submit="save">
            <div class="flex flex-col gap-4">
                <x-mary-input
                    label="Title"
                    wire:model="title"
                    hint="Placeholders: {{ '#{baby}' }} {{ '#{action}' }} {{ '#{interval}' }}"
                />
                <x-mary-input
                    label="Description (optional)"
                    wire:model="description"
                    hint="Placeholders: {{ '#{baby}' }} {{ '#{action}' }} {{ '#{interval}' }}"
                />
            </div>

            <x-slot:actions>
                @if ($editingId)
                    <x-mary-button
                        label="Delete"
                        class="btn-ghost text-error mr-auto"
                        wire:click="promptDelete({{ $editingId }})"
                    />
                @endif
                <x-mary-button label="Cancel" wire:click="$set('showModal', false)" />
                <x-mary-button label="Save" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    @if ($showDeleteModal)
        <div class="fixed inset-0 bg-black/50 z-40 flex items-center justify-center" @click.self="$wire.closeDeleteModal()">
            <div class="bg-base-100 rounded-lg p-6 max-w-md w-full mx-4 shadow-lg">
                <h3 class="text-lg font-bold mb-4">Delete message?</h3>
                <p class="mb-4">This will remove it from any plans it's attached to.</p>
                <div class="flex gap-2">
                    <button class="btn btn-ghost flex-1" wire:click="closeDeleteModal">Cancel</button>
                    <button class="btn btn-error flex-1" wire:click="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    @endif
</div>
