<?php

namespace App\Livewire\Pages\BabyAction;

use App\Livewire\Concerns\ManagesBabyActionForm;
use App\Models\BabyAction;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mary\Traits\Toast;

#[Layout('layouts.app')]
class Edit extends Component
{
    use ManagesBabyActionForm;
    use Toast;

    public BabyAction $babyAction;

    public bool $showDeleteModal = false;

    public function mount(BabyAction $babyAction): void
    {
        $this->babyAction = $babyAction;
        $this->baby_id = $babyAction->baby_id;
        $this->baby_action_type_id = $babyAction->baby_action_type_id;
        $this->started_at = $babyAction->started_at?->format('Y-m-d\TH:i') ?? '';
        $this->finished_at = $babyAction->finished_at?->format('Y-m-d\TH:i') ?? '';
        $this->food_type = $babyAction->eatDetail?->food_type?->value;
        $this->temperature = $babyAction->temperatureDetail?->temperature;
        $this->medication_id = $babyAction->medicationDetail?->medication_id;
        $this->amount_ml = $babyAction->medicationDetail?->amount_ml;
    }

    public function update(): void
    {
        $this->validateForm();

        $this->babyAction->update([
            'baby_id' => $this->baby_id,
            'baby_action_type_id' => $this->baby_action_type_id,
            'started_at' => $this->started_at,
            'finished_at' => $this->isInstantAction() ? null : ($this->finished_at ?: null),
        ]);

        if ($this->isEatAction() && $this->food_type !== null) {
            $this->babyAction->eatDetail()->updateOrCreate(
                ['baby_action_id' => $this->babyAction->id],
                [
                    'food_type' => $this->food_type,
                ]
            );
        } else {
            $this->babyAction->eatDetail()->delete();
        }

        if ($this->isTemperatureAction()) {
            $this->babyAction->temperatureDetail()->updateOrCreate(
                ['baby_action_id' => $this->babyAction->id],
                ['temperature' => $this->temperature]
            );
        } else {
            $this->babyAction->temperatureDetail()->delete();
        }

        if ($this->isMedicationAction()) {
            $this->babyAction->medicationDetail()->updateOrCreate(
                ['baby_action_id' => $this->babyAction->id],
                [
                    'medication_id' => $this->resolveMedicationId(),
                    'amount_ml' => $this->amount_ml ?: null,
                ]
            );
        } else {
            $this->babyAction->medicationDetail()->delete();
        }

        $this->success('Baby action updated successfully.');
    }

    public function promptDelete(): void
    {
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        $this->babyAction->delete();

        $this->success('Baby action deleted.');

        $this->redirectRoute('baby_actions.show', navigate: true);
    }

    public function render()
    {
        return view('livewire.pages.baby-action.edit', $this->formData());
    }
}
