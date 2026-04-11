<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SchoolProfile;

class DashboardController extends Controller
{
    public function index()
    {
        // Gunakan eager loading 'with' untuk performa maksimal
        $profile = SchoolProfile::with('school_missions')->first();

        return view('home', compact('profile'));
    }
}