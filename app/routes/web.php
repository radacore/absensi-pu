<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return view('welcome');
});

// Public — keep welcome
// Karyawan PWA — frontend only (mock, no auth yet), Inertia pages
Route::get('/karyawan/login', fn () => Inertia::render('Karyawan/Login'))->name('karyawan.login');
Route::get('/karyawan', fn () => Inertia::render('Karyawan/Dashboard'))->name('karyawan.dashboard');
Route::get('/karyawan/absensi', fn () => Inertia::render('Karyawan/Absensi'))->name('karyawan.absensi');
Route::get('/karyawan/rekap', fn () => Inertia::render('Karyawan/Rekap'))->name('karyawan.rekap');
Route::get('/karyawan/cuti', fn () => Inertia::render('Karyawan/Cuti'))->name('karyawan.cuti');
Route::get('/karyawan/love', fn () => Inertia::render('Karyawan/Love'))->name('karyawan.love');
Route::get('/karyawan/pengumuman', fn () => Inertia::render('Karyawan/Pengumuman'))->name('karyawan.pengumuman');
Route::get('/karyawan/profil', fn () => Inertia::render('Karyawan/Profil'))->name('karyawan.profil');

// Opsi B — Pisah URL: Super Admin (SUPER_ADMIN_PATH) vs Admin Wilayah (WILAYAH_PATH)
// Dev: /super-admin + /wilayah ; Prod: env SUPER_ADMIN_PATH / WILAYAH_PATH obfuscated
// Super Admin — Makassar, unscoped (region_id null)
Route::prefix('super-admin')->group(function () {
    Route::get('/login', fn () => Inertia::render('Admin/Login'))->name('super_admin.login');
    Route::get('/', fn () => Inertia::render('Admin/Dashboard'))->name('super_admin.dashboard');
    Route::get('/regions', fn () => Inertia::render('Admin/Regions'))->name('super_admin.regions');
    Route::get('/employees', fn () => Inertia::render('Admin/Employees'))->name('super_admin.employees');
    Route::get('/admin-wilayah', fn () => Inertia::render('Admin/AdminWilayah'))->name('super_admin.admin-wilayah');
    Route::get('/attendances', fn () => Inertia::render('Admin/Attendances'))->name('super_admin.attendances');
    Route::get('/cuti', fn () => Inertia::render('Admin/Cuti'))->name('super_admin.cuti');
    Route::get('/cuti/{id}', fn ($id) => Inertia::render('Admin/CutiDetail', ['id' => (int) $id]))->name('super_admin.cuti.detail');
    Route::get('/love', fn () => Inertia::render('Admin/Love'))->name('super_admin.love');
    Route::get('/pengumuman', fn () => Inertia::render('Admin/Pengumuman'))->name('super_admin.pengumuman');
    Route::get('/settings', fn () => Inertia::render('Admin/Settings'))->name('super_admin.settings');
});

// Admin Wilayah — per Kabupaten/Kota, scoped own region (region_id FK)
Route::prefix('wilayah')->group(function () {
    Route::get('/login', fn () => Inertia::render('Admin/Login'))->name('wilayah.login');
    Route::get('/', fn () => Inertia::render('Admin/Dashboard'))->name('wilayah.dashboard');
    Route::get('/regions', fn () => Inertia::render('Admin/Regions'))->name('wilayah.regions');
    Route::get('/employees', fn () => Inertia::render('Admin/Employees'))->name('wilayah.employees');
    Route::get('/admin-wilayah', fn () => Inertia::render('Admin/AdminWilayah'))->name('wilayah.admin-wilayah');
    Route::get('/attendances', fn () => Inertia::render('Admin/Attendances'))->name('wilayah.attendances');
    Route::get('/cuti', fn () => Inertia::render('Admin/Cuti'))->name('wilayah.cuti');
    Route::get('/cuti/{id}', fn ($id) => Inertia::render('Admin/CutiDetail', ['id' => (int) $id]))->name('wilayah.cuti.detail');
    Route::get('/love', fn () => Inertia::render('Admin/Love'))->name('wilayah.love');
    Route::get('/pengumuman', fn () => Inertia::render('Admin/Pengumuman'))->name('wilayah.pengumuman');
    Route::get('/settings', fn () => Inertia::render('Admin/Settings'))->name('wilayah.settings');
});

// Legacy alias /admin → backward compat (deprecated, maps to super-admin)
Route::prefix('admin')->group(function () {
    Route::get('/login', fn () => Inertia::render('Admin/Login'))->name('admin.login');
    Route::get('/', fn () => Inertia::render('Admin/Dashboard'))->name('admin.dashboard');
    Route::get('/regions', fn () => Inertia::render('Admin/Regions'))->name('admin.regions');
    Route::get('/employees', fn () => Inertia::render('Admin/Employees'))->name('admin.employees');
    Route::get('/admin-wilayah', fn () => Inertia::render('Admin/AdminWilayah'))->name('admin.admin-wilayah');
    Route::get('/attendances', fn () => Inertia::render('Admin/Attendances'))->name('admin.attendances');
    Route::get('/cuti', fn () => Inertia::render('Admin/Cuti'))->name('admin.cuti');
    Route::get('/cuti/{id}', fn ($id) => Inertia::render('Admin/CutiDetail', ['id' => (int) $id]))->name('admin.cuti.detail');
    Route::get('/love', fn () => Inertia::render('Admin/Love'))->name('admin.love');
    Route::get('/pengumuman', fn () => Inertia::render('Admin/Pengumuman'))->name('admin.pengumuman');
    Route::get('/settings', fn () => Inertia::render('Admin/Settings'))->name('admin.settings');
});

// Fallback: handle Inertia 404 in PWA
Route::fallback(fn () => Inertia::render('Karyawan/Dashboard'));
