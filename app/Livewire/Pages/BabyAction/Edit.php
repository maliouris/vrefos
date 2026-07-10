<?php

namespace App\Livewire\Pages\BabyAction;

use App\Enums\BreastSide;
use App\Enums\FoodType;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use App\Models\Medication;
use App\Models\MedicationCategory;
use App\Services\LocalNotificationScheduler;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public BabyAction $babyAction;

    public ?int $baby_id = null;

    public ?int $baby_action_type_id = null;

    public string $started_at = '';

    public string $finished_at = '';

    public ?string $food_type = null;

    public ?string $breast_side = null;

    public ?string $temperature = null;

    public ?int $medication_id = null;

    public string $new_medication_name = '';

    public ?string $amount_ml = null;

    /** @var array<int, int> */
    public array $new_medication_category_ids = [];

    public string $new_category_name = '';

    public function mount(BabyAction $babyAction): void
    {
        $this->babyAction = $babyAction;
        $this->baby_id = $babyAction->baby_id;
        $this->baby_action_type_id = $babyAction->baby_action_type_id;
        $this->started_at = $babyAction->started_at?->format('Y-m-d\TH:i') ?? '';
        $this->finished_at = $babyAction->finished_at?->format('Y-m-d\TH:i') ?? '';
        $this->food_type = $babyAction->eatDetail?->food_type?->value;
        $this->breast_side = $babyAction->eatDetail?->breast_side?->value;
        $this->temperature = $babyAction->temperatureDetail?->temperature;
        $this->medication_id = $babyAction->medicationDetail?->medication_id;
        $this->amount_ml = $babyAction->medicationDetail?->amount_ml;
    }

    #[Computed]
    public function selectedActionType(): ?BabyActionType
    {
        return $this->baby_action_type_id ? BabyActionType::find($this->baby_action_type_id) : null;
    }

    public function isEatAction(): bool
    {
        return $this->selectedActionType?->name === 'Eat';
    }

    public function isTemperatureAction(): bool
    {
        return $this->selectedActionType?->name === 'Temperature';
    }

    public function isMedicationAction(): bool
    {
        return $this->selectedActionType?->name === 'Medication';
    }

    public function isInstantAction(): bool
    {
        return (bool) $this->selectedActionType?->is_instant;
    }

    public function toggleBaby(int $babyId): void
    {
        $this->baby_id = $this->baby_id === $babyId ? null : $babyId;
    }

    public function toggleActionType(int $actionTypeId): void
    {
        $this->baby_action_type_id = $this->baby_action_type_id === $actionTypeId ? null : $actionTypeId;
        $this->updatedBabyActionTypeId();
    }

    public function toggleFoodType(string $foodType): void
    {
        $this->food_type = $this->food_type === $foodType ? null : $foodType;
        $this->updatedFoodType();
    }

    public function toggleBreastSide(string $breastSide): void
    {
        $this->breast_side = $this->breast_side === $breastSide ? null : $breastSide;
    }

    public function toggleMedication(int $medicationId): void
    {
        $this->medication_id = $this->medication_id === $medicationId ? null : $medicationId;
        $this->new_medication_name = '';
        $this->new_medication_category_ids = [];
        $this->new_category_name = '';
    }

    public function toggleNewMedicationCategory(int $categoryId): void
    {
        if (in_array($categoryId, $this->new_medication_category_ids, true)) {
            $this->new_medication_category_ids = array_values(array_diff($this->new_medication_category_ids, [$categoryId]));
        } else {
            $this->new_medication_category_ids[] = $categoryId;
        }
    }

    public function updatedBabyActionTypeId(): void
    {
        unset($this->selectedActionType);

        if (! $this->isEatAction()) {
            $this->food_type = null;
            $this->breast_side = null;
        }

        if (! $this->isTemperatureAction()) {
            $this->temperature = null;
        }

        if (! $this->isMedicationAction()) {
            $this->medication_id = null;
            $this->new_medication_name = '';
            $this->amount_ml = null;
            $this->new_medication_category_ids = [];
            $this->new_category_name = '';
        }

        if ($this->isInstantAction()) {
            $this->finished_at = '';
        }
    }

    public function updatedFoodType(): void
    {
        if ($this->food_type !== FoodType::BreastMilk->value) {
            $this->breast_side = null;
        }
    }

    public function updatedNewMedicationName(): void
    {
        if (trim($this->new_medication_name) !== '') {
            $this->medication_id = null;
        }
    }

    public function update(LocalNotificationScheduler $scheduler): void
    {
        $this->validate([
            'baby_id' => 'required|exists:babies,id',
            'baby_action_type_id' => 'required|exists:baby_action_types,id',
            'started_at' => 'required|date',
            'finished_at' => 'nullable|date|after_or_equal:started_at',
            'food_type' => ['nullable', Rule::enum(FoodType::class)],
            'breast_side' => ['nullable', Rule::enum(BreastSide::class)],
            'temperature' => [Rule::requiredIf($this->isTemperatureAction()), 'nullable', 'numeric', 'between:30,45'],
            'medication_id' => [
                Rule::requiredIf($this->isMedicationAction() && trim($this->new_medication_name) === ''),
                'nullable',
                'exists:medications,id',
            ],
            'amount_ml' => ['nullable', 'numeric', 'min:0.01', 'max:1000'],
            'new_medication_category_ids' => 'array',
            'new_medication_category_ids.*' => 'exists:medication_categories,id',
        ], attributes: ['medication_id' => 'medication']);

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
                    'breast_side' => $this->breast_side,
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

        if ($this->isTemperatureAction() || $this->isMedicationAction()) {
            $scheduler->rescheduleFor($this->babyAction->refresh());
        }

        session()->flash('success', 'Baby action updated successfully.');
    }

    private function resolveMedicationId(): int
    {
        if ($this->medication_id !== null) {
            return $this->medication_id;
        }

        $medication = Medication::firstOrCreate(['name' => trim($this->new_medication_name)]);

        $categoryIds = collect($this->new_medication_category_ids);

        if (trim($this->new_category_name) !== '') {
            $categoryIds->push(MedicationCategory::firstOrCreate(['name' => trim($this->new_category_name)])->id);
        }

        if ($categoryIds->isNotEmpty()) {
            $medication->categories()->syncWithoutDetaching($categoryIds->unique()->all());
        }

        return $medication->id;
    }

    public function delete(): void
    {
        $this->babyAction->delete();

        session()->flash('success', 'Baby action deleted.');

        $this->redirectRoute('baby_actions.show', navigate: true);
    }

    public function render()
    {
        $babies = Baby::all()->map(fn ($b) => ['id' => $b->id, 'name' => $b->name]);
        $actionTypes = BabyActionType::all()->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]);

        $foodTypes = collect(FoodType::cases())
            ->map(fn ($case) => ['id' => $case->value, 'name' => $case->label()])
            ->all();

        $breastSides = collect(BreastSide::cases())
            ->map(fn ($case) => ['id' => $case->value, 'name' => $case->label()])
            ->all();

        $medications = Medication::orderBy('name')->get()->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])->all();

        $medicationCategories = MedicationCategory::orderBy('name')->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all();

        return view('livewire.pages.baby-action.edit', compact('babies', 'actionTypes', 'foodTypes', 'breastSides', 'medications', 'medicationCategories'));
    }
}
