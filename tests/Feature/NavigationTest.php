<?php

namespace Tests\Feature;

use App\Livewire\Pages\Baby\Create;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_bottom_tab_bar_links_the_main_sections(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Main"', false)
            ->assertSee(route('baby_actions.show'), false)
            ->assertSee(route('planning.show'), false)
            ->assertSee(route('medications.show'), false)
            ->assertSee('aria-label="More"', false);
    }

    public function test_current_section_is_marked_in_the_tab_bar(): void
    {
        $this->get(route('planning.show'))
            ->assertSee('aria-current="page"', false);
    }

    public function test_detail_pages_show_a_back_button_and_top_level_pages_a_menu_button(): void
    {
        $this->get(route('babies.create'))->assertSee('aria-label="Back"', false);
        $this->get(route('dashboard'))->assertSee('aria-label="Open menu"', false)
            ->assertDontSee('aria-label="Back"', false);
    }

    public function test_saving_a_baby_shows_a_toast_instead_of_a_flash_alert(): void
    {
        Livewire::test(Create::class)
            ->set('name', 'Maria')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('babies', ['name' => 'Maria']);
        $this->assertSame('Baby created successfully.', session('mary.toast.title'));
        $this->assertNull(session('success'));
    }
}
