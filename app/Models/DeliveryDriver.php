<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryDriver extends Model
{
    protected $fillable = [
        'business_profile_id',
        'first_name',
        'last_name',
        'phone_number',
        'is_active',
        'last_dispatched_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_dispatched_at' => 'datetime',
    ];

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Crea o reutiliza un repartidor por número de teléfono (normalizado a
     * solo dígitos). Si ya existía con otro nombre, actualiza el nombre al
     * más reciente que se haya escrito -- así el mantenimiento se hace solo,
     * sin pantalla aparte, según pidió el negocio.
     */
    /**
     * Datos mínimos del último repartidor al que se le despachó un pedido
     * (id, nombre, teléfono), para el botón "Reenviar al repartidor" en el
     * detalle del pedido. null si nunca se despachó o el repartidor se borró.
     *
     * @return array{id:int,name:string,phone_number:string}|null
     */
    public static function summaryFor(?int $driverId, ?int $businessProfileId = null): ?array
    {
        if (!$driverId) {
            return null;
        }

        $driver = static::query()
            ->when($businessProfileId, fn ($q) => $q->where('business_profile_id', $businessProfileId))
            ->find($driverId);
        if (!$driver) {
            return null;
        }

        return ['id' => $driver->id, 'name' => $driver->full_name, 'phone_number' => $driver->phone_number];
    }

    public static function findOrCreateByPhone(?int $businessProfileId, string $phone, string $firstName, ?string $lastName = null): self
    {
        $normalizedPhone = preg_replace('/\D+/', '', $phone);

        $driver = static::query()
            ->where('business_profile_id', $businessProfileId)
            ->where('phone_number', $normalizedPhone)
            ->first();

        if ($driver) {
            $driver->fill([
                'first_name' => $firstName ?: $driver->first_name,
                'last_name' => $lastName,
                'is_active' => true,
            ])->save();

            return $driver;
        }

        return static::create([
            'business_profile_id' => $businessProfileId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone_number' => $normalizedPhone,
            'is_active' => true,
        ]);
    }
}
