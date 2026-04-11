@extends('layouts.app')

@section('title', ($profile?->name ?? 'SMPN 45 Sijunjung') . ' | Beranda')

@section('content')

<style>
   .map-wrapper {
    max-width: 900px;   /* atur lebar maksimal */
    width: 100%;
}

.map-wrapper iframe {
    width: 100% !important;
    height: 450px !important;
    border: 0 !important;
    display: block;
    border-radius: 8px;
}
</style>

    <div class="content-box">
        @if($profile && $profile->banner_image_path)
            <img src="{{ asset('storage/'.$profile->banner_image_path) }}" class="hero-img">
        @else
            <img src="{{ asset('img/banner-.png') }}" class="hero-img">
        @endif
    </div>

    <div class="content-box">
        <h5 class="section-title text-center">Visi & Misi</h5>

        <p><strong>Visi</strong></p>
        <p>
            {{ $profile?->vision ?? '-' }}
        </p>

        <p><strong>Misi</strong></p>

        <ol>
            @forelse ($profile?->school_missions?->sortBy('order') ?? [] as $misi)
                <li>{{ $misi->content }}</li>
            @empty
                <p class="text-muted">Misi belum diinput.</p>
            @endforelse
        </ol>
    </div>

<div class="content-box">
    <h5 class="section-title text-center">Lokasi</h5>

    <div class="map-wrapper mx-auto">
        @if($profile && $profile->google_maps_embed)
            {!! $profile->google_maps_embed !!}
        @else
            <iframe
                src="https://maps.google.com/maps?q=SMPN%2045%20Sijunjung&t=&z=13&ie=UTF8&iwloc=&output=embed"
                loading="lazy">
            </iframe>
        @endif
    </div>
</div>

@endsection 