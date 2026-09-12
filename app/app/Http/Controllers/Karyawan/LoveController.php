<?php

namespace App\Http\Controllers\Karyawan;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSetting;
use App\Models\ToleranceClaim;
use App\Support\AdminPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LoveController extends Controller
{
    public function index()
    {
        $me = Auth::guard('employee')->user()->load(['region','site']);
        $settings = AttendanceSetting::first();
        $loveMax = $settings ? (int)$settings->love_max : 4;
        $quota = AdminPresenter::loveQuota($me->id, $loveMax);
        $assigned = $me->site ? ['site'=>['nama_lokasi'=>$me->site->nama_lokasi,'radius'=>(int)$me->site->radius_m,'id'=>$me->site->id], 'regionName'=>$me->region?->name ?? ''] : null;
        $myClaims = ToleranceClaim::with('approver')->where('employee_id',$me->id)->orderByDesc('created_at')->get()->map(function (ToleranceClaim $c) {
            $tgl = $c->claim_date instanceof \Illuminate\Support\Carbon ? $c->claim_date->format('Y-m-d') : (string)$c->claim_date;
            return [
                'id'=>$c->id,'jenis'=>$c->jenis,'tgl'=>$tgl,'jam'=> $c->jam ? substr((string)$c->jam,0,5):'','alasan'=>$c->alasan,
                'approver_id'=>$c->approver_id,'approver_nama'=>$c->approver?->name ?? '','approver_nip'=>$c->approver?->nip ?? '','approver_scope'=>$c->approver?->region?->name ?? '',
                'status'=>$c->status,'note'=>$c->note,'office_location_id'=>$c->site_id,'radius'=> $c->site_id ? null : null,
                'createdAt'=>$c->created_at?->toIsoString(),
            ];
        })->values()->all();

        return Inertia::render('Karyawan/Love', [
            'me'=>['id'=>$me->id,'nama'=>$me->name,'region'=>$me->region?->name ?? '','regionId'=>$me->region_id,'office_location_id'=>$me->site_id],
            'assigned'=>$assigned,
            'settings'=>['loveMax'=>$loveMax,'loveQuota'=>$quota],
            'claims'=>$myClaims,
            'approvers'=> AdminPresenter::approversFor($me->region_id, $me->site_id),
        ]);
    }

    public function store(Request $request)
    {
        $me = Auth::guard('employee')->user()->load(['region','site']);
        if (! $me->site_id || ! $me->site) {
            return back()->withErrors(['site'=>'Titik belum di-assign — tidak bisa klaim.'])->withInput();
        }
        $data = $request->validate([
            'jenis'=>['required','in:lupa_absen,lupa_pulang'],
            'tgl'=>['required','date','before_or_equal:today'],
            'jam'=>['required','date_format:H:i'],
            'alasan'=>['required','string','min:5','max:1000'],
            'approver_id'=>['required','integer','exists:users,id'],
        ]);
        $claimDate = \Illuminate\Support\Carbon::parse($data['tgl']);
        if ($claimDate->isWeekend()) {
            return back()->withErrors(['tgl'=>'Tanggal tidak boleh weekend'])->withInput();
        }
        // same month only
        $now = now('Asia/Makassar');
        if ((int)$claimDate->format('n') !== (int)$now->format('n') || (int)$claimDate->format('Y') !== (int)$now->format('Y')) {
            return back()->withErrors(['tgl'=>'Klaim hanya untuk bulan yang sama'])->withInput();
        }
        $settings = AttendanceSetting::first();
        $loveMax = $settings ? (int)$settings->love_max : 4;
        $quota = AdminPresenter::loveQuota($me->id, $loveMax);
        if ($quota['sisa'] <= 0) {
            return back()->withErrors(['love'=>'Sisa Toleransi 0 — reset bulan depan'])->withInput();
        }
        // approver must be valid for this site/region
        $allow = collect(AdminPresenter::approversFor($me->region_id, $me->site_id))->pluck('id')->all();
        if (! in_array((int)$data['approver_id'], array_map('intval', $allow), true)) {
            return back()->withErrors(['approver_id'=>'Atasan tidak valid — pilih dari daftar'])->withInput();
        }

        ToleranceClaim::create([
            'employee_id'=>$me->id,
            'jenis'=>$data['jenis'],
            'claim_date'=>$data['tgl'],
            'jam'=>$data['jam'].':00',
            'alasan'=>$data['alasan'],
            'site_id'=>$me->site_id,
            'region_id'=>$me->region_id,
            'approver_id'=>$data['approver_id'],
            'status'=>'pending',
        ]);
        return back()->with('success','Toleransi diajukan — menunggu persetujuan');
    }
}
