<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    public function show(Request $request): RedirectResponse
    {
        abort_unless($request->user()->personnel_id, 404);

        return redirect()->route('personnel.show', $request->user()->personnel_id);
    }
}
