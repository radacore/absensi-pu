<?php

namespace App\Http\Controllers\Karyawan;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Support\AdminPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CutiController extends Controller
{
    public function index()
    {
        $me = Auth::guard('employee')->user()->load(['region', 'site']);
        $assigned = $me->site ? ['site' => ['nama_lokasi' => $me->site->nama_lokasi, 'radius' => $me->site->radius_m]] : null;
        $myList = Leave::where('employee_id', $me->id)->orderByDesc('created_at')->get()->map(function (Leave $l) use ($me) {
            $mulai = $l->mulai instanceof Carbon ? $l->mulai->format('Y-m-d') : (string) $l->mulai;
            $selesai = $l->selesai instanceof Carbon ? $l->selesai->format('Y-m-d') : (string) $l->selesai;
            $tglLabel = $mulai === $selesai
                ? Carbon::parse($mulai)->locale('id')->isoFormat('D MMM YYYY')
                : Carbon::parse($mulai)->locale('id')->isoFormat('D MMM').'–'.Carbon::parse($selesai)->locale('id')->isoFormat('D MMM YYYY');

            return [
                'id' => $l->id, 'jenis' => $l->jenis, 'tgl' => $tglLabel, 'mulai' => $mulai, 'selesai' => $selesai, 'alasan' => $l->alasan, 'status' => $l->status, 'level' => (int) $l->level, 'note' => $l->note,
                'wilayah' => $me->region?->name ?? '', 'office_location_id' => $me->site_id,
                'approver_id' => $l->approver_id, 'approver_nama' => $l->approver?->name ?? '', 'approved_by_nama' => $l->approvedBy?->name ?? '',
            ];
        })->values()->all();

        return Inertia::render('Karyawan/Cuti', [
            'me' => ['id' => $me->id, 'nama' => $me->name, 'region' => $me->region?->name ?? '', 'regionId' => $me->region_id, 'office_location_id' => $me->site_id, 'email' => $me->email ?? ''],
            'assigned' => $me->site ? ['site' => ['nama_lokasi' => $me->site->nama_lokasi, 'radius' => (int) $me->site->radius_m], 'regionName' => $me->region?->name ?? ''] : null,
            'list' => $myList,
            // Approver cuti = akun admin saja (admin wilayah + Kantor Pusat).
            'approvers' => AdminPresenter::leaveApproversFor($me->region_id, $me->site_id),
        ]);
    }

    public function store(Request $request)
    {
        $me = Auth::guard('employee')->user();
        $data = $request->validate([
            'jenis' => ['required', 'in:Tahunan,Sakit,Besar,Melahirkan'],
            'mulai' => ['required', 'date', 'after_or_equal:today'],
            'selesai' => ['required', 'date', 'after_or_equal:mulai'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
            'approver_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        // Kalau approver ditunjuk, dia harus akun admin yang berwenang di wilayah ini.
        // Pakai ValidationException (bukan back()->withErrors()) supaya request XHR
        // mendapat 422 yang konsisten dengan validasi lain di aplikasi ini.
        if (! empty($data['approver_id'])) {
            $allow = array_map('intval', collect(AdminPresenter::leaveApproversFor($me->region_id, $me->site_id))->pluck('id')->all());
            if (! in_array((int) $data['approver_id'], $allow, true)) {
                throw ValidationException::withMessages([
                    'approver_id' => 'Approver harus akun admin di wilayah Anda.',
                ]);
            }
        }

        Leave::create([
            'employee_id' => $me->id,
            'jenis' => $data['jenis'],
            'mulai' => $data['mulai'],
            'selesai' => $data['selesai'],
            'alasan' => $data['alasan'],
            'status' => 'Menunggu',
            'level' => 0,
            'approver_id' => $data['approver_id'] ?? null,
        ]);

        return back()->with('success', 'Pengajuan cuti dikirim — menunggu persetujuan');
    }

    public function destroy(Leave $cuti)
    {
        $me = Auth::guard('employee')->user();
        abort_if((int) $cuti->employee_id !== (int) $me->id, 403);
        abort_if($cuti->status !== 'Menunggu' || (int) $cuti->level !== 0, 403, 'Hanya Menunggu level 0 bisa dibatalkan');
        $cuti->delete();

        return back()->with('success', 'Cuti dibatalkan');
    }
}
