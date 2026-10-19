import AdminLayout from '@/Layouts/AdminLayout';
import { toast } from '@/lib/toast';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

function getBase(url) {
    if (url.startsWith('/super-admin')) return '/super-admin';
    if (url.startsWith('/admin')) return '/admin';
    if (url.startsWith('/wilayah')) return '/wilayah';
    return '/admin';
}

function initials(name) {
    if (!name) return 'A';
    return name.trim().split(/\s+/).slice(0, 2).map((s) => s[0]?.toUpperCase() ?? '').join('') || 'A';
}

const emptyPwd = { current_password: '', password: '', password_confirmation: '' };

export default function ProfilAdmin() {
    const { url, props } = usePage();
    const base = getBase(url);
    const me = props.me ?? {};
    const errors = props.errors ?? {};
    const sandiBawaan = props.sandiBawaan || '';
    const karyawan = me.karyawan ?? null;
    const [pwd, setPwd] = useState(emptyPwd);
    const [saving, setSaving] = useState(false);

    const submit = (e) => {
        e.preventDefault();
        if (!pwd.current_password || !pwd.password || !pwd.password_confirmation) {
            toast.error('Lengkapi semua kolom kata sandi');
            return;
        }
        if (pwd.password.length < 8) {
            toast.error('Kata sandi baru minimal 8 karakter');
            return;
        }
        if (!/[A-Z]/.test(pwd.password) || !/[a-z]/.test(pwd.password) || !/\d/.test(pwd.password)) {
            toast.error('Kata sandi wajib mengandung huruf besar, huruf kecil, dan angka');
            return;
        }
        if (sandiBawaan && pwd.password === sandiBawaan) {
            toast.error('Kata sandi baru tidak boleh sama dengan sandi bawaan aplikasi');
            return;
        }
        if (pwd.password !== pwd.password_confirmation) {
            toast.error('Konfirmasi kata sandi tidak cocok');
            return;
        }

        router.put(`${base}/profil/password`, pwd, {
            preserveScroll: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
            onSuccess: () => setPwd(emptyPwd),
        });
    };

    return (
        <AdminLayout>
            <div className="space-y-5">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">Profil &amp; Keamanan</h1>
                    <p className="text-sm text-[#64748B]">Akun yang sedang dipakai di panel ini dan kata sandinya.</p>
                </div>

                <div className="bg-white rounded-2xl p-6 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <div className="flex items-start gap-4">
                        <span className="w-14 h-14 rounded-2xl bg-[#FCB833] text-[#0F172A] font-semibold text-lg flex items-center justify-center shrink-0">
                            {initials(me.nama)}
                        </span>
                        <div className="min-w-0">
                            <h2 className="font-semibold text-[16px] text-[#0F172A] truncate">{me.nama || '—'}</h2>
                            <p className="text-sm text-[#64748B] truncate">{me.email || '—'}</p>
                            <div className="flex flex-wrap items-center gap-2 mt-2">
                                <span className="text-xs font-medium bg-[#F1F5F9] text-[#334155] px-2.5 py-1 rounded-full">{me.peran || 'Admin'}</span>
                                {me.wilayah ? (
                                    <span className="text-xs font-medium bg-[#EFF6FF] text-[#1E3A8A] px-2.5 py-1 rounded-full">{me.wilayah}</span>
                                ) : (
                                    <span className="text-xs font-medium bg-[#F1F5F9] text-[#334155] px-2.5 py-1 rounded-full">Pusat</span>
                                )}
                            </div>
                        </div>
                    </div>

                    {karyawan && (
                        <div className="mt-5 rounded-xl bg-[#FFF7E6] border border-[#FCB833]/30 p-4">
                            <p className="text-xs font-semibold text-[#92400E]">Akun ini juga terdaftar sebagai karyawan</p>
                            <p className="text-xs text-[#92400E]/90 mt-1">
                                {karyawan.nama} • NIK {karyawan.nik || '—'} • {karyawan.jabatan || '—'}
                                {karyawan.titik ? ` • ${karyawan.titik}` : ''}
                            </p>
                            <p className="text-xs text-[#92400E]/90 mt-1">
                                Satu kata sandi dipakai untuk panel admin dan aplikasi karyawan. Mengganti sandi di sini juga mengubah sandi di aplikasi karyawan, dan sebaliknya.
                            </p>
                            <a href="/karyawan/profil" className="inline-block mt-3 text-xs font-semibold text-[#1E3A8A] bg-white/70 px-3 py-2 rounded-lg hover:bg-white transition">
                                Buka Profil aplikasi karyawan
                            </a>
                        </div>
                    )}
                </div>

                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] space-y-4">
                    <div className="flex items-center gap-2.5">
                        <span className="w-8 h-8 rounded-xl bg-[#FFF7E6] flex items-center justify-center">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#92400E" strokeWidth="1.6" aria-hidden="true"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/><circle cx="12" cy="16" r="1.2" fill="#92400E" stroke="none"/></svg>
                        </span>
                        <div>
                            <h3 className="font-medium text-sm text-[#0F172A]">Ganti kata sandi</h3>
                            <p className="text-xs text-[#64748B]">
                                {karyawan ? 'Berlaku untuk panel admin dan aplikasi karyawan.' : 'Berlaku untuk masuk ke panel admin.'}
                            </p>
                        </div>
                    </div>

                    <form onSubmit={submit} className="space-y-3">
                        <div>
                            <label htmlFor="oldPwd" className="text-xs font-medium text-[#334155]">Kata sandi lama</label>
                            <input
                                id="oldPwd"
                                type="password"
                                autoComplete="current-password"
                                value={pwd.current_password}
                                onChange={(e) => setPwd({ ...pwd, current_password: e.target.value })}
                                placeholder="••••••••"
                                aria-invalid={Boolean(errors.current_password)}
                                aria-describedby={errors.current_password ? 'oldPwdError' : undefined}
                                className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3.5 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10"
                            />
                            {errors.current_password && (
                                <p id="oldPwdError" role="alert" className="text-xs text-[#B91C1C] mt-1">{errors.current_password}</p>
                            )}
                        </div>
                        <div>
                            <label htmlFor="newPwd" className="text-xs font-medium text-[#334155]">Kata sandi baru</label>
                            <input
                                id="newPwd"
                                type="password"
                                autoComplete="new-password"
                                value={pwd.password}
                                onChange={(e) => setPwd({ ...pwd, password: e.target.value })}
                                placeholder="Min. 8 karakter, huruf besar+kecil+angka"
                                aria-describedby="newPwdHint"
                                aria-invalid={Boolean(errors.password)}
                                className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3.5 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10"
                            />
                            <p id="newPwdHint" className="text-xs text-[#64748B] mt-1">Wajib mengandung huruf besar, huruf kecil, dan angka. Tidak boleh sama dengan sandi bawaan aplikasi.</p>
                            {errors.password && (
                                <p role="alert" className="text-xs text-[#B91C1C] mt-1">{errors.password}</p>
                            )}
                        </div>
                        <div>
                            <label htmlFor="confirmPwd" className="text-xs font-medium text-[#334155]">Konfirmasi baru</label>
                            <input
                                id="confirmPwd"
                                type="password"
                                autoComplete="new-password"
                                value={pwd.password_confirmation}
                                onChange={(e) => setPwd({ ...pwd, password_confirmation: e.target.value })}
                                placeholder="Ulangi kata sandi baru"
                                aria-invalid={Boolean(errors.password_confirmation)}
                                aria-describedby={errors.password_confirmation ? 'confirmPwdError' : undefined}
                                className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3.5 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10"
                            />
                            {errors.password_confirmation && (
                                <p id="confirmPwdError" role="alert" className="text-xs text-[#B91C1C] mt-1">{errors.password_confirmation}</p>
                            )}
                        </div>
                        <button
                            type="submit"
                            disabled={saving}
                            className="w-full rounded-xl bg-[#0F172A] text-white py-2.5 text-sm font-semibold hover:bg-[#1E3A8A] transition disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            {saving ? 'Menyimpan…' : 'Simpan kata sandi'}
                        </button>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
