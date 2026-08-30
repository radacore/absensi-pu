import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { useEffect, useMemo, useState } from 'react';
import { loadRegions, loadEmployees } from '@/Pages/Admin/_shared';

const MOCK_KARYAWAN_ID = 1;
const LS_PENGUMUMAN = 'bbws_mock_pengumuman_v3';
const LS_READ = 'bbws_mock_pengumuman_read_v3';

const fallback = [
    { id: 1, judul: 'Apel Pagi Senin — Pusat', konten: 'Apel pukul 07:30 di halaman kantor pusat. Kehadiran tercatat melalui absensi.', scope: 'Global', region: '', pin: true, tgl: '24 Agu 2026', stat: 'Terkirim 24 kantor' },
    { id: 2, judul: 'Pemeliharaan Jalan — Gowa', konten: 'Penutupan sementara ruas poros 08:00–16:00. Radius absensi tetap 200 m.', scope: 'Wilayah', region: 'Kab. Gowa', pin: false, tgl: '23 Agu 2026', stat: 'Terkirim 72 karyawan' },
    { id: 3, judul: 'Jadwal Cuti Bersama', konten: 'Cuti bersama nasional — lihat kalender.', scope: 'Global', region: '', pin: false, tgl: '20 Agu 2026', stat: 'Terkirim 24 kantor' },
];
function loadPengumuman() { try { const raw = localStorage.getItem(LS_PENGUMUMAN); if (raw) return JSON.parse(raw); } catch {} return fallback; }
function loadRead() { try { const raw = localStorage.getItem(LS_READ); if (raw) return new Set(JSON.parse(raw)); } catch {} return new Set(); }
function saveRead(set) { try { localStorage.setItem(LS_READ, JSON.stringify([...set])); } catch {} }

export default function Pengumuman() {
    const [regionsData, setRegionsData] = useState(() => loadRegions());
    const [employees, setEmployees] = useState(() => loadEmployees());
    const [raw, setRaw] = useState(() => loadPengumuman());
    const [read, setRead] = useState(() => loadRead());
    const [tab, setTab] = useState('Semua');
    useEffect(() => {
        const sync = () => { setRegionsData(loadRegions()); setEmployees(loadEmployees()); setRaw(loadPengumuman()); setRead(loadRead()); };
        window.addEventListener('focus', sync);
        const onVis = () => { if (document.visibilityState === 'visible') sync(); };
        document.addEventListener('visibilitychange', onVis);
        return () => { window.removeEventListener('focus', sync); document.removeEventListener('visibilitychange', onVis); };
    }, []);
    const me = useMemo(() => employees.find((e) => e.id === MOCK_KARYAWAN_ID) || employees[0], [employees]);

    const visible = useMemo(() => {
        let list = raw.filter((p) => {
            if (p.scope === 'Global') return true;
            if (!me) return false;
            return p.region === me.region;
        });
        if (tab === 'Pusat') list = list.filter((p) => p.scope === 'Global');
        if (tab === 'Wilayah') list = list.filter((p) => p.scope === 'Wilayah');
        // pinned first
        return [...list].sort((a, b) => (b.pin ? 1 : 0) - (a.pin ? 1 : 0));
    }, [raw, me, tab]);

    const markRead = (id) => {
        const next = new Set(read);
        next.add(id);
        setRead(next); saveRead(next);
    };
    const markAll = () => {
        const next = new Set(read);
        for (const p of visible) next.add(p.id);
        setRead(next); saveRead(next);
    };

    const unreadCount = visible.filter((p) => !read.has(p.id)).length;

    return (
        <KaryawanLayout>
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <h2 className="font-semibold text-[17px] tracking-tight text-[#0F172A]">Pengumuman</h2>
                        <p className="text-sm text-[#64748B]">Dari pusat & wilayah {me?.region || ''} {unreadCount > 0 ? `• ${unreadCount} baru` : '• semua dibaca'}</p>
                    </div>
                    <button type="button" onClick={markAll} className="text-xs font-medium text-[#1E3A8A] bg-[#EFF6FF] px-3 py-1.5 rounded-full shrink-0">Tandai semua dibaca</button>
                </div>

                <div className="flex gap-2">
                    {['Semua','Pusat','Wilayah'].map((t) => (
                        <button key={t} type="button" onClick={() => setTab(t)} className={`text-xs font-medium px-3 py-1.5 rounded-full ${tab === t ? 'bg-[#0F172A] text-white' : 'bg-white text-[#64748B] shadow-sm'}`}>{t}</button>
                    ))}
                    <span className="ml-auto text-xs text-[#94A3B8] self-center">{visible.length} pengumuman</span>
                </div>

                {visible.length === 0 ? (
                    <p className="text-sm text-[#94A3B8] bg-white rounded-2xl p-6 text-center">Belum ada pengumuman{me ? ` untuk ${me.region}` : ''} — Admin buat di /super-admin/pengumuman</p>
                ) : (
                    <div className="space-y-3">
                        {visible.map((p) => {
                            const unread = !read.has(p.id);
                            return (
                                <div key={p.id} className={`bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] ${unread ? 'ring-1 ring-[#E0F2FE]' : ''}`}>
                                    <div className="flex items-start justify-between gap-3">
                                        <h3 className="font-medium text-sm leading-snug text-[#0F172A]">{p.pin ? '📌 ' : ''}{p.judul}</h3>
                                        {unread && <span className="mt-1 w-2 h-2 rounded-full bg-[#1E3A8A] shrink-0"></span>}
                                    </div>
                                    <p className="text-xs text-[#94A3B8] mt-1">{p.scope === 'Global' ? `Pusat • ${p.tgl}` : `${p.region} • ${p.tgl}`} {p.pin ? '• Disematkan' : ''} {unread ? '• Belum dibaca' : '• Sudah dibaca'}</p>
                                    <p className="text-sm text-[#475569] leading-relaxed mt-3">{p.konten}</p>
                                    <div className="mt-3 flex gap-2">
                                        <span className={`text-xs font-medium px-2.5 py-1 rounded-full ${p.scope === 'Global' ? 'bg-[#0F172A] text-white' : 'bg-[#F1F5F9] text-[#334155]'}`}>{p.scope === 'Global' ? 'Kantor Pusat' : p.region}</span>
                                        {unread && <button type="button" onClick={() => markRead(p.id)} className="text-xs font-medium text-[#1E3A8A] bg-[#EFF6FF] px-2.5 py-1 rounded-full">Tandai dibaca</button>}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
                <p className="text-xs text-[#94A3B8] text-center">Sinkron dari Admin • buat di /super-admin/pengumuman → langsung muncul di sini (targeted region)</p>
            </div>
        </KaryawanLayout>
    );
}
