import { useState } from 'react';
import { Link } from '@inertiajs/react';

export default function AdminLogin() {
    const [show, setShow] = useState(false);
    return (
        <div className="min-h-[100dvh] bg-[#0F172A] flex items-center justify-center px-5 py-8">
            <div className="w-full max-w-[420px]">
                <div className="text-center mb-8">
                    <img src="/logo.png" alt="BBWS" className="mx-auto w-14 h-14 rounded-2xl object-cover bg-white shadow-[0_4px_16px_rgba(0,0,0,0.3)]" />
                    <p className="text-[11px] tracking-[0.18em] font-medium text-white/50 mt-4">BALAI BESAR WILAYAH SUNGAI</p>
                    <h1 className="font-semibold text-[22px] tracking-tight text-white leading-none mt-1">Pompengan Jeneberang</h1>
                    <p className="text-sm text-white/50 mt-2">Super Admin / Admin Wilayah</p>
                </div>
                <div className="bg-white rounded-2xl shadow-[0_8px_32px_rgba(0,0,0,0.3)] p-6">
                    <h2 className="font-semibold text-[#0F172A]">Masuk Admin</h2>
                    <p className="text-sm text-[#64748B] mt-1">Email + password • FR-10 • rate limit 5/15 menit</p>
                    <form onSubmit={(e)=>e.preventDefault()} className="mt-5 space-y-4">
                        <div>
                            <label htmlFor="email" className="text-xs font-medium text-[#334155]">Email</label>
                            <input id="email" type="email" placeholder="admin@bbws-pj.go.id" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3.5 py-3 text-[15px] placeholder:text-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/10 focus:bg-white" />
                        </div>
                        <div>
                            <label htmlFor="password" className="text-xs font-medium text-[#334155]">Kata sandi</label>
                            <div className="mt-1.5 relative">
                                <input id="password" type={show ? 'text' : 'password'} placeholder="Masukkan kata sandi" className="w-full rounded-xl bg-[#F8FAFC] border-0 px-3.5 py-3 text-[15px] pr-14 placeholder:text-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/10 focus:bg-white" />
                                <button type="button" onClick={()=>setShow(!show)} className="absolute right-1.5 top-1/2 -translate-y-1/2 text-xs font-medium px-3 py-1.5 rounded-lg bg-white shadow-sm text-[#334155]">{show ? 'Sembunyi' : 'Lihat'}</button>
                            </div>
                        </div>
                        <Link href="/admin" className="block w-full text-center bg-[#0F172A] text-white rounded-xl py-3.5 text-sm font-semibold hover:bg-[#1E3A8A] transition">Masuk</Link>
                        <p className="text-center text-xs text-[#94A3B8]">Wilayah scope via region_id • Super Admin NULL region</p>
                    </form>
                </div>
            </div>
        </div>
    );
}
