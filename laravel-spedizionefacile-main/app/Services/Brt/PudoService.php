<?php

/**
 * FILE: PudoService.php
 * SCOPO: Ricerca e gestione punti PUDO (Pick Up Drop Off) BRT.
 *
 * Estratto da BrtService.php per ridurre la complessita' del servizio principale.
 * Contiene tutta la logica di ricerca PUDO: per indirizzo, coordinate, fallback DB,
 * geocoding, griglia geografica, deduplicazione e ordinamento.
 *
 * DOVE SI USA:
 *   - BrtService.php — delegazione tramite getPudoByAddress(), getPudoByCoordinates(), getPudoDetails()
 *   - BrtController.php — endpoint HTTP PUDO (tramite BrtService)
 *
 * VINCOLI:
 *   - Richiede BrtConfig con pudoApiUrl e pudoToken configurati
 *   - La tabella pudo_points deve esistere per il fallback database
 *   - La tabella locations deve esistere per la risoluzione CAP alternativi
 */

namespace App\Services\Brt;

use App\Models\Location;
use App\Models\PudoPoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PudoService
{
    private string $pudoApiUrl;
    private string $pudoToken;

    public function __construct(BrtConfig $config)
    {
        $this->pudoApiUrl = $config->pudoApiUrl;
        $this->pudoToken = $config->pudoToken;
    }

    /**
     * Cerca punti PUDO (Pick Up Drop Off) per indirizzo.
     * I PUDO sono negozi convenzionati (tabaccai, edicole, ecc.) dove si possono
     * ritirare o consegnare i pacchi. Comodo per chi non e' a casa durante la consegna.
     *
     * Cerca in un raggio esteso (fino a 50 km) con strategia multi-pass dall'indirizzo specificato.
     * FALLBACK: Se l'API BRT fallisce, usa il database locale con punti PUDO mock.
     */
    public function getPudoByAddress(string $address, string $zipCode, string $city, string $countryCode = 'ITA', int $maxResults = 50): array
    {
        $address = trim($address);
        $zipCode = preg_replace('/\D/', '', (string) $zipCode);
        $city = trim($city);
        $maxResults = max(1, min($maxResults, 50));
        $coverageKm = 80;

        $strategyUsed = [];
        $combinedPoints = [];
        $fallbackUsed = false;
        $geocodedSeed = null;

        $mergePoints = function (array $points) use (&$combinedPoints, $maxResults): void {
            if (empty($points)) {
                return;
            }
            $combinedPoints = $this->mergePudoPoints($combinedPoints, $points, $maxResults);
        };

        // Pass 1: citta + CAP (se disponibili entrambi)
        if ($city !== '' && $zipCode !== '') {
            $strategyUsed[] = 'city_zip';
            $primaryResult = $this->queryPudoByAddressNoFallback($address, $zipCode, $city, $countryCode, $maxResults);
            if (! empty($primaryResult['pudo'])) {
                $mergePoints($primaryResult['pudo']);
            }
        }

        // Pass 2: citta con CAP alternativi trovati nella tabella localita'
        if (count($combinedPoints) < $maxResults && $city !== '') {
            $alternativeZips = $this->resolveAlternativeZipsForCity($city, $zipCode);
            if (! empty($alternativeZips)) {
                $strategyUsed[] = 'city_alt_zip';
                foreach ($alternativeZips as $alternativeZip) {
                    if (count($combinedPoints) >= $maxResults) {
                        break;
                    }
                    $altResult = $this->queryPudoByAddressNoFallback($address, $alternativeZip, $city, $countryCode, $maxResults);
                    if (! empty($altResult['pudo'])) {
                        $mergePoints($altResult['pudo']);
                    }
                }
            }
        }

        // Pass 2b: solo citta (se ancora incompleto)
        if (count($combinedPoints) < $maxResults && $city !== '') {
            $strategyUsed[] = 'city_only';
            $cityOnlyResult = $this->queryPudoByAddressNoFallback($address, '', $city, $countryCode, $maxResults);
            if (! empty($cityOnlyResult['pudo'])) {
                $mergePoints($cityOnlyResult['pudo']);
            }
        }

        // Pass 3: solo CAP (utile se la citta non produce match)
        if (count($combinedPoints) < $maxResults && $zipCode !== '') {
            $strategyUsed[] = 'zip_only';
            $zipOnlyResult = $this->queryPudoByAddressNoFallback($address, $zipCode, '', $countryCode, $maxResults);
            if (! empty($zipOnlyResult['pudo'])) {
                $mergePoints($zipOnlyResult['pudo']);
            }
        }

        // Pass 4: nearby da coordinate geocodificate dell'input testuale
        if (count($combinedPoints) < $maxResults) {
            $geocodedSeed = $this->geocodeInputToCoordinates($address, $city, $zipCode);
            if ($geocodedSeed) {
                $strategyUsed[] = 'nearby_geo_input';
                $nearbyResult = $this->getPudoByCoordinates(
                    (float) $geocodedSeed['latitude'],
                    (float) $geocodedSeed['longitude'],
                    $maxResults
                );
                if (! empty($nearbyResult['pudo'])) {
                    $mergePoints($nearbyResult['pudo']);
                    if (! empty($nearbyResult['fallback'])) {
                        $fallbackUsed = true;
                    }
                }
            }
        }

        // Pass 5: griglia geografica attorno al seed per aumentare la copertura in citta piccole.
        if (
            count($combinedPoints) < min($maxResults, 30) &&
            is_array($geocodedSeed) &&
            isset($geocodedSeed['latitude'], $geocodedSeed['longitude'])
        ) {
            $strategyUsed[] = 'nearby_geo_grid';
            $gridPoints = $this->buildGeoGridSearchPoints((float) $geocodedSeed['latitude'], (float) $geocodedSeed['longitude']);
            $gridBatchResults = min($maxResults, 30);

            foreach ($gridPoints as $gridPoint) {
                if (count($combinedPoints) >= $maxResults) {
                    break;
                }

                $gridNearbyResult = $this->getPudoByCoordinates(
                    (float) $gridPoint['latitude'],
                    (float) $gridPoint['longitude'],
                    $gridBatchResults
                );

                if (! empty($gridNearbyResult['pudo'])) {
                    $mergePoints($gridNearbyResult['pudo']);
                    if (! empty($gridNearbyResult['fallback'])) {
                        $fallbackUsed = true;
                    }
                }
            }
        }

        // Fallback finale database locale
        if (empty($combinedPoints)) {
            $fallbackResult = $this->getPudoFromDatabase($city, $zipCode, $maxResults);
            if (! empty($fallbackResult['pudo'])) {
                $mergePoints($fallbackResult['pudo']);
                $fallbackUsed = true;
                $strategyUsed[] = 'fallback_db';
            }
        }

        $combinedPoints = array_values(array_filter($combinedPoints, function ($point) {
            $provider = strtoupper(trim((string) ($point['provider'] ?? 'BRT')));

            return $provider === '' || $provider === 'BRT';
        }));
        $combinedPoints = array_map(function ($point) {
            $point['provider'] = 'BRT';

            return $point;
        }, $combinedPoints);

        $combinedPoints = $this->sortPudoByDistance($combinedPoints);
        if (count($combinedPoints) > $maxResults) {
            $combinedPoints = array_slice($combinedPoints, 0, $maxResults);
        }

        if (empty($combinedPoints)) {
            return [
                'success' => false,
                'error' => 'Nessun punto PUDO trovato per i dati inseriti.',
                'pudo' => [],
                'fallback' => $fallbackUsed,
                'meta' => [
                    'strategy_used' => array_values(array_unique($strategyUsed)),
                    'search_passes' => count(array_unique($strategyUsed)),
                    'coverage_km' => $coverageKm,
                    'returned_count' => 0,
                    'requested_count' => $maxResults,
                    'fallback' => $fallbackUsed,
                    'provider' => 'BRT',
                ],
            ];
        }

        return [
            'success' => true,
            'pudo' => $combinedPoints,
            'fallback' => $fallbackUsed,
            'meta' => [
                'strategy_used' => array_values(array_unique($strategyUsed)),
                'search_passes' => count(array_unique($strategyUsed)),
                'coverage_km' => $coverageKm,
                'returned_count' => count($combinedPoints),
                'requested_count' => $maxResults,
                'fallback' => $fallbackUsed,
                'provider' => 'BRT',
            ],
        ];
    }

    /**
     * Cerca punti PUDO per coordinate GPS (latitudine e longitudine).
     * Utile quando l'utente condivide la propria posizione dal telefono.
     * FALLBACK: Se l'API BRT fallisce, usa il database locale con punti PUDO mock.
     */
    public function getPudoByCoordinates(float $latitude, float $longitude, int $maxResults = 50): array
    {
        $maxResults = max(1, min($maxResults, 50));

        try {
            // withoutVerifying(): certificato self-signed BRT (vedi searchPudo)
            $response = Http::timeout(15)
                ->withoutVerifying()
                ->withHeaders([
                    'X-API-Auth' => $this->pudoToken,
                    'Accept' => 'application/json',
                ])
                ->get($this->pudoApiUrl.'/pudo/v1/open/pickup/get-pudo-by-lat-lng', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'max_pudo_number' => $maxResults,
                    'maxDistanceSearch' => 50000,        // Raggio di ricerca in metri (max 50000)
                ]);

            $body = $response->json();

            if (! $response->successful()) {
                Log::warning('BRT PUDO coordinates API error - using fallback', [
                    'status' => $response->status(),
                    'lat' => $latitude,
                    'lng' => $longitude,
                ]);

                // FALLBACK: usa database locale
                return $this->getPudoFromDatabaseByCoordinates($latitude, $longitude, $maxResults);
            }

            $pudoList = $body['pudo'] ?? [];

            // Se l'API non restituisce risultati, prova il fallback
            if (empty($pudoList)) {
                Log::info('BRT PUDO coordinates API returned no results - using fallback', ['lat' => $latitude, 'lng' => $longitude]);

                return $this->getPudoFromDatabaseByCoordinates($latitude, $longitude, $maxResults);
            }

            return [
                'success' => true,
                'pudo' => array_map(fn ($p) => [
                    'pudo_id' => $p['pudoId'] ?? '',
                    'carrier_pudo_id' => $p['carrierPudoId'] ?? '',
                    'name' => $p['pointName'] ?? '',
                    'address' => $p['fullAddress'] ?? (($p['street'] ?? '').' '.($p['streetNumber'] ?? '')),
                    'city' => $p['town'] ?? '',
                    'zip_code' => $p['zipCode'] ?? '',
                    'province' => $p['state'] ?? '',
                    'country' => $p['country'] ?? 'ITA',
                    'latitude' => $p['latitude'] ?? null,
                    'longitude' => $p['longitude'] ?? null,
                    'distance_meters' => $p['distanceFromPoint'] ?? null,
                    'enabled' => $p['enabled'] ?? true,
                    'opening_hours' => $p['hours'] ?? [],
                    'localization_hint' => $p['localizationHint'] ?? '',
                    'provider' => 'BRT',
                ], $pudoList),
                'fallback' => false,
                'meta' => [
                    'strategy_used' => ['nearby_geo'],
                    'returned_count' => count($pudoList),
                    'requested_count' => $maxResults,
                    'fallback' => false,
                    'provider' => 'BRT',
                ],
            ];
        } catch (\Exception $e) {
            Log::error('BRT PUDO coordinates exception - using fallback', ['error' => $e->getMessage(), 'lat' => $latitude, 'lng' => $longitude]);

            // FALLBACK: usa database locale
            return $this->getPudoFromDatabaseByCoordinates($latitude, $longitude, $maxResults);
        }
    }

    /**
     * Mostra i dettagli di un punto PUDO specifico (orari completi, servizi disponibili, ecc.).
     */
    public function getPudoDetails(string $pudoId): array
    {
        try {
            // withoutVerifying(): certificato self-signed BRT (vedi searchPudo)
            $response = Http::timeout(15)
                ->withoutVerifying()
                ->withHeaders([
                    'X-API-Auth' => $this->pudoToken,
                    'Accept' => 'application/json',
                ])
                ->get($this->pudoApiUrl.'/pudo/v1/open/pickup/get-pudo-details', [
                    'pudoId' => $pudoId,
                ]);

            $body = $response->json();

            if (! $response->successful()) {
                return ['success' => false, 'error' => 'Errore PUDO details API'];
            }

            return ['success' => true, 'pudo' => $body];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Chiamata singola API PUDO senza fallback automatico.
     */
    private function queryPudoByAddressNoFallback(string $address, string $zipCode, string $city, string $countryCode, int $maxResults): array
    {
        try {
            $headers = ['Accept' => 'application/json'];
            if (! empty($this->pudoToken)) {
                $headers['X-API-Auth'] = $this->pudoToken;
            }

            $response = Http::timeout(15)
                ->withoutVerifying()
                ->withHeaders($headers)
                ->get($this->pudoApiUrl.'/pudo/v1/open/pickup/get-pudo-by-address', [
                    'address' => $address,
                    'zipCode' => $zipCode,
                    'city' => $city,
                    'countryCode' => $countryCode,
                    'max_pudo_number' => max(1, min($maxResults, 50)),
                    'maxDistanceSearch' => 80000,
                ]);

            if (! $response->successful()) {
                Log::warning('BRT PUDO API error (no fallback pass)', [
                    'status' => $response->status(),
                    'city' => $city,
                    'zip' => $zipCode,
                ]);

                return ['success' => false, 'pudo' => []];
            }

            $body = $response->json();
            $pudoList = $body['pudo'] ?? [];
            if (empty($pudoList)) {
                return ['success' => true, 'pudo' => []];
            }

            return [
                'success' => true,
                'pudo' => array_map(fn ($item) => $this->mapBrtPudoPoint($item), $pudoList),
            ];
        } catch (\Exception $e) {
            Log::warning('BRT PUDO API exception (no fallback pass)', [
                'error' => $e->getMessage(),
                'city' => $city,
                'zip' => $zipCode,
            ]);

            return ['success' => false, 'pudo' => []];
        }
    }

    /**
     * Mappa payload PUDO BRT al formato usato dal frontend.
     */
    private function mapBrtPudoPoint(array $point): array
    {
        return [
            'pudo_id' => $point['pudoId'] ?? '',
            'carrier_pudo_id' => $point['carrierPudoId'] ?? '',
            'name' => $point['pointName'] ?? '',
            'address' => $point['fullAddress'] ?? trim(($point['street'] ?? '').' '.($point['streetNumber'] ?? '')),
            'city' => $point['town'] ?? '',
            'zip_code' => $point['zipCode'] ?? '',
            'province' => $point['state'] ?? '',
            'country' => $point['country'] ?? 'ITA',
            'latitude' => $point['latitude'] ?? null,
            'longitude' => $point['longitude'] ?? null,
            'distance_meters' => isset($point['distanceFromPoint']) ? (int) round((float) $point['distanceFromPoint']) : null,
            'enabled' => $point['enabled'] ?? true,
            'opening_hours' => $point['hours'] ?? [],
            'localization_hint' => $point['localizationHint'] ?? '',
            'provider' => 'BRT',
        ];
    }

    /**
     * Geocodifica input testuale (via/citta/CAP) in coordinate per pass nearby.
     */
    private function geocodeInputToCoordinates(string $address, string $city, string $zipCode): ?array
    {
        try {
            $normalizedZipCode = preg_replace('/\D/', '', (string) $zipCode);
            if (trim($address) === '' && trim($city) === '' && $normalizedZipCode === '') {
                return null;
            }

            $parts = array_values(array_filter([
                trim($address),
                $normalizedZipCode,
                trim($city),
                'Italia',
            ], fn ($value) => (string) $value !== ''));

            $response = Http::timeout(8)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => 'SpediamoFacile/1.0 (PUDO geocode)',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'q' => implode(', ', $parts),
                ]);

            if (! $response->successful()) {
                return null;
            }

            $payload = $response->json();
            $first = is_array($payload) ? ($payload[0] ?? null) : null;
            if (! $first) {
                return null;
            }

            if (! isset($first['lat'], $first['lon']) || ! is_numeric($first['lat']) || ! is_numeric($first['lon'])) {
                return null;
            }
            $lat = (float) $first['lat'];
            $lng = (float) $first['lon'];

            return [
                'latitude' => $lat,
                'longitude' => $lng,
            ];
        } catch (\Exception $e) {
            Log::debug('PUDO geocode input failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Estrae CAP alternativi affidabili dalla tabella localita' per una citta.
     */
    private function resolveAlternativeZipsForCity(string $city, string $currentZip = ''): array
    {
        try {
            $normalizedCity = mb_strtoupper(trim($city), 'UTF-8');
            if ($normalizedCity === '') {
                return [];
            }

            $exact = Location::query()
                ->whereRaw('UPPER(place_name) = ?', [$normalizedCity])
                ->pluck('postal_code')
                ->map(fn ($zip) => preg_replace('/\D/', '', (string) $zip))
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $zips = $exact;
            if (empty($zips)) {
                $zips = Location::query()
                    ->whereRaw('UPPER(place_name) LIKE ?', [$normalizedCity.'%'])
                    ->limit(100)
                    ->pluck('postal_code')
                    ->map(fn ($zip) => preg_replace('/\D/', '', (string) $zip))
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();
            }

            $currentZip = preg_replace('/\D/', '', (string) $currentZip);
            if ($currentZip !== '') {
                $zips = array_values(array_filter($zips, fn ($zip) => $zip !== $currentZip));
            }

            return array_slice($zips, 0, 8);
        } catch (\Exception $e) {
            Log::warning('PUDO alternative ZIP resolution failed', [
                'city' => $city,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Merge + deduplica punti PUDO per id (o fallback chiave composita).
     */
    private function mergePudoPoints(array $base, array $incoming, int $maxResults): array
    {
        $combined = array_merge($base, $incoming);
        $deduped = $this->dedupePudoPoints($combined);
        $sorted = $this->sortPudoByDistance($deduped);

        return array_slice($sorted, 0, max(1, min($maxResults, 50)));
    }

    private function dedupePudoPoints(array $points): array
    {
        $map = [];
        foreach ($points as $point) {
            $key = (string) ($point['pudo_id'] ?? '');
            if ($key === '') {
                $lat = isset($point['latitude']) && is_numeric($point['latitude']) ? number_format((float) $point['latitude'], 6, '.', '') : 'na';
                $lng = isset($point['longitude']) && is_numeric($point['longitude']) ? number_format((float) $point['longitude'], 6, '.', '') : 'na';
                $key = sprintf(
                    '%s|%s|%s|%s|%s|%s',
                    strtolower((string) ($point['name'] ?? '')),
                    strtolower((string) ($point['address'] ?? '')),
                    strtolower((string) ($point['zip_code'] ?? '')),
                    strtolower((string) ($point['city'] ?? '')),
                    $lat,
                    $lng
                );
            }

            if (! isset($map[$key])) {
                $map[$key] = $point;

                continue;
            }

            $currentDistance = isset($map[$key]['distance_meters']) && is_numeric($map[$key]['distance_meters'])
                ? (float) $map[$key]['distance_meters']
                : INF;
            $nextDistance = isset($point['distance_meters']) && is_numeric($point['distance_meters'])
                ? (float) $point['distance_meters']
                : INF;

            if ($nextDistance < $currentDistance) {
                $map[$key] = $point;
            }
        }

        return array_values($map);
    }

    private function sortPudoByDistance(array $points): array
    {
        usort($points, function ($a, $b) {
            $aDistance = isset($a['distance_meters']) && is_numeric($a['distance_meters']) ? (float) $a['distance_meters'] : INF;
            $bDistance = isset($b['distance_meters']) && is_numeric($b['distance_meters']) ? (float) $b['distance_meters'] : INF;

            if ($aDistance === $bDistance) {
                return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            }

            return $aDistance <=> $bDistance;
        });

        return $points;
    }

    private function buildGeoGridSearchPoints(float $latitude, float $longitude): array
    {
        $latKmFactor = 110.574;
        $lngKmFactor = max(111.320 * cos(deg2rad($latitude)), 30.0);

        $distancesKm = [40, 75];
        $directions = [
            [1, 0], [-1, 0], [0, 1], [0, -1],
            [1, 1], [1, -1], [-1, 1], [-1, -1],
        ];

        $points = [];
        foreach ($distancesKm as $distanceKm) {
            foreach ($directions as [$latDirection, $lngDirection]) {
                // Nel ring esterno manteniamo i diagonali solo per evitare troppe chiamate.
                if ($distanceKm >= 75 && abs($latDirection) + abs($lngDirection) === 2) {
                    continue;
                }

                $latDelta = ($distanceKm / $latKmFactor) * $latDirection;
                $lngDelta = ($distanceKm / $lngKmFactor) * $lngDirection;
                $candidateLat = $latitude + $latDelta;
                $candidateLng = $longitude + $lngDelta;
                $key = sprintf('%.5f|%.5f', $candidateLat, $candidateLng);
                $points[$key] = [
                    'latitude' => $candidateLat,
                    'longitude' => $candidateLng,
                ];
            }
        }

        return array_values($points);
    }

    /**
     * FALLBACK: Cerca punti PUDO nel database locale quando l'API BRT non funziona.
     * Usa la tabella pudo_points popolata con dati mock delle citta' principali.
     */
    private function getPudoFromDatabase(string $city, string $zipCode, int $maxResults): array
    {
        try {
            $points = PudoPoint::searchByLocation($city, $zipCode, $maxResults);
            if (empty($points)) {
                $points = $this->getStaticFallbackPudoByLocation($city, $zipCode, $maxResults);
            }

            Log::info('PUDO fallback database search', [
                'city' => $city,
                'zip' => $zipCode,
                'results' => count($points),
            ]);

            return [
                'success' => true,
                'pudo' => array_map(fn ($p) => [
                    'pudo_id' => $p['id'] ?? $p['pudo_id'] ?? '',
                    'carrier_pudo_id' => $p['id'] ?? $p['carrier_pudo_id'] ?? $p['pudo_id'] ?? '',
                    'name' => $p['name'] ?? '',
                    'address' => $p['address'] ?? '',
                    'city' => $p['city'] ?? '',
                    'zip_code' => $p['zip_code'] ?? '',
                    'province' => $p['province'] ?? '',
                    'country' => $p['country'] ?? 'ITA',
                    'latitude' => $p['latitude'] ?? null,
                    'longitude' => $p['longitude'] ?? null,
                    'distance_meters' => isset($p['distance'])
                        ? (int) round(((float) $p['distance']) * 1000)
                        : (isset($p['distance_meters']) ? (int) $p['distance_meters'] : null),
                    'enabled' => true,
                    'opening_hours' => $p['opening_hours'] ?? [],
                    'localization_hint' => '',
                    'provider' => 'BRT',
                ], $points),
                'fallback' => true,
                'meta' => [
                    'strategy_used' => ['fallback_db'],
                    'returned_count' => count($points),
                    'requested_count' => $maxResults,
                    'fallback' => true,
                    'provider' => 'BRT',
                ],
            ];
        } catch (\Exception $e) {
            Log::error('PUDO fallback database error', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => 'Nessun punto PUDO disponibile al momento.', 'pudo' => []];
        }
    }

    /**
     * FALLBACK: Cerca punti PUDO nel database locale per coordinate GPS.
     */
    private function getPudoFromDatabaseByCoordinates(float $latitude, float $longitude, int $maxResults): array
    {
        try {
            $points = PudoPoint::searchByCoordinates($latitude, $longitude, $maxResults);
            if (empty($points)) {
                $points = $this->getStaticFallbackPudoByCoordinates($latitude, $longitude, $maxResults);
            }

            Log::info('PUDO fallback database search by coordinates', [
                'lat' => $latitude,
                'lng' => $longitude,
                'results' => count($points),
            ]);

            return [
                'success' => true,
                'pudo' => array_map(fn ($p) => [
                    'pudo_id' => $p['id'] ?? $p['pudo_id'] ?? '',
                    'carrier_pudo_id' => $p['id'] ?? $p['carrier_pudo_id'] ?? $p['pudo_id'] ?? '',
                    'name' => $p['name'] ?? '',
                    'address' => $p['address'] ?? '',
                    'city' => $p['city'] ?? '',
                    'zip_code' => $p['zip_code'] ?? '',
                    'province' => $p['province'] ?? '',
                    'country' => $p['country'] ?? 'ITA',
                    'latitude' => $p['latitude'] ?? null,
                    'longitude' => $p['longitude'] ?? null,
                    'distance_meters' => isset($p['distance'])
                        ? (int) round(((float) $p['distance']) * 1000)
                        : (isset($p['distance_meters']) ? (int) $p['distance_meters'] : null),
                    'enabled' => true,
                    'opening_hours' => $p['opening_hours'] ?? [],
                    'localization_hint' => '',
                    'provider' => 'BRT',
                ], $points),
                'fallback' => true,
                'meta' => [
                    'strategy_used' => ['fallback_db_coordinates'],
                    'returned_count' => count($points),
                    'requested_count' => $maxResults,
                    'fallback' => true,
                    'provider' => 'BRT',
                ],
            ];
        } catch (\Exception $e) {
            Log::error('PUDO fallback database error (coordinates)', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => 'Nessun punto PUDO disponibile al momento.', 'pudo' => []];
        }
    }

    private function getStaticFallbackPudoByLocation(string $city, string $zipCode, int $maxResults): array
    {
        $cityNeedle = mb_strtolower(trim($city));
        $zipNeedle = preg_replace('/\D/', '', (string) $zipCode);

        $points = array_filter($this->staticFallbackPudoCatalog(), function (array $point) use ($cityNeedle, $zipNeedle) {
            $pointCity = mb_strtolower((string) ($point['city'] ?? ''));
            $pointZip = preg_replace('/\D/', '', (string) ($point['zip_code'] ?? ''));

            if ($cityNeedle !== '' && str_contains($pointCity, $cityNeedle)) {
                return true;
            }

            if ($zipNeedle !== '' && $pointZip === $zipNeedle) {
                return true;
            }

            return false;
        });

        return array_values(array_slice($points, 0, $maxResults));
    }

    private function getStaticFallbackPudoByCoordinates(float $latitude, float $longitude, int $maxResults): array
    {
        $points = array_map(function (array $point) use ($latitude, $longitude) {
            $distanceKm = $this->distanceKm($latitude, $longitude, (float) $point['latitude'], (float) $point['longitude']);
            $point['distance'] = round($distanceKm, 2);
            $point['distance_meters'] = (int) round($distanceKm * 1000);

            return $point;
        }, $this->staticFallbackPudoCatalog());

        $points = array_filter($points, fn (array $point) => ($point['distance'] ?? 9999) <= 80);

        usort($points, fn (array $a, array $b) => ($a['distance'] ?? 9999) <=> ($b['distance'] ?? 9999));

        return array_values(array_slice($points, 0, $maxResults));
    }

    private function staticFallbackPudoCatalog(): array
    {
        return [
            [
                'id' => 'PUDO_SU_IGL_001',
                'name' => 'Tabacchi Iglesias Centro',
                'address' => 'Via Roma 57',
                'city' => 'Iglesias',
                'zip_code' => '09016',
                'province' => 'SU',
                'country' => 'ITA',
                'latitude' => 39.3113,
                'longitude' => 8.5365,
                'opening_hours' => ['monday' => '09:00-19:00', 'tuesday' => '09:00-19:00', 'wednesday' => '09:00-19:00', 'thursday' => '09:00-19:00', 'friday' => '09:00-19:00', 'saturday' => '09:00-13:00', 'sunday' => 'Chiuso'],
                'provider' => 'BRT',
            ],
            [
                'id' => 'PUDO_SU_IGL_002',
                'name' => 'Edicola Santa Barbara',
                'address' => 'Via Cattaneo 21',
                'city' => 'Iglesias',
                'zip_code' => '09016',
                'province' => 'SU',
                'country' => 'ITA',
                'latitude' => 39.3178,
                'longitude' => 8.5288,
                'opening_hours' => ['monday' => '09:00-19:00', 'tuesday' => '09:00-19:00', 'wednesday' => '09:00-19:00', 'thursday' => '09:00-19:00', 'friday' => '09:00-19:00', 'saturday' => '09:00-13:00', 'sunday' => 'Chiuso'],
                'provider' => 'BRT',
            ],
            [
                'id' => 'PUDO_SU_IGL_003',
                'name' => 'Punto BRT Iglesias Stazione',
                'address' => 'Piazza Repubblica 8',
                'city' => 'Iglesias',
                'zip_code' => '09016',
                'province' => 'SU',
                'country' => 'ITA',
                'latitude' => 39.3097,
                'longitude' => 8.5281,
                'opening_hours' => ['monday' => '09:00-19:00', 'tuesday' => '09:00-19:00', 'wednesday' => '09:00-19:00', 'thursday' => '09:00-19:00', 'friday' => '09:00-19:00', 'saturday' => '09:00-13:00', 'sunday' => 'Chiuso'],
                'provider' => 'BRT',
            ],
            [
                'id' => 'PUDO_SU_CAR_001',
                'name' => 'Punto BRT Carbonia Centro',
                'address' => 'Via Roma 102',
                'city' => 'Carbonia',
                'zip_code' => '09013',
                'province' => 'SU',
                'country' => 'ITA',
                'latitude' => 39.1672,
                'longitude' => 8.5239,
                'opening_hours' => ['monday' => '09:00-19:00', 'tuesday' => '09:00-19:00', 'wednesday' => '09:00-19:00', 'thursday' => '09:00-19:00', 'friday' => '09:00-19:00', 'saturday' => '09:00-13:00', 'sunday' => 'Chiuso'],
                'provider' => 'BRT',
            ],
            [
                'id' => 'PUDO_SU_GON_001',
                'name' => 'Tabacchi Gonnesa',
                'address' => 'Via Gramsci 18',
                'city' => 'Gonnesa',
                'zip_code' => '09010',
                'province' => 'SU',
                'country' => 'ITA',
                'latitude' => 39.2646,
                'longitude' => 8.4702,
                'opening_hours' => ['monday' => '09:00-19:00', 'tuesday' => '09:00-19:00', 'wednesday' => '09:00-19:00', 'thursday' => '09:00-19:00', 'friday' => '09:00-19:00', 'saturday' => '09:00-13:00', 'sunday' => 'Chiuso'],
                'provider' => 'BRT',
            ],
            [
                'id' => 'PUDO_SU_DOM_001',
                'name' => 'Cartoleria Domusnovas',
                'address' => 'Via Cagliari 44',
                'city' => 'Domusnovas',
                'zip_code' => '09015',
                'province' => 'SU',
                'country' => 'ITA',
                'latitude' => 39.3238,
                'longitude' => 8.6490,
                'opening_hours' => ['monday' => '09:00-19:00', 'tuesday' => '09:00-19:00', 'wednesday' => '09:00-19:00', 'thursday' => '09:00-19:00', 'friday' => '09:00-19:00', 'saturday' => '09:00-13:00', 'sunday' => 'Chiuso'],
                'provider' => 'BRT',
            ],
            [
                'id' => 'PUDO_SU_FLU_001',
                'name' => 'Punto BRT Fluminimaggiore',
                'address' => 'Via Vittorio Emanuele 73',
                'city' => 'Fluminimaggiore',
                'zip_code' => '09010',
                'province' => 'SU',
                'country' => 'ITA',
                'latitude' => 39.4385,
                'longitude' => 8.4981,
                'opening_hours' => ['monday' => '09:00-19:00', 'tuesday' => '09:00-19:00', 'wednesday' => '09:00-19:00', 'thursday' => '09:00-19:00', 'friday' => '09:00-19:00', 'saturday' => '09:00-13:00', 'sunday' => 'Chiuso'],
                'provider' => 'BRT',
            ],
            [
                'id' => 'PUDO_SU_SAN_001',
                'name' => 'Punto Ritiro Sant Antioco',
                'address' => 'Corso Vittorio Emanuele 95',
                'city' => "Sant'Antioco",
                'zip_code' => '09017',
                'province' => 'SU',
                'country' => 'ITA',
                'latitude' => 39.0699,
                'longitude' => 8.4520,
                'opening_hours' => ['monday' => '09:00-19:00', 'tuesday' => '09:00-19:00', 'wednesday' => '09:00-19:00', 'thursday' => '09:00-19:00', 'friday' => '09:00-19:00', 'saturday' => '09:00-13:00', 'sunday' => 'Chiuso'],
                'provider' => 'BRT',
            ],
        ];
    }

    /**
     * Calcola la distanza in km tra due punti GPS usando la formula di Haversine.
     */
    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $latFrom = deg2rad($lat1);
        $lngFrom = deg2rad($lng1);
        $latTo = deg2rad($lat2);
        $lngTo = deg2rad($lng2);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lngDelta / 2), 2)
        ));

        return $angle * $earthRadius;
    }
}
