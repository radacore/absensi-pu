/**
 * RekapActions — deretan tombol ekspor rekap presensi perorangan.
 *
 * Dipakai bersama oleh sisi admin/super admin (`Admin/EmployeeRekap`) dan sisi
 * karyawan (`Karyawan/Rekap`) supaya tombol serta perilakunya sama persis:
 *
 *  - Cetak  → dialog cetak peramban (pengguna memilih "Simpan sebagai PDF").
 *             Tata letaknya diatur blok @media print di resources/css/app.css.
 *  - PDF    → berkas PDF asli dari server (tanpa dialog cetak).
 *  - CSV    → berkas CSV dari server (hanya tersedia untuk admin).
 *
 * Props:
 *  - pdfHref: string | null
 *  - csvHref: string | null
 *  - namaBerkas: string — dipakai sebagai judul dokumen saat mencetak sehingga
 *    nama berkas usulan peramban ikut rapi.
 *  - pdfLabel: string — label tombol PDF (mis. "Unduh PDF").
 *  - besar: bool — ukuran tombol untuk halaman karyawan (target sentuh ≥44px).
 *  - children: tambahan opsional (mis. pemilih bulan) di sisi kiri tombol.
 */
export default function RekapActions({ pdfHref, csvHref, namaBerkas, pdfLabel = 'Unduh PDF', besar = false, children }) {
    const cetak = () => {
        const judulAsli = document.title;
        if (namaBerkas) document.title = namaBerkas;
        setTimeout(() => {
            window.print();
            setTimeout(() => { document.title = judulAsli; }, 1000);
        }, 50);
    };

    const tombolDasar = besar
        ? 'rounded-lg px-4 py-2.5 text-sm font-semibold transition whitespace-nowrap min-h-[44px] inline-flex items-center'
        : 'rounded-lg px-4 py-2 text-sm font-semibold transition whitespace-nowrap';

    return (
        <div className="flex flex-wrap items-center gap-2">
            {children}
            {csvHref && (
                <a href={csvHref} className={`${tombolDasar} bg-white border border-[#E2E8F0] hover:bg-[#F1F5F9] text-[#334155]`}>
                    Unduh CSV
                </a>
            )}
            {pdfHref && (
                <a href={pdfHref} className={`${tombolDasar} bg-white border border-[#0F172A] hover:bg-[#F1F5F9] text-[#0F172A]`}>
                    {pdfLabel}
                </a>
            )}
            <button type="button" onClick={cetak} className={`${tombolDasar} bg-[#0F172A] hover:bg-[#1E3A8A] text-white`}>
                Cetak
            </button>
        </div>
    );
}
