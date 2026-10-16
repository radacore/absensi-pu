import AdminLayout from '@/Layouts/AdminLayout';
import { toast } from '@/lib/toast';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { getBase, OWN_REGION } from './_shared';

const HARI_LABEL = { 1: 'Senin', 2: 'Selasa', 3: 'Rabu', 4: 'Kamis', 5: 'Jumat', 6: 'Sabtu', 7: 'Minggu' };

const MODE_PILIHAN = [
    { nilai: 'tambah', judul: 'Tambah saja', ket: 'NIK yang sudah terdaftar dilewati. Data lama tidak pernah diubah.' },
    { nilai: 'perbarui', judul: 'Perbarui saja', ket: 'Hanya NIK yang sudah terdaftar yang diubah. NIK baru dilewati. Sandi tidak pernah direset.' },
    { nilai: 'gabungan', judul: 'Tambah & perbarui', ket: 'NIK baru ditambahkan, NIK lama diperbarui. Pilihan paling umum.' },
];

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
    ['nik', 'NIK'],
    ['nama', 'Nama'],
    ['email', 'Email'],
    ['wilayah', 'Wilayah'],
    ['titik', 'Titik Proyek'],
    ['jabatan', 'Jabatan'],
    ['unit', 'Unit Kerja'],
    ['status', 'Status'],
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
    const { settings, batas, errors, flash } = props;
    const base = getBase(url);
    const isWilayah = base === '/admin' || base === '/wilayah';

    const pratinjau = flash?.importPreview ?? null;
    const hasil = flash?.importHasil ?? null;

    const [berkas, setBerkas] = useState(null);
    const [mode, setMode] = useState('gabungan');
    const [sandi, setSandi] = useState('nik');
    const [wajibGanti, setWajibGanti] = useState(true);
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

    const hariKerjaUmum = useMemo(
        () => (settings?.hariKerja ?? ['1', '2', '3', '4', '5']).map((d) => HARI_LABEL[String(d)] ?? d).join(', '),
        [settings],
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
                            {isWilayah && ` Hanya untuk wilayah ${OWN_REGION}.`}
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
                                Templat sudah memuat daftar {isWilayah ? 'titik proyek di wilayah Anda' : 'seluruh wilayah dan titik proyek'} pada lembar <span className="font-medium">Daftar</span>,
                                lengkap dengan pilihan dropdown. Isi satu karyawan per baris.
                            </p>
                            <a
                                href={`${base}/employees/impor/templat`}
                                className="inline-block mt-3 bg-[#1E3A8A] text-white rounded-xl px-4 py-2.5 text-sm font-semibold"
                            >
                                Unduh templat (.xlsx)
                            </a>
                            <p className="text-xs text-[#92400E] bg-[#FFF7E6] border border-[#FCB833]/20 rounded-xl px-3 py-2 mt-3">
                                Jangan mengubah format kolom <span className="font-semibold">NIK</span> dan <span className="font-semibold">NIP</span>.
                                Keduanya sudah disetel sebagai <span className="font-semibold">Teks</span> karena Excel hanya menyimpan 15 angka pertama dengan tepat —
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
                                <h2 className="text-sm font-semibold text-[#0F172A]">Isi dan unggah</h2>
                                <p className="text-xs text-[#64748B] mt-0.5">
                                    Kolom wajib: NIK, Nama, Email{!isWilayah && ', Wilayah'}, Titik Proyek, Jabatan, Unit Kerja, Status.
                                    Sisanya opsional. Maksimal {batas?.baris ?? 1000} baris dan {batas?.mb ?? 5} MB per berkas.
                                </p>
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
                                <p className="text-xs font-medium text-[#334155] mb-1.5">Bila NIK sudah terdaftar</p>
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
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label className="text-xs font-medium text-[#334155]">Sandi awal karyawan baru</label>
                                    <select value={sandi} onChange={(e) => setSandi(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none">
                                        <option value="nik">Sama dengan NIK</option>
                                        <option value="kolom">Ambil dari kolom &quot;Sandi Awal&quot;</option>
                                    </select>
                                    <span className="block text-xs text-[#64748B] mt-1">Karyawan yang sudah ada tidak pernah diganti sandinya.</span>
                                </div>
                                <div className="rounded-xl bg-[#F8FAFC] px-3 py-3 self-start">
                                    <label className="flex items-start gap-3 cursor-pointer">
                                        <input type="checkbox" checked={wajibGanti} onChange={(e) => setWajibGanti(e.target.checked)} className="mt-0.5 w-4 h-4 accent-[#0F172A]" />
                                        <span>
                                            <span className="text-sm font-medium text-[#0F172A]">Wajib ganti sandi saat login pertama</span>
                                            <span className="block text-xs text-[#64748B] mt-0.5">Berlaku untuk akun yang baru dibuat pada impor ini.</span>
                                        </span>
                                    </label>
                                </div>
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
                                <BarisKolom nama="NIK" wajib>16 digit angka. Kolom sudah berformat Teks — jangan diubah.</BarisKolom>
                                <BarisKolom nama="Nama" wajib>Nama lengkap karyawan.</BarisKolom>
                                <BarisKolom nama="Email" wajib>Dipakai untuk login. Harus unik.</BarisKolom>
                                <BarisKolom nama="Wilayah" wajib={!isWilayah}>
                                    {isWilayah ? `Diisi otomatis: ${OWN_REGION}.` : 'Nama wilayah persis seperti pada lembar Daftar.'}
                                </BarisKolom>
                                <BarisKolom nama="Titik Proyek" wajib>Harus titik yang benar-benar ada di wilayah baris tersebut.</BarisKolom>
                                <BarisKolom nama="Jabatan" wajib>Bebas diisi.</BarisKolom>
                                <BarisKolom nama="Unit Kerja" wajib>Bebas diisi.</BarisKolom>
                                <BarisKolom nama="Status" wajib>PNS, PPPK, Kontrak, atau Honorer.</BarisKolom>
                                <BarisKolom nama="NIP">18 digit. Kolom sudah berformat Teks — jangan diubah.</BarisKolom>
                                <BarisKolom nama="Golongan">Mis. III/a. Boleh dikosongkan.</BarisKolom>
                                <BarisKolom nama="Regu">Label bebas (mis. Regu A). Jumlah regu tidak dibatasi.</BarisKolom>
                                <BarisKolom nama="Shift">Ya / Tidak. Kosong berarti Tidak.</BarisKolom>
                                <BarisKolom nama="Hari Kerja">Mis. 1,2,3,4,5 (1 = Senin). Kosong berarti ikut hari kerja umum: {hariKerjaUmum}.</BarisKolom>
                                <BarisKolom nama="Sandi Awal">Dipakai bila sumber sandi dipilih &quot;dari kolom&quot;. Minimal 6 karakter.</BarisKolom>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
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
                        <th className="text-left px-3 py-2 font-medium">NIK</th>
                        <th className="text-left px-3 py-2 font-medium">Nama</th>
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
