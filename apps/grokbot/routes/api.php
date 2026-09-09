<?php

use App\Http\Controllers\RuntimeCallbackController;
use Illuminate\Support\Facades\Route;

Route::middleware('tinyvm.gateway')->prefix('runtime')->group(function (): void {
    Route::post('/messages/{message:public_id}/chunks', [RuntimeCallbackController::class, 'chunk']);
    Route::post('/messages/{message:public_id}/complete', [RuntimeCallbackController::class, 'complete']);
    Route::post('/bots/{bot:public_id}/heartbeat', [RuntimeCallbackController::class, 'heartbeat']);
});
