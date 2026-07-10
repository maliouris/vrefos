<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold">Notification Settings</h1>
    </div>

    <x-notification-permission-banner :status="$permissionStatus" />

    @if (session('success'))
        <x-mary-alert title="{{ session('success') }}" class="alert-success mb-4" />
    @endif

    <div class="space-y-6">
        @foreach ($actionTypes as $actionType)
            <x-mary-card>
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold">{{ $actionType->name }}</h3>
                    <x-mary-button
                        label="Add"
                        icon="o-plus"
                        class="btn-sm btn-primary"
                        wire:click="openCreate({{ $actionType->id }})"
                    />
                </div>

                @forelse ($actionType->notificationSettings as $rule)
                    <div class="flex items-center justify-between gap-4 border-b py-3 last:border-b-0">
                        <div class="flex items-center gap-3">
                            <x-mary-toggle
                                wire:click="toggleEnabled({{ $rule->id }})"
                                :checked="$rule->enabled"
                            />
                            <div>
                                <div class="font-medium">{{ $rule->title }}</div>
                                <div class="text-sm opacity-70">
                                    {{ $rule->notify_after_minutes }} min from
                                    {{ $rule->notify_from === \App\Enums\NotifyFrom::FinishedAt ? 'end' : 'start' }}
                                </div>
                                <div class="text-sm opacity-70">
                                    {{ $rule->all_children ? 'All children' : $rule->babies->pluck('name')->join(', ') }}
                                </div>
                                @if ($rule->feverLevelConditions->isNotEmpty())
                                    <div class="text-sm opacity-70">
                                        when fever is {{ $rule->feverLevelConditions->map(fn ($c) => $c->fever_level->label() . ' (' . $c->fever_level->rangeLabel() . ')')->join(' or ') }}
                                    </div>
                                @endif
                                @if ($rule->targetMedications->isNotEmpty() || $rule->medicationCategories->isNotEmpty())
                                    <div class="text-sm opacity-70">
                                        for {{ collect()
                                            ->merge($rule->targetMedications->pluck('name'))
                                            ->merge($rule->medicationCategories->map(fn ($c) => 'any ' . strtolower($c->name)))
                                            ->join(' or ') }}
                                        @if ($rule->excludedMedications->isNotEmpty())
                                            except {{ $rule->excludedMedications->pluck('name')->join(', ') }}
                                        @endif
                                    </div>
                                @endif
                                @if (filled($rule->description))
                                    <div class="text-sm opacity-70">{{ $rule->description }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-mary-button
                                icon="o-pencil"
                                class="btn-sm btn-ghost"
                                wire:click="openEdit({{ $rule->id }})"
                            />
                            <x-mary-button
                                icon="o-trash"
                                class="btn-sm btn-ghost text-error"
                                wire:click="deleteRule({{ $rule->id }})"
                                wire:confirm="Delete this notification rule?"
                            />
                        </div>
                    </div>
                @empty
                    <p class="text-sm opacity-70">No notification rules yet.</p>
                @endforelse
            </x-mary-card>
        @endforeach
    </div>

    <x-mary-modal wire:model="showModal" title="Notification rule">
        <x-mary-form wire:submit="saveRule">
            <div class="flex flex-col gap-4">
                <x-mary-input
                    label="Notify after (minutes)"
                    wire:model="notifyAfterMinutes"
                    type="number"
                    min="1"
                    max="10080"
                    hint="e.g. 180 = 3 hours, 60 = 1 hour"
                />
                @if (!isset($ruleTypeId) || !\App\Models\BabyActionType::find($ruleTypeId)?->is_instant)
                    <x-mary-select
                        label="Notify from"
                        wire:model="notifyFrom"
                        :options="[
                            ['id' => 'started_at', 'name' => 'Start time'],
                            ['id' => 'finished_at', 'name' => 'End time'],
                        ]"
                        option-value="id"
                        option-label="name"
                    />
                @endif
                <x-mary-input
                    label="Title"
                    wire:model="title"
                    hint="Placeholders: {{ '#{minutes}' }} {{ '#{action}' }} {{ '#{baby}' }}"
                />
                <x-mary-input
                    label="Description (optional)"
                    wire:model="description"
                    hint="Placeholders: {{ '#{minutes}' }} {{ '#{action}' }} {{ '#{baby}' }}{{ isset($ruleTypeId) && \App\Models\BabyActionType::find($ruleTypeId)?->name === 'Temperature' ? ' #{temperature} #{fever_level}' : '' }}{{ isset($ruleTypeId) && \App\Models\BabyActionType::find($ruleTypeId)?->name === 'Medication' ? ' #{medication}' : '' }}"
                />

                @php
                    $actionType = isset($ruleTypeId) ? \App\Models\BabyActionType::find($ruleTypeId) : null;
                @endphp

                @if ($actionType?->name === 'Temperature')
                    <div>
                        <label class="label"><span class="label-text">Fever Levels</span></label>
                        <div class="flex flex-wrap gap-2">
                            <x-mary-button
                                label="Any reading"
                                wire:click="$set('conditionFeverLevels', [])"
                                class="btn-sm {{ empty($conditionFeverLevels) ? 'btn-primary' : 'btn-outline' }}"
                            />
                            @foreach ($feverLevels as $level)
                                <x-mary-button
                                    label="{{ $level->label() }} ({{ $level->rangeLabel() }})"
                                    wire:click="toggleConditionFeverLevel('{{ $level->value }}')"
                                    class="btn-sm {{ in_array($level->value, $conditionFeverLevels) ? 'btn-primary' : 'btn-outline' }} {{ $level->badgeClass() }}"
                                />
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($actionType?->name === 'Medication')
                    @if ($medications->isEmpty())
                        <div class="alert alert-info">
                            <span>Create medications first to set up medication-specific rules.</span>
                        </div>
                    @else
                        <div>
                            <label class="label"><span class="label-text">Medications</span></label>
                            <div class="flex flex-wrap gap-2">
                                <x-mary-button
                                    label="Any"
                                    wire:click="$set('conditionMedicationIds', [])"
                                    class="btn-sm {{ empty($conditionMedicationIds) ? 'btn-primary' : 'btn-outline' }}"
                                />
                                @foreach ($medications as $med)
                                    <x-mary-button
                                        label="{{ $med->name }}"
                                        wire:click="toggleConditionMedication({{ $med->id }})"
                                        class="btn-sm {{ in_array($med->id, $conditionMedicationIds) ? 'btn-primary' : 'btn-outline' }}"
                                    />
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="label"><span class="label-text">Categories</span></label>
                            <div class="flex flex-wrap gap-2">
                                <x-mary-button
                                    label="Any"
                                    wire:click="$set('conditionCategoryIds', [])"
                                    class="btn-sm {{ empty($conditionCategoryIds) ? 'btn-primary' : 'btn-outline' }}"
                                />
                                @foreach ($medicationCategories as $cat)
                                    <x-mary-button
                                        label="{{ $cat->name }}"
                                        wire:click="toggleConditionCategory({{ $cat->id }})"
                                        class="btn-sm {{ in_array($cat->id, $conditionCategoryIds) ? 'btn-primary' : 'btn-outline' }}"
                                    />
                                @endforeach
                            </div>
                        </div>

                        @if (!empty($conditionMedicationIds) || !empty($conditionCategoryIds))
                            <div>
                                <label class="label"><span class="label-text">Exclude Medications</span></label>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($medications as $med)
                                        <x-mary-button
                                            label="{{ $med->name }}"
                                            wire:click="toggleExcludedMedication({{ $med->id }})"
                                            class="btn-sm {{ in_array($med->id, $excludedMedicationIds) ? 'btn-error' : 'btn-outline' }}"
                                        />
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                @endif

                <div>
                    <label class="label"><span class="label-text">Children</span></label>
                    <div class="flex flex-wrap gap-2">
                        <x-mary-button
                            label="All children"
                            wire:click="toggleAllChildren"
                            class="btn-md {{ $allChildren ? 'btn-primary' : 'btn-outline' }}"
                        />
                        @foreach ($babies as $baby)
                            <x-mary-button
                                label="{{ $baby->name }}"
                                wire:click="toggleBaby({{ $baby->id }})"
                                class="btn-sm {{ in_array($baby->id, $targetBabyIds) ? 'btn-primary' : 'btn-outline' }}"
                            />
                        @endforeach
                    </div>
                    @error('targetBabyIds') <span class="text-error text-sm">{{ $message }}</span> @enderror
                </div>

                <x-mary-toggle label="Enabled" wire:model="enabled" />
            </div>

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('showModal', false)" />
                <x-mary-button label="Save" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>
</div>
