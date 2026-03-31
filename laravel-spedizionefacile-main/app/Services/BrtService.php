<?php

/**
 * FILE: BrtService.php
 * SCOPO: Comunicazione con le API BRT per spedizioni, etichette, tracking e punti PUDO.
 *
 * DOVE SI USA:
 *   - BrtController.php — endpoint HTTP per operazioni BRT
 *   - GenerateBrtLabel.php — listener che genera etichetta automaticamente dopo pagamento
 *   - AdminController.php — regenerateLabel per rigenerazione manuale admin
 *
 * DATI IN INGRESSO:
 *   - Order (con pacchi e indirizzi) per createShipment
 *     Esempio: $brt->createShipment($order, ['is_cod' => true, 'cod_amount' => 1500])
 *   - Options array: is_cod, cod_amount, cod_payment_type (BM|CC|AS), pudo_id per opzioni spedizione
 *   - numericSenderReference (int) per confirmShipment, deleteShipment
 *   - Indirizzo o coordinate lat/lng per ricerca PUDO
 *
 * DATI IN USCITA:
 *   - Array con success, parcel_id, tracking_url, label_base64, tracking_number, raw_response
 *     Esempio: ['success' => true, 'parcel_id' => '12345', 'label_base64' => 'JVBERi0...']
 *   - Array con punti PUDO (id, nome, indirizzo, coordinate)
 *   - URL di tracking BRT (stringa)
 *
 * VINCOLI:
 *   - Le credenziali BRT (client_id, password) devono essere configurate in config/services.php
 *   - Gli indirizzi devono avere citta', CAP e provincia validi per il routing BRT
 *   - Il CAP deve corrispondere alla citta', altrimenti BRT restituisce errore -63
 *   - Le note BRT sono limitate a 50 caratteri
 *
 * ERRORI TIPICI:
 *   - BRT non configurato: client_id/password vuoti (restituisce success=false)
 *   - Errori API BRT: formato indirizzo non valido, dimensioni fuori range
 *   - SSL: in dev puo' servire verify_ssl=false (config services.brt.verify_ssl)
 *   - Errore -63: citta' non corrisponde al CAP (routing BRT fallito)
 *
 * PUNTI DI MODIFICA SICURI:
 *   - Per aggiungere un nuovo servizio BRT: modificare $serviceMapping in addServicesToPayload()
 *   - Per cambiare il formato dell'etichetta: modificare labelParameters in createShipment()
 *   - Per aggiungere un nuovo paese: aggiungere una riga in countryToIso2()
 *   - Per cambiare il raggio di ricerca PUDO: modificare maxDistanceSearch in getPudoByAddress()
 *
 * COLLEGAMENTI:
 *   - config/services.php — brt.api_url, brt.client_id, brt.password, brt.pudo_token
 *   - app/Http/Controllers/BrtController.php — controller HTTP che delega a questo servizio
 *   - app/Listeners/GenerateBrtLabel.php — generazione automatica post-pagamento
 *   - app/Models/Location.php — tabella localita' usata per normalizzazione indirizzi
 */

namespace App\Services;

