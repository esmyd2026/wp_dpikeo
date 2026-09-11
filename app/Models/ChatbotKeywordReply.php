<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Palabra(s) clave configurable(s) desde el admin: si el cliente escribe
 * alguna, el bot responde con `response_text` en vez de pasar por ChatGPT o
 * por el flujo. Alcance por sucursal (`all_branches` o la lista en
 * `branches()`) para que, p. ej., una promoción de un solo local no le
 * llegue a clientes de otras. Ver WhatsappService::generateChatbotResponse().
 */
class ChatbotKeywordReply extends Model
{
    protected $fillable = [
        'business_profile_id',
        'keywords',
        'all_branches',
        'response_text',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'keywords' => 'array',
        'all_branches' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(WhatsappBusinessProfile::class, 'business_profile_id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(BusinessBranch::class, 'keyword_reply_branch');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function appliesToBranch(?int $branchId): bool
    {
        if ($this->all_branches) {
            return true;
        }

        if (! $branchId) {
            return false;
        }

        return $this->relationLoaded('branches')
            ? $this->branches->contains('id', $branchId)
            : $this->branches()->whereKey($branchId)->exists();
    }

    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    /**
     * Busca la primera palabra clave activa que calce como palabra completa
     * dentro del mensaje del cliente y aplique a su sucursal. Se usa un
     * límite de palabra unicode (no `\b`, que no entiende acentos) para que
     * "horarios" dispare con "¿cuáles son sus horarios?" pero no con
     * "desahorarios" (caso límite improbable, pero evita falsos positivos
     * dentro de otra palabra).
     */
    public static function findMatch(int $businessProfileId, string $message, ?int $branchId): ?self
    {
        $normalizedMessage = self::normalize($message);

        if ($normalizedMessage === '') {
            return null;
        }

        return self::query()
            ->where('business_profile_id', $businessProfileId)
            ->where('is_active', true)
            ->with('branches')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->first(function (self $entry) use ($normalizedMessage, $branchId) {
                if (! $entry->appliesToBranch($branchId)) {
                    return false;
                }

                foreach ((array) $entry->keywords as $keyword) {
                    $normalizedKeyword = self::normalize((string) $keyword);

                    if ($normalizedKeyword === '') {
                        continue;
                    }

                    if (preg_match('/(?<!\p{L}|\p{N})'.preg_quote($normalizedKeyword, '/').'(?!\p{L}|\p{N})/u', $normalizedMessage)) {
                        return true;
                    }
                }

                return false;
            });
    }
}
