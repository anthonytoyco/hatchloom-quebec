<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClassifiedPostController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\MessageController;

Route::middleware('auth:sanctum')->group(function () {

    // Feed
    Route::get('/feed', [FeedController::class, 'index']);
    Route::post('/feed', [FeedController::class, 'store']);
    Route::post('/feed/{feedItem}/like', [FeedController::class, 'like']);
    Route::post('/feed/{feedItem}/comment', [FeedController::class, 'comment']);

    // Classifieds
    Route::get('/classifieds', [ClassifiedPostController::class, 'index']);
    Route::post('/classifieds', [ClassifiedPostController::class, 'store']);
    Route::get('/classifieds/{classifiedPost}', [ClassifiedPostController::class, 'show']);
    Route::patch('/classifieds/{classifiedPost}/status', [ClassifiedPostController::class, 'updateStatus']);

    // Messaging
    Route::get('/threads', [MessageController::class, 'indexThreads']);
    Route::post('/threads', [MessageController::class, 'storeThread']);
    Route::get('/threads/{thread}/messages', [MessageController::class, 'indexMessages']);
    Route::post('/threads/{thread}/messages', [MessageController::class, 'storeMessage']);
});