<?php

namespace App\Enums;

/**
 * Every permission the back-office checks. Permissions live in code (each one guards a
 * concrete route/action), roles live in the database and pick from this list.
 * Each case is registered as a Gate ability in AppServiceProvider, so `can:rooms.view`
 * route middleware and `@can('rooms.view')` in Blade both work.
 */
enum Permission: string
{
    case RoomsView = 'rooms.view';
    case RoomsEdit = 'rooms.edit';
    case RoomsManage = 'rooms.manage';
    case GuestsList = 'guests.list';
    case GuestsView = 'guests.view';
    case GuestsManage = 'guests.manage';
    case GuestsDelete = 'guests.delete';
    case ReservationsView = 'reservations.view';
    case ReservationsManage = 'reservations.manage';
    case ReservationsCancel = 'reservations.cancel';
    case AffectationsView = 'affectations.view';
    case AffectationsManage = 'affectations.manage';
    case AffectationsCancel = 'affectations.cancel';
    case AffectationsDelete = 'affectations.delete';
    case InvoicesView = 'invoices.view';
    case PaymentsCreate = 'payments.create';
    case PaymentsDelete = 'payments.delete';
    case SalesCreate = 'sales.create';
    case SalesCancel = 'sales.cancel';
    case CatalogManage = 'catalog.manage';
    case EventsManage = 'events.manage';
    case ExpensesCreate = 'expenses.create';
    case ExpensesValidate = 'expenses.validate';
    case DashboardRevenue = 'dashboard.revenue';
    case AlertsCheckout = 'alerts.checkout';
    case DutyTake = 'duty.take';
    case DutyManage = 'duty.manage';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case SettingsManage = 'settings.manage';
    case ActivityView = 'activity.view';
    case SiteManage = 'site.manage';
    case SiteStats = 'site.stats';

    public function getLabel(): string
    {
        return match ($this) {
            self::RoomsView => 'Voir le tableau et la liste des chambres',
            self::RoomsEdit => 'Modifier une chambre (dont son statut)',
            self::RoomsManage => 'Créer/supprimer des chambres, gérer les types de chambre et leurs tarifs',
            self::GuestsList => 'Voir la liste des clients (menu « Clients »)',
            self::GuestsView => 'Rechercher un client (réservations, recherche à l\'accueil)',
            self::GuestsManage => 'Créer et modifier des clients',
            self::GuestsDelete => 'Supprimer un client',
            self::ReservationsView => 'Voir les réservations (séjours réservés à l\'avance)',
            self::ReservationsManage => 'Créer/modifier/reporter une réservation, enregistrer l\'arrivée du client',
            self::ReservationsCancel => 'Annuler une réservation',
            self::AffectationsView => 'Voir les affectations (séjours en cours et passés)',
            self::AffectationsManage => 'Affecter une chambre, prolonger un séjour, valider une sortie',
            self::AffectationsCancel => 'Annuler une affectation',
            self::AffectationsDelete => 'Supprimer des affectations saisies par erreur, corbeille (restaurer, supprimer définitivement) — Gérant uniquement',
            self::InvoicesView => 'Voir les factures',
            self::PaymentsCreate => 'Enregistrer un paiement',
            self::PaymentsDelete => 'Supprimer un paiement',
            self::SalesCreate => 'Enregistrer des ventes (restauration, bar, plage et piscine), y compris le report sur la chambre d\'un client hébergé, et voir les ventes',
            self::SalesCancel => 'Annuler une vente (avec un motif ; une vente déjà encaissée doit d\'abord être remboursée)',
            self::CatalogManage => 'Gérer le catalogue des prestations (plats, boissons, entrées, locations…), les salles et leurs prix',
            self::EventsManage => 'Réserver les salles (réunions et événements) : devis, confirmation, modification, acompte, planning',
            self::ExpensesCreate => 'Demander une dépense (avec justificatif ; elle attend la validation du gérant) et voir ses propres dépenses',
            self::ExpensesValidate => 'Voir toutes les dépenses, les valider, les refuser ou les annuler, gérer leurs catégories (ses propres dépenses sont validées d\'office)',
            self::DashboardRevenue => "Voir le chiffre d'affaires sur le tableau de bord",
            self::AlertsCheckout => "Recevoir l'alarme de fin de séjour (sonne en continu jusqu'à la sortie du client ou à la prolongation ; jamais pour le Gérant)",
            self::DutyTake => 'Prendre son service : réceptionniste du jour, à qui reviennent toutes les ventes de la journée (obligatoire avant de travailler ; jamais pour le Gérant)',
            self::DutyManage => 'Voir les prises de service et désigner ou corriger la réceptionniste d\'un jour',
            self::UsersManage => 'Gérer les comptes du personnel',
            self::RolesManage => 'Gérer les rôles et leurs permissions',
            self::SettingsManage => "Modifier les paramètres de l'hôtel (journée commerciale, horaires des tarifs)",
            self::ActivityView => "Consulter le journal d'activité (qui a fait quoi, quand) et l'exporter",
            self::SiteManage => 'Gérer le contenu du site web (accueil, services, galerie, événements)',
            self::SiteStats => 'Voir les statistiques de visite du site web',
        };
    }

    public function getGroup(): string
    {
        return match ($this) {
            self::RoomsView, self::RoomsEdit, self::RoomsManage => 'Chambres',
            self::GuestsList, self::GuestsView, self::GuestsManage, self::GuestsDelete => 'Clients',
            self::AffectationsView, self::AffectationsManage, self::AffectationsCancel, self::AffectationsDelete => 'Affectations',
            self::ReservationsView, self::ReservationsManage, self::ReservationsCancel => 'Réservations',
            self::InvoicesView, self::PaymentsCreate, self::PaymentsDelete => 'Factures et paiements',
            self::SalesCreate, self::SalesCancel, self::CatalogManage => 'Ventes (restauration, bar, plage et piscine)',
            self::EventsManage => 'Réunions et événements',
            self::ExpensesCreate, self::ExpensesValidate => 'Dépenses',
            self::DashboardRevenue => 'Tableau de bord',
            self::AlertsCheckout => 'Alertes',
            self::DutyTake, self::DutyManage => 'Prise de service',
            self::UsersManage, self::RolesManage, self::SettingsManage, self::ActivityView => 'Administration',
            self::SiteManage, self::SiteStats => 'Site web',
        };
    }

    /**
     * Held by the Gérant's system role only — never grantable to another role (client request,
     * 2026-11-11: « seul le profil gérant peut supprimer »). Not offered in the role form, and
     * the Gate ignores any role row that would list it.
     */
    public function isSystemOnly(): bool
    {
        return $this === self::AffectationsDelete;
    }

    /**
     * Permissions a role may be given (the role form): every one but the system-only ones.
     *
     * @return list<string>
     */
    public static function grantableValues(): array
    {
        return array_values(array_map(fn (self $p) => $p->value, array_filter(self::cases(), fn (self $p) => ! $p->isSystemOnly())));
    }

    /**
     * @return array<string, list<self>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $permission) {
            if ($permission->isSystemOnly()) {
                continue;
            }
            $groups[$permission->getGroup()][] = $permission;
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
