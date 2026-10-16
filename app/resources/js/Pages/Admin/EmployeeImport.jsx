import AdminLayout from '@/Layouts/AdminLayout';
import { toast } from '@/lib/toast';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { getBase } from './_shared';

const MODE_PILIHAN = [
    { nilai: 'tambah', judul: 'Tambah saja', ket: 'Karyawan yang NIK/NIP-nya (atau emailnya) sudah terdaftar dilewati. Data lama tidak pernah diubah.' },
    { nilai: 'perbarui', judul: 'Perbarui saja', ket: 'Hanya karyawan yang sudah terdaftar yang diubah. Yang belum terdaftar dilewati. Sandi tidak pernah direset.' },
    { nilai: 'gabungan', judul: 'Tambah & perbarui', ket: 'Yang belum ada ditambahkan, yang sudah ada diperbarui. Pilihan paling umum.' },
];

// Sumber sandi awal. Nilai "bawaan" adalah pilihan utama: satu sandi yang sama
// untuk semua akun baru, sama seperti tambah manual dan reset kata sandi.
// Nilainya ditulis apa adanya supaya admin tahu sandi mana yang harus
// diberitahukan ke karyawan.
const sandiPilihan = (bawaan) => [
    { nilai: 'bawaan', judul: bawaan ? `Sandi bawaan (${bawaan})` : 'Sandi bawaan', ket: 'Satu sandi yang sama untuk semua akun baru pada impor ini.' },
    { nilai: 'nik', judul: 'NIK/NIP', ket: 'Bila NIK kosong, otomatis memakai email.' },
    { nilai: 'email', judul: 'Email', ket: 'Selalu memakai alamat email karyawan.' },
    { nilai: 'kolom', judul: 'Kolom "Sandi Awal"', ket: 'Hanya untuk berkas yang punya kolom "Sandi Awal". Minimal 6 karakter.' },
];

const STATUS_PILIHAN = ['Kontrak', 'PNS', 'PPPK', 'Honorer'];

const AKSI_GAYA = {
    tambah: { label: 'Ditambahkan', kelas: 'bg-[#ECFDF5] text-[#065F46] border-[#10B981]/20' },
    perbarui: { label: 'Diperbarui', kelas: 'bg-[#EFF6FF] text-[#1E40AF] border-[#3B82F6]/20' },
    lewati: { label: 'Dilewati', kelas: 'bg-[#F1F5F9] text-[#475569] border-[#CBD5E1]/40' },
    gagal: { label: 'Bermasalah', kelas: 'bg-[#FEF2F2] text-[#991B1B] border-[#EF4444]/20' },
};

const JUDUL_CSV = [
    ['baris', 'Baris'],
    ['aksi', 'Hasil'],
    ['pesan', 'Masalah'],
    ['nik', 'NIK/NIP'],
    ['nama', 'Nama'],
    ['email', 'Email'],
    ['wilayah', 'Wilayah'],
    ['titik', 'Titik Proyek'],
    ['jabatan', 'Status Pegawai'],
    ['unit', 'Satker'],
    ['ppk', 'PPK'],
    ['status', 'Status Kepegawaian'],
    ['nip', 'NIP'],
    ['gol', 'Golongan'],
    ['regu', 'Regu'],
    ['isShift', 'Shift'],
    ['hariKerja', 'Hari Kerja'],
];

/**
 * Simpan baris pratinjau jadi berkas CSV, dihitung di peramban.
 *
 * Dipakai untuk menyerahkan daftar baris bermasalah kembali ke penyusun berkas.
 * Pemisah titik koma dan BOM dipakai supaya Excel versi Indonesia langsung
 * memecah kolomnya dengan benar.
 */
