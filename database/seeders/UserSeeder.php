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
        // Ne pas créer d'utilisateurs de test en production
        if (app()->environment('production')) {
            $this->command->warn('⚠️  Mode PRODUCTION détecté. Les utilisateurs de test ne seront PAS créés.');
            $this->command->info('Créez vos utilisateurs de production manuellement via la commande: php artisan tinker');
            $this->command->info('Ou utilisez l\'interface d\'administration après avoir créé le premier utilisateur admin.');
            return;
        }

        // Créer un utilisateur admin (développement/test uniquement)
        User::firstOrCreate(
            ['email' => 'admin@express-business.com'],
            [
                'name' => 'Administrateur',
                'email' => 'admin@express-business.com',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
            ]
        );

        // Créer un utilisateur boss (développement/test uniquement)
        User::firstOrCreate(
            ['email' => 'boss@express-business.com'],
            [
                'name' => 'Directeur',
                'email' => 'boss@express-business.com',
                'password' => Hash::make('boss123'),
                'role' => 'boss',
            ]
        );

        // Créer un utilisateur secrétaire (développement/test uniquement)
        User::firstOrCreate(
            ['email' => 'secretary@express-business.com'],
            [
                'name' => 'Secrétaire',
                'email' => 'secretary@express-business.com',
                'password' => Hash::make('secretary123'),
                'role' => 'secretary',
            ]
        );

        // Créer un utilisateur voyageur (développement/test uniquement)
        User::firstOrCreate(
            ['email' => 'traveler@express-business.com'],
            [
                'name' => 'Voyageur',
                'email' => 'traveler@express-business.com',
                'password' => Hash::make('traveler123'),
                'role' => 'traveler',
            ]
        );

        $this->command->info('Utilisateurs créés avec succès !');
        $this->command->info('Admin: admin@express-business.com / admin123');
        $this->command->info('Boss: boss@express-business.com / boss123');
        $this->command->info('Secretary: secretary@express-business.com / secretary123');
        $this->command->info('Traveler: traveler@express-business.com / traveler123');
    }
}
