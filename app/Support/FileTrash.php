<?php

namespace App\Support;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\TrashedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * « Corbeille » of affectations (client request, 2026-11-11: test files typed into production by
 * mistake). Gérant only (permission affectations.delete).
 *
 * Deleting really removes the file from the live tables — the stay (and the réservation it came
 * from: same row), its invoice, every payment, and its client when they have no other file — so
 * every figure (Recettes, tills, dashboard, « Reste à encaisser », exports, availability) drops it
 * without any query having to know about the trash. A copy of the removed rows is kept, encrypted,
 * in `trashed_files`: « Restaurer » puts them back exactly (same ids, invoice number, payments and
 * their dates), refused with a message when the room has been taken meanwhile. The copy is erased
 * by « Supprimer définitivement » or automatically after config('hotel.trash_retention_days');
 * the summary row stays as the deletion log. Invoice numbers are never reused (counter untouched).
 */
class FileTrash
{
    /**
     * Moves affectations to the trash, all or nothing.
     *
     * @param  list<int>  $ids
     * @return Collection<int, TrashedFile>
     */
    public static function trash(array $ids, ?string $reason): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $entries = DB::transaction(function () use ($ids, $reason) {
            $stays = Reservation::query()->stays()->whereKey($ids)->lockForUpdate()->orderBy('id')->get();

            if ($stays->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'ids' => 'Certaines affectations sélectionnées n\'existent plus (déjà supprimées ?). Rechargez la page et recommencez.',
                ]);
            }

            $batch = (string) Str::uuid();

