<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Author\PaperController;
use App\Http\Controllers\Api\Editorial\ReviewController as EditorialReviewController;
use App\Http\Controllers\Api\Reviewer\ReviewController as ReviewerReviewController;
use App\Http\Controllers\Api\Admin\UserManagementController;

/*
|--------------------------------------------------------------------------
| Public Routes - No CSRF, No Auth
|--------------------------------------------------------------------------
*/

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Protected Routes with Sanctum Token
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'changePassword']);

    /*
    |--------------------------------------------------------------------------
    | Author Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:author'])->prefix('author')->group(function () {
        Route::get('/dashboard', function () {
            return response()->json([
                'message' => 'Author Dashboard',
                'stats' => [
                    'total_papers' => \App\Models\Paper::where('submitted_by', request()->user()->id)->count(),
                    'under_review' => \App\Models\Paper::where('submitted_by', request()->user()->id)->where('status', 'under_review')->count(),
                    'accepted' => \App\Models\Paper::where('submitted_by', request()->user()->id)->where('status', 'accepted')->count(),
                ]
            ]);
        });
        
        Route::get('/categories', [PaperController::class, 'categories']);
        Route::get('/papers', [PaperController::class, 'index']);
        Route::post('/papers', [PaperController::class, 'store']);
        Route::get('/papers/{paper}', [PaperController::class, 'show']);
        Route::put('/papers/{paper}', [PaperController::class, 'update']);
        Route::delete('/papers/{paper}', [PaperController::class, 'destroy']);
        Route::post('/papers/{paper}/submit', [PaperController::class, 'submit']);
        Route::post('/papers/{paper}/withdraw', [PaperController::class, 'withdraw']);
        Route::post('/papers/{paper}/files', [PaperController::class, 'uploadFile']);
        Route::post('/papers/{paper}/submit-revision', [PaperController::class, 'submitRevision']);
    });

    /*
    |--------------------------------------------------------------------------
    | Editorial Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:editorial'])->prefix('editorial')->group(function () {
        Route::get('/dashboard', [EditorialReviewController::class, 'dashboard']);
        Route::get('/papers', [EditorialReviewController::class, 'papers']);
        Route::get('/papers/{id}', [EditorialReviewController::class, 'showPaper']);
        Route::post('/papers/{paperId}/start-round', [EditorialReviewController::class, 'startReviewRound']);
        Route::get('/review-rounds/{roundId}/reviews', [EditorialReviewController::class, 'getReviews']);
        Route::get('/available-reviewers', [EditorialReviewController::class, 'availableReviewers']);
        Route::post('/review-rounds/{roundId}/assign-reviewers', [EditorialReviewController::class, 'assignReviewers']);
        Route::post('/review-rounds/{roundId}/decision', [EditorialReviewController::class, 'makeDecision']);
    });

    /*
    |--------------------------------------------------------------------------
    | Reviewer Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:reviewer'])->prefix('reviewer')->group(function () {
        Route::get('/dashboard', [ReviewerReviewController::class, 'dashboard']);
        Route::get('/reviews', [ReviewerReviewController::class, 'index']);
        Route::get('/reviews/{id}', [ReviewerReviewController::class, 'show']);
        Route::post('/reviews/{id}/accept', [ReviewerReviewController::class, 'accept']);
        Route::post('/reviews/{id}/decline', [ReviewerReviewController::class, 'decline']);
        Route::post('/reviews/{id}/submit', [ReviewerReviewController::class, 'submit']);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:admin'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [UserManagementController::class, 'dashboard']);
        Route::get('/users', [UserManagementController::class, 'index']);
        Route::post('/users', [UserManagementController::class, 'store']);
        Route::get('/users/{id}', [UserManagementController::class, 'show']);
        Route::put('/users/{id}', [UserManagementController::class, 'update']);
        Route::delete('/users/{id}', [UserManagementController::class, 'destroy']);
        Route::get('/roles', [UserManagementController::class, 'roles']);
    });
});