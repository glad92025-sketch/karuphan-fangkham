<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'position', 'department', 'phone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function canEditAssets(): bool
    {
        return in_array($this->role, ['admin', 'staff'], true);
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'admin' => 'ผู้ดูแลระบบ / เจ้าหน้าที่พัสดุ',
            'staff' => 'เจ้าหน้าที่ตรวจนับ',
            default => 'ผู้เข้าชมข้อมูล',
        };
    }
}
