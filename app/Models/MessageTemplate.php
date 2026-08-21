<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Textos de los mensajes automáticos que el sistema le envía al cliente por
 * WhatsApp (cambios de estado, costo de envío, etc.), editables desde el
 * panel en vez de estar fijos en el código. No cubre el flujo conversacional
 * del bot (ese ya se edita desde el editor de flujo de marketing).
 */
class MessageTemplate extends Model
{
    protected $fillable = ['key', 'name', 'body', 'placeholders'];

    protected $casts = [
        'placeholders' => 'array',
    ];

    /**
     * Reemplaza los placeholders {{clave}} del template guardado en la base
     * de datos. Si el template no existe (nunca debería pasar tras la
     * migración semilla, pero por si se borra a mano), usa $fallback tal
     * cual sin reemplazos.
     *
     * @param array<string, string> $replacements
     */
    public static function render(string $key, array $replacements, string $fallback = ''): string
    {
        $body = static::query()->where('key', $key)->value('body') ?? $fallback;

        foreach ($replacements as $placeholder => $value) {
            $body = str_replace('{{' . $placeholder . '}}', (string) $value, $body);
        }

        return $body;
    }
}
