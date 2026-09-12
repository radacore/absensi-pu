import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { useConfirm } from '@/Components/ConfirmDialog';
import { toast } from '@/lib/toast';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export default function Profil(){
    const { props } = usePage();
    const me0 = props.me ?? { nama:'—', email:'', phone:'', foto:'', region:'', jabatan:'', unit:'', status:'', gol:'-', nip:'', nik:'' };
    const assigned0 = props.assigned ?? null;
    const confirm = useConfirm();

    const [phone,setPhone]=useState(me0.phone || '');
    const [email,setEmail]=useState(me0.email || '');
    const [photoPreview,setPhotoPreview]=useState(me0.foto || null);
    const fileRef=useRef(null);
    const [pwd,setPwd]=useState({ current_password:'', password:'', password_confirmation:'' });

    useEffect(()=>{
        setPhone(me0.phone || '');
        setEmail(me0.email || '');
        if(me0.foto) setPhotoPreview(me0.foto);
    },[me0.phone, me0.email, me0.foto]); // eslint-disable-line react-hooks/exhaustive-deps

    const handlePhoto=(e)=>{
        const file=e.target.files?.[0];
        if(!file) return;
        const reader=new FileReader();
        reader.onload=()=> setPhotoPreview(reader.result);
        reader.readAsDataURL(file);
    };
    const askClearPhoto=async ()=>{
        const ok = await confirm({
            title: 'Hapus foto profil?',
            description: 'Tindakan ini akan menghapus foto dan tidak dapat dibatalkan.',
            confirmLabel: 'Ya, hapus',
            cancelLabel: 'Batal',
            tone: 'danger',
        });
        if(!ok) return;
        router.delete('/karyawan/profil/foto', {
            preserveScroll:true,
            onSuccess:()=>{ setPhotoPreview(null); if(fileRef.current) fileRef.current.value=''; },
        });
    };
    const handleSaveProfile=()=>{
        const file=fileRef.current?.files?.[0] || null;
        const payload={ phone: phone.trim()||null, email: email.trim()||null };
        if(file){
            const fd=new FormData();
            fd.append('_method','put');
            if(payload.phone) fd.append('phone', payload.phone);
            if(payload.email) fd.append('email', payload.email);
            fd.append('foto', file);
            router.post('/karyawan/profil', fd, { preserveScroll:true, forceFormData:true });
            return;
        }
        if(photoPreview && photoPreview.startsWith('data:')) payload.foto_url=photoPreview;
        router.put('/karyawan/profil', payload, { preserveScroll:true });
    };
    const handleReset=(e)=>{
        e.preventDefault();
        if(!pwd.current_password || !pwd.password || !pwd.password_confirmation){
            toast.error('Lengkapi semua field kata sandi'); return;
        }
        if(pwd.password.length < 8){ toast.error('Kata sandi baru minimal 8 karakter'); return; }
        if(!/[A-Z]/.test(pwd.password) || !/[a-z]/.test(pwd.password) || !/\d/.test(pwd.password)){
            toast.error('Kata sandi wajib mengandung huruf besar, huruf kecil, dan angka'); return;
        }
        if(me0.nik && pwd.password === me0.nik){
            toast.error('Kata sandi baru tidak boleh sama dengan NIK'); return;
        }
        if(pwd.password !== pwd.password_confirmation){ toast.error('Konfirmasi kata sandi tidak cocok'); return; }
        router.put('/karyawan/profil/password', pwd, {
            preserveScroll:true,
            onSuccess:()=> setPwd({ current_password:'', password:'', password_confirmation:'' }),
        });
    };
    const handleLogout=async ()=>{
        const ok = await confirm({
            title: 'Keluar dari akun?',
            description: 'Anda perlu login ulang untuk mengakses aplikasi.',
            confirmLabel: 'Ya, keluar',
            cancelLabel: 'Batal',
            tone: 'danger',
        });
        if(!ok) return;
        router.post('/karyawan/logout');
    };

    return (
        <KaryawanLayout>
            <div className="space-y-5">
                <div className="bg-white rounded-2xl p-6 shadow-[0_2px_16px_rgba(15,23,42,0.04)] text-center">
                    <div className="relative mx-auto w-20 h-20">
                        <div className="w-20 h-20 rounded-full bg-[#F1F5F9] overflow-hidden flex items-center justify-center border-2 border-white shadow-sm">
                            {photoPreview ? (
                                <img src={photoPreview} alt="Foto profil" className="w-full h-full object-cover" />
                            ) : (
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#334155" strokeWidth="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0116 0"/></svg>
                            )}
                        </div>
                        {photoPreview && (
                            <button type="button" onClick={askClearPhoto} aria-label="Hapus foto profil" className="absolute -top-1 -right-1 w-7 h-7 rounded-full bg-white border border-[#E2E8F0] shadow-sm flex items-center justify-center text-[#64748B] hover:bg-[#FEF2F2] hover:text-[#EF4444] hover:border-[#FECACA] transition">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                            </button>
                        )}
                    </div>
                    <h2 className="font-semibold text-[16px] text-[#0F172A] mt-3">{me0.nama}</h2>
                    <p className="text-sm text-[#64748B]">{me0.jabatan} • {me0.unit}</p>
                    <p className="text-xs text-[#94A3B8] mt-1">{me0.status} • {me0.gol} • NIK {String(me0.nik||'').slice(-4)} • NIP {String(me0.nip||'').slice(0,4) || '—'}</p>
                    <span className="inline-block mt-3 text-xs font-medium bg-[#F1F5F9] text-[#334155] px-3 py-1 rounded-full">{me0.region}</span>
                    {assigned0 && <p className="mt-3 text-xs bg-[#EFF6FF] text-[#1E3A8A] px-3 py-1.5 rounded-full inline-flex">{assigned0.nama_lokasi} • {assigned0.radius} m</p>}
                </div>

                {assigned0 && (
                    <div className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                        <p className="text-xs font-medium text-[#94A3B8]">Titik assigned</p>
                        <p className="text-sm font-semibold text-[#0F172A] mt-1">{assigned0.nama_lokasi}</p>
                        <p className="text-xs font-mono text-[#64748B]">{Number(assigned0.lat).toFixed(4)}, {Number(assigned0.lng).toFixed(4)} • {assigned0.radius} m</p>
                        {assigned0.address && <p className="text-xs text-[#94A3B8] mt-1">{assigned0.address}</p>}
                        <p className="text-xs text-[#94A3B8] mt-2">Absen valid hanya dalam radius titik penugasan</p>
                    </div>
                )}

                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] space-y-4">
                    <h3 className="font-medium text-sm text-[#0F172A]">Data pribadi</h3>
                    <p className="text-xs text-[#94A3B8]">NIK, NIP, golongan, dan unit tidak dapat diubah mandiri</p>
                    <div className="space-y-3">
                        <div>
                            <label htmlFor="phone" className="text-xs font-medium text-[#334155]">Nomor ponsel</label>
                            <input id="phone" value={phone} onChange={(e)=>setPhone(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3.5 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                        </div>
                        <div>
                            <label htmlFor="email" className="text-xs font-medium text-[#334155]">Email</label>
                            <input id="email" value={email} onChange={(e)=>setEmail(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3.5 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                        </div>
                    </div>
                    <input ref={fileRef} type="file" accept="image/*" onChange={handlePhoto} className="hidden" />
                    <button type="button" onClick={()=>fileRef.current?.click()} className="w-full rounded-xl bg-white border border-[#E2E8F0] py-2.5 text-sm font-medium text-[#334155] hover:bg-[#F8FAFC] transition">{photoPreview ? 'Ganti foto' : 'Upload foto'}</button>
                    <button type="button" onClick={handleSaveProfile} className="w-full rounded-xl bg-[#0F172A] text-white py-2.5 text-sm font-semibold hover:bg-[#1E3A8A] transition">Simpan</button>
                </div>

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
                            <input id="oldPwd" type="password" value={pwd.current_password} onChange={(e)=>setPwd({...pwd, current_password:e.target.value})} placeholder="••••••••" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3.5 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                        </div>
                        <div>
                            <label htmlFor="newPwd" className="text-xs font-medium text-[#334155]">Kata sandi baru</label>
                            <input id="newPwd" type="password" value={pwd.password} onChange={(e)=>setPwd({...pwd, password:e.target.value})} placeholder="Min. 8 karakter, huruf besar+kecil+angka" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3.5 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                            <p className="text-xs text-[#94A3B8] mt-1">Wajib mengandung huruf besar, huruf kecil, dan angka. Tidak boleh sama dengan NIK.</p>
                        </div>
                        <div>
                            <label htmlFor="confirmPwd" className="text-xs font-medium text-[#334155]">Konfirmasi baru</label>
                            <input id="confirmPwd" type="password" value={pwd.password_confirmation} onChange={(e)=>setPwd({...pwd, password_confirmation:e.target.value})} placeholder="Ulangi kata sandi baru" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3.5 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                        </div>
                        <button type="submit" className="w-full rounded-xl bg-[#0F172A] text-white py-2.5 text-sm font-semibold hover:bg-[#1E3A8A] transition">Simpan kata sandi</button>
                    </form>
                </div>

                <button type="button" onClick={handleLogout} className="w-full rounded-xl bg-[#FEF2F2] text-[#991B1B] py-3 text-sm font-semibold">Keluar</button>
            </div>
        </KaryawanLayout>
    );
}
