<?php

use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\VendorController;
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
    Route::delete('/daftar-project/{project}', [ProjectController::class, 'deleteProject'])->name('daftar-project.delete');
    Route::post('/daftar-project/{project}/step', [ProjectController::class, 'storeStep'])->name('daftar-project.step.store');
    Route::put('/daftar-project/step/{step}', [ProjectController::class, 'updateStep'])->name('daftar-project.step.update');
    Route::delete('/daftar-project/step/{step}', [ProjectController::class, 'deleteStep'])->name('daftar-project.step.delete');

    Route::get('/profil', function () {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('pages.dashboard.profil');
    })->name('profil');

    Route::get('/manajemen-vendor', [VendorController::class, 'index'])->name('manajemen-vendor.index');
    Route::post('/manajemen-vendor', [VendorController::class, 'store'])->name('manajemen-vendor.store');
    Route::put('/manajemen-vendor/{vendor}', [VendorController::class, 'update'])->name('manajemen-vendor.update');
    Route::delete('/manajemen-vendor/{vendor}', [VendorController::class, 'destroy'])->name('manajemen-vendor.delete');

    Route::get('/manajemen-user', [UserManagementController::class, 'index'])->name('manajemen-user.index');
    Route::post('/manajemen-user', [UserManagementController::class, 'store'])->name('manajemen-user.store');
    Route::put('/manajemen-user/{user}', [UserManagementController::class, 'update'])->name('manajemen-user.update');
    Route::delete('/manajemen-user/{user}', [UserManagementController::class, 'destroy'])->name('manajemen-user.delete');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
