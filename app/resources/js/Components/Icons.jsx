/**
 * Ikon sebaris untuk antarmuka. Semua memakai viewBox 24, garis 1.6, dan
 * `currentColor` supaya warnanya mengikuti teks induknya. Tidak ada emoji:
 * setiap ikon digambar sebagai SVG agar tampilannya sama di semua perangkat.
 */

const dasar = (size) => ({
    width: size,
    height: size,
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 1.6,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
    'aria-hidden': 'true',
    focusable: 'false',
});

/** Ikon bawaan: sebaris dengan teks di sekitarnya. */
const SEBARIS = 'inline-block shrink-0 align-[-0.15em]';

/** Pin penanda pengumuman yang disematkan. */
export function IconPin({ size = 16, className = SEBARIS }) {
    return (
        <svg {...dasar(size)} className={className}>
            <path d="M12 17v4" />
            <path d="M9 3h6l-.8 5.2 3 3.3v1.5H6.8v-1.5l3-3.3L9 3z" />
        </svg>
    );
}

/** Moda transportasi perjalanan dinas. */
export function IconCar({ size = 16, className = SEBARIS }) {
    return (
        <svg {...dasar(size)} className={className}>
            <path d="M3.5 16.5v-3.2a2 2 0 01.3-1l2.2-3.4A2 2 0 017.7 8h8.6a2 2 0 011.7.9l2.2 3.4a2 2 0 01.3 1v3.2" />
            <path d="M3.5 13.5h17" />
            <circle cx="7.5" cy="16.5" r="1.6" />
            <circle cx="16.5" cy="16.5" r="1.6" />
        </svg>
    );
}

/** Berkas dokumen (PDF). */
export function IconFile({ size = 16, className = SEBARIS }) {
    return (
        <svg {...dasar(size)} className={className}>
            <path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z" />
            <path d="M14 3v5h5" />
            <path d="M9 13h6M9 17h4" />
        </svg>
    );
}

/** Berkas gambar (JPG/PNG/WEBP). */
export function IconImage({ size = 16, className = SEBARIS }) {
    return (
        <svg {...dasar(size)} className={className}>
            <rect x="3" y="4" width="18" height="16" rx="2" />
            <circle cx="8.5" cy="9.5" r="1.5" />
            <path d="M21 16.5l-5-5-5.5 5.5-2.5-2.5L3 19" />
        </svg>
    );
}

/** Lampiran berkas yang akan diunggah. */
export function IconPaperclip({ size = 16, className = SEBARIS }) {
    return (
        <svg {...dasar(size)} className={className}>
            <path d="M20.5 11.5l-8.4 8.4a5 5 0 01-7.1-7.1l8.4-8.4a3.4 3.4 0 014.8 4.8l-8.4 8.4a1.8 1.8 0 01-2.5-2.5l7.8-7.8" />
        </svg>
    );
}

/** Titik tujuan perjalanan dinas. */
export function IconMapPin({ size = 16, className = SEBARIS }) {
    return (
        <svg {...dasar(size)} className={className}>
            <path d="M12 21s7-5.5 7-11a7 7 0 10-14 0c0 5.5 7 11 7 11z" />
            <circle cx="12" cy="10" r="2.5" />
        </svg>
    );
}

/** Sumber pembebanan anggaran. */
export function IconBanknote({ size = 16, className = SEBARIS }) {
    return (
        <svg {...dasar(size)} className={className}>
            <rect x="2.5" y="6.5" width="19" height="11" rx="2" />
            <circle cx="12" cy="12" r="2.5" />
            <path d="M6.5 12h.01M17.5 12h.01" />
        </svg>
    );
}

/** Unduh berkas (ekspor CSV). */
export function IconDownload({ size = 16, className = SEBARIS }) {
    return (
        <svg {...dasar(size)} className={className}>
            <path d="M12 3v11.5" />
            <path d="M7.5 10l4.5 4.5 4.5-4.5" />
            <path d="M4 19.5h16" />
        </svg>
    );
}

/** Tutup dialog atau panel. */
export function IconClose({ size = 16, className = SEBARIS }) {
    return (
        <svg {...dasar(size)} className={className}>
            <path d="M6 6l12 12M18 6L6 18" />
        </svg>
    );
}

/** Tanda berhasil atau terpilih. */
export function IconCheck({ size = 16, className = SEBARIS }) {
    return (
        <svg {...dasar(size)} className={className}>
            <path d="M4.5 12.5l5 5 10-11" />
        </svg>
    );
}
