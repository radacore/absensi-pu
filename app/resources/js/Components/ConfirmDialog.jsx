import { createContext, useCallback, useContext, useMemo, useState } from 'react';

const ConfirmContext = createContext(null);

/**
 * useConfirm — hook untuk memanggil modal konfirmasi.
 *
 * Contoh:
 *   const confirm = useConfirm();
 *   const ok = await confirm({
 *     title: 'Hapus Andi Saputra?',
 *     description: 'Data karyawan akan dihapus permanen.',
 *     confirmLabel: 'Ya, hapus',
 *     tone: 'danger',
 *   });
 *   if (ok) router.delete(...);
 */
export function useConfirm() {
    const ctx = useContext(ConfirmContext);
    if (!ctx) throw new Error('useConfirm() harus dipanggil di dalam <ConfirmProvider>');
    return ctx;
}

export function ConfirmProvider({ children }) {
    const [state, setState] = useState(null); // { opts, resolve } | null

    const confirm = useCallback((opts = {}) => {
        return new Promise((resolve) => {
            setState({ opts, resolve });
        });
    }, []);

    const finish = (ok) => {
        state?.resolve(ok);
        setState(null);
    };

    const value = useMemo(() => confirm, [confirm]);

    return (
        <ConfirmContext.Provider value={value}>
            {children}
            {state && <ConfirmDialog opts={state.opts} onConfirm={() => finish(true)} onCancel={() => finish(false)} />}
        </ConfirmContext.Provider>
    );
}

function ConfirmDialog({ opts, onConfirm, onCancel }) {
    const {
        title = 'Konfirmasi',
        description = null,
        confirmLabel = 'Ya, lanjutkan',
        cancelLabel = 'Batal',
        tone = 'default', // 'danger' | 'warning' | 'default'
        icon = null,
        children = null,
    } = opts || {};

    const confirmCls = tone === 'danger'
        ? 'bg-[#EF4444] hover:bg-[#DC2626] text-white'
        : tone === 'warning'
            ? 'bg-[#FCB833] hover:bg-[#EAB308] text-[#0F172A]'
            : 'bg-[#0F172A] hover:bg-[#1E3A8A] text-white';

    const iconBg = tone === 'danger' ? 'bg-[#FEF2F2] text-[#991B1B]' : tone === 'warning' ? 'bg-[#FEF3C7] text-[#92400E]' : 'bg-[#EFF6FF] text-[#1E3A8A]';

    return (
        <div className="fixed inset-0 z-[80] bg-black/50 flex items-center justify-center p-4" onClick={onCancel}>
            <div className="bg-white rounded-2xl w-full max-w-[420px] shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="px-6 py-5">
                    <div className="flex items-start gap-3">
                        {icon !== false && (
                            <span className={`w-10 h-10 rounded-xl ${iconBg} flex items-center justify-center shrink-0`}>
                                {icon || (
                                    tone === 'danger' ? (
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 01-2 2H9a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 012-2h2a2 2 0 012 2v2"/></svg>
                                    ) : tone === 'warning' ? (
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                    ) : (
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                    )
                                )}
                            </span>
                        )}
                        <div className="min-w-0 flex-1">
                            <h3 className="font-semibold text-[#0F172A]">{title}</h3>
                            {description && <p className="text-sm text-[#64748B] mt-1 leading-relaxed">{description}</p>}
                        </div>
                    </div>
                    {children && <div className="mt-4">{children}</div>}
                </div>
                <div className="px-6 pb-5 flex gap-2">
                    <button type="button" onClick={onCancel} className="flex-1 rounded-xl bg-[#F1F5F9] hover:bg-[#E2E8F0] py-3 text-sm font-semibold text-[#64748B] transition">{cancelLabel}</button>
                    <button type="button" onClick={onConfirm} autoFocus className={`flex-1 rounded-xl py-3 text-sm font-semibold transition ${confirmCls}`}>{confirmLabel}</button>
                </div>
            </div>
        </div>
    );
}
