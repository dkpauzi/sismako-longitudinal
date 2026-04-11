<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\SchoolActivity;

class GalerikegiatanController extends Controller
{
    public function index()
    {
        $activities = SchoolActivity::where('is_published', true)
            ->orderBy('school_profile_id') // urutan berdasarkan school_profile_id
            ->get();

        return view('dashboard.galerikegiatan', compact('activities'));
    }
}