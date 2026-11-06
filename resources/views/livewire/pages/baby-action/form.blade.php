{{-- Shared by baby-action create/edit. Expects $submit (the wire:submit action name). --}}
<x-mary-card>
        <x-mary-form wire:submit="{{ $submit }}">
            <div>
                <label class="label"><span class="label-text">Baby</span></label>
                <div class="flex flex-wrap gap-2">
                    @foreach ($babies as $b)
                        <x-mary-button
                            label="{{ $b['name'] }}"
                            wire:click="toggleBaby({{ $b['id'] }})"
                            aria-pressed="{{ $baby_id === $b['id'] ? 'true' : 'false' }}" class="btn-md rounded-full {{ $baby_id === $b['id'] ? 'btn-primary' : 'btn-outline' }}"
                        />
                    @endforeach
                </div>
                @error('baby_id') <span class="text-error text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="label"><span class="label-text">Action Type</span></label>
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($actionTypes as $t)
                        <button type="button" wire:click="toggleActionType({{ $t['id'] }})"
                            aria-pressed="{{ $baby_action_type_id === $t['id'] ? 'true' : 'false' }}"
                            class="type-chip-{{ \App\Models\BabyActionType::slugFor($t['name']) }} flex flex-col items-center justify-center gap-1 min-h-20 rounded-2xl font-bold active:scale-95 transition-transform {{ $baby_action_type_id === $t['id'] ? 'ring-4 ring-primary' : '' }}">
                            <x-mary-icon name="{{ \App\Models\BabyActionType::iconFor($t['name']) }}" class="type-icon w-7 h-7" />
                            <span>{{ $t['name'] }}</span>
                        </button>
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
                                aria-pressed="{{ $food_type === $ft['id'] ? 'true' : 'false' }}" class="btn-md rounded-full {{ $food_type === $ft['id'] ? 'btn-primary' : 'btn-outline' }}"
                            />
                        @endforeach
                    </div>
                    @error('food_type') <span class="text-error text-sm">{{ $message }}</span> @enderror
                </div>

            @endif

            @if ($this->isTemperatureAction())
                <x-mary-input
                    label="Temperature (°C)"
                    wire:model="temperature"
                    type="number"
                    step="0.1"
                    required
                />
            @endif

            @if ($this->isMedicationAction())
                <div>
                    <label class="label"><span class="label-text">Medication</span></label>
                    <div class="flex flex-wrap gap-2 mb-2">
                        @foreach ($medications as $m)
                            <x-mary-button
                                label="{{ $m['name'] }}"
                                wire:click="toggleMedication({{ $m['id'] }})"
                                aria-pressed="{{ $medication_id === $m['id'] ? 'true' : 'false' }}" class="btn-md rounded-full {{ $medication_id === $m['id'] ? 'btn-primary' : 'btn-outline' }}"
                            />
                        @endforeach
                    </div>

                    <x-mary-input
                        label="Or type a new medication…"
                        wire:model.live.debounce.300ms="new_medication_name"
                        type="text"
                        placeholder="New medication name"
                    />
                </div>

                @if (trim($new_medication_name) !== '')
                    <div>
                        <label class="label"><span class="label-text">Categories</span></label>
                        <div class="flex flex-wrap gap-2 mb-2">
                            @foreach ($medicationCategories as $c)
                                <x-mary-button
                                    label="{{ $c['name'] }}"
                                    wire:click="toggleNewMedicationCategory({{ $c['id'] }})"
                                    aria-pressed="{{ in_array($c['id'], $new_medication_category_ids) ? 'true' : 'false' }}" class="btn-sm {{ in_array($c['id'], $new_medication_category_ids) ? 'btn-primary' : 'btn-outline' }}"
                                />
                            @endforeach
                        </div>

                        <x-mary-input
                            label="Or type a new category…"
                            wire:model="new_category_name"
                            type="text"
                            placeholder="New category name"
                        />
                    </div>
                @endif

                <x-mary-input
                    label="Amount (ml)"
                    wire:model="amount_ml"
                    type="number"
                    step="0.01"
                    placeholder="Optional"
                />
            @endif

            <div x-data="{
                    utc: @entangle('started_at'),
                    get local() { return window.utcToLocalInput(this.utc) },
                    set local(value) { this.utc = window.localInputToUtc(value) },
                 }">
                <label class="label" for="started-at"><span class="label-text">{{ $this->isInstantAction() ? 'Time' : 'Started At' }}</span></label>
                <input id="started-at" type="datetime-local" x-model="local" required class="input input-bordered w-full" />
                @error('started_at') <span class="text-error text-sm">{{ $message }}</span> @enderror
            </div>

            @unless ($this->isInstantAction())
                <div x-data="{
                        utc: @entangle('finished_at'),
                        get local() { return window.utcToLocalInput(this.utc) },
                        set local(value) { this.utc = window.localInputToUtc(value) },
                     }">
                    <label class="label" for="finished-at"><span class="label-text">Finished At</span></label>
                    <input id="finished-at" type="datetime-local" x-model="local" class="input input-bordered w-full" />
                    @error('finished_at') <span class="text-error text-sm">{{ $message }}</span> @enderror
                </div>
            @endunless

            <x-slot:actions>
                <x-mary-button label="Cancel" link="{{ route('baby_actions.show') }}" class="btn-ghost" />
                <x-mary-button type="submit" label="Save" spinner="{{ $submit }}" wire:loading.attr="disabled" wire:target="{{ $submit }}" class="btn-primary" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-card>
