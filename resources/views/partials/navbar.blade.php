@php
    $profile = \App\Models\SchoolProfile::first();
@endphp

<header id="navbar" class="bg-white/95 backdrop-blur-sm sticky top-0 z-50 transition-all duration-300">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 gap-4">

            {{-- LOGO + NAMA SEKOLAH --}}
            <a href="{{ url('/') }}" class="flex items-center gap-3 flex-shrink-0 min-w-0 group">
                @if($profile?->logo_path)
                    <img src="{{ asset('storage/'.$profile->logo_path) }}"
                         class="w-9 h-9 object-contain flex-shrink-0 transition-transform duration-200 group-hover:scale-105"
                         alt="Logo">
                @else
                    <div class="w-9 h-9 rounded-lg flex-shrink-0 flex items-center justify-center text-white text-sm font-bold transition-transform duration-200 group-hover:scale-105"
                         style="background: var(--primary);">
                        {{ strtoupper(substr($profile?->name ?? 'SK', 0, 2)) }}
                    </div>
                @endif
                <span class="hidden sm:block font-semibold text-slate-800 text-sm leading-tight truncate max-w-[200px] lg:max-w-xs">
                    {{ $profile?->name ?? 'SMPN 45 SIJUNJUNG' }}
                </span>
            </a>

            {{-- DESKTOP NAVIGATION --}}
            <nav class="hidden md:flex flex-1 justify-center items-center gap-1">
                @php
                    $navLinks = [
                        ['label' => 'Tentang Kami',         'url' => url('/'),                    'active' => Request::is('/')],
                        ['label' => 'Struktur Organisasi',  'url' => url('/struktur-organisasi'), 'active' => Request::is('struktur-organisasi')],
                        ['label' => 'Galeri Kegiatan',      'url' => url('/galeri-kegiatan'),     'active' => Request::is('galeri-kegiatan')],
                    ];
                @endphp

                @foreach($navLinks as $link)
                    <a href="{{ $link['url'] }}"
                       class="relative px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200
                              {{ $link['active']
                                  ? 'text-white'
                                  : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' }}"
                       @if($link['active']) style="background: var(--primary);" @endif>
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>

            {{-- CTA + HAMBURGER --}}
            <div class="flex items-center gap-2 flex-shrink-0">
                {{-- Login / Dashboard Button --}}
                @auth
                    <a href="{{ route('filament.admin.pages.dashboard') }}"
                       class="hidden sm:inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white rounded-lg transition-all duration-200 hover:opacity-90 active:scale-95 shadow-sm"
                       style="background: #16a34a;">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('filament.admin.auth.login') }}"
                       class="hidden sm:inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white rounded-lg transition-all duration-200 hover:opacity-90 active:scale-95 shadow-sm"
                       style="background: var(--primary);">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                        </svg>
                        Login
                    </a>
                @endauth

                {{-- Mobile Hamburger --}}
                <button id="mobileMenuBtn"
                        class="md:hidden p-2 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors duration-200"
                        aria-label="Buka menu">
                    <svg id="iconHamburger" class="w-5 h-5 transition-all duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg id="iconClose" class="w-5 h-5 hidden transition-all duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

        </div>
    </div>

    {{-- MOBILE MENU --}}
    <div id="mobileMenu"
         class="md:hidden overflow-hidden transition-all duration-300 ease-in-out max-h-0"
         style="border-top: 0px solid transparent;">
        <div class="px-4 py-3 space-y-1 border-t border-slate-100 bg-white">

            @foreach($navLinks as $link)
                <a href="{{ $link['url'] }}"
                   class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-all duration-200
                          {{ $link['active']
                              ? 'text-white'
                              : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' }}"
                   @if($link['active']) style="background: var(--primary);" @endif>

                    {{-- Icon per link --}}
                    @if($loop->index === 0)
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    @elseif($loop->index === 1)
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    @else
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    @endif

                    {{ $link['label'] }}
                </a>
            @endforeach

            {{-- Mobile Login/Dashboard --}}
            <div class="pt-2 border-t border-slate-100 mt-2">
                @auth
                    <a href="{{ route('filament.admin.pages.dashboard') }}"
                       class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-all duration-200"
                       style="background: #16a34a;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        Buka Dashboard Admin
                    </a>
                @else
                    <a href="{{ route('filament.admin.auth.login') }}"
                       class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-all duration-200"
                       style="background: var(--primary);">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                        </svg>
                        Login Admin
                    </a>
                @endauth
            </div>
        </div>
    </div>
</header>

{{-- Navbar JS: scroll shadow + mobile toggle --}}
<script>
    (function () {
        const navbar    = document.getElementById('navbar');
        const menuBtn   = document.getElementById('mobileMenuBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        const iconOpen  = document.getElementById('iconHamburger');
        const iconClose = document.getElementById('iconClose');
        let isOpen = false;

        // Scroll shadow
        window.addEventListener('scroll', () => {
            if (window.scrollY > 8) {
                navbar.style.boxShadow = '0 1px 0 rgba(0,0,0,0.06), 0 4px 16px rgba(0,0,0,0.07)';
            } else {
                navbar.style.boxShadow = 'none';
            }
        }, { passive: true });

        // Mobile toggle dengan animasi max-height
        menuBtn.addEventListener('click', () => {
            isOpen = !isOpen;

            if (isOpen) {
                mobileMenu.style.maxHeight = mobileMenu.scrollHeight + 'px';
                iconOpen.classList.add('hidden');
                iconClose.classList.remove('hidden');
            } else {
                mobileMenu.style.maxHeight = '0px';
                iconOpen.classList.remove('hidden');
                iconClose.classList.add('hidden');
            }
        });

        // Tutup mobile menu saat klik di luar
        document.addEventListener('click', (e) => {
            if (isOpen && !navbar.contains(e.target)) {
                isOpen = false;
                mobileMenu.style.maxHeight = '0px';
                iconOpen.classList.remove('hidden');
                iconClose.classList.add('hidden');
            }
        });
    })();
</script>