<?php
/**
 * FILE: CartController.php
 * SCOPO: Gestisce il carrello spedizioni per utenti autenticati, salvando i pacchi nel database.
 *
 * DOVE SI USA: Pagina carrello, pagina riepilogo, composable useCart.js
 *
 * DATI IN INGRESSO:
 *   - store(): PackageStoreRequest {packages: [{package_type, quantity, weight, first_size, ...}],
 *     origin_address: {name, address, city, postal_code, ...}, destination_address: {...}, services: {...}}
 *   - update(): Request con {origin_address?, destination_address?, packages?, services?}
 *   - updateQuantity(): {quantity: 3}
 *   - show/destroy: ID pacco nella URL
 *
 * DATI IN USCITA:
 *   - index/store: PackageResource[] con meta {empty, subtotal: "9,00 EUR", total, address_groups}
 *   - show: PackageResource singolo
 *   - updateQuantity: {message, quantity, single_price}
 *   - destroy: {message: "Spedizione rimossa dal carrello"}
 *
 * VINCOLI:
 *   - I prezzi nel DB sono in CENTESIMI (900 = 9,00 EUR). Il frontend invia in euro.
 *   - La conversione euro->centesimi avviene in store() e update(): round(euro * 100)
 *   - single_price = prezzo_unitario * quantita' (NON il prezzo di un singolo pacco)
 *   - L'auto-merge in index() unisce pacchi identici: stesse dimensioni + stessi indirizzi + stesso servizio
 *   - La tabella pivot cart_user collega user_id a package_id (relazione many-to-many)
 *
 * ERRORI TIPICI:
 *   - 404: pacco non trovato nel carrello dell'utente
 *   - 422: dati di validazione non corretti (PackageStoreRequest)
 *
 * PUNTI DI MODIFICA SICURI:
 *   - Per cambiare i campi di confronto del merge: modificare la chiave in autoMergePackages()
 *   - Per cambiare i campi del raggruppamento indirizzi: modificare buildAddressGroups()
 *   - Per aggiungere un campo al pacco: aggiungere in store(), update() e PackageStoreRequest
 *
 * COLLEGAMENTI:
 *   - GuestCartController.php — stessa logica ma per utenti non autenticati (sessione)
 *   - SavedShipmentController.php — spedizioni salvate come template riutilizzabili
 *   - StripeController.php — createOrder() legge i pacchi dal carrello per creare ordini
 *   - pages/carrello.vue — interfaccia frontend del carrello
 */

namespace App\Http\Controllers;

use App\Cart\MyMoney;
use App\Models\PackageAddress;
use App\Models\Package;
use App\Models\Service;
use App\Services\CartService;
use App\Services\ShipmentServicePricingService;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\PackageResource;
use App\Http\Requests\PackageStoreRequest;
use Illuminate\Http\Request;
class CartController extends Controller
{
    use Traits\NormalizesServiceData;
    /**
     * index — Mostra il contenuto del carrello con auto-merge e pulizia automatica.
     *
     * PERCHE': Ogni volta che il carrello viene caricato, unisce automaticamente i pacchi
     *   identici e rimuove quelli senza dati validi, per mantenere il carrello pulito.
     * COME LEGGERLO: 1) Carica pacchi dal DB  2) Auto-merge pacchi identici  3) Ricarica
     *   4) Pulizia pacchi non validi  5) Restituisce con meta (subtotale, gruppi indirizzi)
     * COME MODIFICARLO: Per cambiare i criteri di merge, modificare autoMergePackages().
     *   Per cambiare i criteri di pulizia, modificare il filtro $invalidPackages.
     * COSA EVITARE: Non rimuovere il ricaricamento dopo il merge — i dati in memoria sarebbero obsoleti.
     */
    public function index(Request $request) {

        $user = auth()->user();

        // Prendiamo gli ID dei pacchi presenti nel carrello dell'utente
        $cart = DB::table('cart_user')
            ->where('user_id', $user->id)
            ->get();

        // Carichiamo i pacchi con le relazioni (indirizzi di partenza/destinazione, servizi)
        $packages = Package::with(['originAddress', 'destinationAddress', 'service'])
            ->whereIn('id', $cart->pluck('package_id'))
            ->get();

        // Auto-merge: uniamo automaticamente i pacchi identici nel carrello
        // (stesso tipo, peso, dimensioni, indirizzi e servizio)
        $this->autoMergePackages($packages, $user->id);

        // Ricarichiamo dopo il merge
        $cart = DB::table('cart_user')
            ->where('user_id', $user->id)
            ->get();
        $packages = Package::with(['originAddress', 'destinationAddress', 'service'])
            ->whereIn('id', $cart->pluck('package_id'))
            ->get();

        // Pulizia automatica: rimuoviamo i pacchi senza dati validi
        // (es. pacchi senza tipo o senza dimensioni, rimasti per errore)
        $invalidPackages = $packages->filter(function ($pkg) {
            return empty($pkg->package_type)
                || (empty($pkg->weight) && empty($pkg->first_size));
        });

        if ($invalidPackages->isNotEmpty()) {
            foreach ($invalidPackages as $pkg) {
                DB::table('cart_user')
                    ->where('user_id', $user->id)
                    ->where('package_id', $pkg->id)
                    ->delete();
                $pkg->delete();
            }
            // Ricarichiamo i pacchi validi dopo la pulizia
            $cart = DB::table('cart_user')
                ->where('user_id', $user->id)
                ->get();
            $packages = Package::with(['originAddress', 'destinationAddress', 'service'])
                ->whereIn('id', $cart->pluck('package_id'))
                ->get();
        }

        return PackageResource::collection($packages)
            ->additional([
                'meta' => $this->meta($packages)
            ]);
    }

