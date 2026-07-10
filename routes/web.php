<?php

use App\Livewire\Pages\Baby\Create as BabyCreate;
use App\Livewire\Pages\Baby\Edit as BabyEdit;
use App\Livewire\Pages\Baby\Index as BabyIndex;
use App\Livewire\Pages\BabyAction\Create as BabyActionCreate;
use App\Livewire\Pages\BabyAction\Edit as BabyActionEdit;
use App\Livewire\Pages\BabyAction\Index as BabyActionIndex;
use App\Livewire\Pages\Dashboard\Index as Dashboard;
use App\Livewire\Pages\Medication\Edit as MedicationEdit;
use App\Livewire\Pages\Medication\Index as MedicationIndex;
use App\Livewire\Pages\NotificationSettings\Index;
use Illuminate\Support\Facades\Route;

Route::get('/', Dashboard::class)->name('dashboard');

Route::get('/babies', BabyIndex::class)->name('babies.show');
Route::get('/babies/add', BabyCreate::class)->name('babies.create');
Route::get('/babies/{baby}/edit', BabyEdit::class)->name('babies.edit');

Route::get('/baby_actions', BabyActionIndex::class)->name('baby_actions.show');
Route::get('/baby_actions/add', BabyActionCreate::class)->name('baby_actions.create');
Route::get('/baby_actions/{babyAction}/edit', BabyActionEdit::class)->name('baby_actions.edit');

Route::get('/notification-settings', Index::class)->name('notification-settings.edit');

Route::get('/medications', MedicationIndex::class)->name('medications.show');
Route::get('/medications/{medication}/edit', MedicationEdit::class)->name('medications.edit');

Route::get('/terms-and-conditions', fn () => view('legal.terms-and-conditions'));
