import { useState } from 'react';
import { Link } from '@inertiajs/react';

export default function Login() {
    const [nik, setNik] = useState('');
    const [show, setShow] = useState(false);
    return (
        <div className="min-h-[100dvh] bg-[#F8FAFC] flex items-center justify-center px-5 py-8">
            <div className="w-full max-w-[420px]">
                {/* Brand — no outline card, airy */}
                <div className="text-center mb-8">
                    <img src="/logo.png" alt="BBWS Pompengan Jeneberang" className="mx-auto w-14 h-14 rounded-2xl object-cover shadow-[0_4px_16px_rgba(15,23,42,0.12)] bg-white" />
                    <p className="text-[11px] tracking-[0.18em] font-medium text-[#6B7280] mt-4">BALAI BESAR WILAYAH SUNGAI</p>
                    <h1 className="font-semibold text-[22px] tracking-tight text-[#0F172A] leading-none mt-1">Pompengan Jeneberang</h1>
                </div>

                <div className="bg-white rounded-2xl shadow-[0_8px_32px_rgba(15,23,42,0.06)] p-6">
                    <div className="mb-5">
                        <h2 className="font-semibold text-[#0F172A]">Masuk</h2>
                    </div>

                    <form onSubmit={(e) => e.preventDefault()} className="space-y-4">
                        <div>
                            <label htmlFor="nik" className="text-xs font-medium text-[#334155]">NIK</label>
                            <input
                                id="nik"
                                inputMode="numeric"
                                value={nik}
                                onChange={(e) => setNik(e.target.value.replace(/\D/g, '').slice(0, 16))}
                                placeholder="7371 00 • • • • • • • • • • • •"
                                className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3.5 py-3 text-[15px] placeholder:text-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/10 focus:bg-white"
                            />
                        </div>
                        <div>
                            <label htmlFor="password" className="text-xs font-medium text-[#334155]">Kata sandi</label>
                            <div className="mt-1.5 relative">
                                <input
                                    id="password"
                                    type={show ? 'text' : 'password'}
                                    placeholder="Masukkan kata sandi"
                                    className="w-full rounded-xl bg-[#F8FAFC] border-0 px-3.5 py-3 text-[15px] pr-14 placeholder:text-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/10 focus:bg-white"
                                />
                                <button type="button" onClick={() => setShow(!show)} className="absolute right-1.5 top-1/2 -translate-y-1/2 text-xs font-medium px-3 py-1.5 rounded-lg bg-white shadow-sm text-[#334155]">
                                    {show ? 'Sembunyi' : 'Lihat'}
                                </button>
                            </div>
                        </div>

                        <Link href="/karyawan" className="block w-full text-center bg-[#0F172A] text-white rounded-xl py-3.5 text-sm font-semibold hover:bg-[#1E3A8A] transition">
                            Masuk
                        </Link>
                        <p className="text-center text-xs text-[#94A3B8]">Akses khusus karyawan BBWS Pompengan Jeneberang</p>
                    </form>
                </div>
            </div>
        </div>
    );
}
