<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentApartment;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\HandoverRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ResolvesCurrentApartment;

    public function index(Request $request): View
    {
        $apartment = $this->currentApartment($request);

        $stats = [
            'unit_types' => $apartment->unitTypes()->count(),
            'knowledge_items' => $apartment->knowledgeItems()->count(),
            'pending_bookings' => $apartment->bookings()->where('status', Booking::STATUS_PENDING)->count(),
            'open_handovers' => HandoverRequest::whereHas('conversation', fn ($q) => $q->where('apartment_id', $apartment->id))
                ->where('status', HandoverRequest::STATUS_OPEN)
                ->count(),
        ];

        $recentBookings = $apartment->bookings()->latest()->take(5)->with('unitType')->get();

        $openHandovers = HandoverRequest::whereHas('conversation', fn ($q) => $q->where('apartment_id', $apartment->id))
            ->where('status', HandoverRequest::STATUS_OPEN)
            ->latest()
            ->take(5)
            ->with('conversation')
            ->get();

        return view('admin.dashboard', [
            'apartment' => $apartment,
            'stats' => $stats,
            'recentBookings' => $recentBookings,
            'openHandovers' => $openHandovers,
        ]);
    }
}
