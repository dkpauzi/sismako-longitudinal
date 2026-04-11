@php
    $profile = \App\Models\SchoolProfile::first();
@endphp

<nav class="navbar navbar-expand-lg navbar-light bg-white">
    <div class="container position-relative">

        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="{{ url('/') }}">
            @if($profile && $profile->logo_path)
                <img src="{{ asset('storage/'.$profile->logo_path) }}" width="40" height="40">
            @endif

            {{ $profile->name ?? 'SMPN 45 SIJUNJUNG' }}
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav w-100 align-items-lg-center">

                <div class="mx-lg-auto d-lg-flex gap-4">
                    <li class="nav-item">
                        <a class="nav-link {{ Request::is('tentang-kami') ? 'active' : '' }}" href="/">
                            Tentang Kami
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::is('struktur-organisasi') ? 'active' : '' }}" href="{{ url('/struktur-organisasi') }}">
                            Struktur Organisasi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::is('galeri-kegiatan') ? 'active' : '' }}" href="{{ url('/galeri-kegiatan') }}">
                            Galeri Kegiatan
                        </a>
                    </li>
                </div>

                <li class="nav-item ms-lg-auto mt-3 mt-lg-0">
                @auth
                    <a class="btn btn-success btn-sm px-3 w-100" href="{{ route('filament.admin.pages.dashboard') }}">
                    Dashboard
                    </a>
                @else
                    <a class="btn btn-primary btn-sm px-3 w-100" href="{{ route('filament.admin.auth.login') }}">
                    Login
                    </a>
                @endauth
                </li>
            </ul>
        </div>
    </div>
</nav>
