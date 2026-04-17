<?php

namespace App\Http\Controllers;

use App\Models\SchoolProfile;
use App\Models\SchoolPost;

class DashboardController extends Controller
{
    public function index()
    {
        $profile = SchoolProfile::with('school_missions')->first();

        $posts = SchoolPost::published()
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->paginate(6);

        // Siapkan data untuk JS — hindari transformasi kompleks di dalam @json() Blade
        $postsJson = $posts->mapWithKeys(function ($p) {
            $cover = $p->cover_image_path
                ? '/storage/' . $p->cover_image_path
                : null;

            $gallery = collect($p->gallery_images ?? [])
                ->map(fn($g) => '/storage/' . $g)
                ->values()
                ->toArray();

            $date = ($p->published_at ?? $p->created_at)?->translatedFormat('d F Y');

            return [
                $p->id => [
                    'title' => $p->title,
                    'body' => $p->body,
                    'category' => $p->category,
                    'cover' => $cover,
                    'gallery' => $gallery,
                    'date' => $date,
                ]
            ];
        })->toArray();

        return view('home', compact('profile', 'posts', 'postsJson'));
    }
}