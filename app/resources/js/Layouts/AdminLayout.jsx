import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

function getAdminBase(url) {
    if (url.startsWith('/super-admin')) return '/super-admin';
    if (url.startsWith('/admin')) return '/admin';
    if (url.startsWith('/wilayah')) return '/wilayah';
    return '/admin';
}

const menuDefs = [
    { path: '', label: 'Dashboard', icon: (a) => <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke={a ? '#FCB833' : '#94A3B8'} strokeWidth="1.6"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>, exact: true },
    { path: '/regions', label: 'Kantor Wilayah', icon: (a) => <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke={a ? '#FCB833' : '#94A3B8'} strokeWidth="1.6"><path d="M12 21s7-5.5 7-11a7 7 0 10-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg> },
    { path: '/employees', label: 'Karyawan', icon: (a) => <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke={a ? '#FCB833' : '#94A3B8'} strokeWidth="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0116 0"/><path d="M8 12h8"/></svg> },
    { path: '/admin-wilayah', label: 'Admin Wilayah', icon: (a) => <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke={a ? '#FCB833' : '#94A3B8'} strokeWidth="1.6"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg> },
    { path: '/attendances', label: 'Absensi', icon: (a) => <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke={a ? '#FCB833' : '#94A3B8'} strokeWidth="1.6"><path d="M12 22a10 10 0 100-20 10 10 0 000 20z"/><path d="M12 6v6l4 2"/></svg> },
    { path: '/cuti', label: 'Cuti', icon: (a) => <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke={a ? '#FCB833' : '#94A3B8'} strokeWidth="1.6"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/></svg> },
    { path: '/love', label: 'Love Claims', icon: (a) => <svg width="18" height="18" viewBox="0 0 24 24" fill={a ? '#FCB833' : 'none'} stroke={a ? '#FCB833' : '#94A3B8'} strokeWidth="1.6"><path d="M12 21s-6.5-4.2-8.5-8.5A4.5 4.5 0 0112 5a4.5 4.5 0 018.5 7.5C18.5 16.8 12 21 12 21z"/></svg> },
    { path: '/pengumuman', label: 'Pengumuman', icon: (a) => <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke={a ? '#FCB833' : '#94A3B8'} strokeWidth="1.6"><path d="M15 17h5l-1.5-1.5A6 6 0 0118 9V7a6 6 0 00-6-6 6 6 0 00-6 6v2a6 6 0 01-.5 5.5L4 16h5"/><path d="M9 17a3 3 0 006 0"/></svg> },
    { path: '/settings', label: 'Pengaturan', icon: (a) => <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke={a ? '#FCB833' : '#94A3B8'} strokeWidth="1.6"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg> },
];

export default function AdminLayout({ children }) {
    const { url } = usePage();
    const base = getAdminBase(url);
    const menu = menuDefs.map((m) => ({ ...m, href: `${base}${m.path}` }));
    const isWilayah = base === '/admin' || base === '/wilayah';
    const roleLabel = isWilayah ? 'Admin Wilayah' : 'Super Admin • Makassar';
    const [open, setOpen] = useState(false);
    return (
        <div className="min-h-screen bg-[#F8FAFC] flex">
            {/* Sidebar — desktop */}
            <aside className={`hidden lg:flex lg:flex-col w-[264px] bg-[#0F172A] text-white sticky top-0 h-screen shrink-0`}>
                <div className="px-5 py-5 flex items-center gap-3 border-b border-white/10">
                    <img src="/logo.png" alt="BBWS" className="w-9 h-9 rounded-xl object-cover bg-white" />
                    <div>
                        <p className="text-[10px] tracking-[0.14em] text-white/60">BALAI BESAR WILAYAH SUNGAI</p>
                        <p className="font-semibold text-sm leading-none">Pompengan Jeneberang</p>
                        <p className="text-xs text-white/50">{roleLabel}</p>
                    </div>
                </div>
                <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                    {menu.map((m) => {
                        const active = m.exact ? url === m.href : url.startsWith(m.href);
                        return (
                            <Link key={m.href} href={m.href} className={`flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition ${active ? 'bg-white/10 text-white' : 'text-white/60 hover:bg-white/5 hover:text-white'}`}>
                                <span className={`w-7 h-7 rounded-lg flex items-center justify-center ${active ? 'bg-[#FCB833]/15' : 'bg-white/5'}`}>{m.icon(active)}</span>
                                {m.label}
                                {active && <span className="ml-auto w-2 h-2 rounded-full bg-[#FCB833]"></span>}
                            </Link>
                        );
                    })}
                </nav>
                <div className="p-4 border-t border-white/10">
                    <div className="bg-white/5 rounded-xl p-3 flex items-center gap-3">
                        <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100&h=100&fit=crop&crop=face&auto=format" alt="admin" className="w-9 h-9 rounded-full object-cover" />
                        <div className="min-w-0">
                            <p className="text-sm font-medium truncate">{isWilayah ? 'Admin Wilayah' : 'Super Admin'}</p>
                            <p className="text-xs text-white/60 truncate">{isWilayah ? 'wilayah@bbws-pj.go.id' : 'pusat@bbws-pj.go.id'}</p>
                        </div>
                    </div>
                </div>
            </aside>

            {/* Mobile topbar + sidebar drawer */}
            <div className="flex-1 flex flex-col min-w-0">
                <header className="lg:hidden sticky top-0 z-20 bg-[#0F172A] text-white px-4 py-3 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <button type="button" onClick={() => setOpen(!open)} className="w-9 h-9 rounded-xl bg-white/10 flex items-center justify-center">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                        </button>
                        <img src="/logo.png" alt="BBWS" className="w-8 h-8 rounded-xl object-cover bg-white" />
                        <span className="font-semibold text-sm">BBWS PJ</span>
                    </div>
                    <span className="text-xs bg-[#FCB833] text-[#0F172A] font-semibold px-2.5 py-1 rounded-full">{isWilayah ? 'Wilayah' : 'Super Admin'}</span>
                </header>
                {open && (
                    <div className="lg:hidden fixed inset-0 z-30 bg-black/40" onClick={() => setOpen(false)}>
                        <div className="w-[280px] h-full bg-[#0F172A] p-4 space-y-1 overflow-y-auto" onClick={(e) => e.stopPropagation()}>
                            {menu.map((m) => {
                                const active = m.exact ? url === m.href : url.startsWith(m.href);
                                return (
                                    <Link key={m.href} href={m.href} onClick={() => setOpen(false)} className={`flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm ${active ? 'bg-white/10 text-white' : 'text-white/60'}`}>
                                        {m.icon(active)} {m.label}
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                )}
                <main className="flex-1 p-4 lg:p-6 max-w-[1280px] w-full mx-auto">{children}</main>
            </div>
        </div>
    );
}
