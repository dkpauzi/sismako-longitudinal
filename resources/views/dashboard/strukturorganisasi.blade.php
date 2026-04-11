@extends('layouts.app')

@section('title', 'Struktur Organisasi | SMPN 45 Sijunjung')

@section('content')

<style>
    .org-section {
        background: #f8f9fa;
        padding: 40px 20px;
        border-radius: 10px;
    }

    .org-card {
        background: #fff;
        border-radius: 10px;
        padding: 20px 15px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        height: 100%;
    }

    .org-photo {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        margin: 0 auto 10px;
        object-fit: cover;
    }

    .org-name {
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 4px;
    }

    .org-role {
        font-size: 12px;
        color: #6c757d;
    }
</style>

<div class="org-section">

    @php
        $structures = $structures ?? collect();
    @endphp

    <!-- KEPALA SEKOLAH (ORDER 1) -->
    @php $kepsek = $structures->where('order', 1)->first(); @endphp
    <div class="row justify-content-center mb-5">
        <div class="col-md-4 col-sm-6">
            <div class="org-card">

                @if($kepsek && $kepsek->photo_path)
                    <img src="{{ asset('storage/'.$kepsek->photo_path) }}" class="org-photo">
                @else
                    <div class="org-photo" style="background:#7ec6ec;"></div>
                @endif

                <div class="org-name">{{ $kepsek->name ?? '-' }}</div>
                <div class="org-role">{{ $kepsek->position ?? '-' }}</div>

            </div>
        </div>
    </div>

    <!-- BARIS 1 (ORDER 2,3,4) -->
    <div class="row justify-content-center g-4 mb-4">
        @foreach([2,3,4] as $i)
            @php $item = $structures->where('order', $i)->first(); @endphp
            <div class="col-md-4 col-sm-6">
                <div class="org-card">

                    @if($item && $item->photo_path)
                        <img src="{{ asset('storage/'.$item->photo_path) }}" class="org-photo">
                    @else
                        <div class="org-photo" style="background:#7ec6ec;"></div>
                    @endif

                    <div class="org-name">{{ $item->name ?? '-' }}</div>
                    <div class="org-role">{{ $item->position ?? '-' }}</div>

                </div>
            </div>
        @endforeach
    </div>

    <!-- BARIS 2 (ORDER 5,6,7) -->
    <div class="row justify-content-center g-4 mb-4">
        @foreach([5,6,7] as $i)
            @php $item = $structures->where('order', $i)->first(); @endphp
            <div class="col-md-4 col-sm-6">
                <div class="org-card">

                    @if($item && $item->photo_path)
                        <img src="{{ asset('storage/'.$item->photo_path) }}" class="org-photo">
                    @else
                        <div class="org-photo" style="background:#7ec6ec;"></div>
                    @endif

                    <div class="org-name">{{ $item->name ?? '-' }}</div>
                    <div class="org-role">{{ $item->position ?? '-' }}</div>

                </div>
            </div>
        @endforeach
    </div>

    <!-- BARIS 3 (ORDER 8,9,10) -->
    <div class="row justify-content-center g-4">
        @foreach([8,9,10] as $i)
            @php $item = $structures->where('order', $i)->first(); @endphp
            <div class="col-md-4 col-sm-6">
                <div class="org-card">

                    @if($item && $item->photo_path)
                        <img src="{{ asset('storage/'.$item->photo_path) }}" class="org-photo">
                    @else
                        <div class="org-photo" style="background:#7ec6ec;"></div>
                    @endif

                    <div class="org-name">{{ $item->name ?? '-' }}</div>
                    <div class="org-role">{{ $item->position ?? '-' }}</div>

                </div>
            </div>
        @endforeach
    </div>

</div>

@endsection