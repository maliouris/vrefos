<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold">Medications</h1>
    </div>

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
                    role="link"
                    tabindex="0"
                    @click="Livewire.navigate('{{ route('medications.edit', $medication) }}')"
                    @keydown.enter.self="Livewire.navigate('{{ route('medications.edit', $medication) }}')"
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
                        aria-label="Delete category {{ $category->name }}"
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
    <x-confirm-delete
        :show="(bool) $deletingCategoryId"
        :title="$deleteModalTitle"
        :message="$deleteModalMessage"
        :confirm="'confirmCategoryDelete('.$deletingCategoryId.')'"
        cancel="closeCategoryModal"
    >
        @if ($deleteModalType === 'warn')
            <div class="mb-4 bg-warning/20 p-3 rounded text-sm">
                <div class="font-semibold mb-2">Medications losing this category:</div>
                <ul class="space-y-1">
                    @foreach ($uncategorizedMedications ?? [] as $medName)
                        <li>• {{ $medName }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-confirm-delete>

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
