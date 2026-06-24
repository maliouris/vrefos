<div>
    <h1 class="text-2xl font-bold mb-4">Edit Baby Action</h1>

    @if (session('success'))
        <x-mary-alert title="{{ session('success') }}" class="alert-success mb-4" />
    @endif

    <x-mary-card>
        <x-mary-form wire:submit="update">
            <div>
                <label class="label"><span class="label-text">Baby</span></label>
                <div class="flex flex-wrap gap-2">
                    @foreach ($babies as $b)
                        <x-mary-button
                            label="{{ $b['name'] }}"
                            wire:click="toggleBaby({{ $b['id'] }})"
                            class="btn-sm {{ $baby_id === $b['id'] ? 'btn-primary' : 'btn-outline' }}"
                        />
                    @endforeach
                </div>
                @error('baby_id') <span class="text-error text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="label"><span class="label-text">Action Type</span></label>
                <div class="flex flex-wrap gap-2">
                    @foreach ($actionTypes as $t)
                        <x-mary-button
                            label="{{ $t['name'] }}"
                            wire:click="toggleActionType({{ $t['id'] }})"
                            class="btn-sm {{ $baby_action_type_id === $t['id'] ? 'btn-primary' : 'btn-outline' }}"
                        />
                    @endforeach
                </div>
                @error('baby_action_type_id') <span class="text-error text-sm">{{ $message }}</span> @enderror
            </div>

            @if ($this->isEatAction)
                <div>
                    <label class="label"><span class="label-text">Food Type</span></label>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($foodTypes as $ft)
                            <x-mary-button
                                label="{{ $ft['name'] }}"
                                wire:click="toggleFoodType('{{ $ft['id'] }}')"
                                class="btn-sm {{ $food_type === $ft['id'] ? 'btn-primary' : 'btn-outline' }}"
                            />
                        @endforeach
                    </div>
                    @error('food_type') <span class="text-error text-sm">{{ $message }}</span> @enderror
                </div>

                @if ($food_type === \App\Enums\FoodType::BreastMilk->value)
                    <div>
                        <label class="label"><span class="label-text">Breast Side</span></label>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($breastSides as $bs)
                                <x-mary-button
                                    label="{{ $bs['name'] }}"
                                    wire:click="toggleBreastSide('{{ $bs['id'] }}')"
                                    class="btn-sm {{ $breast_side === $bs['id'] ? 'btn-primary' : 'btn-outline' }}"
                                />
                            @endforeach
                        </div>
                        @error('breast_side') <span class="text-error text-sm">{{ $message }}</span> @enderror
                    </div>
                @endif
            @endif

            <x-mary-input label="Started At" wire:model="started_at" type="datetime-local" required />
            <x-mary-input label="Finished At" wire:model="finished_at" type="datetime-local" />

            <x-slot:actions>
                <x-mary-button label="Back" link="{{ route('baby_actions.show') }}" class="btn-ghost" />
                <x-mary-button type="submit" label="Update" class="btn-primary" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-card>
</div>
