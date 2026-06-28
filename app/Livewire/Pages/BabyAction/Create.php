<?php

namespace App\Livewire\Pages\BabyAction;

use App\Enums\BreastSide;
use App\Enums\FoodType;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public ?int $baby_id = null;

    public ?int $baby_action_type_id = null;

    public string $started_at = '';

    public string $finished_at = '';

    public ?string $food_type = null;

    public ?string $breast_side = null;

    public function mount(): void
    {
        // UTC wall-clock default; the form converts it to local time for display.
        $this->started_at = now()->format('Y-m-d\TH:i');
    }

    #[Computed]
    public function isEatAction(): bool
    {
        if (! $this->baby_action_type_id) {
            return false;
        }

        return BabyActionType::find($this->baby_action_type_id)?->name === 'Eat';
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

    public function updatedBabyActionTypeId(): void
    {
        if (! $this->isEatAction) {
            $this->food_type = null;
            $this->breast_side = null;
        }
    }

    public function updatedFoodType(): void
    {
        if ($this->food_type !== FoodType::BreastMilk->value) {
            $this->breast_side = null;
        }
    }

    public function save(): void
    {
        $this->validate([
            'baby_id' => 'required|exists:babies,id',
            'baby_action_type_id' => 'required|exists:baby_action_types,id',
            'started_at' => 'required|date',
            'finished_at' => 'nullable|date|after_or_equal:started_at',
            'food_type' => ['nullable', Rule::enum(FoodType::class)],
            'breast_side' => ['nullable', Rule::enum(BreastSide::class)],
        ]);

        $action = BabyAction::create([
            'baby_id' => $this->baby_id,
            'baby_action_type_id' => $this->baby_action_type_id,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at ?: null,
        ]);

        if ($this->isEatAction && $this->food_type !== null) {
            $action->eatDetail()->create([
                'food_type' => $this->food_type,
                'breast_side' => $this->breast_side,
            ]);
        }

        session()->flash('success', 'Baby action created successfully.');

        $this->redirect(route('baby_actions.show'), navigate: true);
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

        return view('livewire.pages.baby-action.create', compact('babies', 'actionTypes', 'foodTypes', 'breastSides'));
    }
}
