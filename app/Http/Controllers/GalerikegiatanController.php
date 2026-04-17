<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\SchoolActivity;

class GalerikegiatanController extends Controller
{
    public function index()
    {
        $activities = SchoolActivity::where('is_published', true)
            ->orderByDesc('date') // Terbaru dulu
            ->get();

        return view('dashboard.galerikegiatan', compact('activities'));
    }
}