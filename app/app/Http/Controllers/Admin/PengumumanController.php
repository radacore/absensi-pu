<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Support\AdminPresenter;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PengumumanController extends Controller
{
    private function user()
    {
        return Auth::guard('web')->user();
    }

    public function index()
    {
        $u = $this->user();
        $isSuper = $u->role === 'super_admin';
        $scope = $isSuper ? null : $u->region_id;
        return Inertia::render('Admin/Pengumuman', [
            'regions' => AdminPresenter::regionsFor($isSuper ? null : $scope),
            'list' => AdminPresenter::announcementsFor($scope, $isSuper),
            'ownRegion' => $u->region?->name ?? '',
        ]);
    }

    public function store(Request $request)
    {
        $u = $this->user();
        $isWilayah = $u->role === 'admin_wilayah';
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:200'],
            'konten' => ['required', 'string', 'max:2000'],
            'scope' => ['required', 'in:Global,Wilayah'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'pin' => ['nullable', 'boolean'],
        ]);
        if ($isWilayah && $data['scope'] === 'Global') {
            return back()->withErrors(['scope' => 'Admin Wilayah tidak boleh membuat Global'])->withInput();
        }
        if ($data['scope'] === 'Wilayah') {
            if ($isWilayah) $data['region_id'] = $u->region_id;
            if (empty($data['region_id'])) return back()->withErrors(['region_id' => 'Pilih wilayah'])->withInput();
        } else {
            $data['region_id'] = null;
        }
        Announcement::create([
            'judul' => $data['judul'],
            'konten' => $data['konten'],
            'scope' => $data['scope'],
            'region_id' => $data['region_id'],
            'pin' => (bool) ($data['pin'] ?? false),
            'created_by' => $u->id,
        ]);
        return back()->with('success', 'Pengumuman dibuat');
    }

    public function update(Request $request, Announcement $pengumuman)
    {
        $u = $this->user();
        if ($u->role === 'admin_wilayah' && $pengumuman->scope === 'Global') abort(403, 'Tidak bisa edit Global');
        if ($u->role === 'admin_wilayah' && $pengumuman->region_id !== $u->region_id) abort(403);
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:200'],
            'konten' => ['required', 'string', 'max:2000'],
            'scope' => ['required', 'in:Global,Wilayah'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'pin' => ['nullable', 'boolean'],
        ]);
        if ($u->role === 'admin_wilayah' && $data['scope'] === 'Global') {
            return back()->withErrors(['scope' => 'Admin Wilayah tidak boleh Global'])->withInput();
        }
        if ($data['scope'] === 'Wilayah' && $u->role === 'admin_wilayah') $data['region_id'] = $u->region_id;
        if ($data['scope'] === 'Wilayah' && empty($data['region_id'])) return back()->withErrors(['region_id' => 'Pilih wilayah'])->withInput();
        if ($data['scope'] === 'Global') $data['region_id'] = null;
        $pengumuman->update([
            'judul' => $data['judul'],
            'konten' => $data['konten'],
            'scope' => $data['scope'],
            'region_id' => $data['region_id'],
            'pin' => (bool) ($data['pin'] ?? false),
        ]);
        return back()->with('success', 'Pengumuman diperbarui');
    }

    public function destroy(Announcement $pengumuman)
    {
        $u = $this->user();
        if ($u->role === 'admin_wilayah' && $pengumuman->scope === 'Global') abort(403, 'Tidak bisa hapus Global');
        if ($u->role === 'admin_wilayah' && $pengumuman->region_id !== $u->region_id) abort(403);

        $snapshot = ['judul' => $pengumuman->judul, 'scope' => $pengumuman->scope, 'region_id' => $pengumuman->region_id];
        $pengumuman->delete();

        Audit::log(
            'pengumuman.delete',
            subject: null,
            label: $snapshot['judul'],
            description: "Hapus pengumuman {$snapshot['judul']}",
            meta: $snapshot
        );

        return back()->with('success', 'Pengumuman dihapus');
    }
}
