<?php

use App\Http\Controllers\Admin\AsetTiController;
use App\Http\Controllers\Admin\AssistantChatController;
use App\Http\Controllers\Admin\CctvController;
use App\Http\Controllers\Admin\DcDrcDeviceController;
use App\Http\Controllers\Admin\DivisionController;
use App\Http\Controllers\Admin\ExternCrController;
use App\Http\Controllers\Admin\KantorController;
use App\Http\Controllers\Admin\KasKantorController;
use App\Http\Controllers\Admin\MonitoringController;
use App\Http\Controllers\Admin\ParameterExternCrApplicationController;
use App\Http\Controllers\Admin\ParameterExternCrChangeReasonController;
use App\Http\Controllers\Admin\ProfileManagementController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ServerStatisticsController;
use App\Http\Controllers\Admin\UserActivityLogController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\ExternCrVerificationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/verifikasi/cr-eksternal/{externCr}', [ExternCrVerificationController::class, 'show'])
    ->middleware('signed')
    ->name('extern-cr.verify');

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', function () {
    if (in_array(auth()->user()?->role, ['admin', 'manager', 'officer', 'vendor', 'cabang'], true)) {
        return redirect()->route('admin.dashboard');
    }

    return view('dashboard');
})->middleware(['auth', 'verified', 'menu.activity'])->name('dashboard');

