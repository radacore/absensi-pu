<?php

use App\Http\Controllers\Admin\AdminWilayahController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CutiController as AdminCutiController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\LoveController as AdminLoveController;
use App\Http\Controllers\Admin\PengumumanController as AdminPengumumanController;
use App\Http\Controllers\Admin\RegionController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\EmployeeAuthController;
use App\Http\Controllers\Karyawan\AttendanceController as KaryawanAttendanceController;
use App\Http\Controllers\Karyawan\CutiController as KaryawanCutiController;
use App\Http\Controllers\Karyawan\DashboardController as KaryawanDashboardController;
use App\Http\Controllers\Karyawan\LoveController as KaryawanLoveController;
use App\Http\Controllers\Karyawan\PengumumanController as KaryawanPengumumanController;
use App\Http\Controllers\Karyawan\ProfilController as KaryawanProfilController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ── Public ────────────────────────────────────────────────────
Route::get('/', function () {
    return view('welcome');
});

// ── Karyawan PWA ──────────────────────────────────────────────
Route::prefix('karyawan')->group(function () {
    Route::get('/login', fn () => Inertia::render('Karyawan/Login'))->name('karyawan.login');
    Route::post('/login', [EmployeeAuthController::class, 'store'])
        ->middleware('throttle:login')
        ->name('karyawan.login.store');

    Route::middleware('employee')->group(function () {
        Route::post('/logout', [EmployeeAuthController::class, 'destroy'])->name('karyawan.logout');
        Route::get('/', [KaryawanDashboardController::class, 'index'])->name('karyawan.dashboard');
        Route::get('/absensi', [KaryawanAttendanceController::class, 'index'])->name('karyawan.absensi');
        Route::post('/absensi/clock-in', [KaryawanAttendanceController::class, 'clockIn'])->name('karyawan.absensi.clock_in');
        Route::post('/absensi/clock-out', [KaryawanAttendanceController::class, 'clockOut'])->name('karyawan.absensi.clock_out');
        Route::get('/rekap', [KaryawanAttendanceController::class, 'rekap'])->name('karyawan.rekap');
        Route::get('/cuti', [KaryawanCutiController::class, 'index'])->name('karyawan.cuti');
        Route::post('/cuti', [KaryawanCutiController::class, 'store'])->name('karyawan.cuti.store');
        Route::delete('/cuti/{cuti}', [KaryawanCutiController::class, 'destroy'])->name('karyawan.cuti.destroy');
        Route::get('/love', [KaryawanLoveController::class, 'index'])->name('karyawan.love');
        Route::post('/love', [KaryawanLoveController::class, 'store'])->name('karyawan.love.store');
        Route::get('/pengumuman', [KaryawanPengumumanController::class, 'index'])->name('karyawan.pengumuman');
        Route::post('/pengumuman/{pengumuman}/read', [KaryawanPengumumanController::class, 'markRead'])->name('karyawan.pengumuman.read');
        Route::post('/pengumuman/read-all', [KaryawanPengumumanController::class, 'markAllRead'])->name('karyawan.pengumuman.readAll');
        Route::get('/profil', [KaryawanProfilController::class, 'index'])->name('karyawan.profil');
        Route::put('/profil', [KaryawanProfilController::class, 'update'])->name('karyawan.profil.update');
        Route::delete('/profil/foto', [KaryawanProfilController::class, 'destroyFoto'])->name('karyawan.profil.foto.destroy');
        Route::put('/profil/password', [KaryawanProfilController::class, 'updatePassword'])->name('karyawan.profil.password');
    });
});

/**
 * Rute menu master data admin — dipakai bersama oleh super-admin/admin/wilayah.
 * $labelPrefix: bagian nama route (super_admin|admin|wilayah).
 */
