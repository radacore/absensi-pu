<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Halaman dirender lewat resources/views/app.blade.php yang memanggil
        // @vite. Manifes Vite hanya ada setelah `npm run build`, sementara
        // public/build/ diabaikan git — jadi di lingkungan yang belum membangun
        // aset (mis. job tes CI) setiap render halaman gagal 500 dengan
        // ViteManifestNotFoundException. Tes ini tidak memeriksa aset, jadi
        // Vite dinonaktifkan agar hasilnya tidak bergantung pada build frontend.
        $this->withoutVite();
    }
}
