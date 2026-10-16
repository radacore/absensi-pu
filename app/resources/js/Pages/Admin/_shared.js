// Helper murni untuk halaman admin. Tidak menyimpan state; semua data dari server.

export const MAX_SITES = 20;

// Wilayah milik admin yang sedang login. Server sudah menyaring daftar `regions`
// ke wilayah admin itu sendiri (lihat AdminPresenter::regionsFor), jadi entri
// pertama pada daftar selalu wilayahnya. Jangan pernah menulis nama wilayah
// secara tetap di sini: setiap admin wilayah punya wilayah yang berbeda.
export function ownRegionName(regionsData) {
    return regionsData?.[0]?.name ?? '';
}

export const WILAYAH_LIST = ['Semua', 'Kota Makassar', 'Kab. Gowa', 'Kab. Maros', 'Kab. Bone', 'Kota Parepare', 'Kota Palopo', 'Kab. Bantaeng', 'Kab. Barru', 'Kab. Bulukumba', 'Kab. Enrekang', 'Kab. Jeneponto', 'Kab. Kepulauan Selayar', 'Kab. Luwu', 'Kab. Luwu Timur', 'Kab. Luwu Utara', 'Kab. Pangkajene dan Kepulauan', 'Kab. Pinrang', 'Kab. Sinjai', 'Kab. Soppeng', 'Kab. Takalar', 'Kab. Tana Toraja', 'Kab. Toraja Utara', 'Kab. Wajo', 'Kab. Sidrap'];
export const REGION_LIST = WILAYAH_LIST.slice(1);

export function getSitesForWilayah(regionsData, wilayah, isWilayah) {
    if (isWilayah) return regionsData?.[0]?.locations || [];
    if (wilayah === 'Semua') return [];
    return regionsData.find((x) => x.name === wilayah)?.locations || [];
}

export function getValidSiteIds(regionsData, wilayah, isWilayah) {
    return new Set(getSitesForWilayah(regionsData, wilayah, isWilayah).map((s) => String(s.id)));
}

export function siteById(siteId, regionsData) {
    if (siteId == null) return null;
    for (const r of regionsData) {
        const s = r.locations.find((x) => x.id === Number(siteId));
        if (s) return { site: s, region: r };
    }
    return null;
}

export function getBase(url) {
    if (url.startsWith('/super-admin')) return '/super-admin';
    if (url.startsWith('/admin')) return '/admin';
    if (url.startsWith('/wilayah')) return '/wilayah';
    return '/admin';
}
