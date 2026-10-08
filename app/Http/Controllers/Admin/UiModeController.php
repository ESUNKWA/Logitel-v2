<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Mode simple / Mode complet" switch, remembered per staff account.
 */
class UiModeController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['mode' => ['required', 'in:'.User::UI_SIMPLE.','.User::UI_FULL]]);

        $request->user()->forceFill(['ui_mode' => $data['mode']])->save();

        return redirect()->route('admin.dashboard')
            ->with('status', $data['mode'] === User::UI_SIMPLE ? 'Mode simple activé.' : 'Mode complet activé.');
    }
}
