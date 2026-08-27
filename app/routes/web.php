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
Route::get('/karyawan/lembur', fn () => Inertia::render('Karyawan/Lembur'))->name('karyawan.lembur');
Route::get('/karyawan/love', fn () => Inertia::render('Karyawan/Love'))->name('karyawan.love');
Route::get('/karyawan/pengumuman', fn () => Inertia::render('Karyawan/Pengumuman'))->name('karyawan.pengumuman');
Route::get('/karyawan/profil', fn () => Inertia::render('Karyawan/Profil'))->name('karyawan.profil');

// Fallback: handle Inertia 404 in PWA
Route::fallback(fn () => Inertia::render('Karyawan/Dashboard'));
