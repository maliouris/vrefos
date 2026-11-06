<?php

namespace Tests\Feature;

use App\Enums\FeverLevel;
use App\Enums\FoodType;
use App\Livewire\Pages\Planning\Index;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionEatDetail;
use App\Models\BabyActionMedicationDetail;
use App\Models\BabyActionTemperatureDetail;
use App\Models\BabyActionType;
use App\Models\Medication;
use App\Models\Plan;
use App\Models\PlanVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class PlanningCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_action_appears_as_an_event(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $action = BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $type->id,
            'started_at' => now(),
            'finished_at' => now()->addMinutes(30),
        ]);

        $events = Livewire::test(Index::class)->instance()->getEvents(now()->subDay()->toDateString(), now()->addDay()->toDateString());

        $this->assertContains('action-'.$action->id, array_column($events, 'id'));
    }

    public function test_planned_slot_appears_as_an_event(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = Plan::factory()->create(['baby_action_type_id' => $type->id, 'all_children' => true]);
        PlanVersion::factory()->daily(['08:00'])->for($plan)->create([
            'effective_from' => now()->subWeek()->toDateString(),
        ]);

        $events = Livewire::test(Index::class)->instance()->getEvents(now()->toDateString(), now()->toDateString());

        $planned = array_filter($events, fn ($e) => $e['calendarId'] === 'planned');
        $this->assertNotEmpty($planned);
        $this->assertSame($plan->id, array_values($planned)[0]['planId']);
    }

    public function test_events_use_iso_8601_instants_with_an_offset(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $action = BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $type->id,
            'started_at' => now()->startOfHour(),
            'finished_at' => now()->startOfHour()->addMinutes(30),
        ]);

        $events = Livewire::test(Index::class)->instance()->getEvents(now()->subDay()->toDateString(), now()->addDay()->toDateString());
        $event = collect($events)->firstWhere('id', 'action-'.$action->id);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $event['start']);
        $this->assertTrue(Carbon::parse($event['start'])->eq($action->started_at));
        $this->assertTrue(Carbon::parse($event['end'])->eq($action->finished_at));
    }

    public function test_planned_slot_events_are_iso_8601_instants(): void
    {
        Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = Plan::factory()->create(['baby_action_type_id' => $type->id, 'all_children' => true]);
        PlanVersion::factory()->daily(['08:00'])->for($plan)->create([
            'effective_from' => now()->subWeek()->toDateString(),
        ]);

        $events = Livewire::test(Index::class)->set('deviceTimezone', 'UTC')->instance()->getEvents(now()->toDateString(), now()->toDateString());
        $planned = array_values(array_filter($events, fn ($e) => $e['calendarId'] === 'planned'));

        $this->assertSame(now('UTC')->setTime(8, 0)->toIso8601String(), Carbon::parse($planned[0]['start'])->utc()->toIso8601String());
        $this->assertMatchesRegularExpression('/[+-]\d{2}:\d{2}$/', $planned[0]['end']);
    }

    public function test_events_use_the_timezone_passed_by_the_browser(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $action = BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $type->id,
            'started_at' => '2026-01-15 10:00:00',
            'finished_at' => '2026-01-15 10:30:00',
        ]);

        $component = Livewire::test(Index::class)->set('deviceTimezone', 'UTC')->instance();

        $athens = collect($component->getEvents('2026-01-15', '2026-01-15', 'Europe/Athens'))->firstWhere('id', 'action-'.$action->id);
        $this->assertStringEndsWith('+02:00', $athens['start']);
        $this->assertStringStartsWith('2026-01-15T12:00:00', $athens['start']);

        $fallback = collect($component->getEvents('2026-01-15', '2026-01-15', 'Not/AZone'))->firstWhere('id', 'action-'.$action->id);
        $this->assertSame('2026-01-15T10:00:00+00:00', $fallback['start']);
    }

    public function test_plans_list_respects_the_selected_baby(): void
    {
        $theo = Baby::factory()->create();
        $mia = Baby::factory()->create();
        $type = BabyActionType::factory()->create();

        $everyone = Plan::factory()->create(['baby_action_type_id' => $type->id, 'all_children' => true]);
        $miaOnly = Plan::factory()->create(['baby_action_type_id' => $type->id, 'all_children' => false]);
        $miaOnly->babies()->attach($mia->id);

        $theoView = Livewire::test(Index::class)->call('toggleActiveBaby', $theo->id);
        $this->assertSame([$everyone->id], $theoView->viewData('plans')->pluck('id')->all());

        $miaView = Livewire::test(Index::class)->call('toggleActiveBaby', $mia->id);
        $this->assertEqualsCanonicalizing([$everyone->id, $miaOnly->id], $miaView->viewData('plans')->pluck('id')->all());

        $all = $theoView->call('showAllBabies');
        $this->assertEqualsCanonicalizing([$everyone->id, $miaOnly->id], $all->viewData('plans')->pluck('id')->all());
    }

    public function test_calendar_is_told_to_refresh_when_data_changes(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = Plan::factory()->create(['baby_action_type_id' => $type->id, 'all_children' => true]);
        PlanVersion::factory()->daily(['08:00'])->for($plan)->create(['effective_from' => now()->subWeek()->toDateString()]);

        Livewire::test(Index::class)
            ->call('toggleActiveBaby', $baby->id)
            ->assertDispatched('planning-events-changed');

        Livewire::test(Index::class)->call('showAllBabies')->assertDispatched('planning-events-changed');
        Livewire::test(Index::class)->call('toggleEnabled', $plan->id)->assertDispatched('planning-events-changed');
        Livewire::test(Index::class)
            ->set('deletingPlanId', $plan->id)
            ->call('deletePlan')
            ->assertDispatched('planning-events-changed');
    }

    public function test_instant_actions_get_a_minimum_visible_duration(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $action = BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $type->id,
            'started_at' => now()->startOfHour(),
            'finished_at' => now()->startOfHour(),
        ]);

        $events = Livewire::test(Index::class)->instance()->getEvents(now()->subDay()->toDateString(), now()->addDay()->toDateString());
        $event = collect($events)->firstWhere('id', 'action-'.$action->id);

        $this->assertEquals(15, Carbon::parse($event['start'])->diffInMinutes(Carbon::parse($event['end'])));
    }

    public function test_a_done_slot_is_shown_once_as_a_checked_action(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create(['name' => 'Eat']);
        $plan = Plan::factory()->create(['baby_action_type_id' => $type->id, 'all_children' => true, 'device_timezone' => 'UTC']);
        PlanVersion::factory()->daily(['08:00'])->for($plan)->create(['effective_from' => now()->subWeek()->toDateString()]);

        $action = BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $type->id,
            'started_at' => now('UTC')->subDay()->setTime(8, 5),
            'finished_at' => now('UTC')->subDay()->setTime(8, 30),
        ]);

        $day = now('UTC')->subDay()->toDateString();
        $events = collect(Livewire::test(Index::class)->set('deviceTimezone', 'UTC')->instance()->getEvents($day, $day));

        $this->assertStringStartsWith('✓ ', $events->firstWhere('id', 'action-'.$action->id)['title']);
        $this->assertCount(0, $events->where('calendarId', 'planned'));
    }

    public function test_events_report_whether_they_have_a_real_end_time(): void
    {
        $baby = Baby::factory()->create();
        $eat = BabyActionType::factory()->create(['name' => 'Eat', 'is_instant' => false]);
        $temperature = BabyActionType::factory()->create(['name' => 'Temperature', 'is_instant' => true]);
        $start = now()->startOfHour();

        $finished = BabyAction::factory()->for($baby)->create(['baby_action_type_id' => $eat->id, 'started_at' => $start, 'finished_at' => $start->copy()->addMinutes(25)]);
        $ongoing = BabyAction::factory()->for($baby)->create(['baby_action_type_id' => $eat->id, 'started_at' => $start->copy()->subHour(), 'finished_at' => null]);
        $sameMinute = BabyAction::factory()->for($baby)->create(['baby_action_type_id' => $eat->id, 'started_at' => $start->copy()->subHours(2), 'finished_at' => $start->copy()->subHours(2)]);
        $instant = BabyAction::factory()->for($baby)->create(['baby_action_type_id' => $temperature->id, 'started_at' => $start->copy()->subHours(3), 'finished_at' => $start->copy()->subHours(3)->addMinute()]);

        $events = collect(Livewire::test(Index::class)->instance()->getEvents(now()->subDay()->toDateString(), now()->addDay()->toDateString()));

        $this->assertTrue($events->firstWhere('id', 'action-'.$finished->id)['hasEnd']);
        $this->assertFalse($events->firstWhere('id', 'action-'.$ongoing->id)['hasEnd']);
        $this->assertFalse($events->firstWhere('id', 'action-'.$sameMinute->id)['hasEnd']);
        $this->assertFalse($events->firstWhere('id', 'action-'.$instant->id)['hasEnd']);
    }

    public function test_planned_slots_only_have_an_end_when_the_plan_has_a_duration(): void
    {
        Baby::factory()->create();
        $type = BabyActionType::factory()->create();

        $withoutDuration = Plan::factory()->create(['baby_action_type_id' => $type->id, 'all_children' => true, 'device_timezone' => 'UTC']);
        PlanVersion::factory()->daily(['08:00'])->for($withoutDuration)->create(['effective_from' => now()->subWeek()->toDateString(), 'duration_minutes' => null]);

        $withDuration = Plan::factory()->create(['baby_action_type_id' => $type->id, 'all_children' => true, 'device_timezone' => 'UTC']);
        PlanVersion::factory()->daily(['09:00'])->for($withDuration)->create(['effective_from' => now()->subWeek()->toDateString(), 'duration_minutes' => 30]);

        $day = now('UTC')->addDay()->toDateString();
        $planned = collect(Livewire::test(Index::class)->set('deviceTimezone', 'UTC')->instance()->getEvents($day, $day))->where('calendarId', 'planned');

        $this->assertFalse($planned->firstWhere('planId', $withoutDuration->id)['hasEnd']);
        $this->assertTrue($planned->firstWhere('planId', $withDuration->id)['hasEnd']);
    }

    public function test_tapping_an_action_opens_a_details_sheet_with_its_details(): void
    {
        $baby = Baby::factory()->create(['name' => 'Theo']);
        $type = BabyActionType::factory()->create(['name' => 'Eat', 'is_instant' => false]);
        $action = BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $type->id,
            'started_at' => '2026-01-15 10:00:00',
            'finished_at' => '2026-01-15 10:25:00',
        ]);
        BabyActionEatDetail::create(['baby_action_id' => $action->id, 'food_type' => FoodType::BreastMilkLeft]);

        Livewire::test(Index::class)
            ->set('deviceTimezone', 'Europe/Athens')
            ->call('showEvent', 'action-'.$action->id, '✓ Eat — Theo', '2026-01-15T10:00:00Z', '2026-01-15T10:25:00Z')
            ->assertSet('showEventModal', true)
            ->assertSet('selectedEvent.kind', 'action')
            ->assertSet('selectedEvent.title', '✓ Eat — Theo')
            ->assertSet('selectedEvent.when', 'Thu 15 Jan · 12:00 – 12:25')
            ->assertSet('selectedEvent.lines', ['Theo', 'Breast Milk (Left)'])
            ->assertSet('selectedEvent.url', route('baby_actions.edit', $action))
            ->assertSee('Open action');
    }

    public function test_the_sheet_lists_temperature_medication_and_ongoing_details(): void
    {
        $baby = Baby::factory()->create(['name' => 'Mia']);
        $temperatureType = BabyActionType::factory()->create(['name' => 'Temperature', 'is_instant' => true]);
        $medicationType = BabyActionType::factory()->create(['name' => 'Medication', 'is_instant' => true]);
        $sleepType = BabyActionType::factory()->create(['name' => 'Sleep', 'is_instant' => false]);
        $medication = Medication::create(['name' => 'Paracetamol']);

        $temperature = BabyAction::factory()->for($baby)->create(['baby_action_type_id' => $temperatureType->id, 'started_at' => now(), 'finished_at' => now()]);
        BabyActionTemperatureDetail::create(['baby_action_id' => $temperature->id, 'temperature' => 38.2]);

        $dose = BabyAction::factory()->for($baby)->create(['baby_action_type_id' => $medicationType->id, 'started_at' => now(), 'finished_at' => now()]);
        BabyActionMedicationDetail::create(['baby_action_id' => $dose->id, 'medication_id' => $medication->id, 'amount_ml' => 5]);

        $sleep = BabyAction::factory()->for($baby)->create(['baby_action_type_id' => $sleepType->id, 'started_at' => now()->subMinutes(30), 'finished_at' => null]);

        $component = Livewire::test(Index::class)->set('deviceTimezone', 'UTC');

        $component->call('showEvent', 'action-'.$temperature->id, 'Temperature — Mia', now()->toIso8601String());
        $this->assertSame('38.2 °C · '.FeverLevel::fromTemperature(38.2)->label(), $component->get('selectedEvent.lines')[1]);

        $component->call('showEvent', 'action-'.$dose->id, 'Medication — Mia', now()->toIso8601String());
        $this->assertSame('Paracetamol · 5 ml', $component->get('selectedEvent.lines')[1]);

        $component->call('showEvent', 'action-'.$sleep->id, 'Sleep — Mia', now()->subMinutes(30)->toIso8601String());
        $this->assertSame(['Mia', 'Ongoing'], $component->get('selectedEvent.lines'));
        $this->assertStringNotContainsString('–', $component->get('selectedEvent.when'));
    }

    public function test_tapping_a_planned_slot_shows_its_status_and_recurrence_and_opens_the_plan(): void
    {
        $baby = Baby::factory()->create(['name' => 'Theo']);
        $type = BabyActionType::factory()->create(['name' => 'Eat']);
        $plan = Plan::factory()->create(['baby_action_type_id' => $type->id, 'all_children' => true]);
        PlanVersion::factory()->for($plan)->create(['effective_from' => now()->subWeek()->toDateString(), 'interval_minutes' => 180]);

        $component = Livewire::test(Index::class)
            ->set('deviceTimezone', 'UTC')
            ->call('showEvent', 'plan-'.$plan->id.'-'.$baby->id.'-1768471200', '✗ Eat — Theo', '2026-01-15T10:00:00Z')
            ->assertSet('showEventModal', true)
            ->assertSet('selectedEvent.kind', 'plan')
            ->assertSet('selectedEvent.lines', ['Missed', 'Theo', 'Every 3 hours'])
            ->assertSet('selectedEvent.url', route('baby_actions.create', ['baby' => $baby->id, 'type' => $type->id]))
            ->assertSee('Edit plan')
            ->assertSee('Log now');

        $component->call('openPlanFromEvent')
            ->assertSet('showEventModal', false)
            ->assertSet('selectedEvent', [])
            ->assertSet('showModal', true)
            ->assertSet('editingId', $plan->id);
    }

    public function test_the_sheet_ignores_unknown_or_malformed_events(): void
    {
        Livewire::test(Index::class)
            ->call('showEvent', 'action-9999', 'Eat — Theo', now()->toIso8601String())
            ->assertSet('showEventModal', false)
            ->call('showEvent', 'plan-9999-1-1', 'Eat — Theo', now()->toIso8601String())
            ->assertSet('showEventModal', false)
            ->call('showEvent', 'something-else', 'x', now()->toIso8601String())
            ->assertSet('showEventModal', false)
            ->call('showEvent', 'action-1', 'x', 'not a date')
            ->assertSet('showEventModal', false);
    }

    public function test_closing_the_sheet_clears_the_selected_event(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $action = BabyAction::factory()->for($baby)->create(['baby_action_type_id' => $type->id, 'started_at' => now(), 'finished_at' => now()->addMinutes(10)]);

        Livewire::test(Index::class)
            ->call('showEvent', 'action-'.$action->id, 'Event', now()->toIso8601String())
            ->assertSet('showEventModal', true)
            ->set('showEventModal', false)
            ->assertSet('selectedEvent', []);
    }

    public function test_baby_filter_restricts_events_to_selected_baby(): void
    {
        $babyA = Baby::factory()->create();
        $babyB = Baby::factory()->create();
        $type = BabyActionType::factory()->create();

        $actionA = BabyAction::factory()->for($babyA)->create(['baby_action_type_id' => $type->id, 'started_at' => now()]);
        BabyAction::factory()->for($babyB)->create(['baby_action_type_id' => $type->id, 'started_at' => now()]);

        $events = Livewire::test(Index::class)->set('activeBabyIds', [$babyA->id])->instance()->getEvents(now()->subDay()->toDateString(), now()->addDay()->toDateString());

        $ids = array_column($events, 'id');
        $this->assertContains('action-'.$actionA->id, $ids);
        $this->assertCount(1, array_filter($ids, fn ($id) => str_starts_with($id, 'action-')));
    }
}