use App\Models\Order;
use App\Models\Package;
use App\Services\Brt\AddressNormalizer;
use App\Services\Brt\BrtConfig;
use App\Services\Brt\ErrorTranslator;
use App\Services\Brt\PudoService;
use App\Services\Brt\TrackingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrtService
{
    private string $apiUrl;
    private string $clientId;
    private string $password;
    private int $departureDepot;
    private bool $verifySsl;

    private AddressNormalizer $addressNormalizer;
    private ErrorTranslator $errorTranslator;
    private PudoService $pudoService;
    private TrackingService $trackingService;

    public function __construct()
    {
        $config = new BrtConfig();
        $this->apiUrl = $config->apiUrl;
        $this->clientId = $config->clientId;
        $this->password = $config->password;
        $this->departureDepot = $config->departureDepot;
        $this->verifySsl = $config->verifySsl;
        $this->addressNormalizer = new AddressNormalizer();
        $this->errorTranslator = new ErrorTranslator();
        $this->pudoService = new PudoService($config);
        $this->trackingService = new TrackingService($config);
    }

    /**
     * createShipment — Crea una spedizione BRT e genera l'etichetta PDF.
     *
     * PERCHE': E' il metodo principale del servizio. Prende un ordine con pacchi e indirizzi,
     *   prepara il payload nel formato richiesto da BRT, e invia la richiesta HTTP.
     *
     * COME LEGGERLO:
     *   1. Caricamento dati (loadMissing) e validazione campi obbligatori
     *   2. Normalizzazione indirizzo (citta' maiuscolo, CAP 5 cifre, provincia 2 lettere)
     *   3. Costruzione payload JSON per API BRT
     *   4. Invio richiesta HTTP e parsing risposta
     *   5. Estrazione etichetta PDF e dati tracking dalla risposta
     *
     * COME MODIFICARLO:
     *   - Per aggiungere campi al payload: modificare l'array $payload['createData']
     *   - Per cambiare la logica contrassegno: modificare il blocco if is_cod
     *   - Per cambiare il formato etichetta: modificare labelParameters
     *
     * COSA EVITARE:
     *   - Non rimuovere la normalizzazione indirizzi (causa errore -63 routing BRT)
     *   - Non loggare la password BRT (gia' mascherata nel log)
     *   - Non aumentare il timeout oltre 30s (BRT risponde tipicamente in 5-15s)
     *
     * @param  Order  $order  L'ordine (con pacchi e indirizzi caricati)
     * @param  array  $options  Opzioni aggiuntive: contrassegno (is_cod, cod_amount), punto PUDO, note
     * @return array Risultato con: success, parcel_id, label_base64, tracking_url, error
     */
    public function createShipment(Order $order, array $options = []): array
    {
        // Carichiamo i dati collegati all'ordine (pacchi, indirizzi, utente, servizi)
        $order->loadMissing(['packages.originAddress', 'packages.destinationAddress', 'packages.service', 'user']);

        // Prendiamo il primo pacco dell'ordine (per gli indirizzi)
        $package = $order->packages->first();
        if (! $package) {
            return ['success' => false, 'error' => 'Nessun collo trovato nell\'ordine.'];
        }

        $origin = $package->originAddress;
        $destination = $package->destinationAddress;

        if (! $origin || ! $destination) {
            return ['success' => false, 'error' => 'Indirizzi di partenza o destinazione mancanti.'];
        }

        // Calcoliamo peso totale e dimensioni massime di tutti i pacchi
        $totalWeight = $order->packages->sum(function ($pkg) {
            return (float) preg_replace('/[^0-9.]/', '', $pkg->weight ?? '0');
        });
        $totalParcels = $order->packages->sum(function ($pkg) {
            return max(1, (int) ($pkg->quantity ?? 1));
        });
        // Dimensioni: BRT vuole la dimensione del collo piu' grande (cm)
        $maxLength = $order->packages->max(fn ($pkg) => (int) ($pkg->first_size ?? 0));
        $maxWidth = $order->packages->max(fn ($pkg) => (int) ($pkg->second_size ?? 0));
        $maxHeight = $order->packages->max(fn ($pkg) => (int) ($pkg->third_size ?? 0));

        // Usiamo l'ID dell'ordine come riferimento numerico per BRT
        $numericSenderReference = $order->id;

        // Validazione dati obbligatori prima di inviare a BRT
        $missingFields = [];
        if (empty(trim($destination->name ?? ''))) {
            $missingFields[] = 'nome destinatario';
        }
        if (empty(trim(($destination->address ?? '').' '.($destination->address_number ?? '')))) {
            $missingFields[] = 'indirizzo destinatario';
        }
        if (empty(trim($destination->postal_code ?? ''))) {
            $missingFields[] = 'CAP destinatario';
        }
        if (empty(trim($destination->city ?? ''))) {
            $missingFields[] = 'città destinatario';
        }
        if (empty(trim($destination->province ?? ''))) {
            $missingFields[] = 'provincia destinatario';
        }

        if (! empty($missingFields)) {
            return ['success' => false, 'error' => 'Dati mancanti per BRT: '.implode(', ', $missingFields).'.'];
        }

        // Normalizziamo i dati dell'indirizzo per il formato richiesto da BRT
        // BRT richiede: citta' in MAIUSCOLO, CAP a 5 cifre, provincia a 2 lettere
        $normalizedDest = $this->normalizeAddressForBrt($destination);

        // Prepariamo i dati da inviare a BRT nel formato richiesto dalla loro API
        $payload = [
            'account' => [
                'userID' => $this->clientId,
                'password' => $this->password,
            ],
            'createData' => [
                // departureDepot: risolto automaticamente dal CAP mittente tramite config/brt_filiali.php
                'departureDepot' => $this->resolveFilialeByCap($origin->postal_code ?? ''),
                'senderCustomerCode' => (int) $this->clientId,
                // 'network' rimosso: campo opzionale, stringa vuota causava errori di validazione BRT
                'deliveryFreightTypeCode' => $options['delivery_freight_type'] ?? 'DAP', // DAP = consegnato a destinazione
                'consigneeCompanyName' => $destination->name ?? '',          // Nome del destinatario
                'consigneeAddress' => trim(($destination->address ?? '').' '.($destination->address_number ?? '')),
                'consigneeZIPCode' => $normalizedDest['postal_code'],       // CAP (5 cifre, zero-padded)
                'consigneeCity' => $normalizedDest['city'],                 // Citta' (MAIUSCOLO, normalizzata)
                'consigneeProvinceAbbreviation' => $normalizedDest['province'], // Provincia (sigla a 2 lettere)
                'consigneeCountryAbbreviationISOAlpha2' => $this->countryToIso2($destination->country ?? 'Italia'), // Paese ISO Alpha-2
                'consigneeContactName' => $destination->name ?? '',
                'consigneeTelephone' => $destination->telephone_number ?? '',
                'consigneeEMail' => $destination->email ?? ($order->user->email ?? ''),
                'consigneeMobilePhoneNumber' => $destination->telephone_number ?? '',
                'numberOfParcels' => $totalParcels,                          // Numero di colli
                'weightKG' => max(1, (int) ceil($totalWeight)),              // Peso in kg (minimo 1)
                'packageLength' => max(1, $maxLength),                       // Lunghezza in cm
                'packageWidth' => max(1, $maxWidth),                         // Larghezza in cm
                'packageHeight' => max(1, $maxHeight),                       // Altezza in cm
                'numericSenderReference' => $numericSenderReference,
                'alphanumericSenderReference' => 'SF-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT), // Es. "SF-000042"
                'notes' => $this->buildNotes($order, $options),
                'isAlertRequired' => '1',        // Richiedi notifiche al destinatario
                'isCODMandatory' => '0',         // Contrassegno non obbligatorio (di default)
            ],
            'isLabelRequired' => 1,              // Vogliamo l'etichetta PDF
            'labelParameters' => [
                'outputType' => 'PDF',
                'offsetX' => 0,
                'offsetY' => 0,
                'isBorderRequired' => 0,
                'isLogoRequired' => 1,           // Includi il logo BRT nell'etichetta
                'isBarcodeControlRowRequired' => 1,
            ],
        ];

        // Se la spedizione e' in contrassegno (pagamento alla consegna),
        // aggiungiamo i dati necessari
        if (! empty($options['is_cod']) && ! empty($options['cod_amount'])) {
            $payload['createData']['isCODMandatory'] = '1';
            $payload['createData']['cashOnDelivery'] = (float) ($options['cod_amount'] / 100); // Da centesimi a euro
            $payload['createData']['codPaymentType'] = $options['cod_payment_type'] ?? 'BM';   // BM = Bonifico bancario, CC = Assegno circolare, AS = Assegno bancario
            $payload['createData']['codCurrency'] = 'EUR';
        }

        // Se la consegna e' presso un punto PUDO, aggiungiamo l'ID del punto
        if (! empty($options['pudo_id'])) {
            $payload['createData']['pudoId'] = $options['pudo_id'];
        }

        // Aggiungiamo i servizi/accessori selezionati dall'utente al payload BRT
        $this->addServicesToPayload($payload, $order, $options);

        try {
            // Log del payload inviato (senza password) per debug
            $payloadForLog = $payload;
            $payloadForLog['account']['password'] = '***';
            Log::info('BRT createShipment request', [
                'order_id' => $order->id,
                'payload' => $payloadForLog,
            ]);

            // Inviamo la richiesta alle API BRT (con timeout di 30 secondi)
            $response = Http::withOptions(['verify' => $this->verifySsl])
                ->timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->apiUrl.'/shipment', $payload);

            $body = $response->json();
            $rawBody = $response->body();

            // La risposta BRT puo' avere due formati:
            // 1. { "createResponse": { "executionMessage": {...}, "labels": {...}, ... } }
            // 2. { "executionMessage": {...}, "labels": {...}, ... }
            // Normalizziamo estraendo sempre il contenuto di createResponse se presente
            $responseData = $body['createResponse'] ?? $body;

            // Registriamo la risposta completa nei log per debug
            Log::info('BRT createShipment response', [
                'order_id' => $order->id,
                'http_status' => $response->status(),
                'response_data' => $responseData,
            ]);

            // Se la risposta HTTP non e' positiva, restituiamo l'errore
            if (! $response->successful()) {
                $errorMsg = $responseData['executionMessage']['message'] ?? 'Errore API BRT (HTTP '.$response->status().')';

                return ['success' => false, 'error' => $errorMsg];
            }

            // Controlliamo il codice di esecuzione nella risposta BRT
            // Se e' negativo, c'e' stato un errore
            $execCode = $responseData['executionMessage']['code'] ?? -1;
            if ($execCode < 0) {
                $message = $responseData['executionMessage']['message'] ?? '';
                $codeDesc = $responseData['executionMessage']['codeDesc'] ?? '';

                // Creiamo un messaggio di errore leggibile in italiano
                $errorMsg = $this->translateBrtError($execCode, $codeDesc, $message, $payload['createData'] ?? []);

                // Aggiungiamo dettagli utili per il debug nei log
                Log::warning('BRT createShipment error response', [
                    'order_id' => $order->id,
                    'exec_code' => $execCode,
                    'exec_code_desc' => $codeDesc,
                    'exec_message' => $message,
                    'payload_sent' => [
                        'consigneeCity' => $payload['createData']['consigneeCity'] ?? '',
                        'consigneeZIPCode' => $payload['createData']['consigneeZIPCode'] ?? '',
                        'consigneeProvinceAbbreviation' => $payload['createData']['consigneeProvinceAbbreviation'] ?? '',
                        'consigneeAddress' => $payload['createData']['consigneeAddress'] ?? '',
                        'departureDepot' => $payload['createData']['departureDepot'] ?? 0,
                    ],
                ]);

                return ['success' => false, 'error' => $errorMsg];
            }

            // Estraiamo i dati dell'etichetta dalla risposta
            // BRT restituisce le etichette in: createResponse.labels.label[] (array)
            $parcelId = '';
            $labelBase64 = '';
            $labels = $responseData['labels']['label'] ?? $responseData['labels'] ?? [];
            if (! empty($labels) && is_array($labels)) {
                $firstLabel = $labels[0] ?? null;
                if ($firstLabel) {
                    $parcelId = $firstLabel['parcelID'] ?? $firstLabel['parcelId'] ?? '';
                    $labelBase64 = $firstLabel['stream'] ?? '';
                }
            }

            // Estraiamo i dati di routing/tracking dalla risposta BRT
            // Questi campi si trovano direttamente nella createResponse
            $parcelNumberFrom = (string) ($responseData['parcelNumberFrom'] ?? '');
            $parcelNumberTo = (string) ($responseData['parcelNumberTo'] ?? '');
            $departureDepot = (string) ($responseData['departureDepot'] ?? '');
            $arrivalTerminal = (string) ($responseData['arrivalTerminal'] ?? '');
            $arrivalDepot = (string) ($responseData['arrivalDepot'] ?? '');
            $deliveryZone = (string) ($responseData['deliveryZone'] ?? '');
            $seriesNumber = (string) ($responseData['seriesNumber'] ?? '');
            $serviceType = (string) ($responseData['serviceType'] ?? '');

            // Il numero di tracking principale e' parcelNumberFrom
            // Se non presente, usiamo il parcelId dall'etichetta come fallback
            $trackingNumber = $parcelNumberFrom ?: $parcelId;

            // URL di tracking BRT usando il formato VAS (Visual Automated System)
            // che accetta il numero di collo (parcelNumber) come riferimento
            $trackingUrl = '';
            if ($trackingNumber) {
                $trackingUrl = 'https://vas.brt.it/vas/sped_det_show.hsm?refnr='.urlencode($trackingNumber).'&tiession=';
            }

            Log::info('BRT createShipment tracking data extracted', [
                'order_id' => $order->id,
                'parcel_id' => $parcelId,
                'tracking_number' => $trackingNumber,
                'parcel_number_from' => $parcelNumberFrom,
                'parcel_number_to' => $parcelNumberTo,
                'departure_depot' => $departureDepot,
                'arrival_terminal' => $arrivalTerminal,
                'arrival_depot' => $arrivalDepot,
                'delivery_zone' => $deliveryZone,
                'series_number' => $seriesNumber,
                'service_type' => $serviceType,
            ]);

            return [
                'success' => true,
                'parcel_id' => $parcelId,
                'numeric_sender_reference' => $numericSenderReference,
                'label_base64' => $labelBase64,
                'tracking_url' => $trackingUrl,
                'tracking_number' => $trackingNumber,
                'parcel_number_from' => $parcelNumberFrom,
                'parcel_number_to' => $parcelNumberTo,
                'departure_depot' => $departureDepot,
                'arrival_terminal' => $arrivalTerminal,
                'arrival_depot' => $arrivalDepot,
                'delivery_zone' => $deliveryZone,
                'series_number' => $seriesNumber,
                'service_type' => $serviceType,
                'raw_response' => $body,
            ];
        } catch (\Exception $e) {
            // Se c'e' un errore di connessione o altro, lo registriamo e restituiamo l'errore
            Log::error('BRT createShipment exception', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'Errore di connessione BRT: '.$e->getMessage()];
        }
    }

    /**
     * Test di creazione spedizione BRT senza ordine reale.
     * Invia una richiesta di test all'API BRT e restituisce la risposta completa.
     */
    public function testCreateShipment(array $data): array
    {
        $numericSenderReference = (int) (time() % 1000000000);

        // Normalizziamo i dati anche nel test per coerenza
        $testAddress = (object) [
            'city' => $data['consignee_city'] ?? '',
            'postal_code' => $data['consignee_zip'] ?? '',
            'province' => $data['consignee_province'] ?? '',
        ];
        $normalizedTest = $this->normalizeAddressForBrt($testAddress);

        $payload = [
            'account' => [
                'userID' => $this->clientId,
                'password' => $this->password,
            ],
            'createData' => [
                'departureDepot' => $this->departureDepot,
                'senderCustomerCode' => (int) $this->clientId,
                'deliveryFreightTypeCode' => 'DAP',
                'consigneeCompanyName' => $data['consignee_name'],
                'consigneeAddress' => $data['consignee_address'],
                'consigneeZIPCode' => $normalizedTest['postal_code'],
                'consigneeCity' => $normalizedTest['city'],
                'consigneeProvinceAbbreviation' => $normalizedTest['province'],
                'consigneeCountryAbbreviationISOAlpha2' => $data['consignee_country'],
                'consigneeContactName' => $data['consignee_name'],
                'consigneeTelephone' => $data['consignee_phone'] ?? '',
                'consigneeEMail' => $data['consignee_email'] ?? '',
                'consigneeMobilePhoneNumber' => $data['consignee_phone'] ?? '',
                'numberOfParcels' => (int) ($data['parcels'] ?? 1),
                'weightKG' => max(1, (int) ($data['weight_kg'] ?? 1)),
                'numericSenderReference' => $numericSenderReference,
                'alphanumericSenderReference' => 'TEST-'.$numericSenderReference,
                'notes' => $data['notes'] ?? 'Test SpediamoFacile',
                'isAlertRequired' => '1',
                'isCODMandatory' => '0',
            ],
            'isLabelRequired' => 1,
            'labelParameters' => [
                'outputType' => 'PDF',
                'offsetX' => 0,
                'offsetY' => 0,
                'isBorderRequired' => 0,
                'isLogoRequired' => 1,
                'isBarcodeControlRowRequired' => 1,
            ],
        ];

        try {
            Log::info('BRT TEST createShipment request', ['payload' => array_merge($payload, ['account' => ['userID' => $this->clientId, 'password' => '***']])]);

            $response = Http::withOptions(['verify' => $this->verifySsl])
                ->timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->apiUrl.'/shipment', $payload);

            $body = $response->json();

            Log::info('BRT TEST createShipment response', ['http_status' => $response->status(), 'body' => $body]);

            // Controlliamo il codice nella risposta
            // La risposta BRT ha struttura: createResponse > executionMessage > code
            $createResponse = $body['createResponse'] ?? $body;
            $execCode = $createResponse['executionMessage']['code'] ?? $body['executionMessage']['code'] ?? -1;

            if ($execCode < 0) {
                return [
                    'success' => false,
                    'error' => $createResponse['executionMessage']['message'] ?? 'Errore BRT',
                    'exec_code' => $execCode,
                    'raw_response' => $body,
                    'payload_sent' => array_merge($payload, ['account' => ['userID' => $this->clientId, 'password' => '***']]),
                ];
            }

            // Estraiamo l'etichetta
            $labels = $createResponse['labels']['label'] ?? $body['labels'] ?? [];
            $labelBase64 = '';
            $parcelId = '';
            if (! empty($labels) && is_array($labels)) {
                $first = $labels[0] ?? null;
                if ($first) {
                    $parcelId = $first['parcelID'] ?? $first['parcelId'] ?? '';
                    $labelBase64 = $first['stream'] ?? '';
                }
            }

            return [
                'success' => true,
                'parcel_id' => $parcelId,
                'label_base64' => $labelBase64,
                'tracking_url' => $parcelId ? 'https://www.brt.it/it/tracking?parcelId='.urlencode($parcelId) : '',
                'raw_response' => $body,
            ];
        } catch (\Exception $e) {
            Log::error('BRT TEST createShipment exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => 'Errore connessione BRT: '.$e->getMessage()];
        }
    }

    /**
     * Conferma una spedizione BRT (modalita' di conferma esplicita).
     * Alcune configurazioni BRT richiedono una conferma separata dopo la creazione.
     */
    public function confirmShipment(int $numericSenderReference): array
    {
        $payload = [
            'account' => [
                'userID' => $this->clientId,
                'password' => $this->password,
            ],
            'confirmData' => [
                'senderCustomerCode' => (int) $this->clientId,
                'numericSenderReference' => $numericSenderReference,
            ],
        ];

        try {
            $response = Http::withOptions(['verify' => $this->verifySsl])
                ->timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->put($this->apiUrl.'/shipment', $payload);

            $body = $response->json();

            Log::info('BRT confirmShipment response', [
                'reference' => $numericSenderReference,
                'body' => $body,
            ]);

            $execCode = $body['executionMessage']['code'] ?? -1;
            if ($execCode < 0) {
                return ['success' => false, 'error' => $body['executionMessage']['message'] ?? 'Errore conferma BRT.'];
            }

            return ['success' => true, 'raw_response' => $body];
        } catch (\Exception $e) {
            Log::error('BRT confirmShipment exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Elimina una spedizione BRT.
     * Utile se l'admin vuole annullare una spedizione gia' creata.
     */
    public function deleteShipment(int $numericSenderReference): array
    {
        $payload = [
            'account' => [
                'userID' => $this->clientId,
                'password' => $this->password,
            ],
            'deleteData' => [
                'senderCustomerCode' => (int) $this->clientId,
                'numericSenderReference' => $numericSenderReference,
            ],
        ];

        try {
            $response = Http::withOptions(['verify' => $this->verifySsl])
                ->timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->put($this->apiUrl.'/delete', $payload);

            $body = $response->json();
            $execCode = $body['executionMessage']['code'] ?? -1;

            return [
                'success' => $execCode >= 0,
                'error' => $execCode < 0 ? ($body['executionMessage']['message'] ?? 'Errore') : null,
                'raw_response' => $body,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cerca punti PUDO (Pick Up Drop Off) per indirizzo.
     * Delega a PudoService per la logica di ricerca multi-pass con fallback.
     */
    public function getPudoByAddress(string $address, string $zipCode, string $city, string $countryCode = 'ITA', int $maxResults = 50): array
    {
        return $this->pudoService->getPudoByAddress($address, $zipCode, $city, $countryCode, $maxResults);
    }

    /**
     * Cerca punti PUDO per coordinate GPS (latitudine e longitudine).
     * Delega a PudoService per la logica di ricerca con fallback database.
     */
    public function getPudoByCoordinates(float $latitude, float $longitude, int $maxResults = 50): array
    {
        return $this->pudoService->getPudoByCoordinates($latitude, $longitude, $maxResults);
    }

    /**
     * Mostra i dettagli di un punto PUDO specifico (orari completi, servizi disponibili, ecc.).
     * Delega a PudoService.
     */
    public function getPudoDetails(string $pudoId): array
    {
        return $this->pudoService->getPudoDetails($pudoId);
    }

    /**
     * Genera l'URL per seguire il tracking di un pacco BRT.
     * Delega a TrackingService.
     *
     * @param  string  $parcelNumber  Il numero di collo BRT (parcelNumberFrom) o parcelId
     */
    public function getTrackingUrl(string $parcelNumber): string
    {
        return $this->trackingService->getTrackingUrl($parcelNumber);
    }

    /**
     * Restituisce lo stato di tracking di un ordine BRT.
     * Delega a TrackingService.
     */
    public function getTrackingStatus(Order $order): array
    {
        return $this->trackingService->getTrackingStatus($order);
    }

    public function requestHomePickup(Order $order, array $pickupRequest): array
    {
        if (! ((bool) ($pickupRequest['enabled'] ?? false))) {
            return ['success' => true, 'status' => 'not_requested'];
        }

        if (empty($order->brt_parcel_id)) {
            return [
                'success' => false,
                'status' => 'failed',
                'error' => 'Impossibile richiedere il ritiro senza etichetta BRT generata.',
            ];
        }

        return [
            'success' => false,
            'status' => 'failed',
            'error' => 'Integrazione ritiro BRT non disponibile in questa installazione.',
        ];
    }

    public function createBordero(Order $order): array
    {
        $order->loadMissing(['packages.originAddress', 'packages.destinationAddress', 'packages.service', 'user']);

        /** @var Package|null $package */
        $package = $order->packages->first();
        if (! $package || ! $package->originAddress || ! $package->destinationAddress) {
            return [
                'success' => false,
                'error' => 'Dati spedizione insufficienti per generare il borderò.',
            ];
        }

        $origin = $package->originAddress;
        $destination = $package->destinationAddress;
        $parcelCount = (int) $order->packages->sum(fn (Package $item) => max(1, (int) ($item->quantity ?? 1)));
        $reference = 'BORD-'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT);
        $pdf = app(BorderoPdfBuilder::class)->build([
            'bordero_date' => now()->format('d/m/Y'),
            'bordero_number' => (string) $order->id,
            'bordero_reference' => $reference,
            'localita' => (string) ($destination->city ?? ''),
            'prov' => (string) ($destination->province ?? ''),
            'lna' => (string) ($destination->postal_code ?? ''),
            'rif_num' => (string) $order->id,
            'rif_alpha' => (string) ($order->brt_parcel_id ?? $order->id),
            'cod_bolla' => (string) ($order->brt_parcel_id ?? 'n/d'),
            'incasso' => $order->is_cod ? 'COD' : 'NO',
            'importo_incasso' => $order->is_cod ? number_format(((int) $order->cod_amount) / 100, 2, ',', '.') : '0,00',
            'importo_assicurare' => '0,00',
            'colli' => (string) $parcelCount,
            'sender_name' => (string) ($origin->name ?? ''),
            'sender_address' => trim((string) (($origin->address ?? '').' '.($origin->address_number ?? ''))),
            'sender_city_line' => trim((string) (($origin->postal_code ?? '').' '.($origin->city ?? '').' ('.($origin->province ?? '').')')),
            'sender_phone' => (string) ($origin->telephone_number ?? ''),
            'recipient_name' => (string) ($destination->name ?? ''),
            'recipient_address' => trim((string) (($destination->address ?? '').' '.($destination->address_number ?? ''))),
            'recipient_city_line' => trim((string) (($destination->postal_code ?? '').' '.($destination->city ?? '').' ('.($destination->province ?? '').')')),
            'recipient_phone' => (string) ($destination->telephone_number ?? ''),
            'created_at' => now()->format('d/m/Y H:i'),
        ]);

        return [
            'success' => true,
            'bordero_reference' => $reference,
            'document_base64' => base64_encode($pdf),
            'document_mime' => 'application/pdf',
            'document_filename' => 'bordero-'.$order->id.'.pdf',
        ];
    }

    /**
     * addServicesToPayload — Mappa i servizi dell'app ai parametri API BRT.
     *
     * PERCHE': I servizi dell'applicazione (dalla tabella "services") hanno nomi diversi
     *   dai parametri dell'API BRT. Questa funzione traduce i nomi (es. "consegna al piano"
     *   diventa il campo BRT 'particularitiesDeliveryManagement' con valore 'CP').
     *
     * COME LEGGERLO:
     *   1. Definizione mappa servizio app → parametro BRT ($serviceMapping)
     *   2. Ciclo sui pacchi dell'ordine per raccogliere i servizi richiesti
     *   3. Aggiunta opzioni extra (assicurazione, appuntamento) dalle $options
     *   4. Log dei servizi applicati per debug
     *
     * COME MODIFICARLO:
     *   - Per aggiungere un nuovo servizio: aggiungere una riga in $serviceMapping
     *   - Per cambiare il codice BRT di un servizio: modificare il 'value' nella mappa
     *
     * COSA EVITARE:
     *   - Non sovrascrivere campi gia' impostati (controllato con isset)
     *   - Non rimuovere il log dei servizi non mappati (utile per trovare servizi mancanti)
     *
     * @param  array  &$payload  Il payload da inviare a BRT (modificato per riferimento)
     * @param  Order  $order  L'ordine con i pacchi e servizi caricati
     * @param  array  $options  Opzioni aggiuntive passate dal chiamante
     */
    private function addServicesToPayload(array &$payload, Order $order, array $options): void
    {
        // Mappa dei nomi di servizio dell'applicazione -> parametri API BRT
        // I nomi sono normalizzati in minuscolo per la comparazione
        $serviceMapping = [
            'consegna al piano' => ['field' => 'particularitiesDeliveryManagement', 'value' => 'CP'],
            'delivery al piano' => ['field' => 'particularitiesDeliveryManagement', 'value' => 'CP'],
            'ritiro al piano' => ['field' => 'particularitiesPickupManagement', 'value' => 'RP'],
            'pickup al piano' => ['field' => 'particularitiesPickupManagement', 'value' => 'RP'],
            'express' => ['field' => 'serviceType', 'value' => 'E'],
            'priority' => ['field' => 'serviceType', 'value' => 'P'],
            '10:30' => ['field' => 'serviceType', 'value' => 'O'],
            'economy' => ['field' => 'serviceType', 'value' => 'N'],
            'sponda idraulica' => ['field' => 'particularitiesDeliveryManagement', 'value' => 'SU'],
            'tail lift' => ['field' => 'particularitiesDeliveryManagement', 'value' => 'SU'],
        ];

        // Raccogliamo tutti i tipi di servizio dai pacchi dell'ordine
        $appliedServices = [];
        foreach ($order->packages as $package) {
            if ($package->service && ! empty($package->service->service_type)) {
                $serviceType = mb_strtolower(trim($package->service->service_type), 'UTF-8');

                if (isset($serviceMapping[$serviceType])) {
                    $mapping = $serviceMapping[$serviceType];
                    $field = $mapping['field'];
                    $value = $mapping['value'];

                    // Evitiamo di sovrascrivere campi gia' impostati
                    if (! isset($payload['createData'][$field])) {
                        $payload['createData'][$field] = $value;
                        $appliedServices[] = [
                            'app_service' => $package->service->service_type,
                            'brt_field' => $field,
                            'brt_value' => $value,
                        ];
                    }
                } else {
                    // Servizio non mappato: lo registriamo per debug
                    // cosi' possiamo vedere nei log quali servizi mancano nella mappa
                    Log::info('BRT service not mapped', [
                        'order_id' => $order->id,
                        'service_type' => $package->service->service_type,
                        'available_mappings' => array_keys($serviceMapping),
                    ]);
                }
            }
        }

        // Servizi aggiuntivi passati nelle opzioni (dal chiamante)
        if (! empty($options['insurance_amount'])) {
            $payload['createData']['insuranceAmount'] = (float) ($options['insurance_amount'] / 100); // Da centesimi a euro
            $appliedServices[] = [
                'app_service' => 'assicurazione',
                'brt_field' => 'insuranceAmount',
                'brt_value' => $payload['createData']['insuranceAmount'],
            ];
        }

        // Senza etichetta: il mittente non stampa l'etichetta, BRT la genera al ritiro
        if (! empty($options['no_label'])) {
            $payload['isLabelRequired'] = 0;
            $appliedServices[] = [
                'app_service' => 'senza_etichetta',
                'brt_field' => 'isLabelRequired',
                'brt_value' => 0,
            ];
        }

        if (! empty($options['delivery_appointment'])) {
            $payload['createData']['isAlertRequired'] = '1';
            $payload['createData']['particularitiesDeliveryManagement'] =
                $payload['createData']['particularitiesDeliveryManagement'] ?? 'AP'; // AP = Appuntamento
            $appliedServices[] = [
                'app_service' => 'appuntamento_consegna',
                'brt_field' => 'particularitiesDeliveryManagement',
                'brt_value' => 'AP',
            ];
        }

        // Logghiamo i servizi applicati per debug e monitoraggio
        if (! empty($appliedServices)) {
            Log::info('BRT services applied to shipment', [
                'order_id' => $order->id,
                'services' => $appliedServices,
            ]);
        }
    }

    /**
     * normalizeAddressForBrt — Normalizza indirizzo per il sistema di routing BRT.
     *
     * PERCHE': BRT rifiuta indirizzi non normalizzati con errore -63 (routing fallito).
     *   Senza questa funzione, "S. Giovanni Lupatoto" fallirebbe perche' BRT vuole
     *   "SAN GIOVANNI LUPATOTO". Stessa cosa per CAP senza zero iniziale o provincia estesa.
     *
     * COME LEGGERLO:
     *   1. Normalizza CAP: solo cifre, zero-padded a 5 caratteri
     *   2. Normalizza provincia: converte nome completo in sigla 2 lettere
     *   3. Normalizza citta': maiuscolo + espansione abbreviazioni (S. → SAN)
     *   4. Verifica con database locations: se il CAP e' noto, usa il nome citta' dal DB
     *
     * COME MODIFICARLO:
     *   - Per aggiungere abbreviazioni: modificare $abbreviations in normalizeCityName()
     *   - Per cambiare la strategia di matching: modificare resolveCityFromLocations()
     *
     * COSA EVITARE:
     *   - Non rimuovere lo step 4 (risoluzione da DB): risolve molti casi ambigui
     *   - Non rendere lo step 4 obbligatorio: se la tabella locations non esiste, deve continuare
     *
     * @param  object  $address  L'oggetto indirizzo (PackageAddress) con city, postal_code, province
     * @return array Array con chiavi: city, postal_code, province (normalizzati per BRT)
     */
    private function normalizeAddressForBrt(object $address): array
    {
        return $this->addressNormalizer->normalize($address);
    }

    private function normalizeCityName(string $city): string
    {
        return $this->addressNormalizer->normalizeCityName($city);
    }

    private function resolveCityFromLocations(string $normalizedCity, string $postalCode, string $province): string
    {
        return $this->addressNormalizer->resolveCityFromLocations($normalizedCity, $postalCode, $province);
    }

    private function translateBrtError(int $code, string $codeDesc, string $message, array $createData): string
    {
        return $this->errorTranslator->translate($code, $codeDesc, $message, $createData);
    }

    /**
     * Costruisce le note per la spedizione BRT.
     * Include la descrizione del contenuto dei pacchi se disponibile.
     */
    private function buildNotes(Order $order, array $options): string
    {
        // Se l'utente ha specificato note personalizzate, le usiamo
        if (! empty($options['notes'])) {
            return $options['notes'];
        }

        $notes = 'SpediamoFacile ordine #'.$order->id;

        // Aggiungiamo la descrizione del contenuto dai pacchi (campo content_description)
        $descriptions = $order->packages
            ->pluck('content_description')
            ->filter()
            ->unique()
            ->implode(', ');

        if ($descriptions) {
            $notes .= ' - Contenuto: '.$descriptions;
        }

        // BRT ha un limite di 50 caratteri per le note
        return mb_substr($notes, 0, 50);
    }

    private function provinceToAbbreviation(string $province): string
    {
        return $this->addressNormalizer->provinceToAbbreviation($province);
    }

    private function countryToIso2(string $country): string
    {
        return $this->addressNormalizer->countryToIso2($country);
    }

    /**
     * Risolve automaticamente il codice filiale BRT dal CAP del mittente.
     * Strategia: match esatto → prime 3 cifre → prime 2 cifre → fallback config.
     */
    private function resolveFilialeByCap(string $senderPostalCode): int
    {
        $cap = str_pad(trim($senderPostalCode), 5, '0', STR_PAD_LEFT);
        $filiali = config('brt_filiali.filiali', []);

        // 1. Match esatto sul CAP
        foreach ($filiali as $filiale) {
            if ($filiale['cap'] === $cap) {
                return (int) $filiale['codice'];
            }
        }

        // 2. Match sulle prime 3 cifre (stesso sottozone postale)
        $prefix3 = substr($cap, 0, 3);
        foreach ($filiali as $filiale) {
            if (substr($filiale['cap'], 0, 3) === $prefix3) {
                return (int) $filiale['codice'];
            }
        }

        // 3. Match sulle prime 2 cifre (stessa provincia)
        $prefix2 = substr($cap, 0, 2);
        foreach ($filiali as $filiale) {
            if (substr($filiale['cap'], 0, 2) === $prefix2) {
                return (int) $filiale['codice'];
            }
        }

        // 4. Fallback al depot configurato in BRT_DEPARTURE_DEPOT
        return $this->departureDepot;
    }
}
