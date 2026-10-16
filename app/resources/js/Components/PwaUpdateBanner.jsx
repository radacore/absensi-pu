import { IconClose } from '@/Components/Icons';
import { useEffect, useState } from 'react';

/**
 * Banner "versi baru tersedia".
 *
 * Dipicu oleh event `bbws:update-ready` dari pendaftaran service worker di
 * app.jsx. Sengaja TIDAK memuat ulang halaman sendiri: reload paksa bisa
 * menghapus isian formulir yang sedang diketik karyawan (mis. saat absen).
 */
export default function PwaUpdateBanner() {
    const [siap, setSiap] = useState(false);

    useEffect(() => {
        const handler = () => setSiap(true);
        window.addEventListener('bbws:update-ready', handler);
        return () => window.removeEventListener('bbws:update-ready', handler);
    }, []);

    if (!siap) return null;

    return (
        <div
            role="status"
            className="fixed bottom-4 left-1/2 z-[100] w-[min(92vw,26rem)] -translate-x-1/2 rounded-2xl bg-[#0F172A] px-4 py-3 text-white shadow-[0_8px_28px_rgba(15,23,42,0.28)] flex items-center gap-3"
        >
            <p className="flex-1 text-xs leading-snug">Versi baru aplikasi tersedia.</p>
            <button
                type="button"
                onClick={() => window.location.reload()}
                className="shrink-0 rounded-xl bg-[#FCB833] px-3 py-1.5 text-xs font-semibold text-[#0F172A] focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
            >
                Muat ulang
            </button>
            <button
                type="button"
                onClick={() => setSiap(false)}
                aria-label="Tutup pemberitahuan versi baru"
                className="shrink-0 rounded-lg p-2 text-white/70 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
            >
                <IconClose size={15} />
            </button>
        </div>
    );
}
