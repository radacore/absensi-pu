import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/**
 * ToastHost — pendengar global untuk flash.success / flash.error dari Inertia
 * ditambah errors validasi. Dipasang via Inertia layout wrapper di app.jsx
 * supaya usePage() valid.
 *
 * Halaman-halaman TIDAK perlu bikin state toast sendiri; cukup return
 * back()->with('success'|'error') dari controller, atau kirim event
 * `bbws:toast` (window.dispatchEvent) untuk pesan ad-hoc dari FE.
 */
export default function ToastHost({ position = 'bottom-right' }) {
    const { props } = usePage();
    const flash = props.flash;
    const errors = props.errors;
    const [items, setItems] = useState([]);
    const seenRef = useRef({ success: null, error: null, errorsKey: null });

    const push = (kind, msg) => {
        if (!msg) return;
        const id = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
        setItems((prev) => [...prev, { id, kind, msg }]);
        setTimeout(() => setItems((prev) => prev.filter((t) => t.id !== id)), 3500);
    };

    useEffect(() => {
        if (flash?.success && flash.success !== seenRef.current.success) {
            seenRef.current.success = flash.success;
            push('success', flash.success);
        }
        if (flash?.error && flash.error !== seenRef.current.error) {
            seenRef.current.error = flash.error;
            push('error', flash.error);
        }
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        const keys = errors ? Object.keys(errors) : [];
        const key = keys.length ? keys.join('|') + '::' + Object.values(errors).join('|') : '';
        if (key && key !== seenRef.current.errorsKey) {
            seenRef.current.errorsKey = key;
            const first = errors[keys[0]];
            if (first) push('error', Array.isArray(first) ? first[0] : first);
        } else if (!key) {
            seenRef.current.errorsKey = null;
        }
    }, [errors]);

    useEffect(() => {
        const handler = (ev) => {
            const detail = ev.detail || {};
            push(detail.kind === 'error' ? 'error' : 'success', detail.msg || detail.message);
        };
        window.addEventListener('bbws:toast', handler);
        return () => window.removeEventListener('bbws:toast', handler);
    }, []);

    if (items.length === 0) return null;

    const anchorMap = {
        'bottom-right': 'bottom-4 right-4 sm:bottom-6 sm:right-6 items-end',
        'bottom-left': 'bottom-4 left-4 sm:bottom-6 sm:left-6 items-start',
        'top-right': 'top-4 right-4 sm:top-6 sm:right-6 items-end',
        'top-left': 'top-4 left-4 sm:top-6 sm:left-6 items-start',
        top: 'top-4 left-1/2 -translate-x-1/2 items-center',
        bottom: 'bottom-24 left-1/2 -translate-x-1/2 items-center',
    };
    const anchor = anchorMap[position] || anchorMap['bottom-right'];

    return (
        <div
            className={`fixed ${anchor} z-[70] flex flex-col gap-2 max-w-[calc(100vw-2rem)] sm:max-w-[380px] pointer-events-none`}
            aria-live="polite"
            aria-atomic="true"
        >
            {items.map((t) => (
                <div
                    key={t.id}
                    role={t.kind === 'error' ? 'alert' : 'status'}
                    className={`pointer-events-auto rounded-xl px-4 py-3 text-sm font-medium shadow-[0_10px_28px_-4px_rgba(15,23,42,0.35)] backdrop-blur border animate-toast-in ${
                        t.kind === 'error'
                            ? 'bg-[#0F172A]/95 text-white border-[#EF4444]/60'
                            : 'bg-[#1E3A8A]/95 text-white border-[#3B82F6]/40'
                    }`}
                >
                    <div className="flex items-start gap-2.5">
                        {t.kind === 'error' ? (
                            <span className="w-6 h-6 rounded-lg bg-[#EF4444]/20 flex items-center justify-center shrink-0">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#FCA5A5" strokeWidth="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            </span>
                        ) : (
                            <span className="w-6 h-6 rounded-lg bg-[#FCB833]/20 flex items-center justify-center shrink-0">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#FCB833" strokeWidth="2.4"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </span>
                        )}
                        <p className="leading-snug flex-1 min-w-0 break-words">{t.msg}</p>
                    </div>
                </div>
            ))}
        </div>
    );
}
