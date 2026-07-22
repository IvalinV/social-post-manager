<?php

use App\Http\Controllers\OAuthConnectionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
| OAuth token-broker routes (behind the panel's auth guard). The X callback path
| must match the URI registered in the X developer app: /twitter/redirect.
*/
Route::middleware('auth')->group(function () {
    Route::get('/oauth/{platform}/connect', [OAuthConnectionController::class, 'connect'])
        ->name('oauth.connect');

    Route::get('/twitter/redirect', [OAuthConnectionController::class, 'callback'])
        ->defaults('platform', 'x')
        ->name('oauth.callback.x');
});
