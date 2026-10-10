<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Run at container start-up (docker/entrypoint.sh): on a database with no account at all,
 * creates a default manager (Gérant) so the first login works without any command. Never
 * touches an existing database — as soon as one account exists it does nothing.
 *
 * Credentials come from the real environment (.env.production), read with getenv() so a
 * cached config can't hide them: ADMIN_EMAIL (default admin@espace-katioka.local) and
 * ADMIN_PASSWORD; without a password a strong random one is generated and printed once in
 * the container logs. Either way the password must be changed at first login.
 */
class EnsureAdminAccount extends Command
{
    public const DEFAULT_EMAIL = 'admin@espace-katioka.local';

    protected $signature = 'hotel:ensure-admin';

    protected $description = 'Crée le compte administrateur par défaut si aucun compte n\'existe (démarrage du conteneur)';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->line('Comptes existants : aucun compte administrateur par défaut créé.');

            return self::SUCCESS;
        }

        $role = Role::query()->where('slug', 'gerant')->first();
        if (! $role) {
            $this->error('Rôle « Gérant » introuvable : les migrations doivent être appliquées avant.');

            return self::FAILURE;
        }

        $email = trim((string) getenv('ADMIN_EMAIL')) ?: self::DEFAULT_EMAIL;
        $given = (string) getenv('ADMIN_PASSWORD');
        $password = $given !== '' ? $given : self::randomPassword();

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email', 'max:255'], 'password' => ['required', Password::defaults()]],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error("ADMIN_EMAIL / ADMIN_PASSWORD : {$error}");
            }
            $this->error('Compte administrateur par défaut NON créé. Corrigez .env.production ou utilisez : php artisan hotel:create-manager');

            return self::FAILURE;
        }

        $user = new User(['name' => 'Administrateur', 'email' => $email, 'password' => $password]);
        $user->forceFill(['role_id' => $role->id, 'is_active' => true, 'must_change_password' => true])->save();

        $this->newLine();
        $this->info('================ Compte administrateur par défaut créé ================');
        $this->line("  E-mail       : {$email}");
        $this->line('  Mot de passe : '.($given !== '' ? '(celui de ADMIN_PASSWORD dans .env.production)' : $password));
        $this->line('  Le mot de passe devra être changé à la première connexion.');
        $this->info('=======================================================================');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * 16 characters, letters and digits only (easy to type), always meeting Password::defaults()
     * (mixed case + digits).
     */
    public static function randomPassword(): string
    {
        do {
            $password = Str::password(16, letters: true, numbers: true, symbols: false);
        } while (! preg_match('/[a-z]/', $password) || ! preg_match('/[A-Z]/', $password) || ! preg_match('/\d/', $password));

        return $password;
    }
}