    // Calcola il subtotale del carrello sommando i prezzi di tutti i pacchi
    // I prezzi sono in centesimi (es. 900 = 9,00 euro)
    public function subtotal($packages) {
        return CartService::subtotalFromModels($packages);
    }

    // Prepara le informazioni aggiuntive (meta) per la risposta
    // Include le informazioni sui raggruppamenti per indirizzo
    protected function meta($packages) {
        return [
            'empty' => $packages->isEmpty(),
            'subtotal' => $this->subtotal($packages)->formatted(),  // Es. "9,00 EUR"
            'total' => $this->subtotal($packages)->formatted(),
            'address_groups' => $this->buildAddressGroups($packages), // Gruppi per indirizzo (merge info)
        ];
    }

    /**
     * Costruisce una chiave univoca per un pacco, usata per identificare pacchi identici.
     * Due pacchi con la stessa chiave possono essere uniti (merge).
     */
    private function buildMergeKey($pkg): string
    {
        return CartService::buildMergeKey($pkg);
    }

    /**
     * Unisce automaticamente i pacchi identici nel carrello.
     * Due pacchi sono "identici" se hanno stessi: tipo, peso, dimensioni, indirizzi e servizio.
     */
    private function autoMergePackages($packages, $userId): void
    {
        if ($packages->count() < 2) return;

        $groups = [];
        foreach ($packages as $pkg) {
            $key = $this->buildMergeKey($pkg);
            $groups[$key][] = $pkg;
        }

        foreach ($groups as $groupPackages) {
            if (count($groupPackages) < 2) continue;

            $master = $groupPackages[0];
            $masterQty = (int) $master->quantity;
            $masterUnitPrice = CartService::unitPrice((int) $master->single_price, $masterQty);

            for ($i = 1; $i < count($groupPackages); $i++) {
                $dup = $groupPackages[$i];
                $masterQty += (int) $dup->quantity;

                DB::table('cart_user')
                    ->where('user_id', $userId)
                    ->where('package_id', $dup->id)
                    ->delete();
                $dup->delete();
            }

            $master->update([
                'quantity' => $masterQty,
                'single_price' => $masterUnitPrice * $masterQty,
            ]);
        }
    }

    /**
     * Raggruppa i pacchi per coppia di indirizzi identici E stesso servizio.
     * Delega a CartService::buildAddressGroups().
     */
    private function buildAddressGroups($packages): array
    {
        return CartService::buildAddressGroups($packages);
    }


    // Restituisce un singolo pacco del carrello dell'utente (per la modifica)
    public function show($id) {
        $user = auth()->user();

        // Verifichiamo che il pacco appartenga al carrello dell'utente
        $inCart = DB::table('cart_user')
            ->where('user_id', $user->id)
            ->where('package_id', $id)
            ->exists();

        if (!$inCart) {
            return response()->json(['message' => 'Pacco non trovato nel carrello'], 404);
        }

        $package = Package::with(['originAddress', 'destinationAddress', 'service'])
            ->where('id', $id)
            ->firstOrFail();

        return new PackageResource($package);
    }

