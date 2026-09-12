import RekapView from '@/Components/RekapView';
import { router, usePage } from '@inertiajs/react';

export default function RekapDetail() {
    const { props } = usePage();
    const rekap = props.rekap;

    const changeBulan = (bulan) => {
        router.get('/karyawan/rekap/detail', { bulan }, { preserveState: true, preserveScroll: false });
    };

    return <RekapView rekap={rekap} onChangeBulan={changeBulan} backHref="/karyawan/rekap" />;
}
