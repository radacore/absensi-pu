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
export default function ToastHost({ position = 'top' }) {
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

    const anchor = position === 'top' ? 'top-4' : 'bottom-24';
    return (
        <div className={`fixed ${anchor} left-1/2 -translate-x-1/2 z-[70] flex flex-col gap-2 px-4 w-full max-w-[440px] pointer-events-none`} aria-live="polite" aria-atomic="true">
            {items.map((t) => (
                <div
                    key={t.id}
                    role={t.kind === 'error' ? 'alert' : 'status'}
                    className={`pointer-events-auto rounded-xl px-4 py-3 text-sm font-medium shadow-lg backdrop-blur border ${
                        t.kind === 'error'
                            ? 'bg-[#FEF2F2]/95 text-[#991B1B] border-[#FCA5A5]'
                            : 'bg-[#ECFDF5]/95 text-[#065F46] border-[#6EE7B7]'
                    }`}
                >
                    <div className="flex items-start gap-2.5">
                        {t.kind === 'error' ? (
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="shrink-0 mt-0.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        ) : (
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="shrink-0 mt-0.5"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        )}
                        <p className="leading-snug flex-1 min-w-0 break-words">{t.msg}</p>
                    </div>
                </div>
            ))}
        </div>
    );
}
