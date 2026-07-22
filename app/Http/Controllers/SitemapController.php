<?php

namespace App\Http\Controllers;

use App\Models\SchoolPost;

/**
 * sitemap.xml dinamis untuk Google Search Console.
 *
 * Sengaja berupa CONTROLLER (bukan route closure) karena route berbasis closure
 * membuat `php artisan route:cache` GAGAL — sementara route:cache adalah salah
 * satu optimasi wajib di shared hosting.
 *
 * Postingan sekolah dibuka lewat modal di beranda (tanpa URL sendiri), jadi
 * sitemap hanya memuat halaman publik yang benar-benar punya URL. `lastmod`
 * diambil dari postingan terbaru agar Google tahu beranda diperbarui.
 */
class SitemapController extends Controller
{
    public function index()
    {
        $lastPost = SchoolPost::published()
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->first();

        $lastMod = ($lastPost?->published_at ?? $lastPost?->created_at ?? now())->toAtomString();

        $urls = [
            ['loc' => url('/'),                      'priority' => '1.0', 'freq' => 'weekly'],
            ['loc' => url('/struktur-organisasi'),   'priority' => '0.7', 'freq' => 'monthly'],
            ['loc' => url('/galeri-kegiatan'),       'priority' => '0.7', 'freq' => 'weekly'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $u) {
            $xml .= "  <url>\n"
                . '    <loc>' . e($u['loc']) . "</loc>\n"
                . '    <lastmod>' . $lastMod . "</lastmod>\n"
                . '    <changefreq>' . $u['freq'] . "</changefreq>\n"
                . '    <priority>' . $u['priority'] . "</priority>\n"
                . "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
