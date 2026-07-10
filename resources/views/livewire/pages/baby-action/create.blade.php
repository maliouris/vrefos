<div>
    <h1 class="text-2xl font-bold mb-4">Add Baby Action</h1>

    <x-mary-card>
        <x-mary-form wire:submit="save">
            <div>
                <label class="label"><span class="label-text">Baby</span></label>
                <div class="flex flex-wrap gap-2">
                    @foreach ($babies as $b)
                        <x-mary-button
                            label="{{ $b['name'] }}"
                            wire:click="toggleBaby({{ $b['id'] }})"
                            class="btn-md {{ $baby_id === $b['id'] ? 'btn-primary' : 'btn-outline' }}"
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
                            class="btn-md {{ $baby_action_type_id === $t['id'] ? 'btn-primary' : 'btn-outline' }}"
                        />
                    @endforeach
                </div>
                @error('baby_action_type_id') <span class="text-error text-sm">{{ $message }}</span> @enderror
            </div>

            @if ($this->isEatAction())
                <div>
                    <label class="label"><span class="label-text">Food Type</span></label>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($foodTypes as $ft)
                            <x-mary-button
                                label="{{ $ft['name'] }}"
                                wire:click="toggleFoodType('{{ $ft['id'] }}')"
                                class="btn-md {{ $food_type === $ft['id'] ? 'btn-primary' : 'btn-outline' }}"
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
                                    class="btn-md {{ $breast_side === $bs['id'] ? 'btn-primary' : 'btn-outline' }}"
                                />
                            @endforeach
                        </div>
                        @error('breast_side') <span class="text-error text-sm">{{ $message }}</span> @enderror
                    </div>
                @endif
            @endif

            @if ($this->isTemperatureAction())
                <x-mary-input
                    label="Temperature (°C)"
                    wire:model="temperature"
                    type="number"
                    step="0.1"
                    required
                />
                @error('temperature') <span class="text-error text-sm">{{ $message }}</span> @enderror
            @endif

            @if ($this->isMedicationAction())
                <div>
                    <label class="label"><span class="label-text">Medication</span></label>
                    <div class="flex flex-wrap gap-2 mb-2">
                        @foreach ($medications as $m)
                            <x-mary-button
                                label="{{ $m['name'] }}"
                                wire:click="toggleMedication({{ $m['id'] }})"
                                class="btn-md {{ $medication_id === $m['id'] ? 'btn-primary' : 'btn-outline' }}"
                            />
                        @endforeach
                    </div>

                    <x-mary-input
                        label="Or type a new medication…"
                        wire:model.live.debounce.300ms="new_medication_name"
                        type="text"
                        placeholder="New medication name"
                    />
                    @error('new_medication_name') <span class="text-error text-sm">{{ $message }}</span> @enderror
                </div>

                @if (trim($new_medication_name) !== '')
                    <div>
                        <label class="label"><span class="label-text">Categories</span></label>
                        <div class="flex flex-wrap gap-2 mb-2">
                            @foreach ($medicationCategories as $c)
                                <x-mary-button
                                    label="{{ $c['name'] }}"
                                    wire:click="toggleNewMedicationCategory({{ $c['id'] }})"
                                    class="btn-sm {{ in_array($c['id'], $new_medication_category_ids) ? 'btn-primary' : 'btn-outline' }}"
                                />
                            @endforeach
                        </div>

                        <x-mary-input
                            label="Or type a new category…"
                            wire:model="new_category_name"
                            type="text"
                            placeholder="New category name"
                        />
                        @error('new_category_name') <span class="text-error text-sm">{{ $message }}</span> @enderror
                    </div>
                @endif

                <x-mary-input
                    label="Amount (ml)"
                    wire:model="amount_ml"
                    type="number"
                    step="0.01"
                    placeholder="Optional"
                />
                @error('amount_ml') <span class="text-error text-sm">{{ $message }}</span> @enderror
            @endif

            <div x-data="{
                    utc: @entangle('started_at'),
                    get local() { return window.utcToLocalInput(this.utc) },
                    set local(value) { this.utc = window.localInputToUtc(value) },
                 }">
                <label class="label"><span class="label-text">{{ $this->isInstantAction() ? 'Time' : 'Started At' }}</span></label>
                <input type="datetime-local" x-model="local" required class="input input-bordered w-full" />
                @error('started_at') <span class="text-error text-sm">{{ $message }}</span> @enderror
            </div>

            @unless ($this->isInstantAction())
                <div x-data="{
                        utc: @entangle('finished_at'),
                        get local() { return window.utcToLocalInput(this.utc) },
                        set local(value) { this.utc = window.localInputToUtc(value) },
                     }">
                    <label class="label"><span class="label-text">Finished At</span></label>
                    <input type="datetime-local" x-model="local" class="input input-bordered w-full" />
                    @error('finished_at') <span class="text-error text-sm">{{ $message }}</span> @enderror
                </div>
            @endunless

            <x-slot:actions>
                <x-mary-button label="Cancel" link="{{ route('baby_actions.show') }}" class="btn-ghost" />
                <x-mary-button type="submit" label="Save" class="btn-primary" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-card>
</div>
