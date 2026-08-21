<?php

namespace App\Models;

use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'is_admin',
        'role',
        'role_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function roleModel(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function sentWhatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'admin_user_id');
    }

    public function isActive(): bool
    {
        return ($this->is_active ?? true) === true;
    }

    public function isSuperAdmin(): bool
    {
        if ($this->roleModel?->slug === 'super_admin') {
            return true;
        }

        return ($this->role ?? '') === 'super_admin';
    }

    public function hasPermission(string $key): bool
    {
        return app(PermissionService::class)->userCan($this, $key);
    }

    public function roleLabel(): string
    {
        return $this->roleModel?->name
            ?? match ($this->role) {
                'super_admin' => 'Super Administrador',
                'admin' => 'Administrador',
                'agent' => 'Agente de ventas',
                'viewer' => 'Consultor',
                default => ucfirst(str_replace('_', ' ', (string) $this->role)),
            };
    }
}
