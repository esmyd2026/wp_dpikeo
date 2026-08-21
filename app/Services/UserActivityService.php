<?php

namespace App\Services;

use App\Models\User;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class UserActivityService
{
    public function dailyStatsForUser(User $user, ?Carbon $date = null): array
    {
        return $this->dailyStatsForUsers(collect([$user]), $date)->get($user->id, $this->emptyStats());
    }

    /**
     * @return Collection<int, array{
     *     messages_sent:int,
     *     clients_served:int,
     *     agent_requests_closed:int,
     *     clients: array<int, array{
     *         id:int,
     *         name:?string,
     *         phone:string,
     *         messages:int,
     *         agent_closed:bool
     *     }>
     * }>
     */
    public function dailyStatsForUsers(Collection $users, ?Carbon $date = null): Collection
    {
        if ($users->isEmpty()) {
            return collect();
        }

        $day = ($date ?? now())->copy()->startOfDay();
        $from = $day->copy();
        $to = $day->copy()->endOfDay();
        $userIds = $users->pluck('id')->map(fn ($id) => (int) $id)->all();

        $messageRows = WhatsappMessage::query()
            ->select('admin_user_id', 'contact_id')
            ->whereNotNull('admin_user_id')
            ->whereIn('admin_user_id', $userIds)
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->groupBy('admin_user_id');

        $agentClosed = WhatsappContact::query()
            ->select('id', 'name', 'phone_number', 'metadata')
            ->where(function ($query) use ($userIds) {
                foreach ($userIds as $userId) {
                    $query->orWhereRaw(
                        "JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.agent_handled_by')) = ?",
                        [(string) $userId]
                    );
                }
            })
            ->get()
            ->groupBy(fn ($contact) => (int) ($contact->metadata['agent_handled_by'] ?? 0))
            ->map(function ($group) use ($from, $to) {
                return $group->filter(function ($contact) use ($from, $to) {
                    $handledAt = $contact->metadata['agent_handled_at'] ?? null;
                    if (!$handledAt) {
                        return false;
                    }

                    try {
                        $at = Carbon::parse($handledAt);
                    } catch (\Throwable) {
                        return false;
                    }

                    return $at->between($from, $to);
                });
            });

        $preliminary = $users->mapWithKeys(function (User $user) use ($messageRows, $agentClosed) {
            $rows = $messageRows->get($user->id, collect());
            $closedContacts = $agentClosed->get($user->id, collect());

            $messagesByContact = $rows
                ->groupBy(fn ($row) => (int) $row->contact_id)
                ->map->count();
            $closedIds = $closedContacts->pluck('id')->map(fn ($id) => (int) $id)->filter()->unique();
            $allContactIds = $messagesByContact->keys()->merge($closedIds)->unique()->filter(fn ($id) => $id > 0);

            return [$user->id => [
                'messages_sent' => $rows->count(),
                'clients_served' => $allContactIds->count(),
                'agent_requests_closed' => $closedIds->count(),
                'messages_by_contact' => $messagesByContact,
                'closed_contacts' => $closedContacts->keyBy('id'),
                'contact_ids' => $allContactIds->values()->all(),
            ]];
        });

        $allIds = $preliminary->pluck('contact_ids')->flatten()->unique()->filter()->values();

        $contacts = $allIds->isEmpty()
            ? collect()
            : WhatsappContact::query()
                ->whereIn('id', $allIds)
                ->get(['id', 'name', 'phone_number'])
                ->keyBy('id');

        return $preliminary->map(function (array $row) use ($contacts) {
            $clients = collect($row['contact_ids'])->map(function ($contactId) use ($row, $contacts) {
                $contactId = (int) $contactId;
                $contact = $contacts->get($contactId) ?? $row['closed_contacts']->get($contactId);
                $messages = (int) ($row['messages_by_contact'][$contactId] ?? $row['messages_by_contact'][(string) $contactId] ?? 0);
                $agentClosed = $row['closed_contacts']->has($contactId);

                return [
                    'id' => $contactId,
                    'name' => $contact?->name,
                    'phone' => $contact?->phone_number ?? '—',
                    'messages' => $messages,
                    'agent_closed' => $agentClosed,
                ];
            })->sortByDesc(fn ($c) => $c['messages'])->values()->all();

            return [
                'messages_sent' => $row['messages_sent'],
                'clients_served' => $row['clients_served'],
                'agent_requests_closed' => $row['agent_requests_closed'],
                'clients' => $clients,
            ];
        });
    }

    /** @return array{messages_sent:int, clients_served:int, agent_requests_closed:int, clients:array} */
    protected function emptyStats(): array
    {
        return [
            'messages_sent' => 0,
            'clients_served' => 0,
            'agent_requests_closed' => 0,
            'clients' => [],
        ];
    }
}
