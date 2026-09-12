// Helper murni untuk halaman admin. Tidak menyimpan state; semua data dari server.

export const OWN_REGION = 'Kab. Gowa';
export const MAX_SITES = 20;

export const WILAYAH_LIST = ['Semua', 'Kota Makassar', 'Kab. Gowa', 'Kab. Maros', 'Kab. Bone', 'Kota Parepare', 'Kota Palopo', 'Kab. Bantaeng', 'Kab. Barru', 'Kab. Bulukumba', 'Kab. Enrekang', 'Kab. Jeneponto', 'Kab. Kepulauan Selayar', 'Kab. Luwu', 'Kab. Luwu Timur', 'Kab. Luwu Utara', 'Kab. Pangkajene dan Kepulauan', 'Kab. Pinrang', 'Kab. Sinjai', 'Kab. Soppeng', 'Kab. Takalar', 'Kab. Tana Toraja', 'Kab. Toraja Utara', 'Kab. Wajo', 'Kab. Sidrap'];
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
