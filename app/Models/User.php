<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'user_id';

    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'email',
        'username',
        'password',
        'role', // constraint roles: super_admin, admin, mahasiswa, mitra, dosbing
        'is_active',
    ];

    protected $hidden = [
        'password',
        'created_at',
        'updated_at',
        'tokens',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function username()
    {
        return 'username'; // agar Auth pakai kolom username
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    // 🔗 Relasi
    public function mahasiswa()
    {
        return $this->hasOne(Mahasiswa::class, 'user_id', 'user_id');
    }

    public function admin()
    {
        return $this->hasOne(Admin::class, 'user_id', 'user_id');
    }

    public function dataAdmin()
    {
        return $this->hasOne(Admin::class, 'user_id', 'user_id');
    }

    public function mitra()
    {
        return $this->hasOne(Mitra::class, 'user_id', 'user_id');
    }
}
