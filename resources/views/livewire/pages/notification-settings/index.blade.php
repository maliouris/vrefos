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
                <x-mary-input
                    label="Title"
                    wire:model="title"
                    hint="Placeholders: {{ '#{minutes}' }} {{ '#{action}' }} {{ '#{baby}' }}"
                />
                <x-mary-input
                    label="Description (optional)"
                    wire:model="description"
                    hint="Placeholders: {{ '#{minutes}' }} {{ '#{action}' }} {{ '#{baby}' }}"
                />
                <div>
                    <label class="label"><span class="label-text">Children</span></label>
                    <div class="flex flex-wrap gap-2">
                        <x-mary-button
                            label="All children"
                            wire:click="toggleAllChildren"
                            class="btn-sm {{ $allChildren ? 'btn-primary' : 'btn-outline' }}"
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
