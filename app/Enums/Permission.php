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
    case GuestsView = 'guests.view';
    case GuestsManage = 'guests.manage';
    case GuestsDelete = 'guests.delete';
    case ReservationsView = 'reservations.view';
    case ReservationsManage = 'reservations.manage';
    case ReservationsCancel = 'reservations.cancel';
    case AffectationsView = 'affectations.view';
    case AffectationsManage = 'affectations.manage';
    case AffectationsCancel = 'affectations.cancel';
    case InvoicesView = 'invoices.view';
    case PaymentsCreate = 'payments.create';
    case PaymentsDelete = 'payments.delete';
    case DashboardRevenue = 'dashboard.revenue';
    case AlertsCheckout = 'alerts.checkout';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case SettingsManage = 'settings.manage';
    case SiteManage = 'site.manage';
    case SiteStats = 'site.stats';

    public function getLabel(): string
    {
        return match ($this) {
            self::RoomsView => 'Voir le tableau et la liste des chambres',
            self::RoomsEdit => 'Modifier une chambre (dont son statut)',
            self::RoomsManage => 'Créer/supprimer des chambres, gérer les types de chambre et leurs tarifs',
            self::GuestsView => 'Voir les clients',
            self::GuestsManage => 'Créer et modifier des clients',
            self::GuestsDelete => 'Supprimer un client',
            self::ReservationsView => 'Voir les réservations (séjours réservés à l\'avance)',
            self::ReservationsManage => 'Créer/modifier/reporter une réservation, enregistrer l\'arrivée du client',
            self::ReservationsCancel => 'Annuler une réservation',
            self::AffectationsView => 'Voir les affectations (séjours en cours et passés)',
            self::AffectationsManage => 'Affecter une chambre, prolonger un séjour, check-out',
            self::AffectationsCancel => 'Annuler une affectation',
            self::InvoicesView => 'Voir les factures',
            self::PaymentsCreate => 'Enregistrer un paiement',
            self::PaymentsDelete => 'Supprimer un paiement',
            self::DashboardRevenue => "Voir le chiffre d'affaires sur le tableau de bord",
            self::AlertsCheckout => "Recevoir l'alarme de fin de séjour (heure de départ atteinte)",
            self::UsersManage => 'Gérer les comptes du personnel',
            self::RolesManage => 'Gérer les rôles et leurs permissions',
            self::SettingsManage => "Modifier les paramètres de l'hôtel (journée commerciale, horaires des tarifs)",
            self::SiteManage => 'Gérer le contenu du site web (accueil, services, galerie, événements)',
            self::SiteStats => 'Voir les statistiques de visite du site web',
        };
    }

    public function getGroup(): string
    {
        return match ($this) {
            self::RoomsView, self::RoomsEdit, self::RoomsManage => 'Chambres',
            self::GuestsView, self::GuestsManage, self::GuestsDelete => 'Clients',
            self::AffectationsView, self::AffectationsManage, self::AffectationsCancel => 'Affectations',
            self::ReservationsView, self::ReservationsManage, self::ReservationsCancel => 'Réservations',
            self::InvoicesView, self::PaymentsCreate, self::PaymentsDelete => 'Factures et paiements',
            self::DashboardRevenue => 'Tableau de bord',
            self::AlertsCheckout => 'Alertes',
            self::UsersManage, self::RolesManage, self::SettingsManage => 'Administration',
            self::SiteManage, self::SiteStats => 'Site web',
        };
    }

    /**
     * @return array<string, list<self>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $permission) {
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
