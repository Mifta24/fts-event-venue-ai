<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentVenue;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\HandoverRequest;
use App\Models\SpaceInventory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ResolvesCurrentVenue;

    /** How far ahead the occupancy figure looks. */
    private const OCCUPANCY_DAYS = 30;

    /** How far ahead confirmed events are listed. */
    private const UPCOMING_DAYS = 14;

    /** A pending request older than this counts as a guest left waiting. */
    private const WAITING_HOURS = 24;

    public function index(Request $request): View
    {
        $venue = $this->currentVenue($request);

        $today = now($venue->timezone)->startOfDay();

        $occupancy = SpaceInventory::whereIn('space_id', $venue->spaces()->where('is_active', true)->select('id'))
            ->whereDate('event_date', '>=', $today->toDateString())
            ->whereDate('event_date', '<=', $today->copy()->addDays(self::OCCUPANCY_DAYS - 1)->toDateString())
            ->selectRaw('SUM(total_units) as total, SUM(booked_units) as booked')
            ->first();

        $stats = [
            'spaces' => $venue->spaces()->count(),
            'knowledge_items' => $venue->knowledgeItems()->count(),
            'pending_bookings' => $venue->bookings()->where('status', Booking::STATUS_PENDING)->count(),
            'waiting_bookings' => $venue->bookings()
                ->where('status', Booking::STATUS_PENDING)
                ->where('created_at', '<', now()->subHours(self::WAITING_HOURS))
                ->count(),
            'upcoming_events' => $venue->bookings()
                ->where('status', Booking::STATUS_CONFIRMED)
                ->whereDate('event_start', '>=', $today->toDateString())
                ->whereDate('event_start', '<=', $today->copy()->addDays(self::UPCOMING_DAYS - 1)->toDateString())
                ->count(),
            'occupancy_percent' => $occupancy?->total > 0 ? (int) round($occupancy->booked / $occupancy->total * 100) : null,
            'open_handovers' => HandoverRequest::whereHas('conversation', fn ($q) => $q->where('venue_id', $venue->id))
                ->where('status', HandoverRequest::STATUS_OPEN)
                ->count(),
        ];

        $upcomingEvents = $venue->bookings()
            ->where('status', Booking::STATUS_CONFIRMED)
            ->whereDate('event_start', '>=', $today->toDateString())
            ->whereDate('event_start', '<=', $today->copy()->addDays(self::UPCOMING_DAYS - 1)->toDateString())
            ->with('space')
            ->orderBy('event_start')
            ->get();

        $recentBookings = $venue->bookings()->latest()->take(5)->with('space')->get();

        $openHandovers = HandoverRequest::whereHas('conversation', fn ($q) => $q->where('venue_id', $venue->id))
            ->where('status', HandoverRequest::STATUS_OPEN)
            ->latest()
            ->take(5)
            ->with('conversation')
            ->get();

        return view('admin.dashboard', [
            'venue' => $venue,
            'stats' => $stats,
            'upcomingEvents' => $upcomingEvents,
            'recentBookings' => $recentBookings,
            'occupancyDays' => self::OCCUPANCY_DAYS,
            'upcomingDays' => self::UPCOMING_DAYS,
            'waitingHours' => self::WAITING_HOURS,
            'openHandovers' => $openHandovers,
        ]);
    }
}
