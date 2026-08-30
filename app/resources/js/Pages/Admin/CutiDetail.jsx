import AdminLayout from '@/Layouts/AdminLayout';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

const dataMap = {
    1: { nama: 'Andi Saputra', kantor: 'Gowa', jenis: 'Tahunan', tgl: '28–30 Agu 2026', alasan: 'Acara keluarga di Makassar — mohon izin 3 hari', dokumen: 'surat-keluarga.pdf', status: 'Menunggu', level: 2, foto: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&h=200&fit=crop&crop=face&auto=format' },
    2: { nama: 'Rudi Hartono', kantor: 'Bone', jenis: 'Sakit', tgl: '23 Agu 2026', alasan: 'Demam tinggi — surat dokter terlampir', dokumen: 'surat-dokter.jpg', status: 'Menunggu', level: 1, foto: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=200&h=200&fit=crop&crop=face&auto=format' },
    3: { nama: 'Siti Rahma', kantor: 'Makassar', jenis: 'Besar', tgl: '20 Agu 2026', alasan: 'Ibadah haji — 12 hari', dokumen: 'jadwal-haji.pdf', status: 'Disetujui', level: 3, foto: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=200&h=200&fit=crop&crop=face&auto=format' },
};

export default function CutiDetail() {
    const { props } = usePage();
    const id = props.id || 1;
    const data = dataMap[id] || dataMap[1];
    const [status, setStatus] = useState(data.status);
    const [showDoc, setShowDoc] = useState(false);

    return (
        <AdminLayout>
            <div className="space-y-5 max-w-[880px]">
                <Link href="/admin/cuti" className="inline-flex items-center gap-2 text-sm font-medium text-[#64748B] hover:text-[#0F172A]">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6"><path d="M15 18l-6-6 6-6"/></svg>
                    Kembali ke daftar cuti
                </Link>

                <div className="bg-white rounded-2xl p-6 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <div className="flex items-start gap-4">
                        <img src={data.foto} alt={data.nama} className="w-14 h-14 rounded-full object-cover" />
                        <div className="flex-1">
                            <h1 className="text-lg font-semibold tracking-tight text-[#0F172A]">{data.nama}</h1>
                            <p className="text-sm text-[#64748B]">{data.kantor} • {data.jenis} • {data.tgl}</p>
                            <span className={`inline-block mt-2 text-xs font-medium px-2.5 py-1 rounded-full ${status === 'Disetujui' ? 'bg-[#ECFDF5] text-[#065F46]' : status === 'Menunggu' ? 'bg-[#FFF7E6] text-[#92400E]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{status}</span>
                        </div>
                    </div>

                    <div className="mt-6 grid lg:grid-cols-2 gap-6">
                        <div className="space-y-4">
                            <div>
                                <p className="text-xs font-medium text-[#94A3B8]">Alasan</p>
                                <p className="text-sm text-[#0F172A] mt-1 leading-relaxed">{data.alasan}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-[#94A3B8]">Dokumen pendukung</p>
                                <button type="button" onClick={() => setShowDoc(true)} className="mt-2 inline-flex items-center gap-2 text-sm font-medium text-[#1E3A8A] bg-[#EFF6FF] px-3 py-2 rounded-xl hover:bg-[#DBEAFE]">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6"><path d="M14 2H7a2 2 0 00-2 2v16a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
                                    {data.dokumen} — Lihat
                                </button>
                            </div>
                        </div>
                        <div className="bg-[#F8FAFC] rounded-2xl p-4">
                            <p className="text-xs font-medium text-[#94A3B8]">Timeline berjenjang</p>
                            <div className="mt-3 space-y-3">
                                {[
                                    { l: 1, name: 'Atasan Langsung', state: data.level >= 1 ? 'approved' : 'pending' },
                                    { l: 2, name: 'Admin Wilayah Gowa', state: data.level >= 2 ? (status === 'Disetujui' && data.level === 2 ? 'current' : 'approved') : 'pending' },
                                    { l: 3, name: 'Kantor Pusat', state: data.level === 3 ? 'approved' : 'pending' },
                                ].map((s) => (
                                    <div key={s.l} className="flex items-center gap-3">
                                        <span className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold ${s.state === 'approved' ? 'bg-[#0F172A] text-white' : s.state === 'current' ? 'bg-[#FCB833] text-[#0F172A]' : 'bg-white border border-[#E2E8F0] text-[#94A3B8]'}`}>{s.l}</span>
                                        <div className="flex-1">
                                            <p className="text-sm font-medium text-[#0F172A]">{s.name}</p>
                                            <p className="text-xs text-[#64748B]">{s.state === 'approved' ? 'Disetujui' : s.state === 'current' ? 'Menunggu Anda' : 'Menunggu'}</p>
                                        </div>
                                        {s.state === 'approved' && <span className="w-2 h-2 rounded-full bg-[#10B981]"></span>}
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {status === 'Menunggu' && (
                        <div className="mt-6 flex gap-3">
                            <button type="button" onClick={() => setStatus('Ditolak')} className="flex-1 rounded-xl bg-[#F1F5F9] py-3 text-sm font-semibold text-[#64748B]">Tolak</button>
                            <button type="button" onClick={() => setStatus('Disetujui')} className="flex-1 rounded-xl bg-[#0F172A] text-white py-3 text-sm font-semibold hover:bg-[#1E3A8A]">Setujui</button>
                        </div>
                    )}
                    {status !== 'Menunggu' && (
                        <p className="mt-6 text-center text-sm font-medium text-[#64748B]">Status final: <span className={status === 'Disetujui' ? 'text-[#065F46]' : 'text-[#991B1B]'}>{status}</span> — frontend only</p>
                    )}
                </div>

                {showDoc && (
                    <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" onClick={() => setShowDoc(false)}>
                        <div className="bg-white rounded-2xl max-w-[640px] w-full overflow-hidden" onClick={(e) => e.stopPropagation()}>
                            <div className="px-5 py-4 flex items-center justify-between border-b">
                                <h3 className="font-medium text-sm text-[#0F172A]">{data.dokumen}</h3>
                                <button type="button" onClick={() => setShowDoc(false)} className="w-8 h-8 rounded-full bg-[#F1F5F9] flex items-center justify-center">✕</button>
                            </div>
                            <div className="p-6">
                                {data.dokumen.endsWith('.jpg') ? (
                                    <img src="https://images.unsplash.com/photo-1589829085413-56de8ae18c73?w=800&h=600&fit=crop&auto=format" alt="Dokumen" className="w-full rounded-xl object-cover" />
                                ) : (
                                    <div className="bg-[#F8FAFC] rounded-xl p-8 text-center">
                                        <p className="text-sm font-medium text-[#0F172A]">Preview PDF</p>
                                        <p className="text-xs text-[#64748B] mt-1">{data.dokumen} — dummy preview (frontend only)</p>
                                        <div className="mt-4 h-32 bg-white rounded-xl border-2 border-dashed border-[#E2E8F0] flex items-center justify-center">
                                            <span className="text-xs text-[#94A3B8]">PDF content preview</span>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
