/**
 * TrenJamChart — grafik tren jam masuk atau jam pulang per hari
 * dalam sebulan. SVG native, tanpa dependency chart library.
 *
 * Props:
 *  - tren: array dari RekapPresenter.tren [{day, jam_masuk_min, jam_pulang_min, is_weekend, is_holiday, is_cuti, is_dinas, zona}]
 *  - mode: 'masuk' | 'pulang'
 *  - settings: {jamMasukMin, jamPulangMin, batasToleransiMin}
 */

const W = 720;
const H = 200;
const PAD_L = 44;
const PAD_R = 12;
const PAD_T = 12;
const PAD_B = 28;

function minToLabel(min) {
    const h = String(Math.floor(min / 60)).padStart(2, '0');
    const m = String(min % 60).padStart(2, '0');
    return `${h}:${m}`;
}

/** Langkah label sumbu-Y supaya jumlah baris wajar (2–5). */
function langkahLabel(rentang) {
    const kandidat = [30, 60, 120, 180, 240, 360];
    return kandidat.find((k) => rentang / k <= 4) ?? 360;
}

export default function TrenJamChart({ tren, mode, settings }) {
    const lastDay = tren.length;
    if (lastDay === 0) return null;

    const isMasuk = mode === 'masuk';
    const isShift = !!settings.isShift;
    const points = tren.map((r) => ({ ...r, val: isMasuk ? r.jam_masuk_min : r.jam_pulang_min }));

    // Rentang Y. Untuk jam kerja tetap dipatok 06:00–10:00 / 15:00–22:00 supaya
    // zona tepat/toleransi/terlambat selalu utuh. Pekerja shift bisa masuk
    // pukul 23:00 atau pulang pukul 07:00 — rentang dihitung dari data,
    // kalau tidak titiknya menempel di tepi dan tidak terbaca.
    let yMin;
    let yMax;
    if (isShift) {
        const nilai = points.filter((p) => p.val !== null && p.val !== undefined).map((p) => p.val);
        if (nilai.length === 0) return null;
        yMin = Math.max(0, Math.floor(Math.min(...nilai) / 60) * 60 - 60);
        yMax = Math.min(24 * 60, Math.ceil(Math.max(...nilai) / 60) * 60 + 60);
        if (yMax - yMin < 120) yMax = Math.min(24 * 60, yMin + 120);
    } else {
        yMin = isMasuk ? 360 : 900;
        yMax = isMasuk ? 600 : 1320;
    }

    const xFor = (day) => PAD_L + ((day - 1) / Math.max(1, lastDay - 1)) * (W - PAD_L - PAD_R);
    const yFor = (min) => {
        const clamped = Math.max(yMin, Math.min(yMax, min));
        return PAD_T + ((clamped - yMin) / (yMax - yMin)) * (H - PAD_T - PAD_B);
    };

    const chartH = H - PAD_T - PAD_B;
    const chartW = W - PAD_L - PAD_R;

    // Zona background (hanya untuk jam kerja tetap — acuan zona tidak berlaku
    // untuk pekerja shift yang jamnya berbeda setiap hari).
    let zonaBg = null;
    if (isMasuk && !isShift) {
        const yTepat0 = yFor(yMin);
        const yTepat1 = yFor(settings.jamMasukMin);
        const yToleransi1 = yFor(settings.batasToleransiMin);
        const yTerlambat1 = yFor(yMax);
        zonaBg = (
            <>
                <rect x={PAD_L} y={yTepat0} width={chartW} height={yTepat1 - yTepat0} fill="#DCFCE7" opacity="0.5" />
                <rect x={PAD_L} y={yTepat1} width={chartW} height={yToleransi1 - yTepat1} fill="#FEF3C7" opacity="0.5" />
                <rect x={PAD_L} y={yToleransi1} width={chartW} height={yTerlambat1 - yToleransi1} fill="#FEE2E2" opacity="0.5" />
            </>
        );
    }

    // Garis referensi — tidak ditampilkan untuk pekerja shift karena jam
    // acuannya berbeda tiap hari (mengikuti jadwal, bukan setelan global).
    const refLines = [];
    if (!isShift) {
        if (isMasuk) {
            refLines.push({ y: yFor(settings.jamMasukMin), label: minToLabel(settings.jamMasukMin), stroke: '#64748B' });
            refLines.push({ y: yFor(settings.batasToleransiMin), label: minToLabel(settings.batasToleransiMin), stroke: '#F59E0B' });
        } else {
            refLines.push({ y: yFor(settings.jamPulangMin), label: minToLabel(settings.jamPulangMin), stroke: '#10B981' });
        }
    }

    // Poin data yang punya value
    const valid = points.filter((p) => p.val !== null && p.val !== undefined);
    const linePath = valid.length > 1
        ? valid.map((p, i) => `${i === 0 ? 'M' : 'L'} ${xFor(p.day)} ${yFor(p.val)}`).join(' ')
        : '';

    // Hari kerja tanpa absen — pekerja shift memakai acuan jadwal dari server
    // (`is_hari_kerja`), non-shift memakai kalender.
    const missing = points.filter((p) => {
        if (p.val !== null && p.val !== undefined) return false;
        if (p.is_cuti || p.is_dinas) return false;
        return p.is_hari_kerja !== undefined
            ? !!p.is_hari_kerja
            : (!p.is_weekend && !p.is_holiday);
    });

    // Label sumbu-Y: tetap untuk jam kerja biasa, mengikuti rentang untuk shift.
    const yLabels = (() => {
        if (!isShift) {
            return isMasuk
                ? [{ min: 600 }, { min: 480 }, { min: 420 }, { min: 360 }]
                : [{ min: 900 }, { min: 1080 }, { min: 1200 }, { min: 1320 }];
        }
        const langkah = langkahLabel(yMax - yMin);
        const out = [];
        for (let m = Math.ceil(yMin / langkah) * langkah; m <= yMax; m += langkah) out.push({ min: m });
        return out;
    })();

    // X-axis labels (tanggal 1, 7, 14, 21, 28, lastDay)
    const xLabels = [1, 7, 14, 21, 28, lastDay].filter((d, i, arr) => arr.indexOf(d) === i && d <= lastDay);

    return (
        <div className="bg-white rounded-lg border border-[#E2E8F0] p-3">
            <p className="text-xs font-medium text-[#334155] mb-2">{isMasuk ? 'Tren jam masuk' : 'Tren jam pulang'}</p>
            <svg viewBox={`0 0 ${W} ${H}`} className="w-full h-auto" xmlns="http://www.w3.org/2000/svg">
                {zonaBg}
                {refLines.map((rl, i) => (
                    <g key={`ref-${i}-${rl.y}`}>
                        <line x1={PAD_L} x2={W - PAD_R} y1={rl.y} y2={rl.y} stroke={rl.stroke} strokeWidth="1" strokeDasharray="3,3" />
                        <text x={PAD_L - 4} y={rl.y + 3} fontSize="9" fill={rl.stroke} textAnchor="end">{rl.label}</text>
                    </g>
                ))}

                {yLabels.map((yl) => (
                    <text key={`yl-${yl.min}`} x={PAD_L - 4} y={yFor(yl.min) + 3} fontSize="10" fill="#64748B" textAnchor="end">{yl.txt ?? minToLabel(yl.min)}</text>
                ))}

                {xLabels.map((d) => (
                    <text key={`xl-${d}`} x={xFor(d)} y={H - PAD_B + 14} fontSize="10" fill="#64748B" textAnchor="middle">{d}</text>
                ))}

                {linePath && <path d={linePath} fill="none" stroke={isMasuk ? '#0D9488' : '#1E3A8A'} strokeWidth="1.6" strokeLinejoin="round" strokeLinecap="round" />}

                {valid.map((p) => (
                    <circle key={`v-${p.day}`} cx={xFor(p.day)} cy={yFor(p.val)} r="2.5" fill={isMasuk ? '#0D9488' : '#1E3A8A'} />
                ))}

                {missing.map((p) => (
                    <circle key={`m-${p.day}`} cx={xFor(p.day)} cy={H - PAD_B - 2} r="2.5" fill="#EF4444" opacity="0.7" />
                ))}
            </svg>
            <p className="text-xs text-[#64748B] mt-1">
                {isShift
                    ? 'Rentang jam mengikuti jadwal shift · Titik merah = hari terjadwal tanpa absen'
                    : (isMasuk
                        ? 'Zona hijau tepat waktu · Zona kuning toleransi · Zona merah muda terlambat · Titik merah = tanpa absen'
                        : 'Garis putus hijau: jam pulang standar · Titik merah = tanpa absen pulang')}
            </p>
        </div>
    );
}
