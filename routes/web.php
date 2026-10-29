<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'soloinventory')->name('home');
Route::prefix('api/v1')->group(function (): void {
    Route::get('session', [SessionController::class, 'show']);
    Route::post('session', [SessionController::class, 'store'])->middleware('throttle:soloinventory-login');
    Route::delete('session', [SessionController::class, 'destroy']);
    Route::middleware('soloinventory.account')->group(function (): void {
        Route::apiResource('sections', SectionController::class)->except('show');
        Route::apiResource('categories', CategoryController::class)->except('show');
        Route::get('positions', [PositionController::class, 'index']);
        Route::get('items/{item}/photo', [InventoryItemController::class, 'photo'])->name('items.photo');
        Route::apiResource('items', InventoryItemController::class);
    });
});
