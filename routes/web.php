<?php

use App\Contracts\BeamsClient;
use App\Http\Controllers\BabyActionController;
use App\Http\Controllers\BabyController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', function () {
//    return Inertia::render('Dashboard');
    return redirect()->route('babies.show');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/pusher/beams-auth', function (Request $request) {
        $userID = $request->user()->id; // If you use a different auth system, do your checks here
        $userIDInQueryParam = $request->input('user_id');

        if ($userID != $userIDInQueryParam) {
            return response('Inconsistent request', 401);
        } else {
            $beamsToken = App::get(BeamsClient::class)->generateToken($userID);
            return response()->json($beamsToken);
        }
    })->name('pusher.beams.auth');


    Route::prefix('babies')->group(function () {
        Route::get('add', [BabyController::class, 'create'])->name('babies.create');
        Route::patch('{baby}/update', [BabyController::class, 'update'])->name('babies.update');
        Route::get('{baby}/edit', [BabyController::class, 'edit'])->name('babies.edit');
        Route::post('', [BabyController::class, 'store'])->name('babies.store');
        Route::get('', [BabyController::class, 'show'])->name('babies.show');
    });

    Route::prefix('baby_actions')->group(function () {
        Route::get('add', [BabyActionController::class, 'create'])->name('baby_actions.create');
        Route::patch('{babyAction}/update', [BabyActionController::class, 'update'])->name('baby_actions.update');
        Route::get('{babyAction}/edit', [BabyActionController::class, 'edit'])->name('baby_actions.edit');
        Route::post('', [BabyActionController::class, 'store'])->name('baby_actions.store');
        Route::get('', [BabyActionController::class, 'show'])->name('baby_actions.show');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/terms-and-conditions', function () {
        return Inertia::render('Legal/TermsAndConditions');
    });
});

require __DIR__.'/auth.php';
