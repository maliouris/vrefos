<?php

use App\Services\BeamsClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/babies'));

Route::middleware('auth')->group(function () {
    Route::get('/pusher/beams-auth', function (Request $request) {
        $userID = $request->user()->id;
        $userIDInQueryParam = $request->input('user_id');

        if ($userID != $userIDInQueryParam) {
            return response('Inconsistent request', 401);
        }

        $beamsToken = App::get(BeamsClientService::class)->generateToken($userID);
        return response()->json($beamsToken);
    })->name('pusher.beams.auth');

    Route::livewire('/babies', 'pages::baby.index')->name('babies.show');
    Route::livewire('/babies/add', 'pages::baby.create')->name('babies.create');
    Route::livewire('/babies/{baby}/edit', 'pages::baby.edit')->name('babies.edit');

    Route::livewire('/baby_actions', 'pages::baby-action.index')->name('baby_actions.show');
    Route::livewire('/baby_actions/add', 'pages::baby-action.create')->name('baby_actions.create');
    Route::livewire('/baby_actions/{babyAction}/edit', 'pages::baby-action.edit')->name('baby_actions.edit');

    Route::livewire('/profile', 'pages::profile.edit')->name('profile.edit');
});

Route::get('/terms-and-conditions', fn () => view('legal.terms-and-conditions'));

require __DIR__.'/auth.php';
