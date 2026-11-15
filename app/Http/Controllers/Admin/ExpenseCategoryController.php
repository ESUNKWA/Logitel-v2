<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DeletionBlocked;
use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Expense categories (V2), managed by validators (expenses.validate). A category in use can't be
 * deleted — it's deactivated (no longer offered, history unchanged).
 */
class ExpenseCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.expenses.categories', [
            'categories' => ExpenseCategory::query()->withCount('expenses')->ordered()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = ExpenseCategory::query()->create($this->validated($request));

        return back()->with('status', 'Catégorie « '.$category->name.' » ajoutée.');
    }

    public function update(Request $request, ExpenseCategory $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));

        return back()->with('status', 'Catégorie « '.$category->name.' » enregistrée.');
    }

    public function destroy(ExpenseCategory $category): RedirectResponse
    {
        try {
            $category->delete();
        } catch (DeletionBlocked $e) {
            return back()->withErrors(['category' => $e->getMessage()]);
        }

        return back()->with('status', 'Catégorie « '.$category->name.' » supprimée.');
    }

    /**
     * @return array{name: string, position: int, is_active: bool}
     */
    private function validated(Request $request, ?ExpenseCategory $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60', Rule::unique(ExpenseCategory::class, 'name')->ignore($category?->id)],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['sometimes', 'boolean'],
        ], [], ['name' => 'nom', 'position' => 'ordre']);

        return [
            'name' => trim($data['name']),
            'position' => (int) ($data['position'] ?? 0),
            'is_active' => $request->boolean('is_active', $category === null),
        ];
    }
}
