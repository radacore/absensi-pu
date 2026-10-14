<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ShiftPattern;
use App\Models\ShiftSchedule;
use App\Support\Audit;
use App\Support\ShiftResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Jadwal shift mingguan — disusun MANUAL oleh Admin Wilayah (K6–K9).
 *
 * Tidak ada rotasi otomatis: admin memilih sendiri shift tiap orang per hari.
 * `shiftKe` kosong = belum diatur; `shiftKe` null = libur.
 */
class ShiftScheduleController extends Controller
{
    private function scope(): ?int
    {
        $u = Auth::guard('web')->user();

        return $u->role === 'super_admin' ? null : $u->region_id;
    }

    public function index(Request $request)
    {
        $scope = $this->scope();
        $now = now('Asia/Makassar');

        // Senin dari minggu yang dipilih (default: minggu berjalan).
        $acuan = $request->query('minggu')
            ? Carbon::parse((string) $request->query('minggu'), 'Asia/Makassar')
            : $now->copy();
        $senin = $acuan->startOfDay()->startOfWeek(Carbon::MONDAY);
        $hari = collect(range(0, 6))->map(fn ($i) => $senin->copy()->addDays($i)->toDateString())->all();

        $employees = Employee::with(['region', 'site'])
            ->where('is_shift', true)
            ->when($scope, fn ($q) => $q->where('region_id', $scope))
            ->orderBy('name')
            ->get();

        $patterns = ShiftResolver::patternsFor($employees);

        $jadwal = [];
        if ($employees->isNotEmpty()) {
            $rows = ShiftSchedule::whereIn('employee_id', $employees->pluck('id')->all())
                ->whereBetween('tanggal', [$hari[0], $hari[6]])
                ->get();

            foreach ($rows as $r) {
                $jadwal[$r->employee_id.'|'.$r->tanggal->toDateString()] = $r->shift_ke;
            }
        }

        $list = $employees->map(function (Employee $e) use ($patterns, $jadwal, $hari) {
            $p = $patterns[$e->id] ?? null;

            $cells = [];
            foreach ($hari as $tgl) {
                $key = $e->id.'|'.$tgl;
                if (! array_key_exists($key, $jadwal)) {
                    continue; // belum diatur
                }
                $cells[$tgl] = $jadwal[$key] === null ? null : (int) $jadwal[$key];
            }

            return [
                'id' => $e->id,
                'nama' => $e->name,
                'nik' => $e->nik,
                'regionNama' => $e->region?->name ?? '',
                'siteNama' => $e->site?->nama_lokasi ?? '',
                'polaNama' => $p?->nama ?? '',
                'jumlahShift' => $p ? (int) $p->jumlah_shift : 0,
                'shiftLabels' => $p
                    ? collect(range(1, (int) $p->jumlah_shift))
                        ->map(fn ($n) => ['ke' => $n, 'label' => $p->labelShift($n), 'mulai' => $p->jamMulaiShift($n)])
                        ->all()
                    : [],
                'cells' => $cells,
            ];
        })->values()->all();

        return Inertia::render('Admin/JadwalShift', [
            'employees' => $list,
            'hari' => $hari,
            'senin' => $hari[0],
            'mingguLalu' => $senin->copy()->subWeek()->toDateString(),
            'mingguDepan' => $senin->copy()->addWeek()->toDateString(),
            'tanpaPola' => collect($list)->where('jumlahShift', 0)->count(),
            'isSuperAdmin' => $scope === null,
        ]);
    }

    public function save(Request $request)
    {
        $scope = $this->scope();

        $data = $request->validate([
            'baris' => ['present', 'array'],
            'baris.*.employeeId' => ['required', 'integer', 'exists:employees,id'],
            'baris.*.tanggal' => ['required', 'date'],
            'baris.*.shiftKe' => ['nullable', 'integer', 'min:1', 'max:'.ShiftPattern::MAX_SHIFT],
        ]);

        $ids = collect($data['baris'])->pluck('employeeId')->map(fn ($v) => (int) $v)->unique()->values()->all();
        $tanggal = collect($data['baris'])->pluck('tanggal')->unique()->values()->all();

        if (! $ids || ! $tanggal) {
            return back()->with('success', 'Tidak ada perubahan.');
        }

        $employees = Employee::with(['region', 'site'])->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($ids as $id) {
            $e = $employees->get($id);
            if (! $e || ! $e->is_shift) {
                throw ValidationException::withMessages(['baris' => 'Ada karyawan yang bukan pekerja shift.']);
            }
            if ($scope !== null && (int) $e->region_id !== $scope) {
                abort(403, 'Karyawan ini di luar cakupan Anda.');
            }
        }

        $patterns = ShiftResolver::patternsFor($employees->values());
        $min = min($tanggal);
        $max = max($tanggal);
        $pengguna = Auth::guard('web')->user();

        DB::transaction(function () use ($data, $ids, $min, $max, $patterns, $pengguna) {
            // Ganti utuh rentang yang dikirim: sel yang dikosongkan admin ikut terhapus.
            // whereDate dipakai supaya batas akhir rentang ikut terhapus walau
            // kolom tanggal menyimpan komponen jam.
            ShiftSchedule::whereIn('employee_id', $ids)
                ->whereDate('tanggal', '>=', $min)
                ->whereDate('tanggal', '<=', $max)
                ->delete();

            foreach ($data['baris'] as $b) {
                $empId = (int) $b['employeeId'];
                $p = $patterns[$empId] ?? null;

                ShiftSchedule::create([
                    'employee_id' => $empId,
                    'tanggal' => $b['tanggal'],
                    'shift_ke' => isset($b['shiftKe']) ? (int) $b['shiftKe'] : null,
                    'shift_pattern_id' => $p?->id,
                    'created_by' => $pengguna?->id,
                ]);
            }
        });

        Audit::log(
            'shift.schedule.save',
            subject: null,
            label: 'Jadwal shift '.$min.' s/d '.$max,
            description: 'Simpan jadwal shift '.count($data['baris'])." sel ({$min} s/d {$max})",
            meta: ['tanggal_mulai' => $min, 'tanggal_akhir' => $max, 'jumlah_sel' => count($data['baris'])]
        );

        return back()->with('success', 'Jadwal shift disimpan.');
    }
}