    /**
     * update — Aggiorna un pacco esistente nel carrello (modifica da pagina riepilogo).
     *
     * PERCHE': L'utente puo' modificare indirizzi, servizi e dimensioni di un pacco gia' nel carrello.
     * COME LEGGERLO: 1) Verifica appartenenza  2) Aggiorna indirizzo partenza  3) Aggiorna indirizzo
     *   destinazione  4) Aggiorna servizi (incluso PUDO)  5) Aggiorna dati pacco
     * COME MODIFICARLO: Per aggiungere un campo al pacco, aggiungerlo nel blocco packages[0].
     * COSA EVITARE: Non rimuovere la conversione euro->centesimi di single_price (round * 100).
     */
    public function update(Request $request, $id) {
        $userId = auth()->id();

        // Verifichiamo che il pacco appartenga al carrello dell'utente
        $inCart = DB::table('cart_user')
            ->where('user_id', $userId)
            ->where('package_id', $id)
            ->exists();

        if (!$inCart) {
            return response()->json(['message' => 'Pacco non trovato nel carrello'], 404);
        }

        $package = Package::where('id', $id)->where('user_id', $userId)->firstOrFail();
        $data = $request->all();

        return DB::transaction(function() use ($package, $data) {
            // Aggiorniamo l'indirizzo di partenza
            if (isset($data['origin_address']) && $package->originAddress) {
                $package->originAddress->update($data['origin_address']);
            }

            // Aggiorniamo l'indirizzo di destinazione
            if (isset($data['destination_address']) && $package->destinationAddress) {
                $package->destinationAddress->update($data['destination_address']);
            }

            // Aggiorniamo i servizi
            if (isset($data['services']) && $package->service) {
                $servicesData = CartService::normalizeServiceData($data['services']);
                $servicesData = CartService::applyPudoData($servicesData, $data);
                $package->service->update($servicesData);
            }

            // Aggiorniamo i dati dei pacchi (il primo pacco nel payload)
            if (isset($data['packages']) && count($data['packages']) > 0) {
                $packageData = $data['packages'][0];
                $singlePriceCents = CartService::euroToCents($packageData['single_price'] ?? 0);
                $newQty = (int) ($packageData['quantity'] ?? 1);

                $package->update([
                    'package_type' => $packageData['package_type'] ?? $package->package_type,
                    'quantity' => $newQty,
                    'weight' => $packageData['weight'] ?? $package->weight,
                    'first_size' => $packageData['first_size'] ?? $package->first_size,
                    'second_size' => $packageData['second_size'] ?? $package->second_size,
                    'third_size' => $packageData['third_size'] ?? $package->third_size,
                    'weight_price' => $packageData['weight_price'] ?? $package->weight_price,
                    'volume_price' => $packageData['volume_price'] ?? $package->volume_price,
                    'single_price' => $singlePriceCents,
                    'content_description' => $data['content_description'] ?? $package->content_description,
                ]);
            }

            $package->load(['originAddress', 'destinationAddress', 'service']);
            return new PackageResource($package);
        });
    }

