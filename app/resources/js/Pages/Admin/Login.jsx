import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';

function getBase(url) {
    if (url.startsWith('/super-admin')) return '/super-admin';
    if (url.startsWith('/wilayah')) return '/wilayah';
    return '/admin';
}

export default function AdminLogin() {
    const [show, setShow] = useState(false);
    const [form, setForm] = useState({ email: '', password: '', remember: false });
    const [processing, setProcessing] = useState(false);
    const { url, props } = usePage();
    const { errors } = props;
    const base = getBase(url);
    const isWilayah = base === '/admin' || base === '/wilayah';
    const isSuper = base === '/super-admin';

    function submit(e) {
        e.preventDefault();
        setProcessing(true);
        router.post(base + '/login', form, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <div className="min-h-[100dvh] bg-[#0F172A] flex items-center justify-center px-5 py-8">
            <div className="w-full max-w-[420px]">
                <div className="text-center mb-8">
                    <img src="/logo.png" alt="BBWS" className="mx-auto w-14 h-14 rounded-2xl object-cover bg-white shadow-[0_4px_16px_rgba(0,0,0,0.3)]" />
                    <p className="text-[11px] tracking-[0.18em] font-medium text-white/50 mt-4">BALAI BESAR WILAYAH SUNGAI</p>
                    <h1 className="font-semibold text-[22px] tracking-tight text-white leading-none mt-1">Pompengan Jeneberang</h1>
                    <p className="text-sm text-white/50 mt-2">{isWilayah ? 'Admin Wilayah' : isSuper ? 'Super Admin Pusat' : 'Admin Wilayah'}</p>
                </div>
                <div className="bg-white rounded-2xl shadow-[0_8px_32px_rgba(0,0,0,0.3)] p-6">
                    <h2 className="font-semibold text-[#0F172A]">Masuk {isWilayah ? 'Admin Wilayah' : 'Super Admin'}</h2>
                    <p className="text-sm text-[#64748B] mt-1">Email + kata sandi • area {isSuper ? 'pusat' : 'wilayah'}</p>
                    <form onSubmit={submit} className="mt-5 space-y-4">
                        {errors.email && <p className="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{errors.email}</p>}
                        <div>
                            <label htmlFor="email" className="text-xs font-medium text-[#334155]">Email</label>
                            <input
                                id="email"
                                type="email"
                                value={form.email}
                                onChange={(e) => setForm({ ...form, email: e.target.value })}
                                placeholder={isWilayah ? 'admin.gowa@bbws-pj.go.id' : 'pusat@bbws-pj.go.id'}
                                className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3.5 py-3 text-[15px] placeholder:text-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/10 focus:bg-white"
                            />
                        </div>
                        <div>
                            <label htmlFor="password" className="text-xs font-medium text-[#334155]">Kata sandi</label>
                            <div className="mt-1.5 relative">
                                <input
                                    id="password"
                                    type={show ? 'text' : 'password'}
                                    value={form.password}
                                    onChange={(e) => setForm({ ...form, password: e.target.value })}
                                    placeholder="Masukkan kata sandi"
                                    className="w-full rounded-xl bg-[#F8FAFC] border-0 px-3.5 py-3 text-[15px] pr-14 placeholder:text-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/10 focus:bg-white"
                                />
                                <button type="button" onClick={() => setShow(!show)} className="absolute right-1.5 top-1/2 -translate-y-1/2 text-xs font-medium px-3 py-1.5 rounded-lg bg-white shadow-sm text-[#334155]">{show ? 'Sembunyi' : 'Lihat'}</button>
                            </div>
                        </div>
                        <button type="submit" disabled={processing} className="block w-full text-center bg-[#0F172A] text-white rounded-xl py-3.5 text-sm font-semibold hover:bg-[#1E3A8A] transition disabled:opacity-60">Masuk</button>
                    </form>
                </div>
                <p className="text-center text-xs text-white/30 mt-4">Karyawan masuk lewat <Link href="/karyawan/login" className="underline text-white/50">/karyawan</Link></p>
            </div>
        </div>
    );
}
