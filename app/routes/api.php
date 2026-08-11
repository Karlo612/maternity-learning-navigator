<?php

use App\Http\Controllers\Api\ClassificationController;
use App\Http\Controllers\Api\DemoClassificationController;
use App\Http\Controllers\Api\DemoExplanationController;
use App\Http\Controllers\Api\DemoSampleController;
use App\Http\Controllers\Api\ExplanationController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ModelCardController;
use App\Http\Controllers\Api\OpenApiController;
use App\Http\Controllers\Api\ResourceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class);
    Route::get('/resources', ResourceController::class);
    Route::get('/model-card', ModelCardController::class);
    Route::get('/openapi.json', OpenApiController::class);
    Route::get('/demo/samples', DemoSampleController::class);
    Route::post('/demo/classifications', DemoClassificationController::class)
        ->middleware('throttle:demo-classifications');
    Route::post('/demo/classifications/{requestId}/explanation', DemoExplanationController::class)
        ->middleware('throttle:demo-explanations');

    Route::post('/classifications', ClassificationController::class)
        ->middleware('throttle:classifications');
    Route::post('/classifications/{requestId}/explanation', ExplanationController::class)
        ->middleware('throttle:explanations');
    Route::post('/feedback', FeedbackController::class)
        ->middleware('throttle:feedback');
});