    /**
     * store — Aggiunge uno o piu' pacchi al carrello, con deduplicazione automatica.
     *
     * PERCHE': Quando l'utente aggiunge pacchi dal preventivo, se un pacco identico esiste gia'
     *   nel carrello (stesse dimensioni, stessi indirizzi, stesso servizio), aumenta la quantita'
     *   invece di creare un duplicato.
     * COME LEGGERLO: 1) Carica pacchi esistenti  2) Per ogni nuovo pacco: cerca duplicato
     *   3) Se duplicato: somma quantita'  4) Se nuovo: crea indirizzi + servizi + pacco
     *   5) Collega alla tabella cart_user
     * COME MODIFICARLO: Per cambiare i criteri di deduplicazione, modificare il confronto nel
     *   blocco $duplicate. Per aggiungere campi, aggiungerli sia nel confronto che nel create().
     * COSA EVITARE: Non rimuovere la transazione DB — senza, si rischia di creare indirizzi orfani.
     */
    public function store(PackageStoreRequest $request) {

        $data = $request->validated();
        $authId = auth()->id();

        // Recuperiamo i pacchi gia' presenti nel carrello per controllare i duplicati
        $cartPackageIds = DB::table('cart_user')
            ->where('user_id', $authId)
            ->pluck('package_id');

        $existingPackages = Package::with(['originAddress', 'destinationAddress', 'service'])
            ->whereIn('id', $cartPackageIds)
            ->get();

        // Creiamo i pacchi in una transazione (tutto o niente)
        $outPackages = DB::transaction(function() use ($data, $authId, $existingPackages) {
            // Prepariamo i dati dei servizi aggiuntivi
            $servicesData = CartService::normalizeServiceData($data['services']);

            // PUDO: se l'utente ha scelto ritiro in un punto BRT, salviamo i dati nel service_data
            $servicesData = CartService::applyPudoData($servicesData, $data);

            $packages = [];
            $origin = null;
            $destination = null;
            $services = null;

            foreach ($data['packages'] as $packageData) {
                // Convertiamo il prezzo da euro a centesimi
                $singlePriceCents = CartService::euroToCents($packageData['single_price'] ?? 0);
                $newQty = (int) ($packageData['quantity'] ?? 1);

                // Calcoliamo il prezzo per singola unita' (prezzo per 1 pacco)
                $unitPriceCents = CartService::unitPrice($singlePriceCents, $newQty);

                // Controlliamo se esiste gia' un pacco identico nel carrello
                // (stesso tipo, stesse dimensioni, stessi indirizzi)
                $newServiceSig = CartService::buildServiceSignatureFromArray(
                    $servicesData['service_type'] ?? 'Nessuno',
                    $servicesData['service_data'] ?? [],
                );
                $duplicate = $existingPackages->first(function ($existing) use ($packageData, $data, $servicesData, $newServiceSig) {
                    if (!$existing->originAddress || !$existing->destinationAddress || !$existing->service) {
                        return false;
                    }
                    return CartService::isDuplicate(
                        $packageData,
                        $data['origin_address'] ?? [],
                        $data['destination_address'] ?? [],
                        $newServiceSig,
                        $existing->toArray(),
                        $existing->originAddress->toArray(),
                        $existing->destinationAddress->toArray(),
                        CartService::buildServiceSignatureFromService($existing->service),
                    );
                });

                if ($duplicate) {
                    // Se il pacco esiste gia', aumentiamo la quantita' e ricalcoliamo il prezzo totale
                    $merged = CartService::mergeQuantity(
                        (int) $duplicate->single_price,
                        (int) $duplicate->quantity,
                        $newQty,
                    );
                    $duplicate->update($merged);
                    $packages[] = $duplicate;
                } else {
                    // Se e' un pacco nuovo, creiamo gli indirizzi e i servizi (solo una volta per gruppo)
                    if (!$origin) {
                        $origin = PackageAddress::create($data['origin_address']);
                        $destination = PackageAddress::create($data['destination_address']);
                        $services = Service::create($servicesData);
                    }

                    // Creiamo il pacco nel database
                    $packages[] = Package::create([
                        'package_type' => $packageData['package_type'],
                        'quantity' => $newQty,
                        'weight' => $packageData['weight'],
                        'first_size' => $packageData['first_size'],
                        'second_size' => $packageData['second_size'],
                        'third_size' => $packageData['third_size'],
                        'weight_price' => $packageData['weight_price'] ?? null,
                        'volume_price' => $packageData['volume_price'] ?? null,
                        'single_price' => $singlePriceCents,
                        'origin_address_id' => $origin->id,
                        'destination_address_id' => $destination->id,
                        'service_id' => $services->id,
                        'user_id' => $authId,
                    ]);
                }
            }

            return $packages;
        });

        // Colleghiamo i nuovi pacchi al carrello (solo se non sono gia' collegati, nel caso dei duplicati)
        foreach ($outPackages as $package) {
            $exists = DB::table('cart_user')
                ->where('user_id', $authId)
                ->where('package_id', $package->id)
                ->exists();

            if (!$exists) {
                DB::table('cart_user')->insert([
                    'user_id' => $authId,
                    'package_id' => $package->id,
                ]);
            }
        }

        return PackageResource::collection($outPackages);
    }

