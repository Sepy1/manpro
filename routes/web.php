<?php

use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', function () {
    if (auth()->user()?->role === 'admin') {
        return redirect()->route('admin.dashboard');
    }

    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [ProjectController::class, 'dashboard'])->name('dashboard');

    Route::get('/insert-project', [ProjectController::class, 'create'])->name('insert-project.create');
    Route::post('/insert-project', [ProjectController::class, 'store'])->name('insert-project.store');
    Route::get('/daftar-project', [ProjectController::class, 'index'])->name('daftar-project.index');
    Route::put('/daftar-project/{project}', [ProjectController::class, 'updateProject'])->name('daftar-project.update');
    Route::put('/daftar-project/step/{step}', [ProjectController::class, 'updateStep'])->name('daftar-project.step.update');

    Route::get('/profil', function () {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('pages.dashboard.profil');
    })->name('profil');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
