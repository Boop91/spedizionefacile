<?php

/**
 * FILE: TrackingService.php
 * SCOPO: Gestione tracking spedizioni BRT (URL e stato).
 *
 * Estratto da BrtService.php per ridurre la complessita' del servizio principale.
 *
 * DOVE SI USA:
 *   - BrtService.php — delegazione tramite getTrackingUrl(), getTrackingStatus()
 *   - BrtController.php — endpoint HTTP tracking (tramite BrtService)
 *   - SyncBrtTracking.php — comando artisan per sincronizzazione tracking
 */

namespace App\Services\Brt;

use App\Models\Order;

class TrackingService
{
    public function __construct(BrtConfig $config)
    {
        // BrtConfig iniettato per coerenza architetturale e futura espansione
        // (es. API tracking autenticata, webhook tracking, ecc.)
    }

    /**
     * Genera l'URL per seguire il tracking di un pacco BRT.
     * Usa il sistema VAS di BRT che accetta il numero di collo come riferimento.
     * Il tracking permette di vedere dove si trova il pacco in tempo reale.
     *
     * @param  string  $parcelNumber  Il numero di collo BRT (parcelNumberFrom) o parcelId
     */
    public function getTrackingUrl(string $parcelNumber): string
    {
        if (empty($parcelNumber)) {
            return '';
        }

        return 'https://vas.brt.it/vas/sped_det_show.hsm?refnr='.urlencode($parcelNumber).'&tiession=';
    }

    /**
     * Restituisce lo stato di tracking di un ordine BRT.
     * Al momento restituisce un placeholder perche' la sincronizzazione
     * automatica del tracking non e' ancora implementata.
     */
    public function getTrackingStatus(Order $order): array
    {
        $trackingReference = $order->brt_tracking_number
            ?: $order->brt_parcel_id
            ?: $order->brt_numeric_sender_reference;

        if (empty($trackingReference)) {
            return [
                'status' => null,
                'description' => 'Riferimento tracking BRT non disponibile.',
                'brt_event' => 'tracking_reference_missing',
                'tracking_url' => '',
                'error' => 'Riferimento tracking BRT mancante.',
            ];
        }

        return [
            'status' => null,
            'description' => 'Tracking BRT non sincronizzato automaticamente in questa installazione.',
            'brt_event' => 'tracking_sync_unavailable',
            'tracking_url' => $order->brt_tracking_url ?: $this->getTrackingUrl((string) $trackingReference),
            'error' => null,
        ];
    }
}
