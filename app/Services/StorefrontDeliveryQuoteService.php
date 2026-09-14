<?php

namespace App\Services;

use App\Models\BusinessBranch;
use App\Models\WhatsappCart;

/** Cotiza el delivery del micrositio con las mismas distancias y tramos del bot. */
class StorefrontDeliveryQuoteService
{
    public function __construct(
        private readonly GeoDistanceService $distances,
        private readonly DeliveryFeeTierService $fees,
    ) {}

    /**
     * @return array{branch: BusinessBranch, distance_km: float, fee: float, pending_review: bool}|null
     */
    public function nearestForProfile(int $profileId, float $latitude, float $longitude): ?array
    {
        $branch = BusinessBranch::query()
            ->where('business_profile_id', $profileId)
            ->availableForOrders()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('deliveryFeeTiers')
            ->get()
            ->sortBy(fn (BusinessBranch $candidate) => $this->distances->distanceKm(
                (float) $candidate->latitude,
                (float) $candidate->longitude,
                $latitude,
                $longitude,
            ))
            ->first();

        if (! $branch) {
            return null;
        }

        $distanceKm = $this->distances->roadDistanceKm(
            (float) $branch->latitude,
            (float) $branch->longitude,
            $latitude,
            $longitude,
        );
        $tierFee = $this->fees->feeForDistance($branch, $distanceKm);

        return [
            'branch' => $branch,
            'distance_km' => round($distanceKm, 1),
            'fee' => round($tierFee ?? (float) ($branch->delivery_fee_minimum ?? config('delivery.minimum_fee')), 2),
            'pending_review' => $tierFee === null,
        ];
    }

    /** Aplica una cotización recién calculada a un carrito nuevo, una sola vez. */
    public function applyToCart(WhatsappCart $cart, array $quote): void
    {
        $metadata = $cart->metadata ?? [];
        $metadata['delivery_distance_km'] = $quote['distance_km'];
        $metadata['delivery_fee'] = $quote['fee'];
        $metadata['delivery_fee_pending_review'] = $quote['pending_review'];

        if ($quote['pending_review']) {
            $metadata['delivery_fee_pending_since'] = now()->toIso8601String();
            unset($metadata['delivery_fee_applied'], $metadata['delivery_fee_confirmed_at']);
        } else {
            $metadata['delivery_fee_applied'] = $quote['fee'];
            $metadata['delivery_fee_confirmed_at'] = now()->toIso8601String();
            unset($metadata['delivery_fee_pending_since']);
            $cart->total = (float) $cart->total + (float) $quote['fee'];
        }

        $cart->branch_id = $quote['branch']->id;
        $cart->metadata = $metadata;
        $cart->save();
    }
}
