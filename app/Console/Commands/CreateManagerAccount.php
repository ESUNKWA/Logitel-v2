<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates (or resets) a manager account (Gérant, full access) from the command line — the way
 * to get the first login on a fresh production database, where the demo seeders (weak
 * "password" account, sample rooms) must not run. Interactive by default; --password reads the
 * value from an option for scripts.
 */
class CreateManagerAccount extends Command
{
    protected $signature = 'hotel:create-manager
        {email? : Adresse e-mail de connexion}
        {--name= : Nom affiché}
        {--password= : Mot de passe (sinon demandé de façon masquée)}';

    protected $description = 'Crée (ou réinitialise) un compte Gérant, accès complet au back-office';

    public function handle(): int
    {
        $role = Role::query()->where('slug', 'gerant')->first();
        if (! $role) {
            $this->error('Rôle « Gérant » introuvable : lancez d\'abord les migrations (php artisan migrate).');

            return self::FAILURE;
        }

        $email = $this->argument('email') ?: $this->ask('E-mail de connexion');
        $name = $this->option('name') ?: $this->ask('Nom affiché', 'Gérant');
        $password = $this->option('password') ?: $this->secret('Mot de passe (10 caractères min., majuscules, minuscules et chiffres)');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            ['email' => ['required', 'email', 'max:255'], 'name' => ['required', 'string', 'max:255'], 'password' => ['required', Password::defaults()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $existed = $user->exists;
        $user->fill(['name' => $name, 'password' => $password]);
        $user->forceFill(['role_id' => $role->id, 'is_active' => true, 'must_change_password' => false])->save();

        if ($existed) {
            $user->logoutEverywhere();
        }

        $this->info(($existed ? 'Compte réinitialisé' : 'Compte créé')." : {$user->email} (Gérant).");

        return self::SUCCESS;
    }
}
