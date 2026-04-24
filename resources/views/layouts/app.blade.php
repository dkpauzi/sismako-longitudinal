<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php
        // $schoolProfile disediakan oleh View Composer di AppServiceProvider
        $primaryColor = $schoolProfile?->primary_color ?? '#1a56db';
        $schoolName   = $schoolProfile?->name ?? 'Sistem Informasi Akademik';
    @endphp

    <title>@yield('title', $schoolName)</title>

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