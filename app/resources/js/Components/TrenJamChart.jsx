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

export default function TrenJamChart({ tren, mode, settings }) {
    const lastDay = tren.length;
    if (lastDay === 0) return null;

    const isMasuk = mode === 'masuk';
    const points = tren.map((r) => ({ ...r, val: isMasuk ? r.jam_masuk_min : r.jam_pulang_min }));

    // Range Y: masuk 06:00–10:00 (360–600 min); pulang 15:00–22:00 (900–1320)
    const yMin = isMasuk ? 360 : 900;
    const yMax = isMasuk ? 600 : 1320;

    const xFor = (day) => PAD_L + ((day - 1) / Math.max(1, lastDay - 1)) * (W - PAD_L - PAD_R);
    const yFor = (min) => {
        const clamped = Math.max(yMin, Math.min(yMax, min));
        return PAD_T + ((clamped - yMin) / (yMax - yMin)) * (H - PAD_T - PAD_B);
    };

    const chartH = H - PAD_T - PAD_B;
    const chartW = W - PAD_L - PAD_R;

    // Zona background (hanya untuk mode masuk)
    let zonaBg = null;
    if (isMasuk) {
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

    // Garis referensi
    const refLines = [];
    if (isMasuk) {
        refLines.push({ y: yFor(settings.jamMasukMin), label: minToLabel(settings.jamMasukMin), stroke: '#94A3B8' });
        refLines.push({ y: yFor(settings.batasToleransiMin), label: minToLabel(settings.batasToleransiMin), stroke: '#F59E0B' });
    } else {
        refLines.push({ y: yFor(settings.jamPulangMin), label: minToLabel(settings.jamPulangMin), stroke: '#10B981' });
    }

    // Poin data yang punya value
    const valid = points.filter((p) => p.val !== null && p.val !== undefined);
    const linePath = valid.length > 1
        ? valid.map((p, i) => `${i === 0 ? 'M' : 'L'} ${xFor(p.day)} ${yFor(p.val)}`).join(' ')
        : '';

    // Missing points (hari kerja tanpa absen)
    const missing = points.filter((p) => p.val === null && !p.is_weekend && !p.is_holiday && !p.is_cuti && !p.is_dinas);

    // Y-axis labels (3-4 label)
    const yLabels = isMasuk
        ? [{ min: 600, txt: '10:00' }, { min: 480, txt: '08:00' }, { min: 420, txt: '07:00' }, { min: 360, txt: '06:00' }]
        : [{ min: 900, txt: '15:00' }, { min: 1080, txt: '18:00' }, { min: 1200, txt: '20:00' }, { min: 1320, txt: '22:00' }];

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
                    <text key={`yl-${yl.min}`} x={PAD_L - 4} y={yFor(yl.min) + 3} fontSize="10" fill="#64748B" textAnchor="end">{yl.txt}</text>
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
            <p className="text-xs text-[#94A3B8] mt-1">
                {isMasuk
                    ? 'Zona hijau tepat waktu · Zona kuning toleransi · Zona merah muda terlambat · Titik merah = tanpa absen'
                    : 'Garis putus hijau: jam pulang standar · Titik merah = tanpa absen pulang'}
            </p>
        </div>
    );
}
