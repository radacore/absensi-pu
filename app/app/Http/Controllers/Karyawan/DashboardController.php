<?php

namespace App\Http\Controllers\Karyawan;

use App\Http\Controllers\Controller;
use App\Models\AnnouncementRead;
use App\Models\Leave;
use App\Models\ToleranceClaim;
use App\Support\AdminPresenter;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $me = Auth::guard('employee')->user()->load(['region','site']);
        $settings = AdminPresenter::settingsFor();
        $loveMax = $settings['loveMax'] ?? 4;
        $quota = AdminPresenter::loveQuota($me->id, $loveMax);
        $assigned = $me->site ? [
            'site'=>['id'=>$me->site->id,'nama_lokasi'=>$me->site->nama_lokasi,'radius'=>(int)$me->site->radius_m],
            'regionName'=>$me->region?->name ?? '',
        ] : null;
        // attend rekap hadir = count attendances this month via presenter quickly
        $readIds = AnnouncementRead::where('employee_id',$me->id)->pluck('announcement_id')->all();
        $announcements = AdminPresenter::announcementsForKaryawan($me, $readIds);
        $unread = collect($announcements)->where('read', false)->count();
        $hadir = \App\Models\Attendance::where('employee_id',$me->id)->whereYear('work_date', now('Asia/Makassar')->year)->whereMonth('work_date', now('Asia/Makassar')->month)->count();
        $myCuti = Leave::where('employee_id',$me->id)->orderByDesc('created_at')->limit(3)->get();

        return Inertia::render('Karyawan/Dashboard', [
            'me'=>['id'=>$me->id,'nama'=>$me->name,'foto'=>$me->foto_url ?? '', 'region'=>$me->region?->name ?? '','regionId'=>$me->region_id,'office_location_id'=>$me->site_id],
            'assigned'=>$assigned,
            'settings'=>$settings,
            'quota'=>$quota,
            'hadir'=>$hadir ?: 0,
            'unread'=>$unread,
            'cutiCount'=> Leave::where('employee_id',$me->id)->count(),
            'myCuti'=> $myCuti->map(fn($l)=>['status'=>$l->status])->values()->all(),
        ]);
    }
}
