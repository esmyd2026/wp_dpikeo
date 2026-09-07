<?php

namespace App\Models;

use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withPivot('role_id')->withTimestamps();
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(BusinessBranch::class, 'business_branch_user')->withTimestamps();
    }

    /**
     * Empresas que este usuario puede administrar: todas si es super_admin
     * de Siglo Tecnológico, o solo las que tenga asignadas explícitamente en
     * company_user. Nunca se infiere de "la primera empresa de la base".
     */
    public function authorizedCompanies()
    {
        return $this->isSuperAdmin()
            ? Company::orderBy('name')->get()
            : $this->companies()->orderBy('name')->get();
    }

    public function canAccessCompany(Company $company): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->companies()->whereKey($company->id)->exists();
    }

    /**
     * Sin filas explícitas para una empresa significa "todas sus sucursales".
     * Esto conserva el acceso de usuarios existentes y permite que una nueva
     * sucursal quede disponible automáticamente para administradores generales.
     */
    public function hasAllBranchAccess(?int $businessProfileId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return ! $this->branches()
            ->where('business_branches.business_profile_id', $businessProfileId)
            ->exists();
    }

    public function accessibleBranchIds(?int $businessProfileId): ?array
    {
        if ($this->hasAllBranchAccess($businessProfileId)) {
            return null;
        }

        return $this->branches()
            ->where('business_branches.business_profile_id', $businessProfileId)
            ->pluck('business_branches.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function canAccessBranch(BusinessBranch $branch): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $company = $branch->businessProfile?->company;
        if (! $company || ! $this->canAccessCompany($company)) {
            return false;
        }

        $ids = $this->accessibleBranchIds($branch->business_profile_id);

        return $ids === null || in_array((int) $branch->id, $ids, true);
    }

    public function roleForCompany(?Company $company): ?Role
    {
        if (! $company || $this->isSuperAdmin()) {
            return $this->roleModel;
        }

        $membership = $this->companies()
            ->whereKey($company->id)
            ->first();
        $roleId = $membership?->pivot?->role_id;

        return $roleId ? Role::find($roleId) : $this->roleModel;
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

    public function roleLabel(?Company $company = null): string
    {
        return $this->roleForCompany($company)?->name
            ?? match ($this->role) {
                'super_admin' => 'Super Administrador',
                'admin' => 'Administrador',
                'agent' => 'Agente de ventas',
                'viewer' => 'Consultor',
                default => ucfirst(str_replace('_', ' ', (string) $this->role)),
            };
    }
}