if (! function_exists('adminMasterRoutes')) {
function adminMasterRoutes(string $prefix, string $role, string $label): void
{
    Route::prefix($prefix)->middleware("role:{$role}")->group(function () use ($label) {
        Route::post('/logout', [AdminAuthController::class, 'destroy'])->name("{$label}.logout");

        Route::get('/', [DashboardController::class, 'index'])->name("{$label}.dashboard");
        Route::get('/attendances', [AttendanceController::class, 'index'])->name("{$label}.attendances");
        Route::delete('/attendances/{attendance}', [AttendanceController::class, 'destroy'])->name("{$label}.attendances.destroy");
        Route::get('/cuti', [AdminCutiController::class, 'index'])->name("{$label}.cuti");
        Route::get('/cuti/{id}', [AdminCutiController::class, 'show'])->name("{$label}.cuti.detail");
        Route::put('/cuti/{cuti}/approve', [AdminCutiController::class, 'approve'])->name("{$label}.cuti.approve");
        Route::put('/cuti/{cuti}/reject', [AdminCutiController::class, 'reject'])->name("{$label}.cuti.reject");
        Route::delete('/cuti/{cuti}', [AdminCutiController::class, 'destroy'])->name("{$label}.cuti.destroy");
        Route::get('/love', [AdminLoveController::class, 'index'])->name("{$label}.love");
        Route::put('/love/{love}/approve', [AdminLoveController::class, 'approve'])->name("{$label}.love.approve");
        Route::put('/love/{love}/reject', [AdminLoveController::class, 'reject'])->name("{$label}.love.reject");
        Route::delete('/love/{love}', [AdminLoveController::class, 'destroy'])->name("{$label}.love.destroy");
        Route::get('/pengumuman', [AdminPengumumanController::class, 'index'])->name("{$label}.pengumuman");
        Route::post('/pengumuman', [AdminPengumumanController::class, 'store'])->name("{$label}.pengumuman.store");
        Route::put('/pengumuman/{pengumuman}', [AdminPengumumanController::class, 'update'])->name("{$label}.pengumuman.update");
        Route::delete('/pengumuman/{pengumuman}', [AdminPengumumanController::class, 'destroy'])->name("{$label}.pengumuman.destroy");
        Route::get('/settings', [SettingController::class, 'index'])->name("{$label}.settings");
        Route::put('/settings', [SettingController::class, 'update'])->name("{$label}.settings.update");

        // Regions
        Route::get('/regions', [RegionController::class, 'index'])->name("{$label}.regions");
        Route::post('/regions', [RegionController::class, 'store'])->name("{$label}.regions.store");
        Route::put('/regions/{region}', [RegionController::class, 'update'])->name("{$label}.regions.update");
        Route::delete('/regions/{region}', [RegionController::class, 'destroy'])->name("{$label}.regions.destroy");

        // Sites
        Route::get('/regions/{region}/sites/{site}', [SiteController::class, 'show'])->name("{$label}.sites.show");
        Route::post('/regions/{region}/sites', [SiteController::class, 'store'])->name("{$label}.sites.store");
        Route::put('/sites/{site}', [SiteController::class, 'update'])->name("{$label}.sites.update");
        Route::delete('/sites/{site}', [SiteController::class, 'destroy'])->name("{$label}.sites.destroy");
        Route::post('/sites/{site}/employees', [SiteController::class, 'assignEmployees'])->name("{$label}.sites.employees");
        Route::put('/sites/{site}/move', [SiteController::class, 'move'])->name("{$label}.sites.move");

        // Employees
        Route::get('/employees', [EmployeeController::class, 'index'])->name("{$label}.employees");
        Route::post('/employees', [EmployeeController::class, 'store'])->name("{$label}.employees.store");
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name("{$label}.employees.update");
        Route::post('/employees/{employee}/reset-password', [EmployeeController::class, 'resetPassword'])->name("{$label}.employees.reset-password");
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name("{$label}.employees.destroy");

        // Audit log (super admin only via controller guard)
        Route::get('/audit-log', [AuditLogController::class, 'index'])->name("{$label}.audit-log");

        // Admin Wilayah (super admin only via controller guard)
        Route::get('/admin-wilayah', [AdminWilayahController::class, 'index'])->name("{$label}.admin-wilayah");
        Route::post('/admin-wilayah', [AdminWilayahController::class, 'store'])->name("{$label}.admin-wilayah.store");
        Route::put('/admin-wilayah/{admin_wilayah}', [AdminWilayahController::class, 'update'])->name("{$label}.admin-wilayah.update");
        Route::put('/admin-wilayah/{admin_wilayah}/toggle', [AdminWilayahController::class, 'toggle'])->name("{$label}.admin-wilayah.toggle");
        Route::delete('/admin-wilayah/{admin_wilayah}', [AdminWilayahController::class, 'destroy'])->name("{$label}.admin-wilayah.destroy");
    });
}
}

// ── Super Admin Pusat ─────────────────────────────────────────
Route::prefix('super-admin')->group(function () {
    Route::get('/login', fn () => Inertia::render('Admin/Login'))->name('super_admin.login');
    Route::post('/login', [AdminAuthController::class, 'store'])
        ->middleware('throttle:login')
        ->name('super_admin.login.store');
});
adminMasterRoutes('super-admin', 'super_admin', 'super_admin');

// ── Admin Wilayah ─────────────────────────────────────────────
Route::prefix('admin')->group(function () {
    Route::get('/login', fn () => Inertia::render('Admin/Login'))->name('admin.login');
    Route::post('/login', [AdminAuthController::class, 'store'])
        ->middleware('throttle:login')
        ->name('admin.login.store');
});
adminMasterRoutes('admin', 'admin_wilayah', 'admin');

// ── Alias legacy /wilayah → Admin Wilayah ─────────────────────
Route::prefix('wilayah')->group(function () {
    Route::get('/login', fn () => Inertia::render('Admin/Login'))->name('wilayah.login');
    Route::post('/login', [AdminAuthController::class, 'store'])
        ->middleware('throttle:login')
        ->name('wilayah.login.store');
});
adminMasterRoutes('wilayah', 'admin_wilayah', 'wilayah');

// Fallback: handle Inertia 404 in PWA
Route::fallback(fn () => Inertia::render('Karyawan/Dashboard'));
