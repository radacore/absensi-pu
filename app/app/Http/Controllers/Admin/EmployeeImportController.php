<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPresenter;
use App\Support\Audit;
use App\Support\KaryawanImpor;
use App\Support\KaryawanImporException;
use App\Support\KaryawanTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Impor akun karyawan dari berkas Excel, per wilayah.
 *
 * Tiga langkah: unduh templat → unggah untuk pratinjau → impor. Pratinjau tidak
 * menulis apa pun; impor memeriksa ulang berkasnya dari awal.
 */
class EmployeeImportController extends Controller
{
    private function scopeRegion(): ?int
    {
        $user = Auth::guard('web')->user();

        return $user->role === 'super_admin' ? null : $user->region_id;
    }

    /** Halaman impor: unggah berkas, lihat pratinjau, lalu simpan. */
    public function index()
    {
        $scope = $this->scopeRegion();

        return Inertia::render('Admin/EmployeeImport', [
            'regions' => AdminPresenter::regionsFor($scope),
            'settings' => AdminPresenter::settingsFor(),
            'batas' => [
                'baris' => KaryawanImpor::MAX_BARIS,
                'mb' => (int) (KaryawanImpor::MAX_BITA / 1024 / 1024),
            ],
        ]);
    }

    /** Unduh templat XLSX, sudah disesuaikan dengan cakupan wilayah pengguna. */
    public function template()
    {
        $path = tempnam(sys_get_temp_dir(), 'impor-karyawan-');

        if ($path === false) {
            abort(500, 'Tidak bisa menyiapkan berkas templat.');
        }

        KaryawanTemplate::simpan($this->scopeRegion(), $path);

        return response()
            ->download($path, 'templat-impor-karyawan-'.now()->format('Y-m-d').'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    /** Periksa berkas dan kembalikan pratinjau barisnya. Tidak menyimpan apa pun. */
    public function preview(Request $request)
    {
        $data = $this->validateBerkas($request);

        try {
            $hasil = KaryawanImpor::periksa(
                $data['berkas']->getRealPath(),
                $this->scopeRegion(),
                $data['mode']
            );
        } catch (KaryawanImporException $e) {
            return back()->withErrors(['berkas' => $e->getMessage()]);
        }

        return back()->with('importPreview', [
            ...$hasil,
            'mode' => $data['mode'],
            'berkas' => $data['berkas']->getClientOriginalName(),
        ]);
    }

    /** Simpan baris yang lolos. Baris bermasalah dilewati, sisanya tetap masuk. */
    public function commit(Request $request)
    {
        $data = $this->validateBerkas($request);

        $opsi = $request->validate([
            'sandi' => ['required', Rule::in(KaryawanImpor::SANDI)],
            'wajibGanti' => ['required', 'boolean'],
        ]);

        try {
            $hasil = KaryawanImpor::terapkan(
                $data['berkas']->getRealPath(),
                $this->scopeRegion(),
                [
                    'mode' => $data['mode'],
                    'sandi' => $opsi['sandi'],
                    'wajib_ganti' => (bool) $opsi['wajibGanti'],
                ]
            );
        } catch (KaryawanImporException $e) {
            return back()->withErrors(['berkas' => $e->getMessage()]);
        }

        $ringkasan = $hasil['ringkasan'];
        $adaPerubahan = $ringkasan['tambah'] > 0 || $ringkasan['perbarui'] > 0;

        // Tidak ada satu pun baris yang bisa dipakai DAN ada yang bermasalah:
        // pengguna harus membereskan berkasnya dulu, bukan diberi pesan sukses.
        if (! $adaPerubahan && $ringkasan['gagal'] > 0) {
            return back()
                ->withErrors(['berkas' => 'Tidak ada baris yang bisa diimpor. Perbaiki dulu baris yang bermasalah, lalu unggah ulang.'])
                ->with('importPreview', [...$hasil, 'mode' => $data['mode'], 'berkas' => $data['berkas']->getClientOriginalName()]);
        }

        Audit::log(
            'employee.import',
            label: $data['berkas']->getClientOriginalName(),
            description: 'Impor akun karyawan: '.$ringkasan['tambah'].' ditambahkan, '
                .$ringkasan['perbarui'].' diperbarui, '.$ringkasan['lewati'].' dilewati, '
                .$ringkasan['gagal'].' gagal',
            meta: [
                'berkas' => $data['berkas']->getClientOriginalName(),
                'mode' => $data['mode'],
                'sandi' => $opsi['sandi'],
                'wajib_ganti' => (bool) $opsi['wajibGanti'],
                'scope_region_id' => $this->scopeRegion(),
                'ringkasan' => $ringkasan,
            ]
        );

        if (! $adaPerubahan) {
            // Berkasnya sah, hanya saja memang tidak ada yang perlu diubah.
            return back()->with([
                'success' => 'Tidak ada perubahan. '.$ringkasan['lewati'].' baris dilewati karena tidak sesuai mode impor yang dipilih.',
                'importHasil' => [...$hasil, 'mode' => $data['mode'], 'berkas' => $data['berkas']->getClientOriginalName()],
            ]);
        }

        $pesan = $ringkasan['tambah'].' karyawan ditambahkan, '.$ringkasan['perbarui'].' diperbarui.';
        if ($ringkasan['gagal'] > 0) {
            $pesan .= ' '.$ringkasan['gagal'].' baris dilewati karena bermasalah.';
        }
        if ($ringkasan['lewati'] > 0) {
            $pesan .= ' '.$ringkasan['lewati'].' baris dilewati sesuai mode impor.';
        }

        return back()->with([
            'success' => $pesan,
            'importHasil' => [...$hasil, 'mode' => $data['mode'], 'berkas' => $data['berkas']->getClientOriginalName()],
        ]);
    }

    private function validateBerkas(Request $request): array
    {
        return $request->validate([
            'berkas' => ['required', 'file', 'max:'.(KaryawanImpor::MAX_BITA / 1024), 'extensions:xlsx,xls'],
            'mode' => ['required', Rule::in(KaryawanImpor::MODE)],
        ], [
            'berkas.extensions' => 'Berkas harus berformat Excel (.xlsx).',
            'berkas.max' => 'Ukuran berkas maksimal '.(KaryawanImpor::MAX_BITA / 1024 / 1024).' MB.',
        ]);
    }
}
