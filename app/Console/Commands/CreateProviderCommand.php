<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateProviderCommand extends Command
{
    protected $signature = 'provider:create
                            {--name= : Full name of the provider}
                            {--email= : Email address}
                            {--password= : Password (min 8 characters)}
                            {--phone= : Phone number (optional)}';

    protected $description = 'Create the provider account with a default ProviderProfile';

    public function handle(): int
    {
        $name = $this->option('name') ?? $this->ask('Provider name');
        $email = $this->option('email') ?? $this->ask('Email address');
        $phone = $this->option('phone') ?? $this->ask('Phone number (optional, press enter to skip)');
        $password = $this->option('password') ?? $this->secret('Password (min 8 characters)');

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email [{$email}] already exists.");

            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $email, $password, $phone) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => UserRole::Provider,
                'phone' => $phone ?: null,
            ]);

            ProviderProfile::create([
                'user_id' => $user->id,
                'appointment_duration_minutes' => (int) config('booking.default_appointment_duration_minutes'),
                'min_cancel_notice_hours' => (int) config('booking.default_min_cancel_notice_hours'),
            ]);

            $this->info('Provider account created successfully.');
            $this->table(['Field', 'Value'], [
                ['ID', $user->id],
                ['Name', $user->name],
                ['Email', $user->email],
                ['Role', $user->role->value],
            ]);
        });

        return self::SUCCESS;
    }
}
