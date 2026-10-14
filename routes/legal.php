<?php

declare(strict_types=1);

use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\Webhooks\MetaCallbackController;
use Illuminate\Support\Facades\Route;

Route::get('privacy', [LegalPageController::class, 'privacy'])->name('legal.privacy');
Route::get('terms', [LegalPageController::class, 'terms'])->name('legal.terms');
Route::get('data-deletion', [LegalPageController::class, 'dataDeletion'])->name('legal.data-deletion');
Route::get('data-deletion/status/{code}', [LegalPageController::class, 'dataDeletionStatus'])
    ->where('code', '[A-Za-z0-9]+')
    ->name('legal.data-deletion.status');

Route::middleware('throttle:60,1')->group(function () {
    Route::post('meta/data-deletion', [MetaCallbackController::class, 'dataDeletion'])->name('meta.data-deletion');
    Route::post('meta/deauthorize', [MetaCallbackController::class, 'deauthorize'])->name('meta.deauthorize');
});
