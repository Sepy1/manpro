<?php

use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ProfileManagementController;
use App\Http\Controllers\Admin\DivisionController;
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
    if (in_array(auth()->user()?->role, ['admin', 'manager', 'officer', 'vendor'], true)) {
        return redirect()->route('admin.dashboard');
    }

    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [ProjectController::class, 'dashboard'])->name('dashboard');

    Route::get('/insert-project', [ProjectController::class, 'create'])->name('insert-project.create');
    Route::post('/insert-project', [ProjectController::class, 'store'])->name('insert-project.store');
    Route::get('/daftar-project', [ProjectController::class, 'index'])->name('daftar-project.index');
    Route::get('/daftar-project/report', [ProjectController::class, 'report'])->name('daftar-project.report');
    Route::put('/daftar-project/{project}', [ProjectController::class, 'updateProject'])->name('daftar-project.update');
    Route::put('/daftar-project/{project}/follow-up', [ProjectController::class, 'updateOfficerProjectFollowUp'])->name('daftar-project.follow-up.update');
    Route::delete('/daftar-project/{project}', [ProjectController::class, 'deleteProject'])->name('daftar-project.delete');
    Route::post('/daftar-project/{project}/step', [ProjectController::class, 'storeStep'])->name('daftar-project.step.store');
    Route::put('/daftar-project/step/{step}', [ProjectController::class, 'updateStep'])->name('daftar-project.step.update');
    Route::put('/daftar-project/step/{step}/follow-up', [ProjectController::class, 'updateOfficerStepFollowUp'])->name('daftar-project.step.follow-up.update');
    Route::delete('/daftar-project/step/{step}', [ProjectController::class, 'deleteStep'])->name('daftar-project.step.delete');

    Route::get('/profil', [ProfileManagementController::class, 'index'])->name('profil');
    Route::put('/profil/update-profile', [ProfileManagementController::class, 'updateProfile'])->name('profil.update-profile');
    Route::put('/profil/update-password', [ProfileManagementController::class, 'updatePassword'])->name('profil.update-password');

    Route::get('/manajemen-vendor', [VendorController::class, 'index'])->name('manajemen-vendor.index');
    Route::post('/manajemen-vendor', [VendorController::class, 'store'])->name('manajemen-vendor.store');
    Route::put('/manajemen-vendor/{vendor}', [VendorController::class, 'update'])->name('manajemen-vendor.update');
    Route::delete('/manajemen-vendor/{vendor}', [VendorController::class, 'destroy'])->name('manajemen-vendor.delete');

    Route::get('/manajemen-divisi', [DivisionController::class, 'index'])->name('manajemen-divisi.index');
    Route::post('/manajemen-divisi', [DivisionController::class, 'store'])->name('manajemen-divisi.store');
    Route::put('/manajemen-divisi/{division}', [DivisionController::class, 'update'])->name('manajemen-divisi.update');
    Route::delete('/manajemen-divisi/{division}', [DivisionController::class, 'destroy'])->name('manajemen-divisi.delete');

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
