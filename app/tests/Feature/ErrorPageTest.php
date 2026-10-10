<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Halaman error harus tetap berupa respons Inertia supaya kunjungan SPA tidak
 * berakhir dengan modal "invalid Inertia response" dari klien.
 */
class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_alamat_tidak_dikenal_mengembalikan_status_404_dan_halaman_error(): void
    {
        $this->get('/alamat-yang-tidak-pernah-ada-xyz')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Errors/Error')
                ->where('status', 404));
    }

    public function test_403_dirender_sebagai_halaman_error_inertia(): void
    {
        // Admin wilayah tanpa cakupan wilayah ditolak 403 oleh middleware role.
        $admin = User::create([
            'name' => 'Wilayah Tanpa Cakupan',
            'email' => 'wilayah.tanpa.cakupan@bbws-pj.go.id',
            'password' => 'rahasia-uji-123',
            'role' => 'admin_wilayah',
            'region_id' => null,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Errors/Error')
                ->where('status', 403));
    }

    public function test_permintaan_json_tetap_menerima_json_bukan_halaman_error(): void
    {
        $admin = User::create([
            'name' => 'Wilayah Tanpa Cakupan',
            'email' => 'wilayah.tanpa.cakupan.json@bbws-pj.go.id',
            'password' => 'rahasia-uji-123',
            'role' => 'admin_wilayah',
            'region_id' => null,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->getJson('/admin')
            ->assertForbidden()
            ->assertJsonMissing(['component' => 'Errors/Error']);
    }
}
