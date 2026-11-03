<?php

namespace App\Modules\Identity\Infrastructure\Console;

use App\Modules\Identity\Application\CreateSuperadmin;
use Illuminate\Console\Command;
use InvalidArgumentException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateSuperadminCommand extends Command
{
    protected $signature = 'clavedealma:superadmin {email?} {--name=Superadmin}';

    protected $description = 'Crea o actualiza un superadministrador';

    public function handle(CreateSuperadmin $createSuperadmin): int
    {
        $email = $this->argument('email') ?? text('Email del superadministrador', required: true);
        $plain = password('Contraseña (mínimo 12 caracteres)', required: true);

        try {
            $user = $createSuperadmin((string) $this->option('name'), (string) $email, $plain);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Superadministrador listo: {$user->email}");

        return self::SUCCESS;
    }
}
