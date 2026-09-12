/**
 * toast.js — helper untuk push toast dari kode FE (bukan flash server).
 * ToastHost di layout mendengarkan event 'bbws:toast'.
 */

function push(kind, msg) {
    if (typeof window === 'undefined' || !msg) return;
    window.dispatchEvent(new CustomEvent('bbws:toast', { detail: { kind, msg } }));
}

export const toast = {
    success: (msg) => push('success', msg),
    error: (msg) => push('error', msg),
};
