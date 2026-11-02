<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AdminPassword extends Command
{
    protected $signature = 'admin:password
        {email? : Email of the user. Not needed when there is only one user}
        {--new-email= : Also change the login email}';

    protected $description = 'Set a new password (and optionally a new email) for the admin. Use it when the password is forgotten.';

    public function handle(): int
    {
        $user = $this->findUser();
        if (! $user) {
            return self::FAILURE;
        }

        $this->info("Changing the login of {$user->name} ({$user->email}).");

        $newEmail = $this->option('new-email');
        if ($newEmail) {
            $check = Validator::make(
                ['email' => $newEmail],
                ['email' => ['email', 'max:255', "unique:users,email,{$user->id}"]],
            );
            if ($check->fails()) {
                $this->error($check->errors()->first('email'));

                return self::FAILURE;
            }
        }

        $password = (string) $this->secret('New password (what you type is hidden)');
        $confirm = (string) $this->secret('Type it again');

        if ($password !== $confirm) {
            $this->error('The two passwords are not the same. Nothing was changed.');

            return self::FAILURE;
        }

        if ($password === 'Admin@12345') {
            $this->error('That is the temporary password. Choose a different one.');

            return self::FAILURE;
        }

        $check = Validator::make(
            ['password' => $password],
            ['password' => [Password::min(10)->mixedCase()->numbers()]],
        );
        if ($check->fails()) {
            $this->error($check->errors()->first('password'));

            return self::FAILURE;
        }

        $user->password = Hash::make($password);
        if ($newEmail) {
            $user->email = $newEmail;
        }
        // A new remember-me token signs out every "keep me signed in" browser
        $user->setRememberToken(Str::random(60));
        $user->save();

        // End every open session of this user, so the new password takes effect everywhere
        DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->info('Done. The new password is set and every open session was signed out.');

        return self::SUCCESS;
    }

    private function findUser(): ?User
    {
        $email = $this->argument('email');

        if ($email) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                $this->error("No user has the email {$email}.");
            }

            return $user;
        }

        $users = User::all();

        if ($users->count() === 1) {
            return $users->first();
        }

        $this->error($users->isEmpty()
            ? 'There are no users yet. Run: php artisan db:seed'
            : 'There is more than one user. Give the email, for example: php artisan admin:password someone@example.com');

        return null;
    }
}