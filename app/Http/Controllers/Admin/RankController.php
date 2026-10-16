<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RankController extends Controller
{
    public function index(): View
    {
        return view('admin.ranks.index', ['ranks' => Rank::query()->withCount('personnel')->orderBy('sort_order')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Rank::query()->create($this->validated($request));

        return back()->with('success', 'Rank added.');
    }

    public function update(Request $request, Rank $rank): RedirectResponse
    {
        $rank->update($this->validated($request, $rank));

        return back()->with('success', 'Rank updated.');
    }

    protected function validated(Request $request, ?Rank $rank = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'abbreviation' => ['required', 'string', 'max:20', Rule::unique('ranks', 'abbreviation')->ignore($rank?->id)],
            'category' => ['required', Rule::in(['officer', 'enlisted'])],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
