<?php

use App\Livewire\Pages\Baby\Index as BabyIndex;
use App\Livewire\Pages\Baby\Create as BabyCreate;
use App\Livewire\Pages\Baby\Edit as BabyEdit;
use App\Livewire\Pages\BabyAction\Index as BabyActionIndex;
use App\Livewire\Pages\BabyAction\Create as BabyActionCreate;
use App\Livewire\Pages\BabyAction\Edit as BabyActionEdit;
use App\Livewire\Pages\Profile\Edit as ProfileEdit;
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

    Route::get('/babies', BabyIndex::class)->name('babies.show');
    Route::get('/babies/add', BabyCreate::class)->name('babies.create');
    Route::get('/babies/{baby}/edit', BabyEdit::class)->name('babies.edit');

    Route::get('/baby_actions', BabyActionIndex::class)->name('baby_actions.show');
    Route::get('/baby_actions/add', BabyActionCreate::class)->name('baby_actions.create');
    Route::get('/baby_actions/{babyAction}/edit', BabyActionEdit::class)->name('baby_actions.edit');

    Route::get('/profile', ProfileEdit::class)->name('profile.edit');

    Route::get('/notification-settings', \App\Livewire\Pages\NotificationSettings\Index::class)->name('notification-settings.edit');
});

Route::get('/terms-and-conditions', fn () => view('legal.terms-and-conditions'));

require __DIR__.'/auth.php';
