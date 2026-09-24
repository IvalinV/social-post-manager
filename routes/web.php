<?php

use App\Http\Controllers\OAuthConnectionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
| OAuth token-broker routes (behind the panel's auth guard). Callback paths must
| match the URIs registered in each provider's developer application.
*/
Route::middleware('auth')->group(function () {
    Route::get('/oauth/{platform}/connect', [OAuthConnectionController::class, 'connect'])
        ->name('oauth.connect');

    Route::get('/twitter/redirect', [OAuthConnectionController::class, 'callback'])
        ->defaults('platform', 'x')
        ->name('oauth.callback.x');

    Route::get('/linkedin/redirect', [OAuthConnectionController::class, 'callback'])
        ->defaults('platform', 'linkedin')
        ->name('oauth.callback.linkedin');
});