    // Modifica la quantita' di un pacco nel carrello
    // Ricalcola automaticamente il prezzo totale in base alla nuova quantita'
    public function updateQuantity(Request $request, $id) {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:99',
        ]);

        $userId = auth()->id();
        // Cerchiamo il pacco assicurandoci che appartenga all'utente
        $package = Package::where('id', $id)->where('user_id', $userId)->firstOrFail();

        $oldQty = max(1, (int) $package->quantity);
        $newQty = (int) $request->quantity;

        // Calcoliamo il prezzo per singola unita' dividendo il prezzo totale per la vecchia quantita'
        $unitPriceCents = CartService::unitPrice((int) $package->single_price, $oldQty);

        // Aggiorniamo la quantita' e il prezzo totale
        $package->update([
            'quantity' => $newQty,
            'single_price' => $unitPriceCents * $newQty,
        ]);

        return response()->json([
            'message' => 'Quantità aggiornata',
            'quantity' => $newQty,
            'single_price' => $unitPriceCents * $newQty,
        ]);
    }

    // Rimuove un singolo pacco dal carrello e lo elimina dal database
    public function destroy($id) {
        $userId = auth()->id();

        // Rimuoviamo il collegamento carrello-pacco
        DB::table('cart_user')
            ->where('user_id', $userId)
            ->where('package_id', $id)
            ->delete();

        // Eliminiamo il pacco dal database
        Package::where('id', $id)->where('user_id', $userId)->delete();

        return response()->json(['message' => 'Spedizione rimossa dal carrello']);
    }

    /**
     * Unisce automaticamente i pacchi identici nel carrello dell'utente.
     * Pacchi con stesse dimensioni, peso, tipo, indirizzi e servizio vengono fusi
     * in un unico record con quantita' sommata e prezzo totale ricalcolato.
     *
     * Chiamato da: POST /api/cart/merge
     */
    public function mergeIdentical()
    {
        $userId = auth()->id();

        $cartPackageIds = DB::table('cart_user')
            ->where('user_id', $userId)
            ->pluck('package_id');

        $packages = Package::with(['originAddress', 'destinationAddress', 'service'])
            ->whereIn('id', $cartPackageIds)
            ->get();

        if ($packages->count() < 2) {
            return response()->json(['message' => 'Nulla da unire.', 'merged' => 0]);
        }

        $merged = 0;

        // Raggruppa per: tipo, peso, dimensioni, indirizzi, servizio
        $groups = [];
        foreach ($packages as $pkg) {
            $key = $this->buildMergeKey($pkg);
            $groups[$key][] = $pkg;
        }

        DB::transaction(function () use ($groups, $userId, &$merged) {
            foreach ($groups as $groupPackages) {
                if (count($groupPackages) < 2) continue;

                // Il primo pacco diventa il "master", gli altri vengono eliminati
                $master = $groupPackages[0];
                $masterQty = (int) $master->quantity;
                $masterUnitPrice = CartService::unitPrice((int) $master->single_price, $masterQty);

                for ($i = 1; $i < count($groupPackages); $i++) {
                    $dup = $groupPackages[$i];
                    $masterQty += (int) $dup->quantity;

                    // Rimuovi dal carrello e elimina il pacco duplicato
                    DB::table('cart_user')
                        ->where('user_id', $userId)
                        ->where('package_id', $dup->id)
                        ->delete();
                    $dup->delete();
                    $merged++;
                }

                // Aggiorna il master con la quantita' totale e il prezzo corretto
                $master->update([
                    'quantity' => $masterQty,
                    'single_price' => $masterUnitPrice * $masterQty,
                ]);
            }
        });

        return response()->json([
            'message' => $merged > 0 ? "$merged pacchi identici uniti." : 'Nessun pacco da unire.',
            'merged' => $merged,
        ]);
    }

    // Svuota completamente il carrello dell'utente
    // Elimina tutti i pacchi che erano nel carrello (ma NON quelli salvati come spedizioni configurate)
    public function emptyCart() {
        $userId = auth()->id();

        // Recuperiamo gli ID dei pacchi nel carrello (solo quelli del carrello, non tutti i pacchi dell'utente)
        $cartPackageIds = DB::table('cart_user')
            ->where('user_id', $userId)
            ->pluck('package_id');

        // Eliminiamo i pacchi dal database
        if ($cartPackageIds->isNotEmpty()) {
            Package::whereIn('id', $cartPackageIds)
                ->where('user_id', $userId)
                ->delete();
        }

        // Rimuoviamo tutti i collegamenti carrello-pacco
        DB::table('cart_user')
            ->where('user_id', $userId)
            ->delete();

        return response()->json(['message' => 'Carrello svuotato']);
    }

    // Delegato a CartService — mantenuti come wrapper per retrocompatibilita'
    private function calculateGroupedServiceSurchargeCents($packages): int
    {
        return CartService::calculateGroupedSurchargeFromModels($packages);
    }

    private function buildServiceSignatureFromService(Service $service): string
    {
        return CartService::buildServiceSignatureFromService($service);
    }

    private function buildServiceSignatureFromArray(string $serviceType, array $serviceData = []): string
    {
        return CartService::buildServiceSignatureFromArray($serviceType, $serviceData);
    }

}
