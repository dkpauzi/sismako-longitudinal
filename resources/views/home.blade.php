{{--
|--------------------------------------------------------------------------
| home.blade.php — Halaman Beranda (Landing Page)
|--------------------------------------------------------------------------
| Data yang dibutuhkan dari DashboardController:
|   - $profile   : App\Models\SchoolProfile (with 'school_missions')
|   - $posts     : Paginator dari App\Models\SchoolPost (published, paginate 6)
|   - $postsJson : Array sudah diproses di controller, aman untuk @json()
|
| Sections (urutan dari atas ke bawah):
|   1. Hero Banner
|   2. Stats Bar
|   3. Sambutan Kepala Sekolah
|   4. Sejarah Sekolah         (toggle: show_history)
|   5. Visi & Misi             (toggle: show_vision_mission)
|   6. Galeri Kegiatan Terbaru
|   7. Postingan & Pengumuman  (feed dari SchoolPost)
|   8. Peta Lokasi             (toggle: show_map)
|   9. Modal Baca Postingan    (JS-driven, tanpa pindah halaman)
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@php
    // Judul WAJIB memuat DUA ejaan sekaligus agar cocok dengan variasi pencarian
    // "SMP 45 Sijunjung" / "SMPN 45 Sijunjung" / "SMP Negeri 45 Sijunjung".
    // Alias dipilih otomatis sebagai lawan dari ejaan yang sudah dipakai di
    // profil sekolah, supaya tidak pernah tertulis ganda (mis. "SMPN … (SMPN …)").
    $namaSekolah = $profile?->name ?? 'SMP Negeri 45 Sijunjung';
    $aliasSekolah = str_contains(strtolower($namaSekolah), 'negeri')
        ? 'SMPN 45 Sijunjung'
        : 'SMP Negeri 45 Sijunjung';
    $judulSeo = $namaSekolah . ' (' . $aliasSekolah . ')';
@endphp

@section('title', $judulSeo . ' — Website Resmi')

@section('meta_description',
    'Website resmi ' . $judulSeo . ', Kabupaten Sijunjung, Sumatera Barat. '
    . 'Profil sekolah, visi & misi, berita, pengumuman, galeri kegiatan, dan sistem informasi akademik.'
)

@section('content')

{{-- ============================================================
     1. HERO BANNER
     Menampilkan banner dari DB. Jika belum diset, fallback ke
     gradient warna primer. Overlay gelap menjamin teks terbaca.
     ============================================================ --}}
<section class="relative overflow-hidden">

    @if($profile?->banner_image_path)
        <img src="{{ asset('storage/'.$profile->banner_image_path) }}"
             class="w-full h-[420px] sm:h-[500px] object-cover"
             alt="Banner {{ $profile->name }}">
        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/30 to-transparent"></div>
    @else
        <div class="w-full h-[420px] sm:h-[500px]"
             style="background: linear-gradient(135deg, var(--primary) 0%, color-mix(in srgb, var(--primary) 60%, #0f172a) 100%);">
        </div>
    @endif

    <div class="absolute inset-0 flex flex-col justify-end">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-10 sm:pb-14 w-full">

            @if($profile?->accreditation)
                <span class="inline-block px-3 py-1 text-xs font-semibold text-white rounded-full mb-4 animate-fade-up"
                      style="background: rgba(255,255,255,0.2); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.3);">
                    Akreditasi {{ $profile->accreditation }}
                </span>
            @endif

            <h1 class="text-2xl sm:text-4xl font-bold text-white leading-tight mb-3 animate-fade-up delay-100">
                {{ $profile?->name ?? 'Selamat Datang di Website Sekolah' }}
            </h1>

            @if($profile?->vision)
                <p class="text-white/80 text-sm sm:text-base max-w-xl font-serif italic animate-fade-up delay-200 leading-relaxed">
                    "{{ Str::limit($profile->vision, 120) }}"
                </p>
            @endif

            <div class="flex gap-3 mt-6 animate-fade-up delay-300 flex-wrap">
                <a href="/galeri-kegiatan"
                   class="px-5 py-2.5 text-sm font-semibold rounded-lg text-white transition-all duration-200 hover:opacity-90 active:scale-95"
                   style="background: var(--primary);">
                    Lihat Galeri Kegiatan
                </a>
                <a href="/struktur-organisasi"
                   class="px-5 py-2.5 text-sm font-semibold rounded-lg transition-all duration-200 hover:bg-white/20 active:scale-95"
                   style="background: rgba(255,255,255,0.15); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.4); color: white;">
                    Struktur Organisasi
                </a>
            </div>
        </div>
    </div>
</section>

{{-- ============================================================
     2. STATS BAR
     Statistik ringkas: NPSN, jumlah kegiatan, tenaga pendidik,
     dan kurikulum. Query ringan langsung dari model.
     ============================================================ --}}
<section style="background: var(--primary);">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-white/20">

            @if($profile?->npsn)
            <div class="py-5 px-4 text-center">
                <p class="text-xs text-white/60 font-medium mb-1 uppercase tracking-wider">NPSN</p>
                <p class="text-white font-bold text-lg">{{ $profile->npsn }}</p>
            </div>
            @endif

            @php
                $activityCount = \App\Models\SchoolActivity::where('is_published', true)->count();
                $orgCount      = \App\Models\SchoolOrganizationStructure::count();
            @endphp

            <div class="py-5 px-4 text-center">
                <p class="text-xs text-white/60 font-medium mb-1 uppercase tracking-wider">Kegiatan</p>
                <p class="text-white font-bold text-lg">{{ $activityCount }}+</p>
            </div>
            <div class="py-5 px-4 text-center">
                <p class="text-xs text-white/60 font-medium mb-1 uppercase tracking-wider">Tenaga Pendidik</p>
                <p class="text-white font-bold text-lg">{{ $orgCount }}</p>
            </div>
            <div class="py-5 px-4 text-center">
                <p class="text-xs text-white/60 font-medium mb-1 uppercase tracking-wider">Kurikulum</p>
                <p class="text-white font-bold text-sm">Merdeka</p>
            </div>

        </div>
    </div>
</section>

{{-- ============================================================
     3. SAMBUTAN KEPALA SEKOLAH
     Ditampilkan jika principal_name DAN welcome_message terisi.
     Layout: foto kepsek (kiri) + kutipan sambutan (kanan).
     ============================================================ --}}
@if($profile?->principal_name && $profile?->welcome_message)
<section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="grid md:grid-cols-3">

            <div class="md:col-span-1 p-8 flex flex-col items-center justify-center text-center border-b md:border-b-0 md:border-r border-slate-100"
                 style="background: var(--primary-xlight);">
                @if($profile->principal_photo_path)
                    <img src="{{ asset('storage/'.$profile->principal_photo_path) }}"
                         class="w-28 h-28 rounded-full object-cover border-4 border-white shadow-md mb-4"
                         alt="{{ $profile->principal_name }}">
                @else
                    <div class="w-28 h-28 rounded-full border-4 border-white shadow-md mb-4 flex items-center justify-center text-3xl font-bold text-white"
                         style="background: var(--primary);">
                        {{ strtoupper(substr($profile->principal_name, 0, 1)) }}
                    </div>
                @endif
                <p class="font-bold text-slate-800 text-base">{{ $profile->principal_name }}</p>
                <p class="text-sm mt-1" style="color: var(--primary);">Kepala Sekolah</p>
            </div>

            <div class="md:col-span-2 p-8 md:p-10">
                <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color: var(--primary);">Kata Sambutan</p>
                <blockquote class="font-serif italic text-slate-600 text-base sm:text-lg leading-relaxed border-l-4 pl-5"
                            style="border-color: var(--primary);">
                    "{{ $profile->welcome_message }}"
                </blockquote>
            </div>

        </div>
    </div>
</section>
@endif

{{-- ============================================================
     4. SEJARAH SEKOLAH
     Dikontrol toggle `show_history` dari SchoolProfile.
     Konten dari RichEditor — di-render dengan {!! !!}.
     ============================================================ --}}
@if($profile?->show_history && $profile?->history)
<section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 md:p-10">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-1 h-8 rounded-full" style="background: var(--primary);"></div>
            <h2 class="text-xl font-bold text-slate-800">Sejarah Sekolah</h2>
        </div>
        <div class="prose-school max-w-none">
            {!! $profile->history !!}
        </div>
    </div>
</section>
@endif

{{-- ============================================================
     5. VISI & MISI
     Dikontrol toggle `show_vision_mission` dari SchoolProfile.
     Misi diambil dari relasi school_missions (eager loaded).
     ============================================================ --}}
@if($profile?->show_vision_mission)
<section class="py-14" style="background: var(--primary-xlight);">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="text-center mb-10">
            <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color: var(--primary);">Landasan</p>
            <h2 class="text-2xl font-bold text-slate-800">Visi & Misi</h2>
        </div>

        <div class="grid md:grid-cols-2 gap-6">

            {{-- VISI --}}
            <div class="bg-white rounded-2xl p-8 shadow-sm border border-slate-100 flex flex-col">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-5 flex-shrink-0"
                     style="background: var(--primary-light);">
                    <svg class="w-5 h-5" style="color: var(--primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </div>
                <h3 class="font-bold text-slate-800 text-lg mb-4">Visi</h3>
                <p class="font-serif italic text-slate-600 leading-relaxed text-base flex-grow">
                    "{{ $profile?->vision ?? 'Visi belum diatur.' }}"
                </p>
            </div>

            {{-- MISI --}}
            <div class="bg-white rounded-2xl p-8 shadow-sm border border-slate-100">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-5"
                     style="background: var(--primary-light);">
                    <svg class="w-5 h-5" style="color: var(--primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
                <h3 class="font-bold text-slate-800 text-lg mb-4">Misi</h3>
                <ul class="space-y-3">
                    @forelse ($profile?->school_missions?->sortBy('order') ?? [] as $index => $misi)
                        <li class="flex gap-3 items-start">
                            <span class="flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white mt-0.5"
                                  style="background: var(--primary);">{{ $index + 1 }}</span>
                            <span class="text-slate-600 text-sm leading-relaxed">{{ $misi->content }}</span>
                        </li>
                    @empty
                        <li class="text-slate-400 text-sm">Misi belum diinput.</li>
                    @endforelse
                </ul>
            </div>

        </div>
    </div>
</section>
@endif

{{-- ============================================================
     6. GALERI KEGIATAN TERBARU
     Menampilkan 3 foto kegiatan terbaru (berdasarkan kolom date).
     Link ke halaman /galeri-kegiatan untuk lihat semua.
     ============================================================ --}}
@php
    $recentActivities = \App\Models\SchoolActivity::where('is_published', true)
        ->latest('date')
        ->take(3)
        ->get();
@endphp

@if($recentActivities->isNotEmpty())
<section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-14">

    <div class="flex items-end justify-between mb-8">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: var(--primary);">Dokumentasi</p>
            <h2 class="text-2xl font-bold text-slate-800">Galeri Kegiatan Terbaru</h2>
        </div>
        <a href="/galeri-kegiatan"
           class="text-sm font-semibold hover:underline transition-colors flex-shrink-0 ml-4"
           style="color: var(--primary);">
            Lihat Semua →
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        @foreach($recentActivities as $item)
        <a href="/galeri-kegiatan"
           class="group block rounded-2xl overflow-hidden bg-white shadow-sm border border-slate-100 hover:shadow-md transition-all duration-300 hover:-translate-y-1">
            <div class="overflow-hidden h-48">
                <img src="{{ asset('storage/'.$item->image_path) }}"
                     alt="{{ $item->title }}"
                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
            </div>
            <div class="p-4">
                <p class="text-xs text-slate-400 mb-1">
                    {{ \Carbon\Carbon::parse($item->date)->translatedFormat('d F Y') }}
                </p>
                <h3 class="font-semibold text-slate-800 text-sm leading-snug line-clamp-2">
                    {{ $item->title }}
                </h3>
            </div>
        </a>
        @endforeach
    </div>

</section>
@endif

{{-- ============================================================
     7. POSTINGAN & PENGUMUMAN
     Feed konten dari tabel school_posts (model SchoolPost).
     Data $posts   : LengthAwarePaginator dari controller.
     Data $postsJson: Array bersih, disiapkan di controller
                      (BUKAN di @json() langsung) untuk menghindari
                      error "Unclosed '['" dari Blade parser.
     Filter kategori via query string: ?kategori=Pengumuman
     Modal baca selengkapnya: section 9 di bawah.
     ============================================================ --}}
@if($posts->isNotEmpty())
<section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-14">

    {{-- Header + Filter Kategori --}}
    <div class="flex items-end justify-between mb-8">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color: var(--primary);">Dari Kami</p>
            <h2 class="text-2xl font-bold text-slate-800">Postingan & Pengumuman</h2>
        </div>

        @php
            $categories = ['Semua', 'Pengumuman', 'Prestasi', 'Kegiatan', 'Berita'];
            $activeKat  = request('kategori');
        @endphp
        <div class="hidden sm:flex items-center gap-2 flex-wrap justify-end">
            @foreach($categories as $cat)
                @php $isActive = ($cat === 'Semua' && !$activeKat) || $activeKat === $cat; @endphp
                <a href="{{ $cat === 'Semua' ? url('/') : url('/').'?kategori='.$cat }}"
                   class="px-3 py-1.5 rounded-full text-xs font-medium border transition-all duration-200
                          {{ $isActive
                              ? 'text-white border-transparent'
                              : 'text-slate-500 border-slate-200 hover:border-slate-300 bg-white' }}"
                   @if($isActive) style="background: var(--primary); border-color: var(--primary);" @endif>
                    {{ $cat }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- Grid Postingan --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($posts as $post)
        @php
            // Tentukan sumber cover: prioritas cover_image_path, fallback gallery_images[0]
            $coverSrc = $post->cover_image_path
                ? asset('storage/'.$post->cover_image_path)
                : (($post->gallery_images && count($post->gallery_images) > 0)
                    ? asset('storage/'.$post->gallery_images[0])
                    : null);

            $badgeColors = [
                'Pengumuman' => 'bg-amber-50 text-amber-700',
                'Prestasi'   => 'bg-emerald-50 text-emerald-700',
                'Kegiatan'   => 'bg-sky-50 text-sky-700',
                'Berita'     => 'bg-violet-50 text-violet-700',
                'Umum'       => 'bg-slate-100 text-slate-600',
            ];
        @endphp

        <article class="bg-white rounded-2xl overflow-hidden shadow-sm border border-slate-100
                        hover:shadow-md hover:-translate-y-1 transition-all duration-300 flex flex-col">

            {{-- Cover Image --}}
            @if($coverSrc)
                <div class="overflow-hidden h-44 flex-shrink-0">
                    <img src="{{ $coverSrc }}" alt="{{ $post->title }}"
                         class="w-full h-full object-cover hover:scale-105 transition-transform duration-500"
                         loading="lazy">
                </div>
            @else
                {{-- Placeholder jika tidak ada gambar --}}
                <div class="h-44 flex-shrink-0 flex items-center justify-center"
                     style="background: var(--primary-xlight);">
                    <svg class="w-10 h-10 opacity-25" style="color: var(--primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                    </svg>
                </div>
            @endif

            {{-- Konten Card --}}
            <div class="p-5 flex flex-col flex-grow">

                {{-- Badge Kategori + Tanggal --}}
                <div class="flex items-center justify-between gap-2 mb-3">
                    <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full {{ $badgeColors[$post->category] ?? 'bg-slate-100 text-slate-600' }}">
                        {{ $post->category }}
                    </span>
                    <time class="text-xs text-slate-400 flex-shrink-0">
                        {{ $post->published_at?->translatedFormat('d M Y') ?? $post->created_at->translatedFormat('d M Y') }}
                    </time>
                </div>

                {{-- Judul --}}
                <h3 class="font-bold text-slate-800 text-base leading-snug mb-2 line-clamp-2">
                    {{ $post->title }}
                </h3>

                {{-- Excerpt: strip HTML dari body, potong 160 karakter --}}
                <p class="text-sm text-slate-500 leading-relaxed line-clamp-3 flex-grow">
                    {{ Str::limit(strip_tags($post->body), 160) }}
                </p>

                {{-- Thumbnail galeri mini (maks 4, sisanya "+N") --}}
                @if($post->gallery_images && count($post->gallery_images) > 1)
                <div class="flex gap-1.5 mt-3">
                    @foreach(array_slice($post->gallery_images, 0, 4) as $i => $img)
                    <div class="relative rounded-lg overflow-hidden w-11 h-11 flex-shrink-0 bg-slate-100">
                        <img src="{{ asset('storage/'.$img) }}"
                             class="w-full h-full object-cover" loading="lazy">
                        @if($i === 3 && count($post->gallery_images) > 4)
                            <div class="absolute inset-0 bg-black/50 flex items-center justify-center">
                                <span class="text-white text-xs font-bold">+{{ count($post->gallery_images) - 4 }}</span>
                            </div>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- Tombol buka modal --}}
                <div class="mt-4 pt-4 border-t border-slate-100">
                    <button onclick="openPost({{ $post->id }})"
                            class="text-xs font-semibold transition-colors hover:underline"
                            style="color: var(--primary);">
                        Baca Selengkapnya →
                    </button>
                </div>

            </div>
        </article>
        @endforeach
    </div>

    {{-- Pagination bawaan Laravel --}}
    @if($posts->hasPages())
        <div class="mt-10 flex justify-center">
            {{ $posts->links() }}
        </div>
    @endif

</section>
@endif

{{-- ============================================================
     8. PETA LOKASI
     Dikontrol toggle `show_map` dari SchoolProfile.
     Embed Google Maps dari kolom google_maps_embed (raw iframe).
     Background gelap (slate-800) agar iframe tidak terasa "melayang".
     ============================================================ --}}
@if($profile?->show_map)
<section class="py-14 bg-slate-800">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="text-center mb-8">
            <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color: var(--primary);">Kunjungi Kami</p>
            <h2 class="text-2xl font-bold text-white">Lokasi Sekolah</h2>
            @if($profile?->address)
                <p class="text-slate-400 text-sm mt-2 max-w-md mx-auto">{{ $profile->address }}</p>
            @endif
        </div>

        <div class="rounded-2xl overflow-hidden border border-slate-700">
            @if($profile?->google_maps_embed)
                <div class="w-full [&>iframe]:w-full [&>iframe]:h-[380px] [&>iframe]:border-0">
                    {!! $profile->google_maps_embed !!}
                </div>
            @else
                <div class="w-full h-[380px] bg-slate-700 flex flex-col items-center justify-center text-slate-500 gap-3">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <p class="text-sm">Peta belum diatur oleh admin</p>
                </div>
            @endif
        </div>

    </div>
</section>
@endif

{{-- ============================================================
     9. MODAL BACA POSTINGAN
     Dibuka via openPost(id) — JS mengisi konten dari $postsJson.
     Ditutup via: tombol ×, klik backdrop, atau tekan Escape.
     PENTING: $postsJson disiapkan di DashboardController, bukan
     di sini, untuk menghindari error Blade parser pada @json()
     dengan arrow function fn($p) => [...].
     ============================================================ --}}
<div id="postModal"
     class="fixed inset-0 z-[100] hidden items-center justify-center p-4"
     style="background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);">

    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto relative">

        {{-- Tombol Tutup --}}
        <button onclick="closePost()"
                class="absolute top-4 right-4 z-10 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200
                       flex items-center justify-center transition-colors">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>

        {{-- Cover Modal --}}
        <div id="modalCover" class="hidden overflow-hidden h-56 rounded-t-2xl">
            <img id="modalCoverImg" src="" alt="" class="w-full h-full object-cover">
        </div>

        <div class="p-6 sm:p-8">
            <div class="flex items-center gap-3 mb-4">
                <span id="modalBadge" class="px-2.5 py-0.5 text-xs font-semibold rounded-full"></span>
                <time id="modalDate" class="text-xs text-slate-400"></time>
            </div>
            <h2 id="modalTitle" class="text-xl font-bold text-slate-800 leading-snug mb-5"></h2>
            <div id="modalBody" class="prose-school text-sm text-slate-600 leading-relaxed max-w-none"></div>

            {{-- Galeri foto di dalam modal --}}
            <div id="modalGallery" class="hidden mt-6 pt-6 border-t border-slate-100">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Foto Kegiatan</p>
                <div id="modalGalleryGrid" class="grid grid-cols-2 sm:grid-cols-3 gap-2"></div>
            </div>
        </div>

    </div>
</div>

@endsection

{{-- ============================================================
     SCRIPTS
     postsJson: di-encode dari $postsJson yang disiapkan controller.
     Tidak menggunakan transformasi map/arrow-function di dalam
     @json() karena menyebabkan error "Unclosed '['" di Blade.
     ============================================================ --}}
@push('scripts')
<script>
    // Data posts sudah diproses bersih dari DashboardController
    const postsData = @json($postsJson);

    // Peta warna badge per kategori (harus cocok dengan Tailwind CSS)
    const badgeMap = {
        'Pengumuman': 'bg-amber-50 text-amber-700',
        'Prestasi'  : 'bg-emerald-50 text-emerald-700',
        'Kegiatan'  : 'bg-sky-50 text-sky-700',
        'Berita'    : 'bg-violet-50 text-violet-700',
        'Umum'      : 'bg-slate-100 text-slate-600',
    };

    // Buka modal dan isi dengan data post yang dipilih
    function openPost(id) {
        const p        = postsData[id];
        const modal    = document.getElementById('postModal');
        const cover    = document.getElementById('modalCover');
        const coverImg = document.getElementById('modalCoverImg');

        // Tentukan cover: prioritas cover, fallback gallery[0]
        const coverSrc = p.cover || (p.gallery.length > 0 ? p.gallery[0] : null);
        if (coverSrc) {
            coverImg.src = coverSrc;
            cover.classList.remove('hidden');
        } else {
            cover.classList.add('hidden');
        }

        // Isi badge kategori
        const badge = document.getElementById('modalBadge');
        badge.textContent = p.category;
        badge.className   = 'px-2.5 py-0.5 text-xs font-semibold rounded-full '
                          + (badgeMap[p.category] || 'bg-slate-100 text-slate-600');

        // Isi konten teks
        document.getElementById('modalDate').textContent  = p.date;
        document.getElementById('modalTitle').textContent = p.title;
        document.getElementById('modalBody').innerHTML    = p.body; // HTML dari RichEditor

        // Isi galeri: skip index 0 jika sudah dipakai sebagai cover
        const galleryGrid    = document.getElementById('modalGalleryGrid');
        const gallerySection = document.getElementById('modalGallery');
        const extras         = p.gallery.slice(p.cover ? 0 : 1);

        if (extras.length > 1) {
            galleryGrid.innerHTML = extras.map(src =>
                `<div class="rounded-lg overflow-hidden aspect-square bg-slate-100">
                    <img src="${src}" class="w-full h-full object-cover" loading="lazy">
                 </div>`
            ).join('');
            gallerySection.classList.remove('hidden');
        } else {
            gallerySection.classList.add('hidden');
        }

        // Tampilkan modal, kunci scroll body
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    // Tutup modal dan kembalikan scroll
    function closePost() {
        const modal = document.getElementById('postModal');
        modal.classList.replace('flex', 'hidden');
        document.body.style.overflow = '';
    }

    // Tutup saat klik di luar konten modal (backdrop)
    document.getElementById('postModal').addEventListener('click', function (e) {
        if (e.target === this) closePost();
    });

    // Tutup dengan tombol Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closePost();
    });
</script>
@endpush