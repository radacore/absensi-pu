<?php

namespace App\Http\Controllers\Karyawan;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Support\AdminPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CutiController extends Controller
{
    public function index()
    {
        $me = Auth::guard('employee')->user()->load(['region','site']);
        $assigned = $me->site ? ['site'=>['nama_lokasi'=>$me->site->nama_lokasi,'radius'=>$me->site->radius_m]] : null;
        $myList = Leave::where('employee_id', $me->id)->orderByDesc('created_at')->get()->map(function (Leave $l) use ($me) {
            $mulai = $l->mulai instanceof \Illuminate\Support\Carbon ? $l->mulai->format('Y-m-d') : (string) $l->mulai;
            $selesai = $l->selesai instanceof \Illuminate\Support\Carbon ? $l->selesai->format('Y-m-d') : (string) $l->selesai;
            $tglLabel = $mulai === $selesai
                ? \Illuminate\Support\Carbon::parse($mulai)->locale('id')->isoFormat('D MMM YYYY')
                : \Illuminate\Support\Carbon::parse($mulai)->locale('id')->isoFormat('D MMM').'–'.\Illuminate\Support\Carbon::parse($selesai)->locale('id')->isoFormat('D MMM YYYY');
            return [
                'id'=>$l->id,'jenis'=>$l->jenis,'tgl'=>$tglLabel,'mulai'=>$mulai,'selesai'=>$selesai,'alasan'=>$l->alasan,'status'=>$l->status,'level'=>(int)$l->level,'note'=>$l->note,
                'wilayah'=>$me->region?->name ?? '', 'office_location_id'=>$me->site_id,
            ];
        })->values()->all();

        return Inertia::render('Karyawan/Cuti', [
            'me'=>['id'=>$me->id,'nama'=>$me->name,'region'=>$me->region?->name ?? '','regionId'=>$me->region_id,'office_location_id'=>$me->site_id,'email'=>$me->email ?? ''],
            'assigned'=> $me->site ? ['site'=>['nama_lokasi'=>$me->site->nama_lokasi,'radius'=>(int)$me->site->radius_m], 'regionName'=>$me->region?->name ?? ''] : null,
            'list'=>$myList,
            'approvers'=> AdminPresenter::approversFor($me->region_id, $me->site_id),
        ]);
    }

    public function store(Request $request)
    {
        $me = Auth::guard('employee')->user();
        $data = $request->validate([
            'jenis'=>['required','in:Tahunan,Sakit,Besar,Melahirkan'],
            'mulai'=>['required','date','after_or_equal:today'],
            'selesai'=>['required','date','after_or_equal:mulai'],
            'alasan'=>['required','string','min:5','max:1000'],
        ]);
        Leave::create([
            'employee_id'=>$me->id,
            'jenis'=>$data['jenis'],
            'mulai'=>$data['mulai'],
            'selesai'=>$data['selesai'],
            'alasan'=>$data['alasan'],
            'status'=>'Menunggu',
            'level'=>0,
        ]);
        return back()->with('success','Pengajuan cuti dikirim — menunggu persetujuan');
    }

    public function destroy(Leave $cuti)
    {
        $me = Auth::guard('employee')->user();
        abort_if((int)$cuti->employee_id !== (int)$me->id, 403);
        abort_if($cuti->status !== 'Menunggu' || (int)$cuti->level !== 0, 403, 'Hanya Menunggu level 0 bisa dibatalkan');
        $cuti->delete();
        return back()->with('success','Cuti dibatalkan');
    }
}
