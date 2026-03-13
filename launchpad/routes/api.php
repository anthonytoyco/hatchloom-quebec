<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SandboxController;
use App\Http\Controllers\SideHustleController;
use App\Http\Controllers\BusinessModelCanvasController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\PositionController;

Route::middleware('auth:sanctum')->group(function () {

    // LaunchPad Home aggregation (Screen 200)
    Route::get('/launchpad/summary', [SideHustleController::class, 'launchpadSummary']);

    // Sandboxes
    Route::prefix('sandboxes')->group(function () {
        Route::get('/', [SandboxController::class, 'index']);
        Route::post('/', [SandboxController::class, 'store']);
        Route::get('{id}', [SandboxController::class, 'show']);
        Route::put('{id}', [SandboxController::class, 'update']);
        Route::delete('{id}', [SandboxController::class, 'destroy']);
        Route::post('{id}/launch', [SideHustleController::class, 'createFromSandbox']);
    });

    // SideHustles
    Route::prefix('sidehustles')->group(function () {
        Route::get('/', [SideHustleController::class, 'index']);
        Route::post('/', [SideHustleController::class, 'store']);
        Route::get('{id}', [SideHustleController::class, 'show']);
        Route::put('{id}', [SideHustleController::class, 'update']);
        Route::delete('{id}', [SideHustleController::class, 'destroy']);
    });

    // Business Model Canvas
    Route::prefix('sidehustles/{sideHustleId}/bmc')->group(function () {
        Route::get('/', [BusinessModelCanvasController::class, 'show']);
        Route::put('/', [BusinessModelCanvasController::class, 'update']);
    });

    // Teams
    Route::prefix('teams')->group(function () {
        Route::get('{sideHustleId}', [TeamController::class, 'show']);
        Route::post('{teamId}/members', [TeamController::class, 'addMember']);
        Route::delete('{teamId}/members/{memberId}', [TeamController::class, 'removeMember']);
    });

    // Positions
    Route::prefix('positions')->group(function () {
        Route::get('{sideHustleId}', [PositionController::class, 'index']);
        Route::post('/', [PositionController::class, 'store']);
        Route::put('{id}', [PositionController::class, 'update']);
        Route::delete('{id}', [PositionController::class, 'destroy']);
    });
});