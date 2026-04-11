<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'SMPN 45 Sijunjung')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    @stack('styles')

    <style>
        body {
            background-color: #f5f6f7;
        }
        .nav-link.active {
    font-weight: 600;
    color: #0d6efd !important;
    border-bottom: 2px solid #0d6efd;
}

        .navbar {
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .hero-img {
            width: 100%;
            height: auto;
            border-radius: 8px;
        }
        .content-box {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .section-title {
            font-weight: 600;
            margin-bottom: 10px;
        }
        iframe {
            border-radius: 8px;
        }
    </style>
</head>
<body>

    {{-- Navbar --}}
    @include('partials.navbar')

    {{-- Main Content --}}
    <main class="container my-4">
        @yield('content')
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    @stack('scripts')
</body>
</html>
