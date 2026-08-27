import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { useState, useRef } from 'react';

export default function Profil() {
    const [photo, setPhoto] = useState(null);
    const [preview, setPreview] = useState(null);
    const fileRef = useRef(null);
    const [pwd, setPwd] = useState({ old: '', next: '', confirm: '' });
    const [msg, setMsg] = useState(null);

    const handlePhoto = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setPhoto(file);
        const reader = new FileReader();
        reader.onload = () => setPreview(reader.result);
        reader.readAsDataURL(file);
    };

    const handleReset = (e) => {
        e.preventDefault();
        if (!pwd.old || !pwd.next || !pwd.confirm) {
            setMsg({ type: 'error', text: 'Lengkapi semua field kata sandi' });
            return;
        }
        if (pwd.next.length < 8) {
            setMsg({ type: 'error', text: 'Kata sandi baru minimal 8 karakter' });
            return;
        }
        if (pwd.next !== pwd.confirm) {
            setMsg({ type: 'error', text: 'Konfirmasi tidak cocok' });
            return;
        }
        setMsg({ type: 'success', text: 'Kata sandi berhasil diperbarui (frontend only)' });
        setPwd({ old: '', next: '', confirm: '' });
    };

    return (
        <KaryawanLayout>
            <div className="space-y-5">
                <div className="bg-white rounded-2xl p-6 shadow-[0_2px_16px_rgba(15,23,42,0.04)] text-center">
                    <div className="mx-auto w-20 h-20 rounded-full bg-[#F1F5F9] overflow-hidden flex items-center justify-center border-2 border-white shadow-sm">
                        {preview ? (
                            <img src={preview} alt="Foto profil" className="w-full h-full object-cover" />
                        ) : (
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#334155" strokeWidth="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0116 0"/></svg>
                        )}
                    </div>
                    <h2 className="font-semibold text-[16px] text-[#0F172A] mt-3">Andi Saputra</h2>
                    <p className="text-sm text-[#64748B]">Staff Teknik • Bidang Jalan</p>
                    <p className="text-xs text-[#94A3B8] mt-1">PNS • Gol. III/a • NIK 7371 • NIP 1985</p>
                    <span className="inline-block mt-3 text-xs font-medium bg-[#F1F5F9] text-[#334155] px-3 py-1 rounded-full">Kantor BBWS PJ Cab. Gowa</span>
                </div>

                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] space-y-4">
                    <h3 className="font-medium text-sm text-[#0F172A]">Data pribadi</h3>
                    <p className="text-xs text-[#94A3B8]">NIK, NIP, golongan, dan unit tidak dapat diubah mandiri</p>
                    <div className="space-y-3">
                        <div>
                            <label htmlFor="phone" className="text-xs font-medium text-[#334155]">Nomor ponsel</label>
                            <input id="phone" defaultValue="0812-3456-7890" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3.5 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                        </div>
                        <div>
                            <label htmlFor="email" className="text-xs font-medium text-[#334155]">Email</label>
                            <input id="email" defaultValue="andi@pu-sulsel.go.id" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3.5 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                        </div>
                    </div>
                    <input ref={fileRef} type="file" accept="image/*" onChange={handlePhoto} className="hidden" />
                    <div className="grid grid-cols-2 gap-2 pt-2">
                        <button type="button" onClick={() => fileRef.current?.click()} className="rounded-xl bg-[#F8FAFC] py-2.5 text-sm font-medium text-[#334155]">{photo ? 'Ganti lagi' : 'Upload foto'}</button>
                        <button type="button" onClick={() => setMsg({ type: 'success', text: 'Data pribadi disimpan (frontend only)' })} className="rounded-xl bg-[#0F172A] text-white py-2.5 text-sm font-semibold">Simpan</button>
                    </div>
                    {photo && <p className="text-xs text-[#10B981]">Terpilih: {photo.name} • Preview di atas</p>}
                    {preview && <button type="button" onClick={() => { setPreview(null); setPhoto(null); }} className="text-xs text-[#EF4444] font-medium">Hapus preview</button>}
                    {msg && msg.text.includes('Data pribadi') && <p className={`text-xs px-3 py-2 rounded-xl ${msg.type === 'success' ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{msg.text}</p>}
                </div>

                {/* Reset Password — frontend only */}
                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] space-y-4">
                    <div className="flex items-center gap-2.5">
                        <span className="w-8 h-8 rounded-xl bg-[#FFF7E6] flex items-center justify-center">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#92400E" strokeWidth="1.6"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/><circle cx="12" cy="16" r="1.2" fill="#92400E" stroke="none"/></svg>
                        </span>
                        <div>
                            <h3 className="font-medium text-sm text-[#0F172A]">Ganti kata sandi</h3>
                        </div>
                    </div>
                    <form onSubmit={handleReset} className="space-y-3">
                        <div>
                            <label htmlFor="oldPwd" className="text-xs font-medium text-[#334155]">Kata sandi lama</label>
                            <input id="oldPwd" type="password" value={pwd.old} onChange={(e) => setPwd({ ...pwd, old: e.target.value })} placeholder="••••••••" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3.5 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                        </div>
                        <div>
                            <label htmlFor="newPwd" className="text-xs font-medium text-[#334155]">Kata sandi baru</label>
                            <input id="newPwd" type="password" value={pwd.next} onChange={(e) => setPwd({ ...pwd, next: e.target.value })} placeholder="Min. 8 karakter" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3.5 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                        </div>
                        <div>
                            <label htmlFor="confirmPwd" className="text-xs font-medium text-[#334155]">Konfirmasi baru</label>
                            <input id="confirmPwd" type="password" value={pwd.confirm} onChange={(e) => setPwd({ ...pwd, confirm: e.target.value })} placeholder="Ulangi kata sandi baru" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3.5 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                        </div>
                        <button type="submit" className="w-full rounded-xl bg-[#0F172A] text-white py-2.5 text-sm font-semibold hover:bg-[#1E3A8A] transition">Simpan kata sandi</button>
                    </form>
                    {msg && !msg.text.includes('Data pribadi') && <p className={`text-xs px-3 py-2 rounded-xl ${msg.type === 'success' ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{msg.text}</p>}
                </div>
            </div>
        </KaryawanLayout>
    );
}
