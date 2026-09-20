<?php

namespace App\Http\Controllers\Karyawan;

use App\Http\Controllers\Controller;
use App\Models\DinasClaim;
use App\Support\StreamsDokumen;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DinasController extends Controller
{
    use StreamsDokumen;

    /** Ekstensi dokumen pendukung yang diizinkan. */
    public const DOKUMEN_MIMES = 'pdf,jpg,jpeg,png,webp';

    /** Tipe konten (hasil deteksi isi berkas) yang diizinkan. */
    public const DOKUMEN_MIMETYPES = 'application/pdf,image/jpeg,image/png,image/webp';

    /** Ukuran maksimal dokumen (KB). */
    public const DOKUMEN_MAX_KB = 5120;

    public function index()
    {
        $me = Auth::guard('employee')->user();
        $list = DinasClaim::where('employee_id', $me->id)
            ->orderByDesc('tanggal_pengajuan')
            ->get()
            ->map(function (DinasClaim $d) {
                $mulai = $d->tanggal_mulai->format('Y-m-d');
                $selesai = $d->tanggal_selesai->format('Y-m-d');
                $tglLabel = $mulai === $selesai
                    ? Carbon::parse($mulai)->locale('id')->isoFormat('D MMM YYYY')
                    : Carbon::parse($mulai)->locale('id')->isoFormat('D MMM').' – '.Carbon::parse($selesai)->locale('id')->isoFormat('D MMM YYYY');

                return [
                    'id' => $d->id,
                    'nomor_surat' => $d->nomor_surat,
                    'mulai' => $mulai,
                    'selesai' => $selesai,
                    'tanggal_pengajuan' => $d->tanggal_pengajuan->format('Y-m-d'),
                    'tglLabel' => $tglLabel,
                    'keterangan' => $d->keterangan,
                    'tujuan' => $d->tujuan,
                    'transportasi' => $d->transportasi,
                    'pembebanan_anggaran' => $d->pembebanan_anggaran,
                    'status' => $d->status,
                    'note' => $d->note,
                    'dokumen_nama' => $d->dokumen_nama,
                    'dokumen_size' => $d->dokumenSizeLabel(),
                    'dokumen_is_image' => $d->dokumenIsImage(),
                    'dokumen_url' => $d->hasDokumen() ? route('karyawan.dinas.dokumen', $d->id) : null,
                ];
            })->all();

        return Inertia::render('Karyawan/Dinas', [
            'me' => ['id' => $me->id, 'nama' => $me->name, 'region' => $me->region?->name ?? ''],
            'list' => $list,
        ]);
    }

    public function store(Request $request)
    {
        $me = Auth::guard('employee')->user();

        $data = $request->validate([
            'nomor_surat' => ['required', 'string', 'max:100'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'keterangan' => ['required', 'string', 'min:5', 'max:1000'],
            'tujuan' => ['required', 'string', 'max:200'],
            'transportasi' => ['nullable', 'string', 'max:100'],
            'pembebanan_anggaran' => ['nullable', 'string', 'max:150'],
            'dokumen' => ['required', 'file', 'mimes:'.self::DOKUMEN_MIMES, 'mimetypes:'.self::DOKUMEN_MIMETYPES, 'max:'.self::DOKUMEN_MAX_KB],
        ], [
            'dokumen.required' => 'Dokumen pendukung (surat tugas) wajib diunggah.',
            'dokumen.file' => 'Dokumen harus berupa berkas.',
            'dokumen.mimes' => 'Dokumen harus berformat PDF, JPG, JPEG, PNG, atau WEBP.',
            'dokumen.mimetypes' => 'Isi dokumen tidak dikenali sebagai PDF atau gambar yang valid.',
            'dokumen.max' => 'Ukuran dokumen maksimal 5 MB.',
        ]);

        $file = $request->file('dokumen');
        unset($data['dokumen']);

        $disk = config('filesystems.uploads.private_disk');

        DinasClaim::create($data + [
            'employee_id' => $me->id,
            'tanggal_pengajuan' => now('Asia/Makassar')->toDateString(),
            'status' => 'Menunggu',
            'dokumen_path' => $file->store('dinas', ['disk' => $disk, 'visibility' => 'private']),
            'dokumen_nama' => $file->getClientOriginalName(),
            'dokumen_mime' => $file->getMimeType() ?: $file->getClientMimeType(),
            'dokumen_size' => $file->getSize(),
        ]);

        return back()->with('success', 'Pengajuan dinas dikirim — menunggu persetujuan.');
    }

    public function destroy(DinasClaim $dinas)
    {
        $me = Auth::guard('employee')->user();
        abort_if((int) $dinas->employee_id !== (int) $me->id, 403);
        abort_if($dinas->status !== 'Menunggu', 403, 'Hanya pengajuan menunggu yang bisa dibatalkan.');

        if ($dinas->hasDokumen()) {
            Storage::disk(config('filesystems.uploads.private_disk'))->delete($dinas->dokumen_path);
        }

        $dinas->delete();

        return back()->with('success', 'Pengajuan dinas dibatalkan.');
    }

    /** Pratinjau / unduh dokumen pendukung milik pengajuan sendiri. */
    public function dokumen(DinasClaim $dinas)
    {
        $me = Auth::guard('employee')->user();
        abort_if((int) $dinas->employee_id !== (int) $me->id, 403);
        abort_unless($dinas->hasDokumen(), 404);

        return $this->streamDokumen($dinas);
    }
}
