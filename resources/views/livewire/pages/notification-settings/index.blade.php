<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold">Notification Settings</h1>
    </div>

    @if (session('success'))
        <x-mary-alert title="{{ session('success') }}" class="alert-success mb-4" />
    @endif

    <x-mary-card>
        <x-mary-form wire:submit="save">
            <div class="space-y-6">
                @foreach ($settings as $actionTypeId => $setting)
                    <div class="border-b pb-6 last:border-b-0 last:pb-0">
                        <h3 class="mb-4 text-lg font-semibold">{{ $setting['name'] }}</h3>
                        <div class="flex flex-col gap-4">
                            <x-mary-toggle
                                label="Enable notifications"
                                wire:model="settings.{{ $actionTypeId }}.enabled"
                            />
                            <x-mary-select
                                label="Notify from"
                                wire:model="settings.{{ $actionTypeId }}.notify_from"
                                :options="[
                                    ['id' => 'started_at', 'name' => 'Start time'],
                                    ['id' => 'finished_at', 'name' => 'End time'],
                                ]"
                                option-value="id"
                                option-label="name"
                            />
                            <x-mary-input
                                label="Notify after (minutes)"
                                wire:model="settings.{{ $actionTypeId }}.notify_after_minutes"
                                type="number"
                                min="1"
                                max="10080"
                                hint="e.g. 180 = 3 hours, 60 = 1 hour"
                            />
                        </div>
                    </div>
                @endforeach
            </div>

            <x-slot:actions>
                <x-mary-button label="Save" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-card>
</div>
