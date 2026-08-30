import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const navItems = [
    { href: '/karyawan', label: 'Beranda', icon: (active) => (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={active ? '#1E3A8A' : '#6B7280'} strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4H9v4a1 1 0 01-1 1H4a1 1 0 01-1-1V9.5z"/><path d="M9 21V11h6v10"/></svg>
    ), match: (url) => url === '/karyawan' },
    { href: '/karyawan/absensi', label: 'Absensi', icon: (active) => (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={active ? '#1E3A8A' : '#6B7280'} strokeWidth="1.7"><path d="M12 21s7-5.5 7-11a7 7 0 10-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
    ) },
    { href: '/karyawan/cuti', label: 'Cuti', icon: (active) => (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={active ? '#1E3A8A' : '#6B7280'} strokeWidth="1.7"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
    ) },
    { href: '/karyawan/love', label: 'Love', icon: (active) => (
        <svg width="20" height="20" viewBox="0 0 24 24" fill={active ? '#FCB833' : 'none'} stroke={active ? '#FCB833' : '#6B7280'} strokeWidth="1.7"><path d="M12 21s-6.5-4.2-8.5-8.5A4.5 4.5 0 0112 5a4.5 4.5 0 018.5 7.5C18.5 16.8 12 21 12 21z"/></svg>
    ) },
    { href: '/karyawan/pengumuman', label: 'Info', icon: (active) => (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={active ? '#1E3A8A' : '#6B7280'} strokeWidth="1.7"><path d="M15 17h5l-1.5-1.5A6 6 0 0118 9V7a6 6 0 00-6-6 6 6 0 00-6 6v2a6 6 0 01-.5 5.5L4 16h5"/><path d="M9 17a3 3 0 006 0"/></svg>
    ) },
    { href: '/karyawan/profil', label: 'Profil', icon: (active) => (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={active ? '#1E3A8A' : '#6B7280'} strokeWidth="1.7"><circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0116 0"/></svg>
    ) },
];

export default function KaryawanLayout({ children }) {
    const { url } = usePage();
    const [unreadInfo, setUnreadInfo] = useState(0);
    useEffect(() => {
        const calc = () => {
            try {
                const raw = localStorage.getItem('bbws_mock_pengumuman_v3');
                const list = raw ? JSON.parse(raw) : [
                    { id: 1, scope: 'Global' }, { id: 2, scope: 'Wilayah', region: 'Kab. Gowa' }, { id: 3, scope: 'Global' },
                ];
                const empRaw = localStorage.getItem('bbws_mock_employees_v3');
                const emps = empRaw ? JSON.parse(empRaw) : [{ id: 1, region: 'Kab. Gowa' }];
                const me = emps.find((e) => e.id === 1) || emps[0];
                const readRaw = localStorage.getItem('bbws_mock_pengumuman_read_v3');
                const read = readRaw ? new Set(JSON.parse(readRaw)) : new Set();
                const visible = list.filter((p) => p.scope === 'Global' || (me && p.region === me.region));
                setUnreadInfo(visible.filter((p) => !read.has(p.id)).length);
            } catch { setUnreadInfo(0); }
        };
        calc();
        window.addEventListener('focus', calc);
        const onVis = () => { if (document.visibilityState === 'visible') calc(); };
        document.addEventListener('visibilitychange', onVis);
        const onStorage = (e) => { if (!e.key || e.key === 'bbws_mock_pengumuman_v3' || e.key === 'bbws_mock_pengumuman_read_v3' || e.key === 'bbws_mock_employees_v3') calc(); };
        window.addEventListener('storage', onStorage);
        return () => { window.removeEventListener('focus', calc); document.removeEventListener('visibilitychange', onVis); window.removeEventListener('storage', onStorage); };
    }, [url]);
    return (
        <div className="min-h-screen bg-[#F8FAFC] flex flex-col max-w-[480px] mx-auto shadow-[0_0_40px_rgba(15,23,42,0.06)]">
            {/* Topbar — no outline, subtle */}
            <header className="sticky top-0 z-20 bg-[#0F172A] text-white px-5 pt-3 pb-4" style={{ paddingTop: 'calc(0.75rem + var(--sat))' }}>
                <div className="flex items-center gap-3">
                    <img src="/logo.png" alt="BBWS Pompengan Jeneberang" className="w-9 h-9 rounded-xl object-cover bg-white shadow-sm" />
                    <div>
                        <p className="text-[10px] tracking-[0.14em] font-medium text-white/60">BALAI BESAR WILAYAH SUNGAI</p>
                        <p className="font-semibold text-[14px] leading-none">Pompengan Jeneberang</p>
                    </div>
                </div>
            </header>

            <main className="flex-1 pb-[86px] px-4 pt-4">{children}</main>

            {/* Bottom Nav — no outline card, blurred bg */}
            <nav className="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[480px] bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/80 flex justify-around py-2" style={{ paddingBottom: 'calc(0.5rem + var(--sab))', boxShadow: '0 -1px 24px rgba(15,23,42,0.08)' }}>
                {navItems.map((it) => {
                    const active = it.match ? it.match(url) : url.startsWith(it.href);
                    const isInfo = it.label === 'Info';
                    return (
                        <Link
                            key={it.href}
                            href={it.href}
                            className={`relative flex flex-col items-center justify-center min-w-[56px] min-h-[44px] rounded-xl px-2 py-1 transition ${active ? 'text-[#1E3A8A]' : 'text-[#6B7280]'}`}
                        >
                            <span className={`relative p-1.5 rounded-lg ${active ? 'bg-[#E0F2FE]' : 'bg-transparent'}`}>
                                {it.icon(active)}
                                {isInfo && unreadInfo > 0 && <span className="absolute -top-1 -right-1 w-2.5 h-2.5 bg-[#EF4444] rounded-full border-2 border-white"></span>}
                            </span>
                            <span className={`text-[10px] mt-1 ${active ? 'font-semibold text-[#1E3A8A]' : 'font-medium'}`}>{it.label}{isInfo && unreadInfo > 0 ? ` • ${unreadInfo}` : ''}</span>
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}
