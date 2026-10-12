<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ProvisionSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'superadmin:provision
                            {--username= : Username for Super Admin}
                            {--email= : Email for Super Admin}
                            {--password= : Password for Super Admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision or ensure Super Admin account safely';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $username = $this->option('username') ?: $this->ask('Masukkan username Super Admin', 'superadmin');
        $email = $this->option('email') ?: $this->ask('Masukkan email Super Admin', 'superadmin@simagang.dev');

        $user = User::where('username', $username)->first();

        if ($user) {
            $this->info("User '{$username}' sudah ada. Memperbarui status ke role 'super_admin' dan aktif...");
            $user->role = 'super_admin';
            $user->is_active = true;
            $user->save();

            // Jangan menimpa password yang sudah ada tanpa izin eksplisit
            $explicitNewPassword = $this->option('password');
            if ($explicitNewPassword) {
                if ($this->confirm("User '{$username}' sudah ada. Apakah Anda yakin ingin menimpa password yang sudah ada?", false)) {
                    $user->password = Hash::make($explicitNewPassword);
                    $user->save();
                    $this->info('Password berhasil diperbarui.');
                } else {
                    $this->warn('Penimpaan password dibatalkan.');
                }
            } elseif ($this->confirm("Apakah ingin mereset password untuk user '{$username}'?", false)) {
                $password = $this->secret('Masukkan password baru');
                if (! empty($password)) {
                    $user->password = Hash::make($password);
                    $user->save();
                    $this->info('Password berhasil diperbarui.');
                }
            }

            $this->info('Akun Super Admin berhasil dipastikan.');

            return self::SUCCESS;
        }

        $password = $this->option('password');
        if (! $password) {
            $password = $this->secret('Masukkan password Super Admin');
        }

        if (empty($password)) {
            $this->error('Password tidak boleh kosong.');

            return self::FAILURE;
        }

        $validator = Validator::make([
            'username' => $username,
            'email' => $email,
            'password' => $password,
        ], [
            'username' => 'required|string|max:50|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->info("Akun Super Admin '{$username}' berhasil dibuat.");

        return self::SUCCESS;
    }
}
