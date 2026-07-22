<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php
        // $schoolProfile disediakan oleh View Composer di AppServiceProvider
        $primaryColor = $schoolProfile?->primary_color ?? '#1a56db';
        $schoolName   = $schoolProfile?->name ?? 'SMP Negeri 45 Sijunjung';

        // ── SEO ────────────────────────────────────────────────────────────
        // Varian ejaan yang benar-benar diketik orang di Google. Dipakai pada
        // alternateName (structured data) & keywords agar ketiganya cocok.
        $seoAltNames = ['SMPN 45 Sijunjung', 'SMP 45 Sijunjung', 'SMP Negeri 45 Sijunjung'];

        $seoAddress = $schoolProfile?->address ? trim(strip_tags($schoolProfile->address)) : null;

        $seoDescription = trim($__env->yieldContent('meta_description')) ?: trim(
            $schoolName . ' — website resmi. Profil sekolah, visi & misi, berita, pengumuman, '
            . 'galeri kegiatan, serta sistem informasi akademik siswa.'
            . ($seoAddress ? ' Alamat: ' . $seoAddress . '.' : '')
        );

        $seoImage = $schoolProfile?->banner_image_path
            ? asset('storage/' . $schoolProfile->banner_image_path)
            : ($schoolProfile?->logo_path ? asset('storage/' . $schoolProfile->logo_path) : asset('favicon.ico'));

        $seoSameAs = array_values(array_filter([
            $schoolProfile?->facebook_url,
            $schoolProfile?->instagram_url,
            $schoolProfile?->youtube_url,
        ]));

        $seoJsonLd = array_filter([
            '@context'      => 'https://schema.org',
            '@type'         => 'School',
            'name'          => $schoolName,
            'alternateName' => $seoAltNames,
            'url'           => url('/'),
            'image'         => $seoImage,
            'logo'          => $schoolProfile?->logo_path ? asset('storage/' . $schoolProfile->logo_path) : null,
            'description'   => $seoDescription,
            'telephone'     => $schoolProfile?->phone,
            'email'         => $schoolProfile?->email,
            'address'       => array_filter([
                '@type'           => 'PostalAddress',
                'streetAddress'   => $seoAddress,
                'postalCode'      => $schoolProfile?->postal_code,
                'addressLocality' => 'Sijunjung',
                'addressRegion'   => 'Sumatera Barat',
                'addressCountry'  => 'ID',
            ]),
            'sameAs'        => $seoSameAs ?: null,
        ]);
    @endphp

    <title>@yield('title', $schoolName)</title>

    {{-- ── SEO dasar ──────────────────────────────────────────────────── --}}
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="keywords" content="{{ implode(', ', $seoAltNames) }}, SMP Sijunjung, sekolah Sijunjung, Sumatera Barat">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="author" content="{{ $schoolName }}">
    <meta name="theme-color" content="{{ $primaryColor }}">
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- ── Open Graph (WhatsApp / Facebook) ───────────────────────────── --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $schoolName }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:title" content="@yield('title', $schoolName)">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ $seoImage }}">

    {{-- ── Twitter / X ────────────────────────────────────────────────── --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', $schoolName)">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $seoImage }}">

    {{-- ── Structured data: Google mengenali entitas "Sekolah" ────────── --}}
    <script type="application/ld+json">{!! json_encode($seoJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Lora:ital@0;1&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary:       {{ $primaryColor }};
            --primary-dark:  color-mix(in srgb, {{ $primaryColor }} 80%, black);
            --primary-light: color-mix(in srgb, {{ $primaryColor }} 20%, white);
            --primary-xlight:color-mix(in srgb, {{ $primaryColor }} 8%, white);
        }
        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-serif  { font-family: 'Lora', serif; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-up  { animation: fadeUp 0.55s ease both; }
        .delay-100 { animation-delay: 0.10s; }
        .delay-200 { animation-delay: 0.20s; }
        .delay-300 { animation-delay: 0.30s; }

        .prose-school p          { line-height: 1.85; color: #475569; }
        .prose-school h2,
        .prose-school h3         { color: #1e293b; margin-top: 1.5rem; }
        .prose-school ul         { list-style: disc; padding-left: 1.5rem; color: #475569; }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-slate-50 flex flex-col min-h-screen text-slate-800">

    @include('partials.navbar')  {{-- ← Navbar tetap di partial --}}

    <main class="flex-grow w-full">
        @yield('content')
    </main>

    {{-- FOOTER --}}
    <footer class="bg-slate-900 text-slate-400 w-full mt-auto">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">

                <div class="md:col-span-2">
                    <div class="flex items-center gap-3 mb-3">
                        @if($schoolProfile?->logo_path)
                            <img src="{{ asset('storage/'.$schoolProfile->logo_path) }}" class="h-8 w-8 object-contain" alt="Logo">
                        @endif
                        <span class="text-white font-semibold">{{ $schoolName }}</span>
                    </div>
                    @if($schoolProfile?->address)
                        <p class="text-sm leading-relaxed max-w-sm">{{ $schoolProfile->address }}</p>
                    @endif
                    @if($schoolProfile?->phone)
                        <p class="text-sm mt-2">📞 {{ $schoolProfile->phone }}</p>
                    @endif
                    @if($schoolProfile?->email)
                        <p class="text-sm">✉️ {{ $schoolProfile->email }}</p>
                    @endif
                </div>

                <div>
                    <p class="text-white text-sm font-semibold mb-3">Ikuti Kami</p>
                    <div class="flex flex-col gap-2 text-sm">
                        @if($schoolProfile?->facebook_url)
                            <a href="{{ $schoolProfile->facebook_url }}" target="_blank" class="hover:text-white transition-colors">→ Facebook</a>
                        @endif
                        @if($schoolProfile?->instagram_url)
                            <a href="{{ $schoolProfile->instagram_url }}" target="_blank" class="hover:text-white transition-colors">→ Instagram</a>
                        @endif
                        @if($schoolProfile?->youtube_url)
                            <a href="{{ $schoolProfile->youtube_url }}" target="_blank" class="hover:text-white transition-colors">→ YouTube</a>
                        @endif
                        @if(!$schoolProfile?->facebook_url && !$schoolProfile?->instagram_url && !$schoolProfile?->youtube_url)
                            <p class="text-slate-600 text-sm">Belum ada sosial media.</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-800 pt-6 text-center text-xs text-slate-600">
                &copy; {{ date('Y') }} {{ $schoolName }}.
                Dikembangkan untuk mendukung Kurikulum Merdeka.
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>