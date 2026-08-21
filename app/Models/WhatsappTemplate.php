<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'content',
        'language',
        'status',
        'template_id',
        'variables',
        'components'
    ];

    protected $casts = [
        'variables' => 'array',
        'components' => 'array'
    ];

    public function approvals()
    {
        return $this->hasMany(WhatsappTemplateApproval::class, 'template_id');
    }

    /**
     * Placeholders {{n}} del encabezado y cuerpo, en el mismo orden que
     * WhatsappService::prepareTemplateComponents espera recibirlos (un
     * arreglo posicional). Se usa para pintar un input por variable al armar
     * una campaña, en vez de pedir un JSON a mano.
     *
     * @return array<int, array{component: string, position: int, label: string}>
     */
    public function variableSlots(): array
    {
        $slots = [];

        foreach ($this->components ?? [] as $component) {
            $type = strtolower($component['type'] ?? '');
            if (!in_array($type, ['header', 'body'], true)) {
                continue;
            }

            $text = $component['text'] ?? '';
            $count = substr_count($text, '{{');
            for ($i = 1; $i <= $count; $i++) {
                $slots[] = [
                    'component' => $type,
                    'position' => $i,
                    'label' => ($type === 'header' ? 'Encabezado' : 'Cuerpo') . " {{$i}}",
                ];
            }
        }

        return $slots;
    }

    public function bodyText(): string
    {
        foreach ($this->components ?? [] as $component) {
            if (strtoupper($component['type'] ?? '') === 'BODY') {
                return $component['text'] ?? '';
            }
        }

        return $this->content ?? '';
    }
}
