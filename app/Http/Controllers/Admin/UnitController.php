<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(): View
    {
        return view('admin.units.index', ['units' => Unit::query()->with('parent')->withCount(['assignments as current_count' => fn ($q) => $q->whereNull('end_date')])->orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Unit::query()->create($this->validated($request));

        return back()->with('success', 'Unit added.');
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $data = $this->validated($request, $unit);
        if (($data['parent_id'] ?? null) && in_array((int) $data['parent_id'], $unit->descendantIds(), true)) {
            return back()->withErrors(['parent_id' => 'A unit cannot be placed under itself or its sub-units.']);
        }
        $unit->update($data);

        return back()->with('success', 'Unit updated.');
    }

    protected function validated(Request $request, ?Unit $unit = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('units', 'code')->ignore($unit?->id)],
            'name' => ['required', 'string', 'max:150'],
            'parent_id' => ['nullable', 'exists:units,id'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['code'] = strtoupper($data['code']);

        return $data;
    }
}
