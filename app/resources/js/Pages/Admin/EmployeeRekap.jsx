import RekapView from '@/Components/RekapView';
import { router, usePage } from '@inertiajs/react';

export default function EmployeeRekap() {
    const { url, props } = usePage();
    const rekap = props.rekap;
    const base = url.startsWith('/super-admin') ? '/super-admin' : (url.startsWith('/wilayah') ? '/wilayah' : '/admin');

    const changeBulan = (bulan) => {
        router.get(`${base}/employees/${rekap.karyawan.id}/rekap`, { bulan }, { preserveState: true, preserveScroll: false });
    };

    const bulanAktif = `${rekap.periode.year}-${String(rekap.periode.month).padStart(2, '0')}`;
    const akar = `${base}/employees/${rekap.karyawan.id}/rekap`;
    const kueri = `?bulan=${bulanAktif}`;

    return (
        <RekapView
            rekap={rekap}
            onChangeBulan={changeBulan}
            backHref={`${base}/employees`}
            pdfHref={`${akar}/pdf${kueri}`}
            csvHref={`${akar}/unduh${kueri}`}
        />
    );
}
