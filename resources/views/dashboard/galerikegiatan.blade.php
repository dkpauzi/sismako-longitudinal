@extends('layouts.app')

@section('title', 'Galeri Kegiatan | SMPN 45 Sijunjung')

@section('content')

<style>
    .gallery-box {
        background: #fff;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .gallery-title {
        font-weight: 600;
        font-size: 20px;
        color: #0d6efd;
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
    }

    .gallery-title::before {
        content: "";
        width: 10px;
        height: 10px;
        background: #0d6efd;
        border-radius: 50%;
        display: inline-block;
    }

    .gallery-item {
        overflow: hidden;
        border-radius: 8px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        cursor: pointer;
    }

    .gallery-item img {
        width: 100%;
        height: 170px;
        object-fit: cover;
        transition: 0.3s ease-in-out;
    }

    .gallery-item:hover img {
        transform: scale(1.1);
    }

    .pagination-box {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 25px;
    }

    .pagination-box .page-numbers {
        display: flex;
        gap: 8px;
    }

    .page-btn {
        width: 35px;
        height: 35px;
        border-radius: 6px;
        display: flex;
        justify-content: center;
        align-items: center;
        border: 1px solid #0d6efd;
        color: #0d6efd;
        font-weight: 500;
        text-decoration: none;
        transition: 0.2s;
    }

    .page-btn.active {
        background: #0d6efd;
        color: white;
    }

    .page-btn:hover {
        background: #0d6efd;
        color: white;
    }

    .page-nav-btn {
        border-radius: 6px;
        padding: 6px 15px;
        font-size: 13px;
    }
</style>

<div class="gallery-box">

    <div class="gallery-title">
        Gallery
    </div>

    <!-- GRID FOTO -->
    <div class="row g-4">

        @php
            $activities = $activities ?? collect();
        @endphp

        @for($i = 0; $i < 12; $i++)
            @php
                $item = $activities[$i] ?? null;
            @endphp

            <div class="col-md-4 col-sm-6">
                <div class="gallery-item">

                    @if($item && $item->image_path)
                        <img src="{{ asset('storage/'.$item->image_path) }}" 
                             alt="{{ $item->title }}"
                             title="{{ $item->title }}">
                    @else
                        <img src="{{ asset('img/gallery/1.jpg') }}" alt="Foto Kegiatan">
                    @endif

                </div>
            </div>
        @endfor

    </div>

    <!-- PAGINATION (Design tetap, belum pakai Laravel paginate) -->
    <div class="pagination-box">

        <div class="page-numbers">
            <a href="#" class="page-btn active">1</a>
            <a href="#" class="page-btn">2</a>
            <a href="#" class="page-btn">3</a>
        </div>

        <div>
            <a href="#" class="btn btn-primary btn-sm page-nav-btn">Prev</a>
            <a href="#" class="btn btn-primary btn-sm page-nav-btn">Next</a>
        </div>

    </div>

</div>

@endsection