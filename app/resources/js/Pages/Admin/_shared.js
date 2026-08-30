// Shared — source of truth untuk Regions & SiteDetail
// 1 karyawan = 1 titik (office_location_id). Absen valid hanya di titik itu.

export const OWN_REGION = 'Kab. Gowa';
export const MAX_SITES = 20;

export const WILAYAH_LIST = ['Semua','Kota Makassar','Kab. Gowa','Kab. Maros','Kab. Bone','Kota Parepare','Kota Palopo','Kab. Bantaeng','Kab. Barru','Kab. Bulukumba','Kab. Enrekang','Kab. Jeneponto','Kab. Kepulauan Selayar','Kab. Luwu','Kab. Luwu Timur','Kab. Luwu Utara','Kab. Pangkajene dan Kepulauan','Kab. Pinrang','Kab. Sinjai','Kab. Soppeng','Kab. Takalar','Kab. Tana Toraja','Kab. Toraja Utara','Kab. Wajo','Kab. Sidrap'];
export const REGION_LIST = WILAYAH_LIST.slice(1);

export function getSitesForWilayah(regionsData, wilayah, isWilayah) {
    if (isWilayah) return regionsData.find((x) => x.name === OWN_REGION)?.locations || [];
    if (wilayah === 'Semua') return [];
    return regionsData.find((x) => x.name === wilayah)?.locations || [];
}
export function getValidSiteIds(regionsData, wilayah, isWilayah) {
    return new Set(getSitesForWilayah(regionsData, wilayah, isWilayah).map((s) => String(s.id)));
}
export function siteById(siteId, regionsData) {
    if (siteId == null) return null;
    for (const r of regionsData) { const s = r.locations.find((x) => x.id === Number(siteId)); if (s) return { site: s, region: r }; }
    return null;
}

