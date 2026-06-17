<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\EkgResult;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $totalPatients = Patient::count();
        $totalEkgResults = EkgResult::count();

        $monthlyEkg = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);

            $monthlyEkg[] = [
                'month' => $month->format('M'),
                'total' => EkgResult::whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->count()
            ];
        }

        $latestPatients = Patient::latest()->take(5)->get();

        $latestEkgs = EkgResult::with('patient')
            ->latest()
            ->take(5)
            ->get();
        
        $recentPatients = Patient::latest()
            ->take(6)
            ->get();
        
        return view('dashboard.index', compact('totalPatients', 'totalEkgResults', 'monthlyEkg', 'latestPatients', 'latestEkgs', 'recentPatients'));
    }
}