<?php

namespace App\Livewire\Pages\BabyAction;

use App\Enums\BreastSide;
use App\Enums\FoodType;
use App\Models\BabyAction;
use App\Models\BabyActionType;
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

    public function mount(BabyAction $babyAction): void
    {
        $this->authorize('update', $babyAction);
        $this->babyAction = $babyAction;
        $this->baby_id = $babyAction->baby_id;
        $this->baby_action_type_id = $babyAction->baby_action_type_id;
        $this->started_at = $babyAction->started_at?->format('Y-m-d\TH:i') ?? '';
        $this->finished_at = $babyAction->finished_at?->format('Y-m-d\TH:i') ?? '';
        $this->food_type = $babyAction->eatDetail?->food_type?->value;
        $this->breast_side = $babyAction->eatDetail?->breast_side?->value;
    }

    #[Computed]
    public function isEatAction(): bool
    {
        if (! $this->baby_action_type_id) {
            return false;
        }

        return BabyActionType::find($this->baby_action_type_id)?->name === 'Eat';
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

    public function update(): void
    {
        $this->validate([
            'baby_id' => 'required|exists:babies,id',
            'baby_action_type_id' => 'required|exists:baby_action_types,id',
            'started_at' => 'required|date',
            'finished_at' => 'nullable|date|after_or_equal:started_at',
            'food_type' => ['nullable', Rule::enum(FoodType::class)],
            'breast_side' => ['nullable', Rule::enum(BreastSide::class)],
        ]);

        $this->babyAction->update([
            'baby_id' => $this->baby_id,
            'baby_action_type_id' => $this->baby_action_type_id,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at ?: null,
        ]);

        if ($this->isEatAction && $this->food_type !== null) {
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

        session()->flash('success', 'Baby action updated successfully.');
    }

    public function render()
    {
        $babies = auth()->user()->babies()->get()->map(fn ($b) => ['id' => $b->id, 'name' => $b->name]);
        $actionTypes = BabyActionType::all()->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]);

        $foodTypes = collect(FoodType::cases())
            ->map(fn ($case) => ['id' => $case->value, 'name' => $case->label()])
            ->all();

        $breastSides = collect(BreastSide::cases())
            ->map(fn ($case) => ['id' => $case->value, 'name' => $case->label()])
            ->all();

        return view('livewire.pages.baby-action.edit', compact('babies', 'actionTypes', 'foodTypes', 'breastSides'));
    }
}
