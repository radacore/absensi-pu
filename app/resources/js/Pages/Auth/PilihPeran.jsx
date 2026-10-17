import { Link, router, usePage } from '@inertiajs/react';

/**
 * Pemilihan peran untuk akun yang sekaligus admin dan karyawan.
 *
 * Satu kali login sudah membuka dua sesi, jadi halaman ini tidak melakukan
 * autentikasi apa pun. Ia hanya mengarahkan orangnya ke panel admin atau ke
 * aplikasi karyawan. Pilihannya sengaja tidak diingat: setiap login ditanya
 * lagi supaya tidak ada yang terjebak di peran yang tidak sedang dibutuhkan.
 */
export default function PilihPeran({ admin, karyawan }) {
    const { props } = usePage();
    const flash = props.flash || {};

    function keluar() {
        router.post('/pilih-peran/keluar');
    }

    return (
        <div className="min-h-[100dvh] bg-[#F8FAFC] flex items-center justify-center px-5 py-8">
            <div className="w-full max-w-[440px]">
                <div className="text-center mb-8">
                    <img src="/logo.png" alt="BBWS Pompengan Jeneberang" className="mx-auto w-14 h-14 rounded-2xl object-cover shadow-[0_4px_16px_rgba(15,23,42,0.12)] bg-white" />
                    <p className="text-[11px] tracking-[0.18em] font-medium text-[#6B7280] mt-4">BALAI BESAR WILAYAH SUNGAI</p>
                    <h1 className="font-semibold text-[22px] tracking-tight text-[#0F172A] leading-none mt-1">Pompengan Jeneberang</h1>
                </div>

                <div className="bg-white rounded-2xl shadow-[0_8px_32px_rgba(15,23,42,0.06)] p-6">
                    <div className="mb-5">
                        <h2 className="font-semibold text-[#0F172A]">Masuk sebagai apa?</h2>
                        <p className="text-xs text-[#64748B] mt-1">
                            Akun ini terdaftar sebagai admin sekaligus karyawan. Pilih salah satu, Anda bisa pindah kapan saja tanpa login ulang.
                        </p>
                    </div>

                    {flash.error && (
                        <p className="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2 mb-4">{flash.error}</p>
                    )}

                    <div className="space-y-3">
                        <Link
                            href={admin.tujuan}
                            className="group flex items-center gap-3.5 rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-4 hover:border-[#1E3A8A]/40 hover:bg-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E3A8A]/30"
                        >
                            <span className="w-11 h-11 rounded-xl bg-[#0F172A] flex items-center justify-center shrink-0">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FCB833" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                    <rect x="3" y="3" width="7" height="7" rx="1.5" />
                                    <rect x="14" y="3" width="7" height="7" rx="1.5" />
                                    <rect x="14" y="14" width="7" height="7" rx="1.5" />
                                    <rect x="3" y="14" width="7" height="7" rx="1.5" />
                                </svg>
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block text-sm font-semibold text-[#0F172A]">Panel Admin</span>
                                <span className="block text-xs text-[#64748B] mt-0.5 truncate">
                                    {admin.peran}{admin.wilayah ? ` · ${admin.wilayah}` : ''}
                                </span>
                            </span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="shrink-0 group-hover:stroke-[#1E3A8A]" aria-hidden="true">
                                <path d="M9 18l6-6-6-6" />
                            </svg>
                        </Link>

                        <Link
                            href={karyawan.tujuan}
                            className="group flex items-center gap-3.5 rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-4 hover:border-[#1E3A8A]/40 hover:bg-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E3A8A]/30"
                        >
                            <span className="w-11 h-11 rounded-xl bg-[#FCB833] flex items-center justify-center shrink-0">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0F172A" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                    <path d="M12 21s7-5.5 7-11a7 7 0 10-14 0c0 5.5 7 11 7 11z" />
                                    <circle cx="12" cy="10" r="2.5" />
                                </svg>
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block text-sm font-semibold text-[#0F172A]">Aplikasi Karyawan</span>
                                <span className="block text-xs text-[#64748B] mt-0.5 truncate">
                                    Absen, cuti, dan rekap pribadi{karyawan.titik ? ` · ${karyawan.titik}` : ''}
                                </span>
                            </span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="shrink-0 group-hover:stroke-[#1E3A8A]" aria-hidden="true">
                                <path d="M9 18l6-6-6-6" />
                            </svg>
                        </Link>
                    </div>

                    <div className="mt-5 pt-4 border-t border-[#E2E8F0] flex items-center justify-between gap-3">
                        <div className="min-w-0">
                            <p className="text-xs font-medium text-[#334155] truncate">{admin.nama}</p>
                            <p className="text-[11px] text-[#64748B] truncate">{admin.email}</p>
                        </div>
                        <button
                            type="button"
                            onClick={keluar}
                            className="shrink-0 text-xs font-semibold text-[#991B1B] bg-[#FEF2F2] hover:bg-[#FEE2E2] rounded-lg px-3 py-2 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#991B1B]/30"
                        >
                            Keluar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
