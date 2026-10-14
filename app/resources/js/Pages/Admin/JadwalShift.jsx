import AdminLayout from '@/Layouts/AdminLayout';
import { toast } from '@/lib/toast';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

function getBase(url) {
    if (url.startsWith('/super-admin')) return '/super-admin';
    if (url.startsWith('/admin')) return '/admin';
    if (url.startsWith('/wilayah')) return '/wilayah';
    return '/admin';
}

const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

function tanggalPendek(iso) {
    const d = new Date(`${iso}T12:00:00`);
    return `${d.getDate()}/${d.getMonth() + 1}`;
}

export default function JadwalShift() {
    const { url, props } = usePage();
    const base = getBase(url);
    const employees = props.employees ?? [];
    const hari = props.hari ?? [];
    const senin = props.senin;
    const mingguLalu = props.mingguLalu;
    const mingguDepan = props.mingguDepan;
    const tanpaPola = props.tanpaPola ?? 0;

    // draft: kunci "employeeId|tgl" → '' (belum diatur) | 'libur' | '1'..'4'
    const [draft, setDraft] = useState({});

    useEffect(() => {
        const awal = {};
        employees.forEach((e) => {
            const cells = e.cells ?? {};
            hari.forEach((tgl) => {
                const ada = Object.prototype.hasOwnProperty.call(cells, tgl);
                const v = cells[tgl];
                awal[`${e.id}|${tgl}`] = !ada ? '' : (v === null ? 'libur' : String(v));
            });
        });
        setDraft(awal);
    }, [employees, hari]);

    const jumlahDiisi = useMemo(
        () => Object.values(draft).filter((v) => v !== '').length,
        [draft],
    );

    const setSel = (empId, tgl, val) => {
        setDraft((d) => ({ ...d, [`${empId}|${tgl}`]: val }));
    };

    const isiSepekan = (empId, val) => {
        setDraft((d) => {
            const next = { ...d };
            hari.forEach((tgl) => { next[`${empId}|${tgl}`] = val; });
            return next;
        });
    };

    const simpan = () => {
        const baris = [];
        employees.forEach((e) => {
            hari.forEach((tgl) => {
                const v = draft[`${e.id}|${tgl}`];
                if (!v) return;
                baris.push({
                    employeeId: e.id,
                    tanggal: tgl,
                    shiftKe: v === 'libur' ? null : Number(v),
                });
            });
        });
        router.post(`${base}/jadwal-shift`, { baris }, {
            preserveScroll: true,
            onSuccess: () => toast.success('Jadwal shift disimpan'),
        });
    };

    const pindah = (tgl) => router.get(`${base}/jadwal-shift`, { minggu: tgl }, { preserveScroll: true });

    return (
        <AdminLayout>
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">Jadwal Shift</h1>
                        <p className="text-sm text-[#64748B]">
                            Susun giliran kerja per orang per hari. Kosongkan sel bila hari itu belum diatur, pilih <b>Libur</b> bila memang tidak masuk.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <button type="button" onClick={() => pindah(mingguLalu)} className="rounded-xl bg-white px-3 py-2.5 text-sm font-medium text-[#334155] shadow-[0_2px_16px_rgba(15,23,42,0.04)]">← Minggu lalu</button>
                        <button type="button" onClick={() => pindah(senin)} className="rounded-xl bg-white px-3 py-2.5 text-sm font-medium text-[#334155] shadow-[0_2px_16px_rgba(15,23,42,0.04)]">Minggu ini</button>
                        <button type="button" onClick={() => pindah(mingguDepan)} className="rounded-xl bg-white px-3 py-2.5 text-sm font-medium text-[#334155] shadow-[0_2px_16px_rgba(15,23,42,0.04)]">Minggu depan →</button>
                        <button type="button" onClick={simpan} className="rounded-xl bg-[#0F172A] px-4 py-2.5 text-sm font-semibold text-white">Simpan Jadwal</button>
                    </div>
                </div>

                {tanpaPola > 0 && (
                    <div className="rounded-2xl bg-[#FFF7E6] border border-[#FCB833]/30 px-4 py-3 text-sm text-[#92400E]">
                        {tanpaPola} pekerja shift belum punya pola shift, jadi kolomnya belum bisa diisi. Buat polanya dulu di halaman <b>Pola Shift</b>.
                    </div>
                )}

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm min-w-[900px]">
                            <thead className="bg-[#F8FAFC] text-xs font-medium text-[#64748B]">
                                <tr>
                                    <th className="text-left px-4 py-3 sticky left-0 bg-[#F8FAFC]">Karyawan</th>
                                    {hari.map((tgl, i) => (
                                        <th key={tgl} className="text-center px-2 py-3 min-w-[92px]">
                                            <span className="block">{HARI[i]}</span>
                                            <span className="block font-normal text-[#94A3B8]">{tanggalPendek(tgl)}</span>
                                        </th>
                                    ))}
                                    <th className="text-center px-2 py-3 min-w-[120px]">Isi sepekan</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#F1F5F9]">
                                {employees.map((e) => (
                                    <tr key={e.id} className="hover:bg-[#F8FAFC]/50">
                                        <td className="px-4 py-3 sticky left-0 bg-white">
                                            <p className="font-medium text-[#0F172A]">{e.nama}</p>
                                            <p className="text-xs text-[#64748B]">{e.polaNama || 'belum ada pola'} • {e.siteNama}</p>
                                        </td>
                                        {hari.map((tgl) => {
                                            const val = draft[`${e.id}|${tgl}`] ?? '';
                                            return (
                                                <td key={tgl} className="px-1.5 py-2 text-center">
                                                    <select
                                                        value={val}
                                                        disabled={e.jumlahShift === 0}
                                                        onChange={(ev) => setSel(e.id, tgl, ev.target.value)}
                                                        className={`w-full rounded-lg border-0 px-1.5 py-1.5 text-xs outline-none ${
                                                            val === '' ? 'bg-[#F8FAFC] text-[#94A3B8]'
                                                                : val === 'libur' ? 'bg-[#F1F5F9] text-[#475569] font-medium'
                                                                    : 'bg-[#EFF6FF] text-[#1E3A8A] font-semibold'
                                                        }`}
                                                    >
                                                        <option value="">—</option>
                                                        <option value="libur">Libur</option>
                                                        {e.shiftLabels.map((s) => (
                                                            <option key={s.ke} value={String(s.ke)}>{s.label} {s.mulai}</option>
                                                        ))}
                                                    </select>
                                                </td>
                                            );
                                        })}
                                        <td className="px-2 py-2 text-center">
                                            <select
                                                value=""
                                                disabled={e.jumlahShift === 0}
                                                onChange={(ev) => { if (ev.target.value) isiSepekan(e.id, ev.target.value); }}
                                                className="w-full rounded-lg border-0 bg-[#F8FAFC] px-1.5 py-1.5 text-xs outline-none"
                                            >
                                                <option value="">—</option>
                                                <option value="libur">Libur</option>
                                                {e.shiftLabels.map((s) => (
                                                    <option key={s.ke} value={String(s.ke)}>{s.label}</option>
                                                ))}
                                            </select>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {employees.length === 0 && (
                        <p className="text-center text-sm text-[#64748B] py-8">
                            Belum ada pekerja shift. Tandai karyawan sebagai pekerja shift dulu di halaman Karyawan.
                        </p>
                    )}
                    <div className="px-4 py-3 bg-[#F8FAFC] text-xs text-[#64748B] flex flex-wrap gap-2 justify-between">
                        <span>{employees.length} pekerja shift • {jumlahDiisi} sel terisi</span>
                        <span>Jadwal disusun manual — tidak ada rotasi otomatis</span>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
