<?php

namespace App\Http\Controllers\Karyawan;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Support\AdminPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PengumumanController extends Controller
{
    public function index()
    {
        $me = Auth::guard('employee')->user()->load(['region','site']);
        $readIds = AnnouncementRead::where('employee_id',$me->id)->pluck('announcement_id')->all();
        $list = AdminPresenter::announcementsForKaryawan($me, $readIds);
        $assigned = $me->site ? ['site'=>['nama_lokasi'=>$me->site->nama_lokasi,'radius'=>(int)$me->site->radius_m]] : null;
        return Inertia::render('Karyawan/Pengumuman', [
            'me'=>['id'=>$me->id,'nama'=>$me->name,'region'=>$me->region?->name ?? ''],
            'assigned'=>$assigned,
            'list'=>$list,
            'readIds'=>$readIds,
            'unreadCount'=> collect($list)->where('read', false)->count(),
        ]);
    }

    public function markRead(Announcement $pengumuman)
    {
        $me = Auth::guard('employee')->user();
        AnnouncementRead::firstOrCreate(['announcement_id'=>$pengumuman->id,'employee_id'=>$me->id]);
        return back()->with('success','Ditandai dibaca');
    }

    public function markAllRead(Request $request)
    {
        $me = Auth::guard('employee')->user()->load('region');
        $ids = Announcement::where(fn($q)=>$q->where('scope','Global')->orWhere('region_id',$me->region_id))->pluck('id')->all();
        foreach ($ids as $id) {
            AnnouncementRead::firstOrCreate(['announcement_id'=>$id,'employee_id'=>$me->id]);
        }
        return back()->with('success','Semua ditandai dibaca');
    }
}
