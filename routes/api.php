<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SandboxController;
use App\Http\Controllers\SideHustleController;
use App\Http\Controllers\BusinessModelCanvasController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\ClassifiedPostController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PositionController;

// ==============================
// SANDBOX ROUTES
// ==============================
Route::prefix('sandboxes')->group(function () {
    Route::get('/', [SandboxController::class, 'index']);          // List sandboxes, optionally filter by student_id
    Route::post('/', [SandboxController::class, 'store']);         // Create sandbox
    Route::get('{id}', [SandboxController::class, 'show']);        // Get single sandbox
    Route::put('{id}', [SandboxController::class, 'update']);      // Update sandbox
    Route::delete('{id}', [SandboxController::class, 'destroy']);  // Delete sandbox

    Route::post('{id}/launch', [SideHustleController::class, 'createFromSandbox']);
});

// ==============================
// SIDEHUSTLE ROUTES
// ==============================
Route::prefix('sidehustles')->group(function () {
    Route::get('/', [SideHustleController::class, 'index']);          // List sidehustles, optionally filter by student_id
    Route::post('/', [SideHustleController::class, 'store']);         // Create sidehustle
    Route::get('{id}', [SideHustleController::class, 'show']);        // Get single sidehustle (with BMC, team, positions)
    Route::put('{id}', [SideHustleController::class, 'update']);      // Update sidehustle
    Route::delete('{id}', [SideHustleController::class, 'destroy']);  // Delete sidehustle
});

// ==============================
// BUSINESS MODEL CANVAS ROUTES
// ==============================
Route::prefix('sidehustles/{sideHustleId}/bmc')->group(function () {
    Route::get('/', [BusinessModelCanvasController::class, 'show']);       // Get BMC for a sidehustle
    Route::put('/', [BusinessModelCanvasController::class, 'update']);     // Update BMC sections
});

// ==============================
// TEAM ROUTES
// ==============================
Route::prefix('teams')->group(function () {
    Route::get('{sideHustleId}', [TeamController::class, 'show']);                     // Get team and members for a sidehustle
    Route::post('{teamId}/members', [TeamController::class, 'addMember']);             // Add a member to a team
    Route::delete('{teamId}/members/{memberId}', [TeamController::class, 'removeMember']); // Remove a member
});

// ==============================
// POSITION ROUTES
// ==============================
Route::prefix('positions')->group(function () {
    Route::get('{sideHustleId}', [PositionController::class, 'index']);  // List all positions for a sidehustle
    Route::post('/', [PositionController::class, 'store']);               // Create a new position
    Route::put('{id}', [PositionController::class, 'update']);            // Update a position
});

// ==============================
// CONNECTHUB ROUTES (auth required)
// ==============================
Route::middleware('auth:sanctum')->group(function () {

    // Feed
    Route::get('/feed', [FeedController::class, 'index']);                          // Get feed (newest first)
    Route::post('/feed', [FeedController::class, 'store']);                         // Create feed post
    Route::post('/feed/{feedItem}/like', [FeedController::class, 'like']);          // Like a post
    Route::post('/feed/{feedItem}/comment', [FeedController::class, 'comment']);    // Comment on a post

    // Classifieds
    Route::get('/classifieds', [ClassifiedPostController::class, 'index']);                             // List classifieds (optional ?status= filter)
    Route::post('/classifieds', [ClassifiedPostController::class, 'store']);                            // Create classified post
    Route::get('/classifieds/{classifiedPost}', [ClassifiedPostController::class, 'show']);             // Get single classified
    Route::patch('/classifieds/{classifiedPost}/status', [ClassifiedPostController::class, 'updateStatus']); // Update status

    // Messaging
    Route::get('/threads', [MessageController::class, 'indexThreads']);                         // List user's threads
    Route::post('/threads', [MessageController::class, 'storeThread']);                         // Create thread
    Route::get('/threads/{thread}/messages', [MessageController::class, 'indexMessages']);      // Get messages in thread
    Route::post('/threads/{thread}/messages', [MessageController::class, 'storeMessage']);      // Send message
});