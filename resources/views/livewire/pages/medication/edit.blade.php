<div>
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">Edit Medication</h1>
        <x-mary-button
            label="Delete"
            icon="o-trash"
            class="btn-error btn-outline btn-sm"
            wire:click="promptDelete"
        />
    </div>

    @if (session('success'))
        <x-mary-alert title="{{ session('success') }}" class="alert-success mb-4" />
    @endif

    <x-mary-card>
        <x-mary-form wire:submit="save">
            <x-mary-input
                label="Name"
                wire:model="name"
                required
            />

            <div>
                <label class="label"><span class="label-text">Categories</span></label>
                <div class="flex flex-wrap gap-2 mb-2">
                    @foreach ($categories as $category)
                        <x-mary-button
                            label="{{ $category->name }}"
                            wire:click="toggleCategory({{ $category->id }})"
                            class="btn-sm {{ in_array($category->id, $categoryIds) ? 'btn-primary' : 'btn-outline' }}"
                        />
                    @endforeach
                </div>

                <x-mary-input
                    label="Or type a new category…"
                    wire:model="newCategoryName"
                    type="text"
                    placeholder="New category name"
                />
            </div>

            <x-slot:actions>
                <x-mary-button label="Back" link="{{ route('medications.show') }}" class="btn-ghost" />
                <x-mary-button type="submit" label="Update" class="btn-primary" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-card>

    {{-- Delete Modal --}}
    @if ($showDeleteModal)
        <div class="fixed inset-0 bg-black/50 z-40 flex items-center justify-center" @click.self="$wire.set('showDeleteModal', false)">
            <div class="bg-base-100 rounded-lg p-6 max-w-md w-full mx-4 shadow-lg">
                <h3 class="text-lg font-bold mb-4">{{ $deleteModalType === 'blocked' ? 'Cannot delete medication' : 'Delete medication?' }}</h3>

                <p class="mb-4">{{ $deleteBlockReason }}</p>

                @if ($deleteModalType === 'blocked')
                    <div class="mb-4 bg-base-200 p-3 rounded text-sm">
                        <div class="font-semibold mb-2">Rules referencing this medication:</div>
                        <ul class="space-y-1">
                            @foreach ($blockingRules ?? [] as $rule)
                                <li>• {{ $rule['title'] }} ({{ $rule['type'] }}, {{ $rule['delay'] }})</li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="flex gap-2">
                        <button class="btn btn-ghost flex-1" wire:click="$set('showDeleteModal', false)">Close</button>
                    </div>
                @else
                    <div class="flex gap-2">
                        <button class="btn btn-ghost flex-1" wire:click="$set('showDeleteModal', false)">Cancel</button>
                        <button class="btn btn-error flex-1" wire:click="confirmDelete">Delete</button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
