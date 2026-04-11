<?php

namespace App\Http\Controllers;

use App\Models\SchoolOrganizationStructure;
use Illuminate\Http\Request;

class StrukturorganisasiController extends Controller
{
    public function index()
    {
        $structures = SchoolOrganizationStructure::orderBy('order')->get();

        return view('dashboard.strukturorganisasi', compact('structures'));
    }
}