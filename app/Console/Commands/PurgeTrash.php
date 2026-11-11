<?php

namespace App\Console\Commands;

use App\Models\TrashedFile;
use App\Support\FileTrash;
use Illuminate\Console\Command;

/**
 * Erases for good the affectations left in the « Corbeille » longer than
 * config('hotel.trash_retention_days') (30 by default). Scheduled daily; the trash page runs the
 * same clean-up when it opens, so it also works without a cron.
 */
class PurgeTrash extends Command
{
    protected $signature = 'hotel:purge-trash';

    protected $description = 'Supprime définitivement les affectations restées trop longtemps dans la corbeille';

    public function handle(): int
    {
        $count = FileTrash::purgeExpired();
        $this->info("{$count} dossier(s) supprimé(s) définitivement (corbeille de plus de ".TrashedFile::retentionDays().' jours).');

        return self::SUCCESS;
    }
}