export const DUMMY_REGIONS = [
    { id: 1, name: 'Kota Makassar', kantor: 'Kantor Pusat', tipe: 'pusat', locations: [{ id: 101, nama_lokasi: 'Bendungan Tallo — Makassar', lat: -5.1477, lng: 119.4327, radius: 300, address: 'Jl. AP Pettarani No.1' }, { id: 102, nama_lokasi: 'Jembatan Pettarani', lat: -5.156, lng: 119.44, radius: 200, address: 'Jl. Pettarani' }], address: 'Jl. AP Pettarani No.1 — Makassar' },
    { id: 2, name: 'Kab. Gowa', kantor: 'Kantor Wilayah Gowa', tipe: 'cabang', locations: [{ id: 201, nama_lokasi: 'Bendungan Bili-Bili', lat: -5.3114, lng: 119.42, radius: 200, address: 'Jl. Poros Malino' }, { id: 202, nama_lokasi: 'Jembatan Pampang', lat: -5.32, lng: 119.45, radius: 150, address: 'Jl. Pampang' }], address: 'Jl. Poros Malino — Gowa' },
    { id: 3, name: 'Kab. Maros', kantor: 'Kantor Wilayah Maros', tipe: 'cabang', locations: [{ id: 301, nama_lokasi: 'Kantor Maros', lat: -5.005, lng: 119.58, radius: 200, address: 'Jl. Poros Maros' }], address: 'Jl. Poros Maros' },
    { id: 4, name: 'Kab. Bone', kantor: 'Kantor Wilayah Bone', tipe: 'cabang', locations: [{ id: 401, nama_lokasi: 'Kantor Bone', lat: -4.54, lng: 120.33, radius: 150, address: 'Jl. Ahmad Yani — Bone' }], address: 'Jl. Ahmad Yani — Bone' },
    { id: 5, name: 'Kota Parepare', kantor: 'Kantor Wilayah Parepare', tipe: 'cabang', locations: [{ id: 501, nama_lokasi: 'Kantor Parepare', lat: -4.0148, lng: 119.625, radius: 200, address: 'Jl. Andi Makkasau — Parepare' }], address: 'Jl. Andi Makkasau — Parepare' },
    { id: 6, name: 'Kota Palopo', kantor: 'Kantor Wilayah Palopo', tipe: 'cabang', locations: [{ id: 601, nama_lokasi: 'Kantor Palopo', lat: -3.0014, lng: 120.192, radius: 200, address: 'Jl. Andi Djemma — Palopo' }], address: 'Jl. Andi Djemma — Palopo' },
    { id: 7, name: 'Kab. Bantaeng', kantor: 'Kantor Wilayah Bantaeng', tipe: 'cabang', locations: [{ id: 701, nama_lokasi: 'Kantor Bantaeng', lat: -5.54, lng: 119.93, radius: 180, address: 'Jl. Andi Mannappiang — Bantaeng' }], address: 'Jl. Andi Mannappiang — Bantaeng' },
    { id: 8, name: 'Kab. Barru', kantor: 'Kantor Wilayah Barru', tipe: 'cabang', locations: [{ id: 801, nama_lokasi: 'Kantor Barru', lat: -4.42, lng: 119.68, radius: 180, address: 'Jl. Sultan Hasanuddin — Barru' }], address: 'Jl. Sultan Hasanuddin — Barru' },
    { id: 9, name: 'Kab. Bulukumba', kantor: 'Kantor Wilayah Bulukumba', tipe: 'cabang', locations: [{ id: 901, nama_lokasi: 'Kantor Bulukumba', lat: -5.56, lng: 120.19, radius: 200, address: 'Jl. Sam Ratulangi — Bulukumba' }], address: 'Jl. Sam Ratulangi — Bulukumba' },
    { id: 10, name: 'Kab. Enrekang', kantor: 'Kantor Wilayah Enrekang', tipe: 'cabang', locations: [{ id: 1001, nama_lokasi: 'Kantor Enrekang', lat: -3.58, lng: 119.77, radius: 200, address: 'Jl. Pahlawan — Enrekang' }], address: 'Jl. Pahlawan — Enrekang' },
    { id: 11, name: 'Kab. Jeneponto', kantor: 'Kantor Wilayah Jeneponto', tipe: 'cabang', locations: [{ id: 1101, nama_lokasi: 'Kantor Jeneponto', lat: -5.66, lng: 119.73, radius: 200, address: 'Jl. Pahlawan — Jeneponto' }], address: 'Jl. Pahlawan — Jeneponto' },
    { id: 12, name: 'Kab. Kepulauan Selayar', kantor: 'Kantor Wilayah Selayar', tipe: 'cabang', locations: [{ id: 1201, nama_lokasi: 'Kantor Selayar', lat: -6.12, lng: 120.45, radius: 250, address: 'Jl. Ahmad Yani — Benteng Selayar' }], address: 'Jl. Ahmad Yani — Benteng Selayar' },
    { id: 13, name: 'Kab. Luwu', kantor: 'Kantor Wilayah Luwu', tipe: 'cabang', locations: [{ id: 1301, nama_lokasi: 'Kantor Luwu', lat: -3.39, lng: 120.38, radius: 200, address: 'Jl. Trans Sulawesi — Belopa' }], address: 'Jl. Trans Sulawesi — Belopa' },
    { id: 14, name: 'Kab. Luwu Timur', kantor: 'Kantor Wilayah Luwu Timur', tipe: 'cabang', locations: [{ id: 1401, nama_lokasi: 'Kantor Luwu Timur', lat: -2.60, lng: 121.10, radius: 200, address: 'Jl. Soekarno Hatta — Malili' }], address: 'Jl. Soekarno Hatta — Malili' },
    { id: 15, name: 'Kab. Luwu Utara', kantor: 'Kantor Wilayah Luwu Utara', tipe: 'cabang', locations: [{ id: 1501, nama_lokasi: 'Kantor Luwu Utara', lat: -2.77, lng: 120.10, radius: 200, address: 'Jl. Simpurusiang — Masamba' }], address: 'Jl. Simpurusiang — Masamba' },
    { id: 16, name: 'Kab. Pangkajene dan Kepulauan', kantor: 'Kantor Wilayah Pangkep', tipe: 'cabang', locations: [{ id: 1601, nama_lokasi: 'Kantor Pangkep', lat: -4.84, lng: 119.54, radius: 200, address: 'Jl. H. Abd. Rahman — Pangkajene' }], address: 'Jl. H. Abd. Rahman — Pangkajene' },
    { id: 17, name: 'Kab. Pinrang', kantor: 'Kantor Wilayah Pinrang', tipe: 'cabang', locations: [{ id: 1701, nama_lokasi: 'Kantor Pinrang', lat: -3.79, lng: 119.65, radius: 200, address: 'Jl. Bintang — Pinrang' }], address: 'Jl. Bintang — Pinrang' },
    { id: 18, name: 'Kab. Sinjai', kantor: 'Kantor Wilayah Sinjai', tipe: 'cabang', locations: [{ id: 1801, nama_lokasi: 'Kantor Sinjai', lat: -5.12, lng: 120.25, radius: 200, address: 'Jl. Persatuan Raya — Sinjai' }], address: 'Jl. Persatuan Raya — Sinjai' },
    { id: 19, name: 'Kab. Soppeng', kantor: 'Kantor Wilayah Soppeng', tipe: 'cabang', locations: [{ id: 1901, nama_lokasi: 'Kantor Soppeng', lat: -4.35, lng: 119.88, radius: 200, address: 'Jl. Lamumpatue — Watansoppeng' }], address: 'Jl. Lamumpatue — Watansoppeng' },
    { id: 20, name: 'Kab. Takalar', kantor: 'Kantor Wilayah Takalar', tipe: 'cabang', locations: [{ id: 2001, nama_lokasi: 'Kantor Takalar', lat: -5.41, lng: 119.44, radius: 200, address: 'Jl. Syekh Yusuf — Takalar' }], address: 'Jl. Syekh Yusuf — Takalar' },
    { id: 21, name: 'Kab. Tana Toraja', kantor: 'Kantor Wilayah Tana Toraja', tipe: 'cabang', locations: [{ id: 2101, nama_lokasi: 'Kantor Tana Toraja', lat: -3.04, lng: 119.84, radius: 200, address: 'Jl. Pongtiku — Makale' }], address: 'Jl. Pongtiku — Makale' },
    { id: 22, name: 'Kab. Toraja Utara', kantor: 'Kantor Wilayah Toraja Utara', tipe: 'cabang', locations: [{ id: 2201, nama_lokasi: 'Kantor Toraja Utara', lat: -3.05, lng: 119.81, radius: 200, address: 'Jl. Poros Rantepao — Rantepao' }], address: 'Jl. Poros Rantepao — Rantepao' },
    { id: 23, name: 'Kab. Wajo', kantor: 'Kantor Wilayah Wajo', tipe: 'cabang', locations: [{ id: 2301, nama_lokasi: 'Kantor Wajo', lat: -4.12, lng: 120.03, radius: 200, address: 'Jl. Andi Paddanguri — Sengkang' }], address: 'Jl. Andi Paddanguri — Sengkang' },
    { id: 24, name: 'Kab. Sidrap', kantor: 'Kantor Wilayah Sidrap', tipe: 'cabang', locations: [{ id: 2401, nama_lokasi: 'Kantor Sidrap', lat: -3.94, lng: 119.79, radius: 200, address: 'Jl. Jenderal Sudirman — Pangkajene Sidenreng' }], address: 'Jl. Jenderal Sudirman — Pangkajene Sidenreng' },
];

