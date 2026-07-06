<?php

namespace Tests\Feature;

use App\Livewire\Pages\Baby\Create;
use App\Livewire\Pages\Baby\Edit;
use App\Models\Baby;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BabyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_persists_baby_and_redirects(): void
    {
        Livewire::test(Create::class)
            ->set('name', 'Lily')
            ->set('birth_date', '2026-01-15')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('babies.show'));

        $this->assertDatabaseHas('babies', ['name' => 'Lily']);
    }

    public function test_create_requires_name_but_not_birth_date(): void
    {
        Livewire::test(Create::class)
            ->call('save')
            ->assertHasErrors(['name'])
            ->assertHasNoErrors(['birth_date']);
    }

    public function test_create_persists_baby_without_birth_date(): void
    {
        Livewire::test(Create::class)
            ->set('name', 'Lily')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('babies.show'));

        $this->assertDatabaseHas('babies', ['name' => 'Lily', 'birth_date' => null]);
    }

    public function test_edit_updates_existing_baby(): void
    {
        $baby = Baby::factory()->create(['name' => 'Old']);

        Livewire::test(Edit::class, ['baby' => $baby])
            ->assertSet('name', 'Old')
            ->set('name', 'New')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertEquals('New', $baby->fresh()->name);
    }

    public function test_edit_handles_baby_without_birth_date(): void
    {
        $baby = Baby::factory()->create(['name' => 'Old', 'birth_date' => null]);

        Livewire::test(Edit::class, ['baby' => $baby])
            ->assertSet('birth_date', '')
            ->set('name', 'New')
            ->call('update')
            ->assertHasNoErrors();

        $baby->refresh();
        $this->assertEquals('New', $baby->name);
        $this->assertNull($baby->birth_date);
    }

    public function test_edit_can_clear_birth_date(): void
    {
        $baby = Baby::factory()->create();

        Livewire::test(Edit::class, ['baby' => $baby])
            ->set('birth_date', '')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertNull($baby->fresh()->birth_date);
    }
}