function unduhCsv(namaBerkas, baris) {
    const kutip = (nilai) => `"${String(nilai ?? '').replace(/"/g, '""')}"`;

    const isi = [JUDUL_CSV.map(([, judul]) => kutip(judul)).join(';')];

    for (const b of baris) {
        isi.push(
            JUDUL_CSV.map(([kunci]) => {
                const nilai = b[kunci];
                if (Array.isArray(nilai)) return kutip(nilai.join(', '));
                if (typeof nilai === 'boolean') return kutip(nilai ? 'Ya' : 'Tidak');
                return kutip(nilai);
            }).join(';'),
        );
    }

    const blob = new Blob([`\uFEFF${isi.join('\r\n')}`], { type: 'text/csv;charset=utf-8' });
    const tautan = document.createElement('a');
    tautan.href = URL.createObjectURL(blob);
    tautan.download = namaBerkas;
    document.body.appendChild(tautan);
    tautan.click();
    document.body.removeChild(tautan);
    URL.revokeObjectURL(tautan.href);
}

export default function EmployeeImport() {
    const { url, props } = usePage();
    const { regions, batas, errors, flash, sandiBawaan } = props;
    const base = getBase(url);
    const isWilayah = base === '/admin' || base === '/wilayah';

    const wilayahDaftar = useMemo(() => regions ?? [], [regions]);
    const wilayahTetap = isWilayah ? (wilayahDaftar[0]?.name ?? '') : null;

    const pratinjau = flash?.importPreview ?? null;
    const hasil = flash?.importHasil ?? null;

    const [berkas, setBerkas] = useState(null);
    const [mode, setMode] = useState('gabungan');
    const [sandi, setSandi] = useState('bawaan');
    const [wajibGanti, setWajibGanti] = useState(true);
    const [wilayah, setWilayah] = useState(wilayahTetap ?? '');
    const [titik, setTitik] = useState('');
    const [statusDefault, setStatusDefault] = useState('Kontrak');
    const [hanyaMasalah, setHanyaMasalah] = useState(false);
    const [seret, setSeret] = useState(false);
    const [sibuk, setSibuk] = useState(false);

    const inputRef = useRef(null);
    const hasilRef = useRef(null);

    // Setelah impor benar-benar dijalankan, lepaskan berkasnya supaya tombol
    // "Impor sekarang" tidak bisa terklik dua kali untuk berkas yang sama.
    useEffect(() => {
        if (hasil && hasilRef.current !== hasil) {
            hasilRef.current = hasil;
            setBerkas(null);
            setHanyaMasalah(false);
            if (inputRef.current) inputRef.current.value = '';
        }
    }, [hasil]);

    // Wilayah admin wilayah bisa berganti setelah prop regions tiba.
    useEffect(() => {
        if (wilayahTetap && wilayahTetap !== wilayah) setWilayah(wilayahTetap);
    }, [wilayahTetap]);

    const titikDaftar = useMemo(
        () => wilayahDaftar.find((r) => r.name === wilayah)?.locations ?? [],
        [wilayahDaftar, wilayah],
    );

    const barisTampil = useMemo(() => {
        const daftar = pratinjau?.baris ?? [];
        return hanyaMasalah ? daftar.filter((b) => b.aksi === 'gagal' || b.aksi === 'lewati') : daftar;
    }, [pratinjau, hanyaMasalah]);

    const ambilBerkas = (f) => {
        if (!f) return;
        const nama = f.name.toLowerCase();
        if (!nama.endsWith('.xlsx') && !nama.endsWith('.xls')) {
            toast.error('Berkas harus berformat Excel (.xlsx)');
            return;
        }
        if (batas?.mb && f.size > batas.mb * 1024 * 1024) {
            toast.error(`Ukuran berkas melebihi ${batas.mb} MB`);
            return;
        }
        setBerkas(f);
    };

    const kirim = (tujuan) => {
        if (!berkas) {
            toast.error('Pilih berkas Excel terlebih dahulu');
            return;
        }
        const data = new FormData();
        data.append('berkas', berkas);
        data.append('mode', mode);
        data.append('wilayah', wilayah);
        data.append('titik', titik);
        data.append('statusDefault', statusDefault);
        if (tujuan === 'impor') {
            data.append('sandi', sandi);
            data.append('wajibGanti', wajibGanti ? '1' : '0');
        }
        setSibuk(true);
        router.post(`${base}/employees/impor${tujuan === 'impor' ? '' : '/pratinjau'}`, data, {
            forceFormData: true,
            preserveState: true,
            preserveScroll: true,
            onFinish: () => setSibuk(false),
        });
    };

    const galat = errors?.berkas;

    return (
        <AdminLayout>
            <div className="space-y-5 max-w-6xl">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <Link href={`${base}/employees`} className="text-xs font-medium text-[#1E3A8A]">← Kembali ke Karyawan</Link>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A] mt-1">Impor Akun Karyawan</h1>
                        <p className="text-sm text-[#64748B]">
                            Tambah atau perbarui banyak akun karyawan sekaligus dari satu berkas Excel.
                            {isWilayah && ` Hanya untuk wilayah ${wilayah || '—'}.`}
                        </p>
                    </div>
                </div>

                {/* Langkah 1 — templat */}
                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <div className="flex items-start gap-3">
                        <span className="shrink-0 w-7 h-7 rounded-full bg-[#0F172A] text-white text-xs font-semibold grid place-items-center">1</span>
                        <div className="flex-1">
                            <h2 className="text-sm font-semibold text-[#0F172A]">Unduh templat</h2>
                            <p className="text-xs text-[#64748B] mt-0.5">
                                Templat ini memuat <span className="font-medium">enam kolom</span> yang sama persis dengan berkas yang biasa dikirim PPK
                                (NAMA, NIK/NIP, STATUS PEGAWAI, SATKER, PPK, EMAIL) — isi berkas lama bisa disalin langsung.
                                Lembar <span className="font-medium">Daftar</span> memuat {isWilayah ? 'titik proyek di wilayah Anda' : 'seluruh wilayah dan titik proyek'}.
                            </p>
                            <a
                                href={`${base}/employees/impor/templat`}
                                className="inline-block mt-3 bg-[#1E3A8A] text-white rounded-xl px-4 py-2.5 text-sm font-semibold"
                            >
                                Unduh templat (.xlsx)
                            </a>
                            <p className="text-xs text-[#92400E] bg-[#FFF7E6] border border-[#FCB833]/20 rounded-xl px-3 py-2 mt-3">
                                Jangan mengubah format kolom <span className="font-semibold">NIK/NIP</span>.
                                Kolom itu sudah disetel sebagai <span className="font-semibold">Teks</span> karena Excel hanya menyimpan 15 angka pertama dengan tepat —
                                bila diubah jadi angka, digit terakhir NIK bisa berubah tanpa peringatan.
                            </p>
                        </div>
                    </div>
                </div>

                {/* Langkah 2 — unggah */}
                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <div className="flex items-start gap-3">
                        <span className="shrink-0 w-7 h-7 rounded-full bg-[#0F172A] text-white text-xs font-semibold grid place-items-center">2</span>
                        <div className="flex-1 space-y-4">
                            <div>
                                <h2 className="text-sm font-semibold text-[#0F172A]">Tujuan dan berkas</h2>
                                <p className="text-xs text-[#64748B] mt-0.5">
                                    Kolom wajib: NAMA, STATUS PEGAWAI, SATKER, EMAIL. NIK/NIP dan PPK boleh dikosongkan.
                                    Maksimal {batas?.baris ?? 1000} baris dan {batas?.mb ?? 5} MB per berkas.
                                </p>
                            </div>

                            <div className="rounded-2xl bg-[#F8FAFC] p-4 space-y-3">
                                <p className="text-xs font-semibold text-[#0F172A]">Impor ke</p>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label className="text-xs font-medium text-[#334155]">Wilayah</label>
                                        {isWilayah ? (
                                            <p className="mt-1.5 rounded-xl bg-white border border-[#E2E8F0] px-3 py-2.5 text-sm text-[#0F172A]">{wilayah || '—'}</p>
                                        ) : (
                                            <select
                                                value={wilayah}
                                                onChange={(e) => { setWilayah(e.target.value); setTitik(''); }}
                                                className="mt-1.5 w-full rounded-xl bg-white border border-[#E2E8F0] px-3 py-2.5 text-sm outline-none"
                                            >
                                                <option value="">— Ikut kolom Wilayah di berkas —</option>
                                                {wilayahDaftar.map((r) => (
                                                    <option key={r.id} value={r.name}>{r.name}</option>
                                                ))}
                                            </select>
                                        )}
                                    </div>
                                    <div>
                                        <label className="text-xs font-medium text-[#334155]">Titik proyek</label>
                                        <select
                                            value={titik}
                                            onChange={(e) => setTitik(e.target.value)}
                                            disabled={!wilayah}
                                            className="mt-1.5 w-full rounded-xl bg-white border border-[#E2E8F0] px-3 py-2.5 text-sm outline-none disabled:opacity-50"
                                        >
                                            <option value="">— Ikut kolom Titik Proyek di berkas —</option>
                                            {titikDaftar.map((s) => (
                                                <option key={s.id} value={String(s.id)}>{s.nama_lokasi}</option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                                <p className="text-xs text-[#64748B]">
                                    Dipakai untuk baris yang tidak menyebut wilayah/titiknya sendiri. Bila berkas memuat kolom
                                    <span className="font-medium"> Wilayah</span> atau <span className="font-medium">Titik Proyek</span> yang terisi, isi kolom itulah yang menang.
                                </p>
                                {errors?.wilayah && <p className="text-xs text-[#991B1B]">{errors.wilayah}</p>}
                                {errors?.titik && <p className="text-xs text-[#991B1B]">{errors.titik}</p>}
                            </div>

                            <div
                                onDragOver={(e) => { e.preventDefault(); setSeret(true); }}
                                onDragLeave={() => setSeret(false)}
                                onDrop={(e) => { e.preventDefault(); setSeret(false); ambilBerkas(e.dataTransfer.files?.[0]); }}
                                className={`rounded-2xl border-2 border-dashed px-4 py-6 text-center transition-colors ${seret ? 'border-[#1E3A8A] bg-[#EFF6FF]' : 'border-[#E2E8F0] bg-[#F8FAFC]'}`}
                            >
                                <input
                                    ref={inputRef}
                                    type="file"
                                    accept=".xlsx,.xls"
                                    onChange={(e) => ambilBerkas(e.target.files?.[0])}
                                    className="hidden"
                                />
                                {berkas ? (
                                    <div className="space-y-2">
                                        <p className="text-sm font-medium text-[#0F172A]">{berkas.name}</p>
                                        <p className="text-xs text-[#64748B]">{(berkas.size / 1024).toFixed(1)} KB</p>
                                        <div className="flex gap-2 justify-center">
                                            <button type="button" onClick={() => inputRef.current?.click()} className="text-xs font-semibold text-[#1E3A8A]">Ganti berkas</button>
                                            <span className="text-[#CBD5E1]">•</span>
                                            <button type="button" onClick={() => { setBerkas(null); if (inputRef.current) inputRef.current.value = ''; }} className="text-xs font-semibold text-[#64748B]">Kosongkan</button>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        <p className="text-sm text-[#334155]">Tarik berkas Excel ke sini, atau</p>
                                        <button type="button" onClick={() => inputRef.current?.click()} className="bg-white border border-[#E2E8F0] rounded-xl px-4 py-2 text-sm font-semibold text-[#0F172A]">
                                            Pilih berkas
                                        </button>
                                    </div>
                                )}
                            </div>

                            <div>
                                <p className="text-xs font-medium text-[#334155] mb-1.5">Bila karyawannya sudah terdaftar</p>
                                <div className="grid gap-2 sm:grid-cols-3">
                                    {MODE_PILIHAN.map((m) => (
                                        <label
                                            key={m.nilai}
                                            className={`rounded-xl border px-3 py-2.5 cursor-pointer ${mode === m.nilai ? 'border-[#1E3A8A] bg-[#EFF6FF]' : 'border-[#E2E8F0] bg-white'}`}
                                        >
                                            <span className="flex items-center gap-2">
                                                <input type="radio" name="mode" checked={mode === m.nilai} onChange={() => setMode(m.nilai)} className="w-4 h-4 accent-[#0F172A]" />
                                                <span className="text-sm font-medium text-[#0F172A]">{m.judul}</span>
                                            </span>
                                            <span className="block text-xs text-[#64748B] mt-1 pl-6">{m.ket}</span>
                                        </label>
                                    ))}
                                </div>
                                <p className="text-xs text-[#64748B] mt-1.5">
                                    Pencocokan memakai NIK/NIP bila barisnya punya NIK; bila NIK kosong, dipakai email.
                                    Saat memperbarui, hanya kolom yang benar-benar ada di berkas yang ditulis — kolom yang tidak
                                    disertakan dibiarkan apa adanya.
                                </p>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label className="text-xs font-medium text-[#334155]">Status kepegawaian bawaan</label>
                                    <select
                                        value={statusDefault}
                                        onChange={(e) => setStatusDefault(e.target.value)}
                                        className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none"
                                    >
                                        {STATUS_PILIHAN.map((s) => <option key={s} value={s}>{s}</option>)}
                                    </select>
                                    <span className="block text-xs text-[#64748B] mt-1">
                                        Templat tidak memuat kolom &quot;Status Kepegawaian&quot;, jadi nilai ini dipakai untuk semua baris. Bila berkas punya kolom itu, isi kolom itulah yang dipakai.
                                    </span>
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-[#334155]">Sandi awal karyawan baru</label>
                                    <select
                                        value={sandi}
                                        onChange={(e) => setSandi(e.target.value)}
                                        className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none"
                                    >
                                        {sandiPilihan(sandiBawaan).map((s) => <option key={s.nilai} value={s.nilai}>{s.judul}</option>)}
                                    </select>
                                    <span className="block text-xs text-[#64748B] mt-1">
                                        {sandiPilihan(sandiBawaan).find((s) => s.nilai === sandi)?.ket} Karyawan yang sudah ada tidak pernah diganti sandinya.
                                    </span>
                                </div>
                            </div>

                            <div className="rounded-xl bg-[#F8FAFC] px-3 py-3">
                                <label className="flex items-start gap-3 cursor-pointer">
                                    <input type="checkbox" checked={wajibGanti} onChange={(e) => setWajibGanti(e.target.checked)} className="mt-0.5 w-4 h-4 accent-[#0F172A]" />
                                    <span>
                                        <span className="text-sm font-medium text-[#0F172A]">Wajib ganti sandi saat login pertama</span>
                                        <span className="block text-xs text-[#64748B] mt-0.5">Berlaku untuk akun yang baru dibuat pada impor ini.</span>
                                    </span>
                                </label>
                            </div>

                            {galat && (
                                <p className="text-xs text-[#991B1B] bg-[#FEF2F2] border border-[#EF4444]/20 rounded-xl px-3 py-2">{galat}</p>
                            )}

                            <div className="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    onClick={() => kirim('pratinjau')}
                                    disabled={sibuk || !berkas}
                                    className="bg-[#0F172A] text-white rounded-xl px-4 py-2.5 text-sm font-semibold disabled:opacity-40"
                                >
                                    Periksa berkas
                                </button>
                                <button
                                    type="button"
                                    onClick={() => kirim('impor')}
                                    disabled={sibuk || !berkas}
                                    className="bg-[#1E3A8A] text-white rounded-xl px-4 py-2.5 text-sm font-semibold disabled:opacity-40"
                                >
                                    Langsung impor
                                </button>
                                <span className="text-xs text-[#64748B] self-center">
                                    &quot;Periksa berkas&quot; tidak menyimpan apa pun — aman dicoba berkali-kali.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Langkah 3 — pratinjau */}
                {pratinjau && (
                    <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                        <div className="flex items-start gap-3">
                            <span className="shrink-0 w-7 h-7 rounded-full bg-[#0F172A] text-white text-xs font-semibold grid place-items-center">3</span>
                            <div className="flex-1 space-y-3">
                                <div className="flex flex-wrap items-center gap-3">
                                    <div>
                                        <h2 className="text-sm font-semibold text-[#0F172A]">Pratinjau — belum ada yang disimpan</h2>
                                        <p className="text-xs text-[#64748B] mt-0.5">Berkas: {pratinjau.berkas}</p>
                                    </div>
                                    <div className="ml-auto flex flex-wrap gap-2 text-xs">
                                        <Chip label="Total" nilai={pratinjau.ringkasan.total} />
                                        <Chip label="Ditambahkan" nilai={pratinjau.ringkasan.tambah} nada="hijau" />
                                        <Chip label="Diperbarui" nilai={pratinjau.ringkasan.perbarui} nada="biru" />
                                        <Chip label="Dilewati" nilai={pratinjau.ringkasan.lewati} nada="abu" />
                                        <Chip label="Bermasalah" nilai={pratinjau.ringkasan.gagal} nada="merah" />
                                    </div>
                                </div>

                                {pratinjau.kolom?.length > 0 && (
                                    <p className="text-xs text-[#64748B]">
                                        Kolom yang terbaca dari berkas: <span className="text-[#334155]">{pratinjau.kolom.join(' · ')}</span>
                                    </p>
                                )}

                                {ringkasanPpk(pratinjau.baris).length > 0 && (
                                    <div className="flex flex-wrap gap-2 text-xs">
                                        {ringkasanPpk(pratinjau.baris).map(([nama, jumlah]) => (
                                            <span key={nama} className="rounded-full border border-[#E2E8F0] bg-[#F8FAFC] px-2.5 py-1 text-[#334155]">
                                                {nama}: <span className="font-semibold">{jumlah}</span>
                                            </span>
                                        ))}
                                    </div>
                                )}

                                <div className="flex flex-wrap items-center gap-3">
                                    <label className="flex items-center gap-2 text-xs text-[#334155] cursor-pointer">
                                        <input type="checkbox" checked={hanyaMasalah} onChange={(e) => setHanyaMasalah(e.target.checked)} className="w-4 h-4 accent-[#0F172A]" />
                                        Tampilkan hanya baris bermasalah / dilewati
                                    </label>
                                    {pratinjau.ringkasan.gagal > 0 && (
                                        <button
                                            type="button"
                                            onClick={() => unduhCsv('baris-bermasalah.csv', pratinjau.baris.filter((b) => b.aksi === 'gagal'))}
                                            className="text-xs font-semibold text-[#1E3A8A]"
                                        >
                                            Unduh {pratinjau.ringkasan.gagal} baris bermasalah (.csv)
                                        </button>
                                    )}
                                </div>

                                <TabelPratinjau baris={barisTampil} />

                                <div className="flex flex-wrap gap-2 pt-1">
                                    <button
                                        type="button"
                                        onClick={() => kirim('impor')}
                                        disabled={sibuk || !berkas || (pratinjau.ringkasan.tambah + pratinjau.ringkasan.perbarui === 0)}
                                        className="bg-[#1E3A8A] text-white rounded-xl px-4 py-2.5 text-sm font-semibold disabled:opacity-40"
                                    >
                                        Impor {pratinjau.ringkasan.tambah + pratinjau.ringkasan.perbarui} baris sekarang
                                    </button>
                                    {pratinjau.ringkasan.tambah + pratinjau.ringkasan.perbarui === 0 && (
                                        <span className="text-xs text-[#92400E] self-center">
                                            Tidak ada baris yang bisa diproses. Perbaiki dulu baris bermasalah, lalu unggah ulang.
                                        </span>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Hasil impor */}
                {hasil && (
                    <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                        <div className="flex flex-wrap items-center gap-3">
                            <div>
                                <h2 className="text-sm font-semibold text-[#0F172A]">Impor selesai — {hasil.berkas}</h2>
                                <p className="text-xs text-[#64748B] mt-0.5">
                                    {hasil.ringkasan.tambah} ditambahkan, {hasil.ringkasan.perbarui} diperbarui,{' '}
                                    {hasil.ringkasan.lewati} dilewati, {hasil.ringkasan.gagal} bermasalah.
                                </p>
                            </div>
                            <div className="ml-auto flex gap-2">
                                {hasil.ringkasan.gagal > 0 && (
                                    <button
                                        type="button"
                                        onClick={() => unduhCsv('baris-bermasalah.csv', hasil.baris.filter((b) => b.aksi === 'gagal'))}
                                        className="text-xs font-semibold text-[#1E3A8A]"
                                    >
                                        Unduh baris bermasalah (.csv)
                                    </button>
                                )}
                                <Link href={`${base}/employees`} className="text-xs font-semibold text-[#1E3A8A]">Lihat daftar karyawan →</Link>
                            </div>
                        </div>

                        {hasil.ringkasan.gagal > 0 && (
                            <div className="mt-3">
                                <TabelPratinjau baris={hasil.baris.filter((b) => b.aksi === 'gagal')} />
                            </div>
                        )}
                    </div>
                )}

                {/* Rujukan kolom */}
                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <h2 className="text-sm font-semibold text-[#0F172A]">Keterangan kolom</h2>
                    <p className="text-xs text-[#64748B] mt-0.5">
                        Templat hanya memuat enam kolom di bawah ini, sama persis dengan berkas PPK. Kolom lain
                        tidak perlu ditambahkan: wilayah, titik proyek, regu, shift, dan hari kerja diatur dari
                        halaman ini atau disunting setelah impor.
                    </p>
                    <div className="overflow-x-auto mt-3">
                        <table className="w-full text-xs">
                            <thead className="bg-[#F8FAFC] text-[#64748B]">
                                <tr>
                                    <th className="text-left px-3 py-2 font-medium">Kolom</th>
                                    <th className="text-left px-3 py-2 font-medium">Wajib</th>
                                    <th className="text-left px-3 py-2 font-medium">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#F1F5F9] text-[#334155]">
                                <BarisKolom nama="NAMA" wajib>Nama lengkap karyawan.</BarisKolom>
                                <BarisKolom nama="NIK/NIP">Boleh dikosongkan dan boleh kurang dari 16 digit. Disimpan apa adanya. Kolom sudah berformat Teks — jangan diubah.</BarisKolom>
                                <BarisKolom nama="STATUS PEGAWAI" wajib>
                                    Bebas diisi, mis. &quot;Petugas OP&quot;, &quot;Tenaga Pendukung&quot;, &quot;Konsultan Individu&quot;. Tersimpan sebagai jabatan.
                                </BarisKolom>
                                <BarisKolom nama="SATKER" wajib>Satuan kerja karyawan, mis. &quot;OP SDA&quot;. Tersimpan sebagai unit kerja.</BarisKolom>
                                <BarisKolom nama="PPK">Label PPK karyawan, mis. &quot;PPK OP SDA II&quot;. Hanya keterangan, tidak mengatur hak akses.</BarisKolom>
                                <BarisKolom nama="EMAIL" wajib>Dipakai untuk login. Harus unik.</BarisKolom>
                            </tbody>
                        </table>
                    </div>
                    <p className="text-xs text-[#64748B] mt-3">
                        Berkas milik PPK boleh diunggah apa adanya. Bila berkas itu kebetulan memuat kolom
                        tambahan (Wilayah, Titik Proyek, NIP, Golongan, Status Kepegawaian, Regu, Shift, Hari Kerja,
                        Sandi Awal), isinya tetap dibaca.
                    </p>
                </div>
            </div>
        </AdminLayout>
    );
}

/** Hitung jumlah baris per PPK, untuk ringkasan cepat sebelum impor. */
function ringkasanPpk(baris) {
    const hitung = new Map();
    for (const b of baris ?? []) {
        if (b.aksi !== 'tambah' && b.aksi !== 'perbarui') continue;
        const nama = (b.ppk || '').trim() || 'Tanpa PPK';
        hitung.set(nama, (hitung.get(nama) ?? 0) + 1);
    }
    return [...hitung.entries()].sort((a, b) => b[1] - a[1]);
}

function Chip({ label, nilai, nada }) {
    const warna = {
        hijau: 'bg-[#ECFDF5] text-[#065F46] border-[#10B981]/20',
        biru: 'bg-[#EFF6FF] text-[#1E40AF] border-[#3B82F6]/20',
        abu: 'bg-[#F1F5F9] text-[#475569] border-[#CBD5E1]/40',
        merah: 'bg-[#FEF2F2] text-[#991B1B] border-[#EF4444]/20',
    }[nada] ?? 'bg-[#F8FAFC] text-[#334155] border-[#E2E8F0]';

    return (
        <span className={`rounded-full border px-2.5 py-1 ${warna}`}>
            {label}: <span className="font-semibold">{nilai}</span>
        </span>
    );
}

function BarisKolom({ nama, wajib = false, children }) {
    return (
        <tr>
            <td className="px-3 py-2 font-medium text-[#0F172A] whitespace-nowrap">{nama}</td>
            <td className="px-3 py-2">
                {wajib
                    ? <span className="text-[#991B1B] font-semibold">Wajib</span>
                    : <span className="text-[#64748B]">Opsional</span>}
            </td>
            <td className="px-3 py-2">{children}</td>
        </tr>
    );
}

function TabelPratinjau({ baris }) {
    if (!baris || baris.length === 0) {
        return <p className="text-xs text-[#64748B] bg-[#F8FAFC] rounded-xl px-3 py-3">Tidak ada baris pada saringan ini.</p>;
    }

    return (
        <div className="overflow-x-auto rounded-xl border border-[#F1F5F9]">
            <table className="w-full text-xs">
                <thead className="bg-[#F8FAFC] text-[#64748B]">
                    <tr>
                        <th className="text-left px-3 py-2 font-medium">Baris</th>
                        <th className="text-left px-3 py-2 font-medium">Hasil</th>
                        <th className="text-left px-3 py-2 font-medium">NIK/NIP</th>
                        <th className="text-left px-3 py-2 font-medium">Nama</th>
                        <th className="text-left px-3 py-2 font-medium">PPK</th>
                        <th className="text-left px-3 py-2 font-medium">Wilayah</th>
                        <th className="text-left px-3 py-2 font-medium">Titik Proyek</th>
                        <th className="text-left px-3 py-2 font-medium">Catatan</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-[#F1F5F9]">
                    {baris.map((b) => {
                        const gaya = AKSI_GAYA[b.aksi] ?? AKSI_GAYA.gagal;
                        return (
                            <tr key={b.baris} className={b.aksi === 'gagal' ? 'bg-[#FEF2F2]/40' : undefined}>
                                <td className="px-3 py-2 text-[#64748B]">{b.baris}</td>
                                <td className="px-3 py-2">
                                    <span className={`inline-block rounded-full border px-2 py-0.5 whitespace-nowrap ${gaya.kelas}`}>{gaya.label}</span>
                                </td>
                                <td className="px-3 py-2 font-mono text-[#0F172A]">{b.nik || '—'}</td>
                                <td className="px-3 py-2 text-[#0F172A]">{b.nama || '—'}</td>
                                <td className="px-3 py-2 text-[#334155]">{b.ppk || '—'}</td>
                                <td className="px-3 py-2 text-[#334155]">{b.wilayah || '—'}</td>
                                <td className="px-3 py-2 text-[#334155]">{b.titik || '—'}</td>
                                <td className="px-3 py-2 text-[#991B1B]">
                                    {b.pesan && b.pesan.length > 0 ? (
                                        <ul className="list-disc list-inside space-y-0.5">
                                            {b.pesan.map((p, i) => <li key={i}>{p}</li>)}
                                        </ul>
                                    ) : (
                                        <span className="text-[#065F46]">Tidak ada masalah.</span>
                                    )}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
