<div>
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">Add Medication</h1>
    </div>

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
                <x-mary-button type="submit" label="Create" class="btn-primary" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-card>
</div>
