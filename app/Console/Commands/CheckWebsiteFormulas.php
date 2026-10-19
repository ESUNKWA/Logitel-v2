<?php

namespace App\Console\Commands;

use App\Models\GalleryPhoto;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\SiteContent;
use App\Models\SiteEvent;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The website only mentions the séjour (PricingMode::WEBSITE, client decision 2026-10-19). The
 * code guarantees it for prices and forms; this lists the texts typed in the application that
 * still mention another formula (« nuitée », « à l'heure »…), for the staff to rephrase.
 * Read only: nothing is changed. Usage (also in production, Coolify → Terminal):
 *   php artisan site:check-formulas
 */
class CheckWebsiteFormulas extends Command
{
    protected $signature = 'site:check-formulas';

    protected $description = 'Liste les textes du site qui mentionnent une autre formule que le séjour (lecture seule)';

    /** Case- and accent-insensitive patterns of the formulas the website must not mention. */
    public const PATTERNS = ['/nuit[ée]e/iu', "/[àa]\\s*l['’]\\s*heure/iu", '/\bjourn[ée]e\b/iu', '/(?:\bpar|\/)\s*(heure|nuit)\b/iu'];

    public function handle(): int
    {
        $sources = [
            'Chambre' => [Room::query()->where('is_published', true)->get(), ['public_name', 'tagline', 'description', 'features', 'rules', 'cancellation_policy'], fn (Room $r) => 'N°'.$r->number],
            'Type de chambre' => [RoomType::query()->where('is_active', true)->get(), ['name', 'description'], fn (RoomType $t) => $t->name],
            'Service' => [Service::query()->where('is_published', true)->get(), ['title', 'category', 'description'], fn (Service $s) => $s->title],
            'Événement' => [SiteEvent::query()->where('is_published', true)->get(), ['title', 'location', 'description', 'price_label', 'booking_info'], fn (SiteEvent $e) => $e->title],
            'Galerie' => [GalleryPhoto::query()->where('is_published', true)->get(), ['caption', 'category'], fn (GalleryPhoto $p) => 'photo #'.$p->id],
            'Accueil du site' => [SiteContent::query()->get(), ['value'], fn (SiteContent $c) => $c->key],
        ];

        $rows = [];
        foreach ($sources as $label => [$records, $fields, $name]) {
            foreach ($records as $record) {
                foreach ($fields as $field) {
                    $text = $this->text($record, $field);
                    if ($text !== '' && $this->mentions($text)) {
                        $rows[] = [$label, $name($record), $field, mb_strimwidth($text, 0, 70, '…')];
                    }
                }
            }
        }

        if ($rows === []) {
            $this->info('Aucun texte publié ne mentionne une autre formule que le séjour.');

            return self::SUCCESS;
        }

        $this->warn(count($rows).' texte(s) à reformuler (le site ne doit parler que du séjour) :');
        $this->table(['Où', 'Élément', 'Champ', 'Extrait'], $rows);

        return self::SUCCESS;
    }

    public static function mentions(string $text): bool
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    private function text(Model $record, string $field): string
    {
        $value = $record->getAttribute($field);
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        return trim(is_array($value) ? implode(' · ', array_map('strval', $value)) : (string) $value);
    }
}
