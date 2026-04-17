@extends('layouts.app')

@php $schoolName = \App\Models\SchoolProfile::first()?->name ?? 'SIAKAD'; @endphp
@section('title', 'Galeri Kegiatan | ' . $schoolName)

@section('content')

{{-- Page Header --}}
<div class="py-10" style="background: linear-gradient(135deg, var(--primary) 0%, color-mix(in srgb, var(--primary) 70%, #0f172a) 100%);">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-white/60 text-xs font-medium mb-1">
            <a href="{{ route('home') }}" class="hover:text-white transition-colors">Beranda</a>
            <span class="mx-2">›</span> Galeri
        </p>
        <h1 class="text-2xl sm:text-3xl font-bold text-white">Galeri Kegiatan</h1>
        <p class="text-white/70 text-sm mt-1">Dokumentasi aktivitas dan kegiatan sekolah</p>
    </div>
</div>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    @if($activities->isEmpty())
        <div class="text-center py-20 text-slate-400">
            <svg class="w-12 h-12 mx-auto mb-4 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <p class="font-medium">Belum ada foto kegiatan</p>
        </div>
    @else

    {{-- Masonry-style responsive grid --}}
    <div class="columns-1 sm:columns-2 md:columns-3 gap-5 space-y-5">
        @foreach($activities as $item)
        <div class="break-inside-avoid group rounded-2xl overflow-hidden bg-white shadow-sm border border-slate-100 hover:shadow-md transition-all duration-300">
            <div class="overflow-hidden">
                <img src="{{ asset('storage/'.$item->image_path) }}"
                     alt="{{ $item->title }}"
                     class="w-full object-cover group-hover:scale-105 transition-transform duration-500"
                     loading="lazy">
            </div>
            <div class="p-4">
                <p class="text-xs font-medium mb-1" style="color: var(--primary);">
                    {{ \Carbon\Carbon::parse($item->date)->translatedFormat('d F Y') }}
                </p>
                <h3 class="font-semibold text-slate-800 text-sm leading-snug">{{ $item->title }}</h3>
                @if($item->description)
                    <p class="text-xs text-slate-500 mt-1 line-clamp-2">{{ $item->description }}</p>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif

</div>
@endsection