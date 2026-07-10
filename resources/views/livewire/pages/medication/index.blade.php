<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold">Medications</h1>
    </div>

    @if (session('success'))
        <x-mary-alert title="{{ session('success') }}" class="alert-success mb-4" />
    @endif

    <div class="space-y-6">
        {{-- Medications List --}}
        <x-mary-card>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold">Medications</h3>
                <x-mary-button label="Add" icon="o-plus" link="{{ route('medications.create') }}" class="btn-primary btn-sm" />
            </div>

            @forelse ($medications as $medication)
                <div
                    class="flex items-center justify-between gap-4 border-b py-3 last:border-b-0 cursor-pointer hover:bg-base-200 transition-colors p-3 -m-3"
                    x-data
                    @click="Livewire.navigate('{{ route('medications.edit', $medication) }}')"
                >
                    <div class="flex-1 min-w-0">
                        <div class="font-medium">{{ $medication->name }}</div>
                        @if ($medication->categories->isNotEmpty())
                            <div class="text-sm opacity-70">
                                {{ $medication->categories->pluck('name')->join(', ') }}
                            </div>
                        @endif
                        @if ($medication->action_details_count > 0)
                            <div class="text-sm opacity-70">{{ $medication->action_details_count }} action(s)</div>
                        @endif
                    </div>
                    <x-mary-icon name="o-chevron-right" class="text-base-content/30 shrink-0" />
                </div>
            @empty
                <p class="text-sm opacity-70">No medications yet.</p>
            @endforelse
        </x-mary-card>

        {{-- Categories Section --}}
        <x-mary-card>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold">Categories</h3>
                <x-mary-button label="Add" icon="o-plus" wire:click="openAddCategoryModal" class="btn-primary btn-sm" />
            </div>

            @forelse ($categories as $category)
                <div class="flex items-center justify-between gap-4 border-b py-3 last:border-b-0">
                    <div>
                        <div class="font-medium">{{ $category->name }}</div>
                    </div>
                    <x-mary-button
                        icon="o-trash"
                        class="btn-sm btn-ghost text-error"
                        wire:click="deleteCategory({{ $category->id }})"
                    />
                </div>
            @empty
                <p class="text-sm opacity-70">No categories yet.</p>
            @endforelse
        </x-mary-card>
    </div>

    {{-- Category Delete Modal --}}
    @if ($deletingCategoryId)
        <div class="fixed inset-0 bg-black/50 z-40 flex items-center justify-center" @click.self="$wire.closeCategoryModal()">
            <div class="bg-base-100 rounded-lg p-6 max-w-md w-full mx-4 shadow-lg">
                <h3 class="text-lg font-bold mb-4">{{ $deleteModalTitle }}</h3>

                <p class="mb-4">{{ $deleteModalMessage }}</p>

                @if ($deleteModalType === 'blocked')
                    <div class="mb-4 bg-base-200 p-3 rounded text-sm">
                        <div class="font-semibold mb-2">Rules to change:</div>
                        <ul class="space-y-1">
                            @foreach ($blockingRules ?? [] as $rule)
                                <li>• {{ $rule['title'] }} ({{ $rule['type'] }}, {{ $rule['delay'] }})</li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="flex gap-2">
                        <button class="btn btn-ghost flex-1" @click="$wire.closeCategoryModal()">Close</button>
                    </div>
                @elseif ($deleteModalType === 'warn')
                    <div class="mb-4 bg-warning/20 p-3 rounded text-sm">
                        <div class="font-semibold mb-2">Medications losing this category:</div>
                        <ul class="space-y-1">
                            @foreach ($uncategorizedMedications ?? [] as $medName)
                                <li>• {{ $medName }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="flex gap-2">
                        <button class="btn btn-ghost flex-1" @click="$wire.closeCategoryModal()">Cancel</button>
                        <button class="btn btn-error flex-1" wire:click="confirmCategoryDelete({{ $deletingCategoryId }})">Delete</button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Add Category Modal --}}
    @if ($showAddCategoryModal)
        <div class="fixed inset-0 bg-black/50 z-40 flex items-center justify-center" @click.self="$wire.closeAddCategoryModal()">
            <div class="bg-base-100 rounded-lg p-6 max-w-md w-full mx-4 shadow-lg">
                <h3 class="text-lg font-bold mb-4">Add Category</h3>

                <x-mary-input
                    label="Name"
                    wire:model="newCategoryName"
                    required
                />

                <div class="flex gap-2 mt-4">
                    <button class="btn btn-ghost flex-1" @click="$wire.closeAddCategoryModal()">Cancel</button>
                    <button class="btn btn-primary flex-1" wire:click="createCategory">Create</button>
                </div>
            </div>
        </div>
    @endif
</div>
