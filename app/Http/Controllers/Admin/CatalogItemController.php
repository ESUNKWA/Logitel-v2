<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Department;
use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * « Catalogue des prestations » (catalog.manage, the Gérant): what each sales service sells and
 * its price. An item already sold is never deleted (the past sales keep their snapshot), only
 * deactivated; prices may change, past sales keep theirs.
 */
class CatalogItemController extends Controller
{
    public function index(Request $request): View
    {
        $offered = Department::offeredSalesServices();
        // No service offered besides the accommodation: nothing to sell (Configuration → Hôtel).
        if ($offered === []) {
            return view('admin.catalog.none');
        }
        $department = $request->query('service') !== null ? $this->department((string) $request->query('service')) : $offered[0];

        return view('admin.catalog.index', [
            'department' => $department,
            'departments' => $offered,
            'items' => CatalogItem::query()->ofDepartment($department)->ordered()->get(),
            'categories' => CatalogItem::query()->ofDepartment($department)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $department = $this->department((string) $request->input('service'));
        $data = $this->validated($request, $department);

        $item = new CatalogItem($data);
        $item->forceFill(['department' => $department])->save();

        return redirect()->route('admin.catalog.index', ['service' => $department->slug()])->with('status', 'Prestation « '.$item->name.' » ajoutée.');
    }

    public function update(Request $request, CatalogItem $item): RedirectResponse
    {
        abort_unless($item->department->isOffered(), 404);
        $item->update($this->validated($request, $item->department, $item));

        return redirect()->route('admin.catalog.index', ['service' => $item->department->slug()])->with('status', 'Prestation « '.$item->name.' » mise à jour.');
    }

    public function destroy(CatalogItem $item): RedirectResponse
    {
        abort_unless($item->department->isOffered(), 404);
        if ($item->isSold()) {
            return back()->withErrors(['item' => 'La prestation « '.$item->name.' » a déjà été vendue : désactivez-la plutôt (les ventes passées la conservent).']);
        }
        $item->delete();

        return redirect()->route('admin.catalog.index', ['service' => $item->department->slug()])->with('status', 'Prestation « '.$item->name.' » supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Department $department, ?CatalogItem $item = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('catalog_items', 'name')->where('department', $department->value)->ignore($item?->id)],
            'category' => ['nullable', 'string', 'max:60'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'counts_entry' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'days' => ['nullable', 'array', 'max:7'],
            'days.*' => ['integer', 'between:1,7'],
        ], [
            'name.unique' => 'Ce service a déjà une prestation de ce nom.',
        ], ['name' => 'nom', 'category' => 'catégorie', 'price' => 'prix', 'position' => 'ordre']);

        return [
            'name' => trim($data['name']),
            'category' => filled($data['category'] ?? null) ? trim($data['category']) : null,
            'price' => $data['price'],
            'position' => (int) ($data['position'] ?? 0),
            // Only the beach / pool counts entries.
            'counts_entry' => $department->countsEntries() && $request->boolean('counts_entry'),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : ($item?->is_active ?? true),
            // Restaurant only: the days the dish is served (none, or all seven: every day).
            'days' => $department->servesByDay() ? self::days($data['days'] ?? []) : null,
        ];
    }

    /**
     * @param  array<int, mixed>  $days
     * @return list<int>|null sorted ISO days, null for every day
     */
    private static function days(array $days): ?array
    {
        $days = array_values(array_unique(array_map('intval', $days)));
        sort($days);

        return $days === [] || count($days) === 7 ? null : $days;
    }

    /** A service the hotel offers (whitelist), else 404. */
    private function department(string $slug): Department
    {
        $department = Department::fromSlug($slug);
        abort_unless($department !== null && in_array($department, Department::offeredSalesServices(), true), 404);

        return $department;
    }
}
