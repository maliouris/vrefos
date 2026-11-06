<?php

namespace App\Livewire\Pages\BabyAction;

use App\Livewire\Concerns\ManagesBabyActionForm;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mary\Traits\Toast;

#[Layout('layouts.app')]
class Create extends Component
{
    use ManagesBabyActionForm;
    use Toast;

    /** Where to go after saving: the page the user came from. */
    public string $returnUrl = '';

    public function mount(): void
    {
        // UTC wall-clock default; the form converts it to local time for display.
        $this->started_at = now()->format('Y-m-d\TH:i');

        $this->returnUrl = $this->resolveReturnUrl();

        // Quick actions link here with ?baby=ID&type=ID; a lone baby is picked automatically.
        $babyId = request()->integer('baby');

        if ($babyId > 0 && Baby::whereKey($babyId)->exists()) {
            $this->baby_id = $babyId;
        } elseif (Baby::count() === 1) {
            $this->baby_id = Baby::value('id');
        }

        $typeId = request()->integer('type');

        if ($typeId > 0 && BabyActionType::whereKey($typeId)->exists()) {
            $this->baby_action_type_id = $typeId;
            $this->updatedBabyActionTypeId();
        }
    }

    private function resolveReturnUrl(): string
    {
        $previous = url()->previous();
        $path = parse_url($previous, PHP_URL_PATH) ?: '';

        $sameHost = parse_url($previous, PHP_URL_HOST) === request()->getHost();
        $isThisForm = $path === parse_url(route('baby_actions.create'), PHP_URL_PATH);

        return $sameHost && ! $isThisForm ? $previous : route('baby_actions.show');
    }

    public function save(): void
    {
        $this->validateForm();

        $action = BabyAction::create([
            'baby_id' => $this->baby_id,
            'baby_action_type_id' => $this->baby_action_type_id,
            'started_at' => $this->started_at,
            'finished_at' => $this->isInstantAction() ? null : ($this->finished_at ?: null),
        ]);

        if ($this->isEatAction() && $this->food_type !== null) {
            $action->eatDetail()->create([
                'food_type' => $this->food_type,
            ]);
        }

        if ($this->isTemperatureAction()) {
            $action->temperatureDetail()->create([
                'temperature' => $this->temperature,
            ]);
        }

        if ($this->isMedicationAction()) {
            $action->medicationDetail()->create([
                'medication_id' => $this->resolveMedicationId(),
                'amount_ml' => $this->amount_ml ?: null,
            ]);
        }

        $this->success('Baby action created successfully.');

        $this->redirect($this->returnUrl ?: route('baby_actions.show'), navigate: true);
    }

    public function render()
    {
        return view('livewire.pages.baby-action.create', $this->formData());
    }
}
