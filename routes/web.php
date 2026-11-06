<?php

use App\Livewire\Pages\Baby\Create as BabyCreate;
use App\Livewire\Pages\Baby\Edit as BabyEdit;
use App\Livewire\Pages\Baby\Index as BabyIndex;
use App\Livewire\Pages\BabyAction\Create as BabyActionCreate;
use App\Livewire\Pages\BabyAction\Edit as BabyActionEdit;
use App\Livewire\Pages\BabyAction\Index as BabyActionIndex;
use App\Livewire\Pages\Dashboard\Index as Dashboard;
use App\Livewire\Pages\Medication\Create as MedicationCreate;
use App\Livewire\Pages\Medication\Edit as MedicationEdit;
use App\Livewire\Pages\Medication\Index as MedicationIndex;
use App\Livewire\Pages\Notification\Index as NotificationIndex;
use App\Livewire\Pages\Planning\Index as PlanningIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', Dashboard::class)->name('dashboard');

Route::get('/babies', BabyIndex::class)->name('babies.show');
Route::get('/babies/add', BabyCreate::class)->name('babies.create');
Route::get('/babies/{baby}/edit', BabyEdit::class)->name('babies.edit');

Route::get('/baby_actions', BabyActionIndex::class)->name('baby_actions.show');
Route::get('/baby_actions/add', BabyActionCreate::class)->name('baby_actions.create');
Route::get('/baby_actions/{babyAction}/edit', BabyActionEdit::class)->name('baby_actions.edit');

Route::get('/medications', MedicationIndex::class)->name('medications.show');
Route::get('/medications/add', MedicationCreate::class)->name('medications.create');
Route::get('/medications/{medication}/edit', MedicationEdit::class)->name('medications.edit');

Route::get('/planning', PlanningIndex::class)->name('planning.show');
Route::get('/notifications', NotificationIndex::class)->name('notifications.show');

Route::get('/terms-and-conditions', fn () => view('legal.terms-and-conditions'));
