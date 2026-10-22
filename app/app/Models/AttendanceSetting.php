<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class AttendanceSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'jam_masuk',
        'jam_pulang',
        'jam_pulang_jumat',
        'toleransi_late_menit',
        'love_max',
        'cuti_tahunan_max',
        'cakupan_pola_shift',
        'cuti_shift_max',
        'absen_libur_aktif',
        'absen_libur_mode',
        'hari_kerja',
        'timezone',
        'updated_by',
    ];

    /** Hari kerja default (1 = Senin … 7 = Minggu, ISO-8601). */
    public const HARI_KERJA_DEFAULT = ['1', '2', '3', '4', '5'];

    /** Kuota cuti tahunan default (hari kerja per tahun). */
    public const CUTI_TAHUNAN_DEFAULT = 12;

    /**
     * Seberapa dalam pola shift dicari saat menentukan pola seorang karyawan.
     * Bawaan `bertingkat` = perilaku yang sudah berjalan.
     */
    public const CAKUPAN_POLA_DEFAULT = 'bertingkat';

    /** Cakupan pola shift yang dikenali, terurut dari paling dalam. */
    public const CAKUPAN_POLA = ['bertingkat', 'wilayah', 'global'];

    /** Jatah cuti tahunan khusus pekerja shift, 0 = ikut kuota umum. */
    public const CUTI_SHIFT_DEFAULT = 0;

    /** Cakupan pola shift yang berlaku, selalu salah satu nilai sah. */
    public function cakupanPola(): string
    {
        $cakupan = (string) $this->cakupan_pola_shift;

        return in_array($cakupan, self::CAKUPAN_POLA, true) ? $cakupan : self::CAKUPAN_POLA_DEFAULT;
    }

    protected function casts(): array
    {
        return [
            'hari_kerja' => 'array',
            'absen_libur_aktif' => 'boolean',
        ];
    }

    /** Daftar hari kerja sebagai string, selalu terisi. */
    public function hariKerjaList(): array
    {
        $list = $this->hari_kerja;

        return is_array($list) && $list !== []
            ? array_map('strval', $list)
            : self::HARI_KERJA_DEFAULT;
    }

    /**
     * Jam pulang yang berlaku pada satu tanggal.
     *
     * Jumat boleh punya jam pulang sendiri (mis. 30 menit lebih awal karena
     * sholat Jumat). Kolom `jam_pulang_jumat` kosong berarti ikut jam pulang
     * global, jadi perilaku lama tidak berubah sampai Super Admin mengisinya.
     *
     * Selalu panggil method ini — bukan `$settings->jam_pulang` langsung —
     * supaya rekap, status pulang cepat, dan tampilan jadwal memakai jam yang
     * sama untuk tanggal yang sama.
     */
    public function jamPulangPada(Carbon|string $tanggal): string
    {
        $date = $tanggal instanceof Carbon ? $tanggal : Carbon::parse($tanggal);

        $khusus = (string) $this->jam_pulang_jumat;

        if ($khusus !== '' && $date->isoWeekday() === Carbon::FRIDAY) {
            return substr($khusus, 0, 5);
        }

        return substr((string) $this->jam_pulang, 0, 5);
    }

    /** Jam pulang pada satu tanggal dalam satuan menit sejak 00:00. */
    public function jamPulangMenitPada(Carbon|string $tanggal): int
    {
        [$jam, $menit] = array_map('intval', explode(':', $this->jamPulangPada($tanggal)));

        return $jam * 60 + $menit;
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
