<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FileSelectionRequest;
use App\Models\TrashedFile;
use App\Support\FileTrash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * « Corbeille » of deleted affectations (Gérant only, can:affectations.delete): restore, erase
 * for good, and the history of every deletion (FileTrash).
 */
class TrashController extends Controller
{
    public const TABS = ['corbeille' => 'Dans la corbeille', 'historique' => 'Historique des suppressions'];

    public function index(Request $request): View
    {
        // Automatic deletion also runs here, so it works even without a cron.
        FileTrash::purgeExpired();

        $tab = $request->query('onglet');
        $tab = is_string($tab) && array_key_exists($tab, self::TABS) ? $tab : 'corbeille';

        $query = TrashedFile::query()->with(['trashedBy', 'restoredBy', 'purgedBy'])->orderByDesc('trashed_at')->orderByDesc('id');
        if ($tab === 'corbeille') {
            $query->inTrash();
        }

        return view('admin.trash.index', [
            'tab' => $tab,
            'entries' => $query->paginate(50)->withQueryString(),
            'inTrashCount' => TrashedFile::query()->inTrash()->count(),
            'historyCount' => TrashedFile::query()->count(),
            'retentionDays' => TrashedFile::retentionDays(),
        ]);
    }

    public function restore(FileSelectionRequest $request): RedirectResponse
    {
        $restored = [];
        $errors = [];

        // One by one: a file whose room was taken stays in the trash, the others come back.
        foreach (TrashedFile::query()->inTrash()->whereKey($request->ids())->orderBy('id')->get() as $entry) {
            try {
                FileTrash::restore($entry);
                $restored[] = $entry->reference;
            } catch (ValidationException $e) {
                $errors[] = collect($e->errors())->flatten()->first();
            }
        }

        if ($restored === [] && $errors === []) {
            $errors[] = 'Aucun des dossiers sélectionnés n\'est dans la corbeille (déjà restaurés ou supprimés ?).';
        }

        $redirect = redirect()->route('admin.trash.index');
        if ($restored !== []) {
            $redirect->with('status', (count($restored) > 1 ? count($restored).' dossiers restaurés' : 'Dossier restauré')
                .' ('.implode(', ', $restored).') : facture, paiements et recettes sont de nouveau comptés.');
        }

        return $errors !== [] ? $redirect->withErrors(['restore' => $errors]) : $redirect;
    }

    public function purge(FileSelectionRequest $request): RedirectResponse
    {
        $purged = TrashedFile::query()->inTrash()->whereKey($request->ids())->orderBy('id')->get()
            ->filter(fn (TrashedFile $entry) => FileTrash::purge($entry, $request->user()->id))
            ->pluck('reference');

        if ($purged->isEmpty()) {
            return redirect()->route('admin.trash.index')
                ->withErrors(['purge' => 'Aucun des dossiers sélectionnés n\'est dans la corbeille (déjà restaurés ou supprimés ?).']);
        }

        return redirect()->route('admin.trash.index')->with('status', ($purged->count() > 1 ? $purged->count().' dossiers supprimés' : 'Dossier supprimé')
            .' définitivement ('.$purged->implode(', ').'). Restauration impossible désormais ; la trace de la suppression reste dans l\'historique.');
    }
}
