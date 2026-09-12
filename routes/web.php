<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::post('/teacher/students/{student}/reset-link', [\App\Http\Controllers\ResetLinkController::class, 'store']);
    Route::post('/teacher/classes', [\App\Http\Controllers\ClassController::class, 'store']);
    Route::get('/teacher/classes/{class}', [\App\Http\Controllers\ClassController::class, 'show']);
    Route::post('/teacher/classes/{class}/roster-import', [\App\Http\Controllers\RosterImportController::class, 'store']);

    Route::post('/admin/levels', [\App\Http\Controllers\Admin\LevelController::class, 'store']);
    Route::post('/admin/levels/{level}/publish', [\App\Http\Controllers\Admin\LevelController::class, 'publish']);
    Route::post('/admin/stages', [\App\Http\Controllers\Admin\StageController::class, 'store']);
    Route::post('/admin/stages/{stage}/publish', [\App\Http\Controllers\Admin\StageController::class, 'publish']);
    Route::post('/admin/stories', [\App\Http\Controllers\Admin\StoryController::class, 'store']);
    Route::post('/admin/questions', [\App\Http\Controllers\Admin\QuestionController::class, 'store']);

    Route::get('/map', [\App\Http\Controllers\MapController::class, 'index']);
    Route::get('/stories/{story}', [\App\Http\Controllers\StoryViewController::class, 'show']);
    Route::post('/watch-pings', [\App\Http\Controllers\WatchPingController::class, 'store']);

    Route::post('/stages/{stage}/attempts', [\App\Http\Controllers\AttemptController::class, 'store']);
    Route::get('/quiz/{story}', [\App\Http\Controllers\QuizController::class, 'show']);
    Route::get('/result/{attempt}', [\App\Http\Controllers\AttemptController::class, 'show']);
});
Route::middleware(['auth'])->get('/admin/users', fn () => abort(403));

require __DIR__.'/auth.php';
