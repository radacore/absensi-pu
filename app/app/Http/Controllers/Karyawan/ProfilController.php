<?php

namespace App\Http\Controllers\Karyawan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProfilController extends Controller
{
    public function index()
    {
        $me = Auth::guard('employee')->user()->load(['region', 'site']);
        $assigned = $me->site ? [
            'id' => $me->site->id,
            'nama_lokasi' => $me->site->nama_lokasi,
            'lat' => (float) $me->site->lat,
            'lng' => (float) $me->site->lng,
            'radius' => (int) $me->site->radius_m,
            'address' => $me->site->address,
            'regionName' => $me->region?->name ?? '',
        ] : null;

        return Inertia::render('Karyawan/Profil', [
            'me' => [
                'id' => $me->id,
                'nama' => $me->name,
                'nik' => $me->nik,
                'nip' => $me->nip ?? '',
                'jabatan' => $me->jabatan,
                'unit' => $me->unit_kerja,
                'status' => $me->status_kepegawaian,
                'gol' => $me->golongan ?? '-',
                'email' => $me->email ?? '',
                'phone' => $me->phone ?? '',
                'foto' => $me->foto_url ?? '',
                'region' => $me->region?->name ?? '',
                'regionId' => $me->region_id,
                'office_location_id' => $me->site_id,
            ],
            'assigned' => $assigned,
        ]);
    }

    public function update(Request $request)
    {
        $me = Auth::guard('employee')->user();

        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'foto' => ['nullable', 'image', 'max:2048'],
            'foto_url' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('foto', 'public');
            $me->foto_url = Storage::url($path);
        } elseif (! empty($data['foto_url'])) {
            $me->foto_url = $data['foto_url'];
        }

        if (array_key_exists('phone', $data)) {
            $me->phone = $data['phone'] ? trim($data['phone']) : null;
        }
        if (array_key_exists('email', $data)) {
            $me->email = $data['email'] ? trim($data['email']) : null;
        }

        $me->save();

        return back()->with('success', 'Data pribadi disimpan');
    }

    public function destroyFoto(Request $request)
    {
        $me = Auth::guard('employee')->user();
        $me->foto_url = null;
        $me->save();

        return back()->with('success', 'Foto profil dihapus');
    }

    public function updatePassword(Request $request)
    {
        $me = Auth::guard('employee')->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/\d/',
            ],
        ], [
            'password.confirmed' => 'Konfirmasi tidak cocok.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
            'password.regex' => 'Kata sandi baru wajib mengandung huruf besar, huruf kecil, dan angka.',
        ]);

        if (! Hash::check($data['current_password'], $me->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Kata sandi lama salah.',
            ]);
        }

        if ($data['password'] === $me->nik) {
            throw ValidationException::withMessages([
                'password' => 'Kata sandi baru tidak boleh sama dengan NIK.',
            ]);
        }

        $me->password = $data['password'];
        $me->must_change_password = false;
        $me->save();

        return back()->with('success', 'Kata sandi berhasil diperbarui');
    }
}
