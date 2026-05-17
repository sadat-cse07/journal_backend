<?php

use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Author\DashboardController as AuthorDashboardController;
use App\Http\Controllers\Api\Author\PaperController;
use App\Http\Controllers\Api\Editorial\ReviewController as EditorialReviewController;
use App\Http\Controllers\Api\FileDownloadController;
use App\Http\Controllers\Api\ImageUploadController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PublicPaperController;
use App\Http\Controllers\Api\Reviewer\ReviewController as ReviewerReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES - No Authentication Required
|--------------------------------------------------------------------------
*/

// Auth routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Public papers - NO AUTH REQUIRED
Route::get('/public/papers', [PublicPaperController::class, 'index']);
Route::get('/public/papers-count', [PublicPaperController::class, 'count']);
Route::get('/public/papers/{id}', [PublicPaperController::class, 'show']);
Route::get('/public/categories', [PublicPaperController::class, 'categories']);

// Public file download
Route::get('/files/{fileId}/download', [FileDownloadController::class, 'download']);

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES - Authentication Required
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Auth Management
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/password', [AuthController::class, 'changePassword']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/read-all', [NotificationController::class, 'markAllAsRead']);
    });

    // Image Upload
    Route::post('/upload-image', [ImageUploadController::class, 'upload']);

    // Author Routes
    Route::middleware(['role:author'])->prefix('author')->group(function () {
        Route::get('/dashboard', [AuthorDashboardController::class, 'index']);
        Route::get('/categories', [PaperController::class, 'categories']);
        Route::get('/papers', [PaperController::class, 'index']);
        Route::post('/papers', [PaperController::class, 'store']);
        Route::get('/papers/{paper}', [PaperController::class, 'show']);
        Route::put('/papers/{paper}', [PaperController::class, 'update']);
        Route::delete('/papers/{paper}', [PaperController::class, 'destroy']);
        Route::delete('/papers/{paper}/files/{fileId}', [PaperController::class, 'deleteFile']);
        Route::post('/papers/{paper}/submit', [PaperController::class, 'submit']);
        Route::post('/papers/{paper}/withdraw', [PaperController::class, 'withdraw']);
        Route::post('/papers/{paper}/files', [PaperController::class, 'uploadFile']);
        Route::post('/papers/{paper}/submit-revision', [PaperController::class, 'submitRevision']);
    });

    // Editorial Routes
    Route::middleware(['role:editorial'])->prefix('editorial')->group(function () {
        Route::get('/dashboard', [EditorialReviewController::class, 'dashboard']);
        Route::get('/papers', [EditorialReviewController::class, 'papers']);
        Route::get('/papers/{id}', [EditorialReviewController::class, 'showPaper']);
        Route::post('/papers/{paperId}/start-round', [EditorialReviewController::class, 'startReviewRound']);
        Route::get('/review-rounds/{roundId}/reviews', [EditorialReviewController::class, 'getReviews']);
        Route::get('/available-reviewers', [EditorialReviewController::class, 'availableReviewers']);
        Route::post('/review-rounds/{roundId}/assign-reviewers', [EditorialReviewController::class, 'assignReviewers']);
        Route::post('/review-rounds/{roundId}/decision', [EditorialReviewController::class, 'makeDecision']);
        Route::post('/papers/{paperId}/desk-reject', [EditorialReviewController::class, 'deskReject']);
        Route::post('/papers/{paperId}/publish', [EditorialReviewController::class, 'publish']);
        Route::post('/papers/{paperId}/unpublish', [EditorialReviewController::class, 'unpublish']);
    });

    // Reviewer Routes
    Route::middleware(['role:reviewer'])->prefix('reviewer')->group(function () {
        Route::get('/dashboard', [ReviewerReviewController::class, 'dashboard']);
        Route::get('/reviews', [ReviewerReviewController::class, 'index']);
        Route::get('/reviews/{id}', [ReviewerReviewController::class, 'show']);
        Route::post('/reviews/{id}/accept', [ReviewerReviewController::class, 'accept']);
        Route::post('/reviews/{id}/decline', [ReviewerReviewController::class, 'decline']);
        Route::post('/reviews/{id}/submit', [ReviewerReviewController::class, 'submit']);
    });

    // Admin Routes
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

// Password Reset Web Route
Route::get('/reset-password/{token}', function ($token) {
    $email = request('email');
    $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
    return redirect($frontendUrl . '/auth/reset-password?token=' . $token . '&email=' . $email);
})->name('password.reset');
