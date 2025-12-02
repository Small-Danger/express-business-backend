<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer un utilisateur admin (développement/test uniquement)
        User::firstOrCreate(
            ['email' => 'admin@express-business.com'],
            [
                'name' => 'Administrateur',
                'email' => 'admin@express-business.com',
                'password' => Hash::make('AdmiN123'),
                'role' => 'admin',
            ]
        );

            $this->command->info('Utilisateurs créés avec succès !');
        $this->command->info('Admin: admin@express-business.com / AdmiN123');
    }
}
