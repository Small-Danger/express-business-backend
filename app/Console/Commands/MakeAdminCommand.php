<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MakeAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:admin 
                            {--email= : Email de l\'administrateur}
                            {--name= : Nom de l\'administrateur}
                            {--password= : Mot de passe de l\'administrateur}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Créer un utilisateur administrateur';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Création d\'un administrateur...');
        $this->newLine();

        // Récupérer ou demander l'email
        $email = $this->option('email');
        if (!$email) {
            $email = $this->ask('Email de l\'administrateur');
        }

        // Valider l'email
        $validator = Validator::make(['email' => $email], [
            'email' => 'required|email|unique:users,email',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            if ($errors->has('email')) {
                if (str_contains($errors->first('email'), 'taken')) {
                    $this->warn("L'email {$email} est déjà utilisé.");
                    
                    if ($this->confirm('Voulez-vous mettre à jour cet utilisateur en administrateur ?', false)) {
                        $user = User::where('email', $email)->first();
                        $user->role = 'admin';
                        $user->save();
                        
                        $this->info("✅ L'utilisateur {$email} a été mis à jour avec le rôle admin.");
                        return Command::SUCCESS;
                    }
                    
                    return Command::FAILURE;
                }
                
                $this->error('Email invalide.');
                return Command::FAILURE;
            }
        }

        // Récupérer ou demander le nom
        $name = $this->option('name');
        if (!$name) {
            $name = $this->ask('Nom de l\'administrateur', 'Administrateur');
        }

        // Récupérer ou demander le mot de passe
        $password = $this->option('password');
        if (!$password) {
            $password = $this->secret('Mot de passe (laisser vide pour générer automatiquement)');
            
            if (empty($password)) {
                $password = $this->generatePassword();
                $this->info("Mot de passe généré : {$password}");
            } else {
                // Confirmer le mot de passe
                $passwordConfirmation = $this->secret('Confirmer le mot de passe');
                
                if ($password !== $passwordConfirmation) {
                    $this->error('Les mots de passe ne correspondent pas.');
                    return Command::FAILURE;
                }
            }
        }

        // Valider le mot de passe
        if (strlen($password) < 8) {
            $this->error('Le mot de passe doit contenir au moins 8 caractères.');
            return Command::FAILURE;
        }

        // Créer l'utilisateur
        try {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'admin',
            ]);

            $this->newLine();
            $this->info('✅ Administrateur créé avec succès !');
            $this->newLine();
            $this->table(
                ['Champ', 'Valeur'],
                [
                    ['Nom', $user->name],
                    ['Email', $user->email],
                    ['Rôle', $user->role],
                ]
            );
            $this->newLine();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Erreur lors de la création de l\'administrateur : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Générer un mot de passe sécurisé aléatoire
     */
    private function generatePassword(int $length = 12): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $password;
    }
}

