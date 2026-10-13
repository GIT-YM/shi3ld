<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateServicesStaff extends Command
{
    protected $signature = 'shi3ld:staff {email=services-admin@example.test} {--name=Services administrator} {--bootstrap : Generate private local development credentials}';

    protected $description = 'Create an authorised services staff account without changing existing accounts';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $password = $this->option('bootstrap') ? Str::password(24) : $this->secret('Password (at least 12 characters)');
        $data = ['email' => $email, 'name' => $this->option('name'), 'password' => $password];
        $validator = Validator::make($data, ['email' => 'required|email|max:255', 'name' => 'required|string|max:255', 'password' => 'required|string|min:12|max:500']);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->warn('Account already exists; its password and permissions were preserved.');

            return self::SUCCESS;
        }
        if ($this->option('bootstrap') && ! app()->environment('local')) {
            $this->error('Bootstrap credentials are only available in the local environment.');

            return self::FAILURE;
        }
        $user = new User;
        $user->name = $data['name'];
        $user->email = $email;
        $user->password = $password;
        $user->services_staff = true;
        $user->save();
        if ($this->option('bootstrap')) {
            $path = storage_path('app/private/services-staff.txt');
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0700, true);
            }
            file_put_contents($path, 'Email: '.$email."\nPassword: ".$password."\n");
            chmod($path, 0600);
            $this->info('Local credentials saved in storage/app/private/services-staff.txt.');
        }
        $this->info('Services staff account created.');

        return self::SUCCESS;
    }
}
