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
        Route::get('/', [SandboxController::class, 'index']);          // List sandboxes, optionally filter by student_id
        Route::post('/', [SandboxController::class, 'store']);         // Create sandbox
        Route::get('{id}', [SandboxController::class, 'show']);        // Get single sandbox
        Route::put('{id}', [SandboxController::class, 'update']);      // Update sandbox
        Route::delete('{id}', [SandboxController::class, 'destroy']);  // Delete sandbox
        Route::post('{id}/launch', [SideHustleController::class, 'createFromSandbox']); // Promote to SideHustle
    });

    // SideHustles
    Route::prefix('sidehustles')->group(function () {
        Route::get('/', [SideHustleController::class, 'index']);          // List sidehustles, optionally filter by student_id
        Route::post('/', [SideHustleController::class, 'store']);         // Create sidehustle
        Route::get('{id}', [SideHustleController::class, 'show']);        // Get single sidehustle (with BMC, team, positions)
        Route::put('{id}', [SideHustleController::class, 'update']);      // Update sidehustle
        Route::delete('{id}', [SideHustleController::class, 'destroy']);  // Delete sidehustle
    });

    // Business Model Canvas
    Route::prefix('sidehustles/{sideHustleId}/bmc')->group(function () {
        Route::get('/', [BusinessModelCanvasController::class, 'show']);   // Get BMC for a sidehustle
        Route::put('/', [BusinessModelCanvasController::class, 'update']); // Update BMC sections
    });

    // Teams
    Route::prefix('teams')->group(function () {
        Route::get('{sideHustleId}', [TeamController::class, 'show']);                         // Get team and members for a sidehustle
        Route::post('{teamId}/members', [TeamController::class, 'addMember']);                 // Add a member to a team
        Route::delete('{teamId}/members/{memberId}', [TeamController::class, 'removeMember']); // Remove a member
    });

    // Positions
    Route::prefix('positions')->group(function () {
        Route::get('{sideHustleId}', [PositionController::class, 'index']);  // List all positions for a sidehustle
        Route::post('/', [PositionController::class, 'store']);               // Create a new position
        Route::put('{id}', [PositionController::class, 'update']);            // Update a position
        Route::delete('{id}', [PositionController::class, 'destroy']);        // Delete a position
    });
});