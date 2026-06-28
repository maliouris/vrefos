<?php

namespace App\Livewire\Pages\Dashboard;

use App\Models\Baby;
use App\Models\BabyAction;
use App\Services\LocalNotificationScheduler;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public function finishNow(BabyAction $babyAction): void
    {
        if ($babyAction->finished_at !== null) {
            return;
        }

        $babyAction->update(['finished_at' => now()]);

        session()->flash('success', 'Baby action finished.');
    }

    public function render(LocalNotificationScheduler $scheduler)
    {
        $babies = Baby::with(['babyActions' => function ($query) {
            $query->whereNull('finished_at')
                ->with('babyActionType')
                ->orderByDesc('started_at');
        }])->orderBy('name')->get();

        $nextByBaby = $this->nextReminderByBaby($scheduler);

        $cards = $babies->map(fn (Baby $baby): array => [
            'baby' => $baby,
            'age' => $this->ageLabel($baby->birth_date),
            'ongoing' => $baby->babyActions,
            'next' => $nextByBaby->get($baby->id),
        ]);

        return view('livewire.pages.dashboard.index', [
            'cards' => $cards,
            'hasBabies' => $babies->isNotEmpty(),
        ]);
    }

    /**
     * Soonest reminder per baby: the earliest future reminder, falling back to
     * the most recent overdue one when none are upcoming.
     *
     * @return Collection<int, array{title: string, fire_at: Carbon, overdue: bool}>
     */
    private function nextReminderByBaby(LocalNotificationScheduler $scheduler): Collection
    {
        return BabyAction::whereNotNull('notification_scheduled_at')
            ->with(['baby', 'babyActionType'])
            ->get()
            ->flatMap(fn (BabyAction $action) => $scheduler->upcomingFor($action)
                ->map(fn (array $planned): array => [
                    'baby_id' => $action->baby_id,
                    'title' => $planned['title'],
                    'fire_at' => $planned['fire_at'],
                ]))
            ->groupBy('baby_id')
            ->map(function (Collection $reminders): array {
                $sorted = $reminders->sortBy(fn (array $reminder): Carbon => $reminder['fire_at'])->values();

                $next = $sorted->first(fn (array $reminder): bool => $reminder['fire_at']->isFuture())
                    ?? $sorted->last();

                return [
                    'title' => $next['title'],
                    'fire_at' => $next['fire_at'],
                    'overdue' => $next['fire_at']->isPast(),
                ];
            });
    }

    private function ageLabel(?Carbon $birthDate): ?string
    {
        if ($birthDate === null) {
            return null;
        }

        $months = $birthDate->diffInMonths(now());

        if ($months < 1) {
            $days = (int) $birthDate->diffInDays(now());

            return $days <= 0 ? 'newborn' : $days.'d';
        }

        if ($months < 24) {
            return ((int) $months).' mo';
        }

        $years = (int) $birthDate->diffInYears(now());
        $remainingMonths = ((int) $months) - ($years * 12);

        return $remainingMonths > 0 ? $years.'y '.$remainingMonths.'mo' : $years.'y';
    }
}
