<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\PhoneNumber;
use App\UserRole;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:make-admin
        {phone : The mobile number of the admin, for example 0917 123 4567}
        {--name= : The name of the admin}
        {--email= : The email the admin signs in with on the admin website}
        {--password= : The password for the admin website. It is asked for when an email is given without one.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make the person with this phone number an admin. With an email and a password they can also sign in to the admin website.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $phone = PhoneNumber::normalize($this->argument('phone'));

        if ($phone === null) {
            $this->error('That is not a Philippine mobile number.');

            return self::FAILURE;
        }

        $email = $this->option('email');
        $password = $this->option('password');

        if ($email !== null && User::where('email', $email)->where('phone', '!=', $phone)->exists()) {
            $this->error('That email belongs to someone else.');

            return self::FAILURE;
        }

        if ($email !== null && $password === null) {
            $password = $this->secret('Password for the admin website (at least 8 characters)');
        }

        if ($email !== null && strlen((string) $password) < 8) {
            $this->error('The password needs at least 8 characters.');

            return self::FAILURE;
        }

        $user = User::firstOrNew(['phone' => $phone]);
        $user->role = UserRole::Admin;

        if ($this->option('name') !== null) {
            $user->name = $this->option('name');
        }

        if ($email !== null) {
            $user->email = $email;
            $user->password = $password;
        }

        $user->save();

        $this->info("{$phone} is now an admin.");

        if ($email !== null) {
            $this->info("They can sign in to the admin website with {$email}.");
        }

        return self::SUCCESS;
    }
}
