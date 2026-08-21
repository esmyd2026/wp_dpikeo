<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'admin_user_id',
        'business_profile_id',
        'message_id',
        'sender_type',
        'receiver_type',
        'content',
        'type',
        'status',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'business_profile_id' => 'integer'
    ];

    public function contact()
    {
        return $this->belongsTo(WhatsappContact::class, 'contact_id');
    }

    public function adminUser()
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function isFromAdminUser(): bool
    {
        return $this->sender_type === 'humano' || $this->admin_user_id !== null;
    }

    public function senderKind(): string
    {
        if ($this->sender_type === 'client') {
            return 'client';
        }

        return $this->isFromAdminUser() ? 'agent' : 'bot';
    }

    public function senderBadgeLabel(?string $clientName = null): string
    {
        return match ($this->senderKind()) {
            'client' => $clientName ?: 'Cliente',
            'agent' => $this->resolveAdminUserName() ?? 'Asesor',
            default => 'Bot',
        };
    }

    public function senderDisplayName(): string
    {
        return match ($this->sender_type) {
            'client' => 'Cliente',
            'humano' => $this->resolveAdminUserName() ?? 'Asesor',
            default => $this->admin_user_id ? ($this->resolveAdminUserName() ?? 'Asesor') : 'Bot',
        };
    }

    protected function resolveAdminUserName(): ?string
    {
        if ($this->relationLoaded('adminUser') && $this->adminUser) {
            return $this->adminUser->name;
        }

        if ($this->admin_user_id) {
            return User::query()->whereKey($this->admin_user_id)->value('name');
        }

        $metaUserId = $this->metadata['admin_user_id'] ?? null;

        if ($metaUserId) {
            return User::query()->whereKey($metaUserId)->value('name');
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function toChatPayload(): array
    {
        $metadata = $this->metadata ?? [];

        $payload = [
            'id' => $this->id,
            'content' => $this->content,
            'display_text' => \App\Helpers\WhatsappMessageFormatter::displayText($this->content, $this->type, $metadata),
            'display_description' => \App\Helpers\WhatsappMessageFormatter::displayDescription($this->content, $metadata),
            'is_interactive_reply' => \App\Helpers\WhatsappMessageFormatter::isInteractiveReply($this->content, $this->type, $metadata),
            'type' => $this->type,
            'sender_type' => $this->sender_type,
            'sender_kind' => $this->senderKind(),
            'sender_label' => $this->senderBadgeLabel(),
            'admin_sender_name' => $this->isFromAdminUser() ? $this->senderDisplayName() : null,
            'whatsapp_message_id' => $this->message_id,
            'metadata' => $metadata,
            'created_at' => $this->created_at->toDateTimeString(),
            'created_at_formatted' => $this->created_at->format('H:i'),
        ];

        if ($this->type === 'image') {
            $payload['has_image'] = true;
        } elseif ($this->type === 'document') {
            $payload['filename'] = $metadata['filename'] ?? 'documento';
            $payload['file_size'] = $metadata['file_size'] ?? null;
        }

        return $payload;
    }

    public function businessProfile()
    {
        return $this->belongsTo(WhatsappBusinessProfile::class);
    }

    public function conversation()
    {
        return $this->belongsTo(WhatsappConversation::class);
    }
}
