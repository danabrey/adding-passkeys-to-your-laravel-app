<?php

use Illuminate\Support\Facades\Route;

Route::get('/passkeys/register', [\App\Http\Controllers\Api\PasskeyController::class, 'registerOptions'])
    ->middleware('auth:sanctum');

Route::get('/passkeys/authenticate', [\App\Http\Controllers\Api\PasskeyController::class, 'authenticateOptions']);
