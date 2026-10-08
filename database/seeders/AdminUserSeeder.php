<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'admin@espace-katioka.local'],
            [
                'name' => 'Administrateur',
                'password' => 'password',
            ],
        );

        // The Gérant role is created by the roles migration; role/status aren't mass assignable.
        $user->forceFill([
            'role_id' => Role::query()->where('slug', 'gerant')->value('id'),
            'is_active' => true,
        ])->save();
    }
}