            return $stays->map(fn (Reservation $stay) => self::trashOne($stay, $batch, $reason));
        });

        Log::notice('Affectations mises à la corbeille', [
            'by' => Auth::id(),
            'references' => $entries->pluck('reference')->all(),
            'paid' => (string) $entries->sum('paid_amount'),
        ]);

        return $entries;
    }

    private static function trashOne(Reservation $stay, string $batch, ?string $reason): TrashedFile
    {
        $stay->loadMissing(['room.roomType', 'guest']);
        $id = $stay->id;

        $reservation = (array) DB::table('reservations')->where('id', $id)->first();
        $invoice = DB::table('invoices')->where('reservation_id', $id)->first();
        $payments = $invoice
            ? DB::table('payments')->where('invoice_id', $invoice->id)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()
            : [];
        $guest = DB::table('guests')->where('id', $stay->guest_id)->first();
        $stayRequests = DB::table('stay_requests')->where('reservation_id', $id)->pluck('id')->all();
        $bookingRequests = DB::table('booking_requests')->where('reservation_id', $id)->pluck('id')->all();

        $paid = array_reduce($payments, fn (string $sum, array $p) => bcadd($sum, (string) $p['amount'], 2), '0');

        $entry = (new TrashedFile)->forceFill([
            'batch' => $batch,
            'reservation_id' => $id,
            'reference' => $stay->reference(),
            'booking_reference' => $stay->bookingReference(),
            'room_number' => $stay->room?->number,
            'room_type' => $stay->room?->roomType?->name,
            'guest_name' => $stay->guest ? Str::limit($stay->guest->fullName(), 250, '') : null,
            'pricing_mode' => $stay->pricing_mode?->value,
            'status' => $stay->status?->value,
            'check_in' => $stay->check_in,
            'check_out' => $stay->check_out,
            'total_amount' => $stay->total_amount ?? 0,
            'paid_amount' => $paid,
            'invoice_number' => $invoice?->number,
            'reason' => $reason,
            'trashed_at' => now(),
            'trashed_by' => Auth::id(),
            'payload' => [
                'reservation' => $reservation,
                'invoice' => $invoice ? (array) $invoice : null,
                'payments' => $payments,
                'guest' => $guest ? (array) $guest : null,
                'stay_requests' => $stayRequests,
                'booking_requests' => $bookingRequests,
            ],
        ]);
        $entry->save();

        // A stay in progress frees its room (« Libre »), like a check-out.
        if ($stay->status === ReservationStatus::Enregistree && $stay->room?->status === RoomStatus::Occupee) {
            $stay->room->update(['status' => RoomStatus::Disponible]);
        }

        // Raw deletes: no model event may re-bill, re-guarantee or log anything for a file that goes away.
        DB::table('stay_requests')->where('reservation_id', $id)->update(['reservation_id' => null]);
        DB::table('booking_requests')->where('reservation_id', $id)->update(['reservation_id' => null]);
        if ($invoice) {
            DB::table('payments')->where('invoice_id', $invoice->id)->delete();
            DB::table('invoices')->where('id', $invoice->id)->delete();
        }
        DB::table('reservations')->where('id', $id)->delete();

        // Its client goes too when they have no other file (e.g. a « Client de passage » made for
        // the test). Each trashed file keeps its own copy, so restoring any of them brings them back.
        if ($guest && ! DB::table('reservations')->where('guest_id', $guest->id)->exists()) {
            DB::table('guests')->where('id', $guest->id)->delete();
        }

        return $entry;
    }

    /**
     * Puts a trashed affectation back exactly as it was. Throws a ValidationException (message
     * starting with the file's reference) when it can't: room gone, taken meanwhile, already
     * restored or erased.
     */
    public static function restore(TrashedFile $entry): Reservation
    {
        $restored = DB::transaction(function () use ($entry) {
            $entry = TrashedFile::query()->lockForUpdate()->findOrFail($entry->id);
            $ref = $entry->reference;
            $fail = fn (string $message) => throw ValidationException::withMessages(['restore' => "{$ref} : {$message}"]);

            if (! $entry->isInTrash() || ! is_array($entry->payload)) {
                $fail('ce dossier a déjà été restauré ou supprimé définitivement.');
            }

            $payload = $entry->payload;
            $reservation = (array) ($payload['reservation'] ?? []);
            $id = (int) ($reservation['id'] ?? 0);

            if ($id === 0 || DB::table('reservations')->where('id', $id)->exists()) {
                $fail('ce dossier existe déjà.');
            }

            $room = Room::query()->lockForUpdate()->find($reservation['room_id'] ?? 0);
            if (! $room) {
                $fail('sa chambre a été supprimée entre-temps, il ne peut pas être restauré.');
            }

            // A stay in progress needs its room back: physically free and not taken by anyone
            // over its period (an overdue stay holds it until now). Finished/cancelled ones don't.
            $status = ReservationStatus::tryFrom((string) ($reservation['status'] ?? ''));
            $holdsRoom = $status === ReservationStatus::Enregistree
                || (in_array($status, [ReservationStatus::EnAttente, ReservationStatus::Confirmee], true) && ! empty($reservation['is_guaranteed']));

            if ($holdsRoom) {
                if ($status === ReservationStatus::Enregistree && $room->status !== RoomStatus::Disponible) {
                    $fail("la chambre N°{$room->number} n'est plus libre (statut : {$room->status->getLabel()}). Libérez-la d'abord.");
                }

                $start = Carbon::parse($reservation['check_in']);
                $end = Carbon::parse($reservation['check_out']);
                if ($status === ReservationStatus::Enregistree && $end->lessThan(now())) {
                    $end = now();
                }
                if ($error = $room->availabilityError($start, $end)) {
                    $fail($error);
                }
            }

            $guest = $payload['guest'] ?? null;
            if (is_array($guest) && ! DB::table('guests')->where('id', $guest['id'] ?? 0)->exists()) {
                DB::table('guests')->insert(self::columnsOf('guests', $guest));
            }

            // A forfait deleted meanwhile: the snapshot (name, price) stays, only the link goes.
            if (! empty($reservation['special_rate_id']) && ! DB::table('special_rates')->where('id', $reservation['special_rate_id'])->exists()) {
                $reservation['special_rate_id'] = null;
            }

            DB::table('reservations')->insert(self::columnsOf('reservations', $reservation));

            if (is_array($payload['invoice'] ?? null)) {
                DB::table('invoices')->insert(self::columnsOf('invoices', $payload['invoice']));
                foreach ((array) ($payload['payments'] ?? []) as $payment) {
                    DB::table('payments')->insert(self::columnsOf('payments', (array) $payment));
                }
            }

            // The guest / online requests it was made from point to it again (unless re-used meanwhile).
            DB::table('stay_requests')->whereIn('id', (array) ($payload['stay_requests'] ?? []))->whereNull('reservation_id')->update(['reservation_id' => $id]);
            DB::table('booking_requests')->whereIn('id', (array) ($payload['booking_requests'] ?? []))->whereNull('reservation_id')->update(['reservation_id' => $id]);

            if ($status === ReservationStatus::Enregistree) {
                $room->update(['status' => RoomStatus::Occupee]);
            }

            $entry->forceFill(['restored_at' => now(), 'restored_by' => Auth::id(), 'payload' => null])->save();

            return Reservation::query()->findOrFail($id);
        });

        Log::notice('Affectation restaurée depuis la corbeille', ['by' => Auth::id(), 'reference' => $entry->reference]);

        return $restored;
    }

    /**
     * Erases a trashed file for good: its copy goes (no restoration possible any more), the log
     * line stays — without the client's name. $byUserId null = automatic deletion.
     */
    public static function purge(TrashedFile $entry, ?int $byUserId): bool
    {
        return DB::transaction(function () use ($entry, $byUserId) {
            $entry = TrashedFile::query()->lockForUpdate()->find($entry->id);
            if (! $entry || ! $entry->isInTrash()) {
                return false;
            }

            $entry->forceFill(['purged_at' => now(), 'purged_by' => $byUserId, 'payload' => null, 'guest_name' => null])->save();

            return true;
        });
    }

    /**
     * Automatic deletion of files older than the retention period. Run daily by the scheduler
     * (hotel:purge-trash) and whenever the trash page opens, so it works without a cron too.
     */
    public static function purgeExpired(): int
    {
        $count = 0;
        TrashedFile::query()->inTrash()
            ->where('trashed_at', '<=', now()->subDays(TrashedFile::retentionDays()))
            ->orderBy('id')
            ->each(function (TrashedFile $entry) use (&$count) {
                $count += self::purge($entry, null) ? 1 : 0;
            });

        if ($count > 0) {
            Log::notice('Corbeille : dossiers effacés automatiquement', ['count' => $count]);
        }

        return $count;
    }

    /**
     * The saved row reduced to the table's current columns (a column added or dropped since the
     * deletion never breaks a restoration).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function columnsOf(string $table, array $row): array
    {
        return array_intersect_key($row, array_flip(Schema::getColumnListing($table)));
    }
}
