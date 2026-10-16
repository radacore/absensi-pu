<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Region;
use App\Models\Site;
use App\Support\AdminPresenter;
use App\Support\Audit;
use App\Support\KaryawanImpor;
use App\Support\KaryawanImporException;
use App\Support\KaryawanTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Impor akun karyawan dari berkas Excel, per wilayah.
 *
 * Tiga langkah: unduh templat → unggah untuk pratinjau → impor. Pratinjau tidak
 * menulis apa pun; impor memeriksa ulang berkasnya dari awal.
 *
 * Wilayah dan titik tujuan dipilih sekali di halaman ini dan berlaku untuk
 * seluruh berkas, karena berkas dari PPK biasanya hanya memuat data
 * kepegawaian dan tidak menyebut wilayah. Bila berkasnya tetap punya kolom
 * Wilayah/Titik Proyek, isi kolom itulah yang menang.
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
            // Ditampilkan di pilihan "Sandi bawaan" supaya admin tahu sandi
            // mana yang harus diberitahukan ke karyawan.
            'sandiBawaan' => Employee::SANDI_BAWAAN,
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
                $data['mode'],
                $this->tujuan($data),
                $data['statusDefault'] ?? null,
            );
        } catch (KaryawanImporException $e) {
            return back()->withErrors(['berkas' => $e->getMessage()]);
        }

        return back()->with('importPreview', $this->flash($hasil, $data));
    }

    /** Simpan baris yang lolos. Baris bermasalah dilewati, sisanya tetap masuk. */
    public function commit(Request $request)
    {
        $data = $this->validateBerkas($request);

        $opsi = $request->validate([
            'sandi' => ['required', Rule::in(KaryawanImpor::SANDI)],
            'wajibGanti' => ['required', 'boolean'],
        ]);

        $tujuan = $this->tujuan($data);

        try {
            $hasil = KaryawanImpor::terapkan(
                $data['berkas']->getRealPath(),
                $this->scopeRegion(),
                [
                    'mode' => $data['mode'],
                    'sandi' => $opsi['sandi'],
                    'wajib_ganti' => (bool) $opsi['wajibGanti'],
                    'tujuan' => $tujuan,
                    'status_default' => $data['statusDefault'] ?? null,
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
                ->with('importPreview', $this->flash($hasil, $data));
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
                'status_default' => $data['statusDefault'] ?? null,
                'scope_region_id' => $this->scopeRegion(),
                'tujuan' => $tujuan,
                'ringkasan' => $ringkasan,
            ]
        );

        if (! $adaPerubahan) {
            // Berkasnya sah, hanya saja memang tidak ada yang perlu diubah.
            return back()->with([
                'success' => 'Tidak ada perubahan. '.$ringkasan['lewati'].' baris dilewati karena tidak sesuai mode impor yang dipilih.',
                'importHasil' => $this->flash($hasil, $data),
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
            'importHasil' => $this->flash($hasil, $data),
        ]);
    }

    /** Bentuk data yang dikirim ke peramban setelah berkas diperiksa. */
    private function flash(array $hasil, array $data): array
    {
        return [
            ...$hasil,
            'kolom' => array_map(KaryawanImpor::labelKolom(...), $hasil['kolom']),
            'mode' => $data['mode'],
            'berkas' => $data['berkas']->getClientOriginalName(),
        ];
    }

    private function validateBerkas(Request $request): array
    {
        return $request->validate([
            'berkas' => ['required', 'file', 'max:'.(KaryawanImpor::MAX_BITA / 1024), 'extensions:xlsx,xls'],
            'mode' => ['required', Rule::in(KaryawanImpor::MODE)],
            'wilayah' => ['nullable', 'string', 'max:120'],
            'titik' => ['nullable', 'integer'],
            'statusDefault' => ['nullable', Rule::in(KaryawanImpor::STATUS_PILIHAN)],
        ], [
            'berkas.extensions' => 'Berkas harus berformat Excel (.xlsx).',
            'berkas.max' => 'Ukuran berkas maksimal '.(KaryawanImpor::MAX_BITA / 1024 / 1024).' MB.',
        ]);
    }

    /**
     * Ubah pilihan "Impor ke" di halaman menjadi wilayah/titik yang sah.
     *
     * Admin wilayah tidak boleh menyentuh wilayah lain, jadi pilihannya selalu
     * dipaksa ke wilayahnya sendiri — bukan sekadar dipercaya dari peramban.
     *
     * @param  array{wilayah?: ?string, titik?: ?int}  $data
     * @return array{region_id: ?int, site_id: ?int}
     */
    private function tujuan(array $data): array
    {
        $scope = $this->scopeRegion();

        $wilayah = $scope !== null
            ? (Region::find($scope)?->name ?? '')
            : trim((string) ($data['wilayah'] ?? ''));

        $region = $wilayah === '' ? null : Region::where('name', $wilayah)->first();

        if ($wilayah !== '' && $region === null) {
            throw ValidationException::withMessages(['wilayah' => 'Wilayah tidak ditemukan.']);
        }

        if ($region !== null && $scope !== null && $region->id !== $scope) {
            throw ValidationException::withMessages(['wilayah' => 'Wilayah ini di luar cakupan Anda.']);
        }

        $site = null;
        $siteId = $data['titik'] ?? null;

        if ($siteId !== null && $siteId !== '') {
            $site = Site::find((int) $siteId);

            if ($site === null) {
                throw ValidationException::withMessages(['titik' => 'Titik proyek tidak ditemukan.']);
            }

            if ($region === null) {
                throw ValidationException::withMessages(['wilayah' => 'Pilih wilayah dulu sebelum memilih titik proyek.']);
            }

            if ($site->region_id !== $region->id) {
                throw ValidationException::withMessages(['titik' => 'Titik proyek tidak berada di wilayah yang dipilih.']);
            }
        }

        return ['region_id' => $region?->id, 'site_id' => $site?->id];
    }
}