Route::middleware(['auth', 'verified', 'admin.2fa', 'menu.activity'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [ProjectController::class, 'dashboard'])->name('dashboard');

    Route::post('/assistant/chat', [AssistantChatController::class, 'chat'])
        ->middleware(['throttle:30,1'])
        ->name('assistant.chat');

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

    Route::get('/aset-ti/data-center', [AsetTiController::class, 'dataCenter'])->name('aset-ti.data-center');
    Route::get('/aset-ti/data-center/metrics', [AsetTiController::class, 'dataCenterMetrics'])->name('aset-ti.data-center.metrics');
    Route::get('/aset-ti/cctv-dashboard', [CctvController::class, 'dashboard'])->name('aset-ti.cctv.dashboard');
    Route::get('/aset-ti/cctv-dashboard/export-missing', [CctvController::class, 'exportMissingUpdates'])->name('aset-ti.cctv.dashboard.export-missing');
    Route::get('/aset-ti/cctv', [CctvController::class, 'index'])->name('aset-ti.cctv.index');
    Route::get('/aset-ti/cctv/export', [CctvController::class, 'export'])->name('aset-ti.cctv.export');
    Route::get('/aset-ti/cctv/template', [CctvController::class, 'downloadTemplate'])->name('aset-ti.cctv.template');
    Route::post('/aset-ti/cctv/import', [CctvController::class, 'import'])->name('aset-ti.cctv.import');
    Route::post('/aset-ti/cctv', [CctvController::class, 'store'])->name('aset-ti.cctv.store');
    Route::delete('/aset-ti/cctv', [CctvController::class, 'destroyAll'])->name('aset-ti.cctv.delete-all');
    Route::put('/aset-ti/cctv/{device}', [CctvController::class, 'update'])->name('aset-ti.cctv.update');
    Route::delete('/aset-ti/cctv/{device}', [CctvController::class, 'destroy'])->name('aset-ti.cctv.delete');
    Route::get('/aset-ti/perangkat-dc-drc-dashboard', [DcDrcDeviceController::class, 'dashboard'])->name('aset-ti.perangkat-dc-drc.dashboard');
    Route::get('/aset-ti/perangkat-dc-drc', [DcDrcDeviceController::class, 'index'])->name('aset-ti.perangkat-dc-drc.index');
    Route::post('/aset-ti/perangkat-dc-drc', [DcDrcDeviceController::class, 'store'])->name('aset-ti.perangkat-dc-drc.store');
    Route::put('/aset-ti/perangkat-dc-drc/{device}', [DcDrcDeviceController::class, 'update'])->name('aset-ti.perangkat-dc-drc.update');
    Route::delete('/aset-ti/perangkat-dc-drc/{device}', [DcDrcDeviceController::class, 'destroy'])->name('aset-ti.perangkat-dc-drc.delete');
    Route::get('/aset-ti/perangkat-dc-drc/export', [DcDrcDeviceController::class, 'export'])->name('aset-ti.perangkat-dc-drc.export');
    Route::get('/aset-ti/perangkat-dc-drc/template', [DcDrcDeviceController::class, 'downloadTemplate'])->name('aset-ti.perangkat-dc-drc.template');
    Route::post('/aset-ti/perangkat-dc-drc/import', [DcDrcDeviceController::class, 'import'])->name('aset-ti.perangkat-dc-drc.import');
    Route::get('/aset-ti/monitoring', [MonitoringController::class, 'index'])->name('aset-ti.monitoring.index');
    Route::get('/aset-ti/monitoring/data', [MonitoringController::class, 'data'])->name('aset-ti.monitoring.data');
    Route::get('/aset-ti/statistik-server', [ServerStatisticsController::class, 'index'])->name('aset-ti.server-statistics.index');
    Route::get('/aset-ti/statistik-server/device/{device}/sensor/{metric}', [ServerStatisticsController::class, 'sensor'])->name('aset-ti.server-statistics.sensor');

    Route::get('/manajemen-vendor', [VendorController::class, 'index'])->name('manajemen-vendor.index');
    Route::post('/manajemen-vendor', [VendorController::class, 'store'])->name('manajemen-vendor.store');
    Route::put('/manajemen-vendor/{vendor}', [VendorController::class, 'update'])->name('manajemen-vendor.update');
    Route::delete('/manajemen-vendor/{vendor}', [VendorController::class, 'destroy'])->name('manajemen-vendor.delete');

    Route::get('/manajemen-divisi', [DivisionController::class, 'index'])->name('manajemen-divisi.index');
    Route::post('/manajemen-divisi', [DivisionController::class, 'store'])->name('manajemen-divisi.store');
    Route::put('/manajemen-divisi/{division}', [DivisionController::class, 'update'])->name('manajemen-divisi.update');
    Route::delete('/manajemen-divisi/{division}', [DivisionController::class, 'destroy'])->name('manajemen-divisi.delete');

    Route::get('/parameter/cr-aplikasi', [ParameterExternCrApplicationController::class, 'index'])->name('parameter.cr-aplikasi.index');
    Route::post('/parameter/cr-aplikasi', [ParameterExternCrApplicationController::class, 'store'])->name('parameter.cr-aplikasi.store');
    Route::put('/parameter/cr-aplikasi/{application}', [ParameterExternCrApplicationController::class, 'update'])->name('parameter.cr-aplikasi.update');
    Route::delete('/parameter/cr-aplikasi/{application}', [ParameterExternCrApplicationController::class, 'destroy'])->name('parameter.cr-aplikasi.delete');

    Route::get('/parameter/cr-alasan-perubahan', [ParameterExternCrChangeReasonController::class, 'index'])->name('parameter.cr-alasan-perubahan.index');
    Route::post('/parameter/cr-alasan-perubahan', [ParameterExternCrChangeReasonController::class, 'store'])->name('parameter.cr-alasan-perubahan.store');
    Route::put('/parameter/cr-alasan-perubahan/{changeReason}', [ParameterExternCrChangeReasonController::class, 'update'])->name('parameter.cr-alasan-perubahan.update');
    Route::delete('/parameter/cr-alasan-perubahan/{changeReason}', [ParameterExternCrChangeReasonController::class, 'destroy'])->name('parameter.cr-alasan-perubahan.delete');

    Route::get('/cr-eksternal', [ExternCrController::class, 'index'])->name('cr-eksternal.index');
    Route::patch('/cr-eksternal/{externCr}/status', [ExternCrController::class, 'updateStatus'])->name('cr-eksternal.status');
    Route::get('/cr-eksternal/create', [ExternCrController::class, 'create'])->name('cr-eksternal.create');
    Route::post('/cr-eksternal', [ExternCrController::class, 'store'])->name('cr-eksternal.store');
    Route::get('/cr-eksternal/{externCr}/edit', [ExternCrController::class, 'edit'])->name('cr-eksternal.edit');
    Route::get('/cr-eksternal/{externCr}/cetak', [ExternCrController::class, 'printPdf'])->name('cr-eksternal.print');
    Route::put('/cr-eksternal/{externCr}', [ExternCrController::class, 'update'])->name('cr-eksternal.update');
    Route::delete('/cr-eksternal/{externCr}', [ExternCrController::class, 'destroy'])->name('cr-eksternal.delete');
    Route::get('/cr-eksternal/{externCr}/lampiran/{attachment}/unduh', [ExternCrController::class, 'downloadAttachment'])->name('cr-eksternal.attachments.download');
    Route::delete('/cr-eksternal/{externCr}/lampiran/{attachment}', [ExternCrController::class, 'destroyAttachment'])->name('cr-eksternal.attachments.delete');

    Route::get('/manajemen-kantor', [KantorController::class, 'index'])->name('manajemen-kantor.index');
    Route::post('/manajemen-kantor', [KantorController::class, 'store'])->name('manajemen-kantor.store');
    Route::get('/manajemen-kantor/export', [KantorController::class, 'export'])->name('manajemen-kantor.export');
    Route::get('/manajemen-kantor/template', [KantorController::class, 'downloadTemplate'])->name('manajemen-kantor.template');
    Route::post('/manajemen-kantor/import', [KantorController::class, 'import'])->name('manajemen-kantor.import');
    Route::delete('/manajemen-kantor/all', [KantorController::class, 'destroyAll'])->name('manajemen-kantor.destroy-all');
    Route::post('/manajemen-kantor/{kantor}/kas-kantor', [KasKantorController::class, 'store'])->name('manajemen-kantor.kas-kantor.store');
    Route::put('/manajemen-kantor/{kantor}/kas-kantor/{kasKantor}', [KasKantorController::class, 'update'])->name('manajemen-kantor.kas-kantor.update');
    Route::delete('/manajemen-kantor/{kantor}/kas-kantor/{kasKantor}', [KasKantorController::class, 'destroy'])->name('manajemen-kantor.kas-kantor.delete');
    Route::put('/manajemen-kantor/{kantor}', [KantorController::class, 'update'])->name('manajemen-kantor.update');
    Route::delete('/manajemen-kantor/{kantor}', [KantorController::class, 'destroy'])->name('manajemen-kantor.delete');

    Route::get('/manajemen-user', [UserManagementController::class, 'index'])->name('manajemen-user.index');
    Route::post('/manajemen-user', [UserManagementController::class, 'store'])->name('manajemen-user.store');
    Route::put('/manajemen-user/{user}', [UserManagementController::class, 'update'])->name('manajemen-user.update');
    Route::delete('/manajemen-user/{user}', [UserManagementController::class, 'destroy'])->name('manajemen-user.delete');

    Route::get('/manajemen-log-user', [UserActivityLogController::class, 'index'])->name('manajemen-log-user.index');
});

Route::middleware(['auth', 'menu.activity'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
});

Route::middleware('auth')->group(function () {
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
