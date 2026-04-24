@extends('layouts.app')

@php $schoolName = $schoolProfile?->name ?? 'SIAKAD'; @endphp
@section('title', 'Struktur Organisasi | ' . $schoolName)

@section('content')

{{-- Page Header --}}
<div class="py-10" style="background: linear-gradient(135deg, var(--primary) 0%, color-mix(in srgb, var(--primary) 70%, #0f172a) 100%);">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-white/60 text-xs font-medium mb-1">
            <a href="{{ route('home') }}" class="hover:text-white transition-colors">Beranda</a>
            <span class="mx-2">›</span> Struktur Organisasi
        </p>
        <h1 class="text-2xl sm:text-3xl font-bold text-white">Struktur Organisasi</h1>
        <p class="text-white/70 text-sm mt-1">Tenaga pendidik dan kependidikan {{ $schoolName }}</p>
    </div>
</div>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12">

    @forelse($structures as $item)
        @if($loop->first)
        {{-- Kepala Sekolah (Urutan 1) tampil lebih besar di tengah --}}
        <div class="flex justify-center mb-10">
            <div class="bg-white rounded-2xl p-8 shadow-sm border border-slate-100 text-center w-full sm:w-72 relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1" style="background: var(--primary);"></div>
                @if($item->photo_path)
                    <img src="{{ asset('storage/'.$item->photo_path) }}"
                         class="w-28 h-28 rounded-full mx-auto mb-4 object-cover border-4 border-white shadow-md">
                @else
                    <div class="w-28 h-28 rounded-full mx-auto mb-4 flex items-center justify-center text-3xl font-bold text-white border-4 border-white shadow-md"
                         style="background: var(--primary);">
                        {{ strtoupper(substr($item->name, 0, 1)) }}
                    </div>
                @endif
                <h3 class="font-bold text-slate-800 text-lg">{{ $item->name }}</h3>
                <span class="inline-block mt-2 px-3 py-1 text-xs font-semibold rounded-full text-white"
                      style="background: var(--primary);">
                    {{ $item->position }}
                </span>
            </div>
        </div>

        {{-- Connector line jika ada member lain --}}
        @if(!$loop->last)
        <div class="flex justify-center mb-6">
            <div class="w-px h-8 bg-slate-200"></div>
        </div>
        @endif

        @else
        {{-- Grid untuk anggota lainnya --}}
        @if($loop->index === 1)
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
        @endif

            <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-100 text-center hover:-translate-y-1 transition-all duration-300 hover:shadow-md">
                @if($item->photo_path)
                    <img src="{{ asset('storage/'.$item->photo_path) }}"
                         class="w-20 h-20 rounded-full mx-auto mb-3 object-cover border-3 border-slate-100 shadow-sm">
                @else
                    <div class="w-20 h-20 rounded-full mx-auto mb-3 flex items-center justify-center text-xl font-bold text-white"
                         style="background: color-mix(in srgb, var(--primary) 80%, white);">
                        {{ strtoupper(substr($item->name, 0, 1)) }}
                    </div>
                @endif
                <h3 class="font-semibold text-slate-800 text-sm leading-tight mb-1">{{ $item->name }}</h3>
                <p class="text-xs font-medium" style="color: var(--primary);">{{ $item->position }}</p>
            </div>

        @if($loop->last)
        </div>
        @endif
        @endif

    @empty
        <div class="text-center py-20 text-slate-400">
            <p>Struktur organisasi belum diatur.</p>
        </div>
    @endforelse

</div>
@endsection