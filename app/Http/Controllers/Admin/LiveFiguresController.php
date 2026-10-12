<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\BusinessDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Live refresh of the money shown on the home page and the figures dashboard (client request:
 * a payment recorded by a receptionist must show up for the other users without reloading).
 * Polled by admin.js for the blocks present on the page ([data-live-block], [data-live-value]);
 * every block is computed with the same code as the page and only when the user may see it —
 * what isn't allowed is simply left out of the answer.
 */
class LiveFiguresController extends Controller
{
    private const BLOCKS = ['my-cash', 'home-figures', 'staff-cash', 'free-rooms'];

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $wanted = array_intersect(self::BLOCKS, explode(',', $request->string('blocs')->toString()));
        $data = FrontDeskController::moneyBlocks($user);

        $blocks = [];
        foreach ($wanted as $name) {
            $html = match ($name) {
                'my-cash' => $data['myCash'] ? view('admin.partials._my-cash', ['myCash' => $data['myCash']])->render() : null,
                'staff-cash' => $data['staffCash'] !== null ? view('admin.partials._staff-cash', ['staffCash' => $data['staffCash']])->render() : null,
                'free-rooms' => $data['freeRooms'] !== null ? view('admin.partials._free-rooms', ['freeRooms' => $data['freeRooms']])->render() : null,
                'home-figures' => view('admin.partials._home-figures', ['finance' => $data['finance'], 'roomCounts' => $data['roomCounts']])->render(),
            };
            if ($html !== null) {
                $blocks[$name] = $html;
            }
        }

        // Figures dashboard headline (manager only), as formatted numbers.
        $values = [];
        if ($request->boolean('valeurs') && $user->can('dashboard.revenue')) {
            [$dayStart, $dayEnd] = BusinessDay::current();
            $money = DashboardController::headlineMoney(BusinessDay::today(), $dayStart, $dayEnd);
            foreach (['revenueToday', 'revenueMonth', 'monthlyRevenue', 'outstandingAmount'] as $key) {
                $values[$key] = number_format($money[$key], 0, ',', ' ');
            }
        }

        return response()->json(['blocks' => $blocks, 'values' => $values], 200, ['Cache-Control' => 'no-store']);
    }
}
