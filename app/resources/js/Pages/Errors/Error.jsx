import { Head } from '@inertiajs/react';

/**
 * Halaman error generik.
 *
 * Dipakai dua jalur: route fallback (404 untuk alamat tak dikenal) dan
 * penanganan exception di bootstrap/app.php (403/419/429/500/503). Karena itu
 * status dikirim sebagai prop, bukan di-hardcode di sini.
 */

const JUDUL = {
    403: 'Akses ditolak',
    404: 'Halaman tidak ditemukan',
    419: 'Sesi sudah kedaluwarsa',
    429: 'Terlalu banyak permintaan',
    500: 'Terjadi gangguan pada server',
    503: 'Layanan sedang tidak tersedia',
};

const PESAN = {
    403: 'Akun Anda tidak punya hak untuk membuka halaman ini.',
    404: 'Alamat yang Anda buka tidak ada atau sudah dipindahkan.',
    419: 'Halaman ini sudah lama terbuka. Muat ulang lalu coba lagi.',
    429: 'Coba lagi beberapa saat lagi.',
    500: 'Gangguan sudah tercatat. Coba lagi sebentar lagi.',
    503: 'Server sedang dalam perawatan. Coba lagi nanti.',
};

export default function Error({ status = 404, message }) {
    const kode = Number(status) || 404;
    const judul = JUDUL[kode] ?? 'Terjadi kesalahan';
    const pesan = message || PESAN[kode] || 'Silakan coba lagi.';

    return (
        <>
            <Head title={`${kode} — ${judul}`} />
            <div className="min-h-screen bg-[#F8FAFC] flex items-center justify-center px-5">
                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] p-8 max-w-md w-full text-center">
                    <p className="text-[52px] leading-none font-bold text-[#1E3A8A]">{kode}</p>
                    <h1 className="mt-3 font-semibold text-[17px] tracking-tight text-[#0F172A]">{judul}</h1>
                    <p className="mt-2 text-sm text-[#64748B]">{pesan}</p>
                    <a
                        href="/"
                        className="mt-6 inline-flex items-center justify-center rounded-xl bg-[#0F172A] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1E3A8A] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E3A8A]/40"
                    >
                        Kembali ke beranda
                    </a>
                </div>
            </div>
        </>
    );
}