export const DUMMY_EMPLOYEES = [
    { id: 1, nik: '7371001234567890', nip: '198501012010011001', nama: 'Andi Saputra', email: 'andi@bbws-pj.go.id', gol: 'III/a', jabatan: 'Staff Teknik', unit: 'Bidang Jalan', status: 'PNS', region: 'Kab. Gowa', regionId: 2, office_location_id: 201, foto: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&h=100&fit=crop&crop=face&auto=format' },
    { id: 2, nik: '7371001234567891', nip: '199002022015022002', nama: 'Siti Rahma', email: 'siti@bbws-pj.go.id', gol: 'III/b', jabatan: 'Analis Data', unit: 'Bidang Air', status: 'PPPK', region: 'Kota Makassar', regionId: 1, office_location_id: 101, foto: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=200&h=200&fit=crop&crop=face&auto=format' },
    { id: 3, nik: '7371001234567892', nip: '', nama: 'Budi Santoso', email: 'budi@bbws-pj.go.id', gol: '-', jabatan: 'Operator', unit: 'Bidang Jalan', status: 'Kontrak', region: 'Kab. Gowa', regionId: 2, office_location_id: 202, foto: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&h=100&fit=crop&crop=face&auto=format' },
    { id: 4, nik: '7371001234567893', nip: '', nama: 'Rina Wati', email: 'rina@bbws-pj.go.id', gol: '-', jabatan: 'Admin', unit: 'Bidang Umum', status: 'Kontrak', region: 'Kab. Gowa', regionId: 2, office_location_id: 201, foto: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=100&h=100&fit=crop&crop=face&auto=format' },
    { id: 5, nik: '7371001234567894', nip: '', nama: 'Dewi Lestari', email: 'dewi@bbws-pj.go.id', gol: '-', jabatan: 'Staff', unit: 'Bidang Jalan', status: 'PNS', region: 'Kab. Maros', regionId: 3, office_location_id: 301, foto: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=100&h=100&fit=crop&crop=face&auto=format' },
];

// Admin/atasan yang boleh approve Love — hanya admin (bukan karyawan isAtasan)
// Scope: Kantor Gowa (own region) + atasan titik proyek assigned. Karyawan hanya lihat subset ini.
export const DUMMY_ADMINS = [
    { id: 801, nama: 'H. Andi Faisal', nip: '196805121992031005', jabatan: 'Kepala Balai', wilayah: 'Kab. Gowa', regionId: 2, office_location_id: null, scope: 'Kantor Gowa' },
    { id: 802, nama: 'Ir. Siti Nurbaya', nip: '197203152000032001', jabatan: 'Kabag Umum & TU', wilayah: 'Kab. Gowa', regionId: 2, office_location_id: null, scope: 'Kantor Gowa' },
    { id: 803, nama: 'Jamaluddin S.T.', nip: '198010052006041002', jabatan: 'Pengawas Bendungan Bili-Bili', wilayah: 'Kab. Gowa', regionId: 2, office_location_id: 201, scope: 'Bendungan Bili-Bili' },
    { id: 804, nama: 'Rahmat Hidayat', nip: '198512102010011003', jabatan: 'Pengawas Jembatan Pampang', wilayah: 'Kab. Gowa', regionId: 2, office_location_id: 202, scope: 'Jembatan Pampang' },
    { id: 805, nama: 'Muh. Amin S.T.', nip: '197909182006041004', jabatan: 'Pengawas Kantor Bone', wilayah: 'Kab. Bone', regionId: 4, office_location_id: 401, scope: 'Kantor Bone' },
];
// Helper: approver options untuk 1 karyawan (assigned) — dibatasi Kantor Gowa + titik assigned
export function getApproversForSite(office_location_id) {
    // Kantor Gowa = admin dengan wilayah OWN_REGION & office_location_id == null (pusat) termasuk
    const kantorGowa = DUMMY_ADMINS.filter((a) => a.wilayah === OWN_REGION && a.office_location_id == null);
    const siteAdmins = DUMMY_ADMINS.filter((a) => a.office_location_id === Number(office_location_id));
    // uniq by id
    const map = new Map();
    [...kantorGowa, ...siteAdmins].forEach((a) => map.set(a.id, a));
    return [...map.values()];
}

const LS_REGIONS = 'bbws_mock_regions_v3';
const LS_EMPLOYEES = 'bbws_mock_employees_v3';
export const LS_CUTI = 'bbws_mock_cuti_v3';
export const LS_CUTI_OLD = 'bbws_mock_cuti_karyawan_v3';
export const LS_LOVE = 'bbws_mock_love_v3';
export const LS_ATTENDANCES = 'bbws_mock_attendances_v3';
export const LS_SETTINGS = 'bbws_mock_settings_v3';
export const LS_PENGUMUMAN = 'bbws_mock_pengumuman_v3';
export const LS_READ = 'bbws_mock_pengumuman_read_v3';

// Seed — unified across Admin dan Karyawan
export const DUMMY_CUTI = [
    { id: 1, employee_id: 1, nama: 'Andi Saputra', email: 'andi@bbws-pj.go.id', wilayah: 'Kab. Gowa', regionId: 2, office_location_id: 201, jenis: 'Tahunan', tgl: '28–30 Agu 2026', mulai: '2026-08-28', selesai: '2026-08-30', alasan: 'Acara keluarga', dokumen: null, status: 'Menunggu', level: 2, createdAt: '2026-08-24T07:00:00+08:00' },
    { id: 2, employee_id: 99, nama: 'Rudi Hartono', email: 'rudi@bbws-pj.go.id', wilayah: 'Kab. Bone', regionId: 4, office_location_id: 401, jenis: 'Sakit', tgl: '23 Agu 2026', mulai: '2026-08-23', selesai: '2026-08-23', alasan: 'Demam — surat dokter', dokumen: null, status: 'Menunggu', level: 1, createdAt: '2026-08-23T08:00:00+08:00' },
    { id: 3, employee_id: 2, nama: 'Siti Rahma', email: 'siti@bbws-pj.go.id', wilayah: 'Kota Makassar', regionId: 1, office_location_id: 101, jenis: 'Besar', tgl: '20 Agu 2026', mulai: '2026-08-20', selesai: '2026-08-20', alasan: 'Haji', dokumen: null, status: 'Disetujui', level: 3, createdAt: '2026-08-20T09:00:00+08:00' },
    { id: 4, employee_id: 3, nama: 'Budi Santoso', email: 'budi@bbws-pj.go.id', wilayah: 'Kab. Gowa', regionId: 2, office_location_id: 202, jenis: 'Tahunan', tgl: '25–26 Agu 2026', mulai: '2026-08-25', selesai: '2026-08-26', alasan: 'Keperluan pribadi', dokumen: null, status: 'Ditolak', level: 1, createdAt: '2026-08-24T09:30:00+08:00', note: 'Stok cuti habis' },
    { id: 5, employee_id: 4, nama: 'Rina Wati', email: 'rina@bbws-pj.go.id', wilayah: 'Kab. Gowa', regionId: 2, office_location_id: 201, jenis: 'Melahirkan', tgl: '15 Agu–15 Nov 2026', mulai: '2026-08-15', selesai: '2026-11-15', alasan: 'Cuti melahirkan', dokumen: null, status: 'Menunggu', level: 2, createdAt: '2026-08-15T10:00:00+08:00' },
];

export const DUMMY_LOVE = [
    { id: 1, employee_id: 1, nama: 'Andi Saputra', wilayah: 'Kab. Gowa', kantor: 'Kantor Gowa', office_location_id: 201, jenis: 'terlambat', tgl: '2026-08-24', jam: '07:52', jarak: 42, radius: 200, alasan: 'Macet poros Gowa — perbaikan jalan', approver_id: 803, approver_nama: 'Jamaluddin S.T.', approver_nip: '198010052006041002', approver_scope: 'Bendungan Bili-Bili', status: 'pending', createdAt: '2026-08-24T07:52:00+08:00' },
    { id: 2, employee_id: 4, nama: 'Rina Wati', wilayah: 'Kab. Gowa', kantor: 'Kantor Gowa', office_location_id: 201, jenis: 'lupa_absen', tgl: '2026-08-23', jam: '07:35', jarak: null, radius: 200, alasan: 'Lupa absen datang — hujan deras', approver_id: 801, approver_nama: 'H. Andi Faisal', approver_nip: '196805121992031005', approver_scope: 'Kantor Gowa', status: 'pending', createdAt: '2026-08-24T07:48:00+08:00' },
    { id: 3, employee_id: 99, nama: 'Rudi Hartono', wilayah: 'Kab. Bone', kantor: 'Kantor Bone', office_location_id: 401, jenis: 'terlambat', tgl: '2026-08-23', jam: '07:55', jarak: 18, radius: 150, alasan: 'Ban bocor', approver_id: 805, approver_nama: 'Muh. Amin S.T.', approver_nip: '197909182006041004', approver_scope: 'Kantor Bone', status: 'approved', createdAt: '2026-08-23T07:55:00+08:00' },
    { id: 4, employee_id: 5, nama: 'Dewi Lestari', wilayah: 'Kab. Takalar', kantor: 'Kantor Takalar', office_location_id: 2001, jenis: 'lupa_pulang', tgl: '2026-08-22', jam: '16:00', jarak: null, radius: 200, alasan: 'Lupa absen pulang — rapat di lapangan', approver_id: 801, approver_nama: 'H. Andi Faisal', approver_nip: '196805121992031005', approver_scope: 'Kantor Gowa', status: 'rejected', note: 'Alasan tidak cukup kuat', createdAt: '2026-08-22T08:05:00+08:00' },
    { id: 5, employee_id: 3, nama: 'Budi Santoso', wilayah: 'Kab. Gowa', kantor: 'Kantor Gowa', office_location_id: 202, jenis: 'terlambat', tgl: '2026-08-24', jam: '07:44', jarak: 21, radius: 150, alasan: 'Keterlambatan KRL', approver_id: 804, approver_nama: 'Rahmat Hidayat', approver_nip: '198512102010011003', approver_scope: 'Jembatan Pampang', status: 'pending', createdAt: '2026-08-24T07:44:00+08:00' },
];

export const DUMMY_ATTENDANCES = [
    { id: 1, employee_id: 1, nama: 'Andi Saputra', email: 'andi@bbws-pj.go.id', wilayah: 'Kab. Gowa', kantor: 'Kantor Gowa', office_location_id: 201, tgl: '2026-08-24', datang: '07:52', pulang: '16:12', status: 'late', love: 'pending', jarak: 42, lat: -5.3114, lng: 119.42, foto: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&h=200&fit=crop&crop=face&auto=format', selfie: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&h=400&fit=crop&crop=face&auto=format' },
    { id: 2, employee_id: 2, nama: 'Siti Rahma', email: 'siti@bbws-pj.go.id', wilayah: 'Kota Makassar', kantor: 'Kantor Pusat', office_location_id: 101, tgl: '2026-08-24', datang: '07:38', pulang: '16:05', status: 'on_time', love: null, jarak: 38, lat: -5.1477, lng: 119.4327, foto: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=200&h=200&fit=crop&crop=face&auto=format', selfie: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&h=400&fit=crop&crop=face&auto=format' },
    { id: 3, employee_id: 3, nama: 'Budi Santoso', email: 'budi@bbws-pj.go.id', wilayah: 'Kab. Gowa', kantor: 'Kantor Gowa', office_location_id: 202, tgl: '2026-08-24', datang: '07:40', pulang: '16:05', status: 'on_time', love: null, jarak: 21, lat: -5.32, lng: 119.45, foto: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=200&h=200&fit=crop&crop=face&auto=format', selfie: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&h=400&fit=crop&crop=face&auto=format' },
    { id: 4, employee_id: 4, nama: 'Rina Wati', email: 'rina@bbws-pj.go.id', wilayah: 'Kab. Gowa', kantor: 'Kantor Gowa', office_location_id: 201, tgl: '2026-08-24', datang: '07:48', pulang: '', status: 'late', love: null, jarak: 28, lat: -5.3114, lng: 119.42, foto: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=200&h=200&fit=crop&crop=face&auto=format', selfie: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=400&h=400&fit=crop&crop=face&auto=format' },
    { id: 5, employee_id: 99, nama: 'Rudi Hartono', email: 'rudi@bbws-pj.go.id', wilayah: 'Kab. Bone', kantor: 'Kantor Bone', office_location_id: 401, tgl: '2026-08-24', datang: '07:55', pulang: '15:40', status: 'excused_love', love: 'approved', jarak: 28, lat: -4.54, lng: 120.33, foto: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=200&h=200&fit=crop&crop=face&auto=format', selfie: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&h=400&fit=crop&crop=face&auto=format' },
    { id: 6, employee_id: 5, nama: 'Dewi Lestari', email: 'dewi@bbws-pj.go.id', wilayah: 'Kab. Takalar', kantor: 'Kantor Takalar', office_location_id: 2001, tgl: '2026-08-24', datang: '08:05', pulang: '', status: 'late', love: null, jarak: 95, lat: -5.41, lng: 119.44, foto: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=200&h=200&fit=crop&crop=face&auto=format', selfie: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=400&h=400&fit=crop&crop=face&auto=format' },
];

export function loadRegions() {
    try {
        const raw = localStorage.getItem(LS_REGIONS);
        if (raw) return JSON.parse(raw);
    } catch {}
    return DUMMY_REGIONS;
}
export function saveRegions(list) {
    try { localStorage.setItem(LS_REGIONS, JSON.stringify(list)); } catch {}
}
export function loadEmployees() {
    try {
        const raw = localStorage.getItem(LS_EMPLOYEES);
        if (raw) return JSON.parse(raw);
    } catch {}
    return DUMMY_EMPLOYEES;
}
export function saveEmployees(list) {
    try { localStorage.setItem(LS_EMPLOYEES, JSON.stringify(list)); } catch {}
}
export function loadCuti() {
    try {
        const raw = localStorage.getItem(LS_CUTI);
        if (raw) return JSON.parse(raw);
        const old = localStorage.getItem(LS_CUTI_OLD);
        if (old) {
            const parsed = JSON.parse(old);
            // migrate karyawan-only shape (jenis/tgl/alasan/status/tone/step) → unified
            const migrated = parsed.map((c) => c.wilayah ? c : ({
                id: c.id, employee_id: 1, nama: 'Andi Saputra', email: 'andi@bbws-pj.go.id', wilayah: 'Kab. Gowa', regionId: 2, office_location_id: 201,
                jenis: c.jenis, tgl: c.tgl, mulai: '', selesai: '', alasan: c.alasan, dokumen: null,
                status: c.status?.includes('Menunggu') ? 'Menunggu' : c.status?.includes('Disetujui') ? 'Disetujui' : c.status?.includes('Ditolak') ? 'Ditolak' : c.status || 'Menunggu',
                level: c.step ?? 0, createdAt: new Date().toISOString(),
            }));
            localStorage.setItem(LS_CUTI, JSON.stringify(migrated.length ? migrated : DUMMY_CUTI));
            return migrated.length ? migrated : DUMMY_CUTI;
        }
    } catch {}
    return DUMMY_CUTI;
}
export function saveCuti(list) { try { localStorage.setItem(LS_CUTI, JSON.stringify(list)); } catch {} }
export const LOVE_JENIS = [
    { value: 'terlambat', label: 'Terlambat' },
    { value: 'lupa_absen', label: 'Lupa Absen Datang' },
    { value: 'lupa_pulang', label: 'Lupa Absen Pulang' },
];
export function loveJenisLabel(v) { return LOVE_JENIS.find((x)=>x.value===v)?.label || v || '—'; }

function migrateLoveList(list) {
    if (!Array.isArray(list)) return DUMMY_LOVE;
    let changed = false;
    const fallbackApprover = DUMMY_ADMINS[0];
    const out = list.map((c) => {
        if (c.jenis && c.approver_id) return c;
        changed = true;
        const next = { ...c };
        if (!next.jenis) next.jenis = 'terlambat';
        if (!next.tgl && next.createdAt) next.tgl = next.createdAt.slice(0,10);
        if (!next.tgl) next.tgl = new Date().toISOString().slice(0,10);
        if (!next.approver_id) {
            // assign atasan dari kantor/titik jika bisa, fallback Kantor Gowa
            const siteId = next.office_location_id;
            const opts = siteId != null ? getApproversForSite(siteId) : [fallbackApprover];
            const pick = opts[0] || fallbackApprover;
            next.approver_id = pick.id; next.approver_nama = pick.nama; next.approver_nip = pick.nip; next.approver_scope = pick.scope;
        }
        return next;
    });
    if (changed) try { localStorage.setItem(LS_LOVE, JSON.stringify(out)); } catch {}
    return out;
}

export function loadLove() {
    try { const raw = localStorage.getItem(LS_LOVE); if (raw) return migrateLoveList(JSON.parse(raw)); } catch {}
    return DUMMY_LOVE;
}
export function saveLove(list) { try { localStorage.setItem(LS_LOVE, JSON.stringify(list)); } catch {} }
export function loadAttendances() {
    try { const raw = localStorage.getItem(LS_ATTENDANCES); if (raw) return JSON.parse(raw); } catch {}
    return DUMMY_ATTENDANCES;
}
export function saveAttendances(list) { try { localStorage.setItem(LS_ATTENDANCES, JSON.stringify(list)); } catch {} }
export function loadSettings() {
    try { const raw = localStorage.getItem(LS_SETTINGS); if (raw) return JSON.parse(raw); } catch {}
    return { jamMasuk: '07:30', jamPulang: '16:00', toleransi: 15, loveMax: 4 };
}
export function saveSettings(v) { try { localStorage.setItem(LS_SETTINGS, JSON.stringify(v)); } catch {} }
export function getBase(url) {
    if (url.startsWith('/super-admin')) return '/super-admin';
    if (url.startsWith('/admin')) return '/admin';
    if (url.startsWith('/wilayah')) return '/wilayah';
    return '/admin';
}
// Settings helper untuk loveMax
export function getLoveMax() { try { const s = loadSettings(); return s.loveMax ?? 4; } catch { return 4; } }
