<!--
  FILE: pages/riepilogo.vue
  SCOPO: Riepilogo spedizione — revisione dati, modifica inline, invio a carrello/checkout/salvati.

  API: POST /api/cart o /api/guest-cart (aggiungi al carrello),
       PUT /api/cart/{id} (aggiorna pacco esistente), GET /api/cart/{id} (carica pacco per modifica),
       POST /api/saved-shipments (salva configurazione), POST /api/create-direct-order (ordine diretto).
  STORE: userStore.pendingShipment (dati spedizione da confermare), userStore.editingCartItemId.
  COMPONENTI: Steps (indicatore progresso).
  ROUTE: /riepilogo (pubblica, ma i dati arrivano dallo store Pinia).

  DATI IN INGRESSO: ?edit={id} (query param per modalita' modifica pacco dal carrello).
  DATI IN USCITA: navigazione a /carrello?updated=ts, /checkout, /account/spedizioni-configurate.

  VINCOLI: single_price arriva in centesimi dal DB, viene convertito in euro per la visualizzazione.
           In modalita' edit, se i dati non sono nello store, vengono caricati via API.
           IMPORTANTE: preparePayloadForBackend() converte prezzi centesimi→euro prima di inviarli al backend.
  ERRORI TIPICI: non pulire pendingShipment dopo il salvataggio (dati stantii al prossimo accesso).
  PUNTI DI MODIFICA SICURI: layout sezioni, servizi disponibili, stili card.
  COLLEGAMENTI: stores/userStore.js, composables/useCart.js, composables/useSession.js,
                pages/carrello.vue, pages/checkout.vue.

  BUGFIX CRITICI (Agent 5):
  ✅ Conversione euro/centesimi: preparePayloadForBackend() converte prezzi da centesimi a euro
     quando si modifica un pacco dal carrello, evitando prezzi 100x più alti nel DB.
-->
<script setup>
import {
	buildPendingShipmentFromSession,
	deriveShipmentFlowStateFromUserStore,
	pickMostAdvancedShipmentFlowState,
	resolveShipmentFlowState,
} from '~/utils/shipmentFlowState';
import { calculateShipmentServiceSurcharge } from "~/utils/shipmentServicePricing";

// Meta tag SEO
useSeoMeta({
	title: 'Riepilogo Spedizione | SpediamoFacile',
	ogTitle: 'Riepilogo Spedizione | SpediamoFacile',
});

definePageMeta({
	middleware: ['shipment-validation'],
});

const userStore = useUserStore();
const { isAuthenticatedForUi } = useAuthUiState();
const isAuthenticated = isAuthenticatedForUi;
const { openAuthModal } = useAuthModal();
const sanctumAuth = useSanctumAuth();
const sanctumClient = useSanctumClient();
const { endpoint, refresh: refreshCart } = useCart();
const { session } = useSession();
const uiFeedback = useUiFeedback();
const fallbackFlowRoute = computed(() => {
	const remoteFlowState = resolveShipmentFlowState(session.value?.data || {});
	const localFlowState = deriveShipmentFlowStateFromUserStore(userStore);
	return pickMostAdvancedShipmentFlowState(remoteFlowState, localFlowState).last_valid_route || '/preventivo';
});

// Promo settings per badge
const { loadPriceBands, promoSettings, priceBands } = usePriceBands();
onMounted(() => { loadPriceBands(); });

const isSubmitting = ref(false);   // Stato di caricamento durante l'invio
const submitError = ref(null);     // Messaggio di errore
const pageReady = ref(false);

// Dati della spedizione in attesa di conferma (salvati nello store Pinia)
const shipment = computed(() => userStore.pendingShipment);

// ID del pacco nel carrello che si sta modificando (null = nuova spedizione)
const editingId = computed(() => userStore.editingCartItemId);

// Caricamento in corso dei dati dal carrello (per edit mode)
const loadingEditData = ref(false);

// Se si arriva con ?edit=ID, carica i dati del pacco dal carrello
const route = useRoute();
const editQueryId = route.query.edit;

const restorePendingShipmentFromSession = () => {
	const restoredShipment = buildPendingShipmentFromSession(session.value?.data || {});
	if (!restoredShipment) {
		return false;
	}

	userStore.pendingShipment = restoredShipment;
	return true;
};

const initRiepilogoPage = async () => {
	if (!shipment.value && restorePendingShipmentFromSession()) {
		return true;
	}

	if (editQueryId && !shipment.value) {
		if (!isAuthenticated.value) {
			loadingEditData.value = false;
			await navigateTo(fallbackFlowRoute.value, { replace: true });
			return false;
		}

		userStore.editingCartItemId = editQueryId;
		loadingEditData.value = true;

		try {
			const res = await sanctumClient(`/api/cart/${editQueryId}`);
			const pkg = res.data || res;
			const priceInCents = pkg.single_price ? Number(pkg.single_price) : 0;
			userStore.pendingShipment = {
				packages: [{
					package_type: pkg.package_type,
					quantity: pkg.quantity || 1,
					weight: pkg.weight,
					first_size: pkg.first_size,
					second_size: pkg.second_size,
					third_size: pkg.third_size,
					weight_price: pkg.weight_price,
					volume_price: pkg.volume_price,
					single_price: priceInCents,
					content_description: pkg.content_description || '',
				}],
				origin_address: pkg.origin_address || {},
				destination_address: pkg.destination_address || {},
				services: pkg.services || {},
			};
			loadingEditData.value = false;
			return true;
		} catch (err) {
			loadingEditData.value = false;
			await navigateTo(fallbackFlowRoute.value, { replace: true });
			return false;
		}
	}

	if (!shipment.value && !editQueryId) {
		const redirectTarget = fallbackFlowRoute.value || '/preventivo';
		if (redirectTarget === route.fullPath || redirectTarget.startsWith('/riepilogo')) {
			await navigateTo('/la-tua-spedizione/2?step=ritiro', { replace: true });
			return false;
		}
		await navigateTo(redirectTarget, { replace: true });
		return false;
	}

	return true;
};

pageReady.value = await initRiepilogoPage();

// Formatta il prezzo da centesimi a euro con virgola (es. 950 -> "9,50€")
const formatPrice = (cents) => {
	if (!cents && cents !== 0) return '0,00€';
	const num = Number(cents) / 100;
	return num.toFixed(2).replace('.', ',') + '€';
};

const promptGuestAuth = (message) => {
	submitError.value = message;
	openAuthModal({
		redirect: route.fullPath,
		tab: 'login',
	});
};

const ensureAuthenticatedAction = (message) => {
	if (isAuthenticated.value || sanctumAuth.isAuthenticated?.value) {
		return true;
	}

	promptGuestAuth(message);
	return false;
};

const packageTypeVisualMap = {
	pacco: {
		label: 'Pacco',
		icon: '/img/quote/first-step/pack.png',
		wrapperClass: 'p-[8px] tablet:p-[9px]',
		iconClass: 'w-[28px] h-[28px] tablet:w-[32px] tablet:h-[32px]',
	},
	pallet: {
		label: 'Pallet',
		icon: '/img/quote/first-step/pallet.png',
		wrapperClass: 'p-[7px] tablet:p-[8px]',
		iconClass: 'w-[28px] h-[28px] tablet:w-[32px] tablet:h-[32px]',
	},
	valigia: {
		label: 'Valigia',
		icon: '/img/quote/first-step/suitcase.png',
		wrapperClass: 'p-[6px_10px] tablet:p-[7px_12px]',
		iconClass: 'w-[22px] h-[34px] tablet:w-[24px] tablet:h-[38px]',
	},
};

const normalizePackageType = (value) =>
	String(value || 'Pacco')
		.trim()
		.toLowerCase();

const getPackageTypeVisual = (pkg) => {
	const normalized = normalizePackageType(pkg?.package_type);
	return packageTypeVisualMap[normalized] || packageTypeVisualMap.pacco;
};

// Converte euro in centesimi (es. 9.50 -> 950)
const toCents = (euros) => Math.round(Number(euros) * 100);

// Flag: arrivo dalla modifica di un pacco nel carrello
const isEditFromCart = computed(() => !!editingId.value || !!editQueryId);

// Prepara il payload per il backend convertendo i prezzi da centesimi a euro
// IMPORTANTE: Il backend si aspetta prezzi in EURO e li moltiplica x100 per salvarli in centesimi
// Quando carichiamo dal carrello (edit mode), i prezzi sono in CENTESIMI → convertiamo in EURO
// Quando carichiamo dalla sessione (new mode), i prezzi sono già in EURO → nessuna conversione
const preparePayloadForBackend = (shipmentData) => {
	if (!shipmentData) return null;

	const payload = { ...shipmentData };

	// Se siamo in edit mode, i prezzi nei packages sono in centesimi → convertiamo in euro
	if (isEditFromCart.value && payload.packages) {
		payload.packages = payload.packages.map(pkg => ({
			...pkg,
			single_price: Number(pkg.single_price) / 100, // Centesimi → Euro
		}));
	}

	return payload;
};

// Prezzo totale: calcolato dai pacchi se disponibili, altrimenti dalla sessione
const totalPrice = computed(() => {
	// Se abbiamo i pacchi nel shipment, calcoliamo da quelli
	if (shipment.value?.packages && shipment.value.packages.length > 0) {
		const packagesTotal = shipment.value.packages.reduce((sum, pkg) => {
			// In edit mode i prezzi sono in centesimi, altrimenti in euro
			const price = isEditFromCart.value
				? (Number(pkg.single_price) || 0) / 100
				: (Number(pkg.single_price) || 0);
			const qty = Number(pkg.quantity) || 1;
			return sum + (price * qty);
		}, 0);
		const serviceSurcharge = calculateShipmentServiceSurcharge({
			serviceType: shipment.value?.services?.service_type || "",
			serviceData: shipment.value?.services?.serviceData || {},
			smsEmailNotification: Boolean(
				shipment.value?.sms_email_notification
				|| shipment.value?.services?.sms_email_notification
				|| shipment.value?.services?.serviceData?.sms_email_notification
			),
			pricingConfig: priceBands.value,
			packages: shipment.value?.packages || [],
			originAddress: shipment.value?.origin_address || {},
			destinationAddress: shipment.value?.destination_address || {},
			deliveryMode: shipment.value?.delivery_mode || shipment.value?.services?.serviceData?.delivery_mode || "home",
			selectedPudo: shipment.value?.selected_pudo || shipment.value?.pudo || shipment.value?.services?.serviceData?.pudo || null,
		}).total;

		return (packagesTotal + serviceSurcharge).toFixed(2).replace('.', ',');
	}
	// Fallback: prova dalla sessione
	const price = session.value?.data?.total_price;
	if (!price && price !== 0) return '0,00';
	return Number(price).toFixed(2).replace('.', ',');
});

// Numero ordine provvisorio (solo visuale, il vero numero viene assegnato al pagamento)
const preOrderNumber = useState('riepilogo-preorder-number', () => `SF-${Date.now().toString().slice(-6)}`);

// --- MODIFICA INLINE ---
// Indica quale sezione e' in fase di modifica: 'colli', 'origin', 'destination', 'services'
const editingSection = ref(null);

// Copie temporanee dei dati per la modifica inline
const editColli = ref([]);         // Copia dei colli in modifica
const editOrigin = ref({});        // Copia dell'indirizzo di partenza in modifica
const editDestination = ref({});   // Copia dell'indirizzo di destinazione in modifica
const serviceDisplayNameMap = {
	"Spedizione Senza etichetta": "Senza Etichetta",
	"Senza Etichetta": "Senza Etichetta",
	"Contrassegno": "Contrassegno",
	"Assicurazione": "Assicurazione",
	"Sponda idraulica": "Sponda idraulica",
};

const formatServiceDisplayName = (serviceName = '') => {
	const normalized = String(serviceName || '').trim();
	return serviceDisplayNameMap[normalized] || normalized;
};

// Avvia la modifica inline di una sezione (colli, origin, destination, services)
// Crea una copia dei dati originali per permettere annullamento
const startEdit = (section) => {
	if (section === 'services') {
		goToServicesEdit();
		return;
	}
	editingSection.value = section;
	if (section === 'colli' && shipment.value?.packages) {
		editColli.value = shipment.value.packages.map(p => ({ ...p }));
	}
	if (section === 'origin' && shipment.value?.origin_address) {
		editOrigin.value = { ...shipment.value.origin_address };
	}
	if (section === 'destination' && shipment.value?.destination_address) {
		editDestination.value = { ...shipment.value.destination_address };
	}
};

// Annulla la modifica in corso e chiude eventuali popup
const cancelEdit = () => {
	editingSection.value = null;
};

// Valida un indirizzo prima del salvataggio
const validateAddress = (addr) => {
	if (!addr.name?.trim()) return 'Nome obbligatorio';
	if (!addr.address?.trim()) return 'Indirizzo obbligatorio';
	if (!addr.city?.trim()) return 'Città obbligatoria';
	if (!addr.postal_code?.trim()) return 'CAP obbligatorio';
	if (!addr.province?.trim()) return 'Provincia obbligatoria';
	return null;
};

// Salva le modifiche inline nello store Pinia (pendingShipment)
const saveEdit = (section) => {
	if (section === 'colli' && userStore.pendingShipment) {
		userStore.pendingShipment.packages = editColli.value.map(p => ({ ...p }));
	}
	if (section === 'origin' && userStore.pendingShipment) {
		const error = validateAddress(editOrigin.value);
		if (error) {
			uiFeedback.error('Controlla i dati di partenza', error);
			return;
		}
		userStore.pendingShipment.origin_address = { ...editOrigin.value };
	}
	if (section === 'destination' && userStore.pendingShipment) {
		const error = validateAddress(editDestination.value);
		if (error) {
			uiFeedback.error('Controlla i dati di destinazione', error);
			return;
		}
		userStore.pendingShipment.destination_address = { ...editDestination.value };
	}
	editingSection.value = null;
	uiFeedback.success('Modifiche salvate.');
};

// --- AZIONI PRINCIPALI ---

// Vai direttamente al checkout: crea un ordine "diretto" SENZA passare dal carrello
// Usa l'endpoint /api/create-direct-order che salva pacchi + crea ordine in un colpo solo
// Se in modalita' modifica, prima aggiorna il pacco nel carrello
const proceedToCheckout = async () => {
	if (!shipment.value) return;
	if (!ensureAuthenticatedAction("Devi effettuare il login per procedere al pagamento.")) {
		return;
	}
	isSubmitting.value = true;
	submitError.value = null;
	try {
		// Prepara il payload convertendo i prezzi da centesimi a euro per il backend
		const payload = preparePayloadForBackend(shipment.value);

		// Se stiamo modificando un pacco, aggiorniamo prima nel carrello
		if (editingId.value) {
			await sanctumClient(`/api/cart/${editingId.value}`, {
				method: "PUT",
				body: payload,
			});
			userStore.editingCartItemId = null;
			userStore.pendingShipment = null;
			clearNuxtData("cart");
			uiFeedback.success('Dati salvati con successo!');
			// Dopo l'aggiornamento, naviga al checkout normale (dal carrello)
			navigateTo('/checkout');
			return;
		}

		// Create a standalone order directly (saves packages + creates order in one step)
		const result = await sanctumClient("/api/create-direct-order", {
			method: "POST",
			body: payload,
		});

		uiFeedback.success('Ordine creato con successo!');
		// Navigate to checkout with only this order
		navigateTo(`/checkout?order_id=${result.order_id}`);
	} catch (error) {
		const errorData = error?.response?._data || error?.data;
		submitError.value = errorData?.message || "Errore durante la creazione dell'ordine. Riprova.";
	} finally {
		isSubmitting.value = false;
	}
};

// Salva la spedizione nelle "spedizioni configurate" per riutilizzarla in futuro
const goToSavedShipments = async () => {
	if (!shipment.value) return;
	if (!ensureAuthenticatedAction("Devi effettuare il login per salvare le spedizioni configurate.")) {
		return;
	}
	isSubmitting.value = true;
	submitError.value = null;
	try {
		const payload = preparePayloadForBackend(shipment.value);
		await sanctumClient("/api/saved-shipments", {
			method: "POST",
			body: payload,
		});
		navigateTo('/account/spedizioni-configurate');
	} catch (error) {
		const errorData = error?.response?._data || error?.data;
		submitError.value = errorData?.message || "Errore durante il salvataggio. Riprova.";
	} finally {
		isSubmitting.value = false;
	}
};

// Salva la spedizione corrente e torna al preventivo per configurarne un'altra
const addAnotherShipment = async () => {
	if (!shipment.value) return;
	isSubmitting.value = true;
	if (isAuthenticated.value || sanctumAuth.isAuthenticated?.value) {
		try {
			const payload = preparePayloadForBackend(shipment.value);
			await sanctumClient("/api/saved-shipments", {
				method: "POST",
				body: payload,
			});
		} catch (e) {
		}
	}
	isSubmitting.value = false;
	navigateTo('/preventivo');
};

// Aggiunge la spedizione al carrello (o aggiorna se in modalita' modifica) e naviga alla pagina carrello
const goToCart = async () => {
	if (!shipment.value) return;

	// Validazione indirizzi prima del salvataggio
	const originError = validateAddress(shipment.value.origin_address);
	if (originError) {
		uiFeedback.error('Indirizzo partenza', originError, { timeout: 5000 });
		return;
	}

	const destError = validateAddress(shipment.value.destination_address);
	if (destError) {
		uiFeedback.error('Indirizzo destinazione', destError, { timeout: 5000 });
		return;
	}

	isSubmitting.value = true;
	submitError.value = null;
	try {
		const payload = preparePayloadForBackend(shipment.value);

		if (editingId.value) {
			// Modalita' modifica: aggiorniamo il pacco esistente nel carrello
			await sanctumClient(`/api/cart/${editingId.value}`, {
				method: "PUT",
				body: payload,
			});
			// Puliamo l'ID di modifica dallo store
			userStore.editingCartItemId = null;
			uiFeedback.success('Spedizione aggiornata nel carrello.');
		} else {
			// Nuova spedizione: aggiungiamo al carrello
			const cartEndpoint = endpoint.value || (isAuthenticated.value ? '/api/cart' : '/api/guest-cart');

			await sanctumClient(cartEndpoint, {
				method: "POST",
				body: payload,
			});
			uiFeedback.success('Spedizione aggiunta al carrello.');
		}

		// RESET COMPLETO: Puliamo tutti i dati per permettere nuovo preventivo
		userStore.pendingShipment = null;
		userStore.packages = [];
		userStore.servicesArray = [];
		userStore.contentDescription = '';
		userStore.shipmentDetails = {
			origin_city: '',
			origin_postal_code: '',
			destination_city: '',
			destination_postal_code: '',
			date: '',
		};

		// Invalidiamo la cache del carrello e forziamo il refresh
		clearNuxtData("cart");
		// Navighiamo al carrello con un query param per forzare il refresh
		// (il carrello leggera' questo param e fara' un refresh forzato)
		navigateTo('/carrello?updated=' + Date.now());
	} catch (error) {
		const errorData = error?.response?._data || error?.data;
		submitError.value = errorData?.message || "Errore durante il salvataggio nel carrello. Riprova.";
		uiFeedback.critical('Errore durante il salvataggio', submitError.value, { timeout: 8000 });
	} finally {
		isSubmitting.value = false;
	}
};

// Torna indietro allo step di configurazione ritiro
const goBack = () => {
	if (editingId.value) {
		// In modalita' modifica dal carrello: torna al carrello
		userStore.editingCartItemId = null;
		navigateTo('/carrello');
	} else {
		navigateTo('/la-tua-spedizione/2?step=ritiro');
	}
};

const goToServicesEdit = async () => {
	if (editingId.value) {
		await navigateTo(`/la-tua-spedizione/2?edit_id=${editingId.value}&step=ritiro`);
		return;
	}
	await navigateTo('/la-tua-spedizione/2?step=ritiro');
};
</script>

<template>
	<section class="min-h-[600px]">
		<div class="my-container mt-[20px] tablet:mt-[32px] mb-[48px] tablet:mb-[84px] px-[12px] tablet:px-0">
			<div v-if="!pageReady" class="space-y-[16px] animate-pulse">
				<div class="h-[64px] rounded-[16px] border border-[#E5EAEC] bg-white/90"></div>
				<div class="rounded-[18px] border border-[#E5EAEC] bg-white p-[18px] tablet:p-[24px] space-y-[14px]">
					<div class="h-[24px] w-[44%] rounded-[10px] bg-[#EEF3F5]"></div>
					<div class="grid grid-cols-1 tablet:grid-cols-2 gap-[12px]">
						<div class="h-[124px] rounded-[16px] bg-[#EEF3F5]"></div>
						<div class="h-[124px] rounded-[16px] bg-[#EEF3F5]"></div>
					</div>
					<div class="h-[82px] rounded-[16px] bg-[#EEF3F5]"></div>
					<div class="grid grid-flow-col auto-cols-[minmax(220px,1fr)] gap-[12px] overflow-hidden tablet:grid-flow-row tablet:grid-cols-3">
						<div class="h-[78px] rounded-[16px] bg-[#F4F7F9]"></div>
						<div class="h-[78px] rounded-[16px] bg-[#F4F7F9]"></div>
						<div class="h-[78px] rounded-[16px] bg-[#F4F7F9]"></div>
					</div>
				</div>
			</div>
			<template v-else>
			<Steps v-if="!isEditFromCart" :current-step="3" />

			<!-- Loading state per modifica dal carrello -->
			<div v-if="loadingEditData" class="text-center py-[60px]">
				<div class="inline-block w-[40px] h-[40px] border-4 border-[#E9EBEC] border-t-[#095866] rounded-full animate-spin mb-[16px]"></div>
				<p class="text-[1rem] text-[#737373]">Caricamento dati spedizione...</p>
			</div>

			<div v-else-if="!shipment" class="text-center py-[60px]">
				<p class="text-[1rem] text-[#737373]">Nessuna spedizione pronta. Torna alla configurazione.</p>
				<NuxtLink to="/la-tua-spedizione/2" class="btn-primary inline-flex mt-[20px] min-h-[48px] items-center justify-center">
					Torna alla configurazione
				</NuxtLink>
			</div>

			<div v-else class="mx-auto">
				<div class="sf-page-intro sf-page-intro--center mb-[28px] tablet:mb-[32px]">
					<h1 class="sf-section-title max-w-[16ch]">{{ editingId ? 'Aggiorna spedizione' : 'Riepilogo' }}</h1>
					<div v-if="!isEditFromCart" class="inline-flex items-center gap-[8px] rounded-[999px] border border-[#D6E7EA] bg-white px-[14px] py-[6px] shadow-[0_8px_18px_rgba(20,37,48,0.05)]">
						<span class="sf-section-kicker !mb-0">Ordine</span>
						<span class="font-mono text-[0.875rem] font-bold text-[#095866]">{{ preOrderNumber }}</span>
					</div>
				</div>

				<!-- Colli -->
				<div class="bg-[#E4E4E4] rounded-[8px] p-[16px] tablet:p-[28px_32px] mb-[16px]">
					<div class="flex items-center justify-between mb-[16px]">
						<h2 class="text-[1.125rem] font-bold text-[#252B42]">Colli</h2>
						<button type="button" @click="startEdit('colli')" class="sf-action-pill sf-action-pill--soft" title="Modifica colli">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
							<span>Modifica</span>
						</button>
					</div>

					<!-- View mode -->
					<div v-if="editingSection !== 'colli'" class="space-y-[10px]">
						<div v-for="(pkg, idx) in shipment.packages" :key="idx" class="bg-white rounded-[12px] p-[12px] tablet:p-[16px] flex items-center justify-between gap-[8px] tablet:gap-[16px]">
							<div class="flex items-center gap-[10px] tablet:gap-[16px] min-w-0">
								<div
									class="w-[48px] h-[48px] tablet:w-[56px] tablet:h-[56px] rounded-[12px] bg-[#F8F9FB] flex items-center justify-center shrink-0 overflow-visible"
									:class="getPackageTypeVisual(pkg).wrapperClass">
									<img
										:src="getPackageTypeVisual(pkg).icon"
										:alt="getPackageTypeVisual(pkg).label"
										loading="lazy"
										decoding="async"
										class="block shrink-0 object-contain"
										:class="getPackageTypeVisual(pkg).iconClass" />
								</div>
								<div class="min-w-0">
									<p class="text-[0.875rem] tablet:text-[0.9375rem] font-semibold text-[#252B42] truncate">{{ pkg.package_type || 'Pacco' }} #{{ idx + 1 }}</p>
									<p class="text-[0.75rem] tablet:text-[0.8125rem] text-[#737373]">{{ pkg.quantity || 1 }}x &ndash; {{ pkg.weight }} kg &ndash; {{ pkg.first_size }}x{{ pkg.second_size }}x{{ pkg.third_size }} cm</p>
								</div>
							</div>
							<span class="text-[0.875rem] tablet:text-[0.9375rem] font-bold text-[#252B42] shrink-0">{{ formatPrice(pkg.single_price) }}</span>
						</div>
					</div>

					<!-- Edit mode -->
					<div v-else class="space-y-[12px]">
						<div v-for="(pkg, idx) in editColli" :key="idx" class="bg-white rounded-[12px] p-[16px]">
							<p class="font-semibold text-[#252B42] mb-[10px]">Collo #{{ idx + 1 }}</p>
							<div class="grid grid-cols-2 tablet:grid-cols-4 gap-[10px]">
								<div>
									<label class="text-[0.75rem] text-[#737373]">Quantità</label>
									<input type="number" v-model="pkg.quantity" min="1" class="w-full bg-[#F1F1F1] rounded-[8px] h-[44px] text-center text-[1rem] px-[8px] transition-[border-color,box-shadow,background-color] duration-200 focus:border-[#095866] focus:bg-white focus:shadow-[0_0_0_3px_rgba(9,88,102,0.1)] border border-transparent" />
								</div>
								<div>
									<label class="text-[0.75rem] text-[#737373]">Peso (kg)</label>
									<input type="number" v-model="pkg.weight" min="0.1" step="0.1" class="w-full bg-[#F1F1F1] rounded-[8px] h-[44px] text-center text-[1rem] px-[8px] transition-[border-color,box-shadow,background-color] duration-200 focus:border-[#095866] focus:bg-white focus:shadow-[0_0_0_3px_rgba(9,88,102,0.1)] border border-transparent" />
								</div>
								<div>
									<label class="text-[0.75rem] text-[#737373]">L (cm)</label>
									<input type="number" v-model="pkg.first_size" min="1" class="w-full bg-[#F1F1F1] rounded-[8px] h-[44px] text-center text-[1rem] px-[8px] transition-[border-color,box-shadow,background-color] duration-200 focus:border-[#095866] focus:bg-white focus:shadow-[0_0_0_3px_rgba(9,88,102,0.1)] border border-transparent" />
								</div>
								<div>
									<label class="text-[0.75rem] text-[#737373]">P (cm)</label>
									<input type="number" v-model="pkg.second_size" min="1" class="w-full bg-[#F1F1F1] rounded-[8px] h-[44px] text-center text-[1rem] px-[8px] transition-[border-color,box-shadow,background-color] duration-200 focus:border-[#095866] focus:bg-white focus:shadow-[0_0_0_3px_rgba(9,88,102,0.1)] border border-transparent" />
								</div>
								<div>
									<label class="text-[0.75rem] text-[#737373]">H (cm)</label>
									<input type="number" v-model="pkg.third_size" min="1" class="w-full bg-[#F1F1F1] rounded-[8px] h-[44px] text-center text-[1rem] px-[8px] transition-[border-color,box-shadow,background-color] duration-200 focus:border-[#095866] focus:bg-white focus:shadow-[0_0_0_3px_rgba(9,88,102,0.1)] border border-transparent" />
								</div>
							</div>
						</div>
						<div class="flex gap-[10px] justify-end">
							<button type="button" @click="cancelEdit" class="sf-action-pill sf-action-pill--neutral">Annulla</button>
							<button type="button" @click="saveEdit('colli')" class="btn-primary">Salva</button>
						</div>
					</div>
				</div>

				<!-- Indirizzi -->
				<div class="grid grid-cols-1 desktop:grid-cols-2 gap-[16px] mb-[16px]">
					<!-- Partenza -->
					<div class="bg-[#E4E4E4] rounded-[16px] p-[16px] tablet:p-[28px_32px]">
						<div class="flex items-center justify-between mb-[16px]">
							<h2 class="text-[1.125rem] font-bold text-[#252B42] flex items-center gap-[8px]">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#E44203" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
								Partenza
							</h2>
							<button type="button" @click="startEdit('origin')" class="sf-action-pill sf-action-pill--soft" title="Modifica partenza">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
								<span>Modifica</span>
							</button>
						</div>

						<!-- View mode -->
						<div v-if="editingSection !== 'origin'" class="text-[0.875rem] text-[#252B42] space-y-[4px]">
							<p class="font-semibold">{{ shipment.origin_address?.name }}</p>
							<p>{{ shipment.origin_address?.address }} {{ shipment.origin_address?.address_number }}</p>
							<p>{{ shipment.origin_address?.postal_code }} {{ shipment.origin_address?.city }} ({{ shipment.origin_address?.province }})</p>
							<p v-if="shipment.origin_address?.telephone_number && shipment.origin_address.telephone_number !== '0000000000'" class="text-[#737373]">Tel: {{ shipment.origin_address.telephone_number }}</p>
							<p v-if="shipment.origin_address?.email" class="text-[#737373]">{{ shipment.origin_address.email }}</p>
						</div>

						<!-- Edit mode -->
						<div v-else class="space-y-[10px]">
							<div>
								<label class="text-[0.75rem] text-[#737373]">Nome e Cognome</label>
								<input type="text" v-model="editOrigin.name" class="w-full bg-white rounded-[8px] h-[44px] px-[10px] text-[1rem] border border-[#D0D0D0] transition-[border-color,box-shadow,background-color] duration-200 focus:border-[#095866] focus:shadow-[0_0_0_3px_rgba(9,88,102,0.1)]" />
							</div>
							<div class="grid grid-cols-1 tablet:grid-cols-2 gap-[10px]">
								<div>
									<label class="text-[0.75rem] text-[#737373]">Indirizzo</label>
									<input type="text" v-model="editOrigin.address" class="w-full bg-white rounded-[8px] h-[44px] px-[10px] text-[1rem] border border-[#D0D0D0] transition-[border-color,box-shadow,background-color] duration-200 focus:border-[#095866] focus:shadow-[0_0_0_3px_rgba(9,88,102,0.1)]" />
								</div>
								<div>
									<label class="text-[0.75rem] text-[#737373]">N. Civico</label>
									<input type="text" v-model="editOrigin.address_number" class="w-full bg-white rounded-[8px] h-[44px] px-[10px] text-[1rem] border border-[#D0D0D0] transition-[border-color,box-shadow,background-color] duration-200 focus:border-[#095866] focus:shadow-[0_0_0_3px_rgba(9,88,102,0.1)]" />
								</div>
							</div>
							<div class="grid grid-cols-2 tablet:grid-cols-3 gap-[10px]">
								<div>
									<label class="text-[0.75rem] text-[#737373]">Città</label>
									<input type="text" v-model="editOrigin.city" class="w-full bg-white rounded-[8px] h-[44px] px-[10px] text-[1rem] border border-[#D0D0D0] transition-[border-color,box-shadow,background-color] duration-200 focus:border-[#095866] focus:shadow-[0_0_0_3px_rgba(9,88,102,0.1)]" required />
								</div>
								<div>
									<label class="text-[0.75rem] text-[#737373]">CAP</label>
									<input
										type="text"
										v-model="editOrigin.postal_code"
										maxlength="5"
										inputmode="numeric"
										pattern="[0-9]{5}"
										@input="editOrigin.postal_code = editOrigin.postal_code.replace(/\D/g, '')"
										class="w-full bg-white rounded-[8px] h-[44px] px-[10px] text-[1rem] border border-[#D0D0D0] transition-[border-color,box-shadow,background-color] duration-200 focus:border-[#095866] focus:shadow-[0_0_0_3px_rgba(9,88,102,0.1)]"
										required />
								</div>
								<div class="col-span-2 tablet:col-span-1">
									<label class="text-[0.75rem] text-[#737373]">Provincia</label>
									<input type="text" v-model="editOrigin.province" class="w-full bg-white rounded-[8px] h-[44px] px-[10px] text-[1rem] border border-[#D0D0D0] transition-[border-color,box-shadow,background-color] duration-200 focus:border-[#095866] focus:shadow-[0_0_0_3px_rgba(9,88,102,0.1)]" required />
								</div>
							</div>
							<div class="grid grid-cols-1 tablet:grid-cols-2 gap-[10px]">
								<div>
									<label class="text-[0.75rem] text-[#737373]">Telefono</label>
									<input type="tel" v-model="editOrigin.telephone_number" class="w-full bg-white rounded-[8px] h-[44px] px-[10px] text-[1rem] border border-[#D0D0D0] transition-[border-color,box-shadow,background-color] duration-200 focus:border-[#095866] focus:shadow-[0_0_0_3px_rgba(9,88,102,0.1)]" />
								</div>
								<div>
									<label class="text-[0.75rem] text-[#737373]">Email</label>
									<input type="email" v-model="editOrigin.email" class="w-full bg-white rounded-[8px] h-[44px] px-[10px] text-[1rem] border border-[#D0D0D0] transition-[border-color,box-shadow,background-color] duration-200 focus:border-[#095866] focus:shadow-[0_0_0_3px_rgba(9,88,102,0.1)]" />
								</div>
							</div>
							<div class="flex gap-[10px] justify-end">
								<button type="button" @click="cancelEdit" class="sf-action-pill sf-action-pill--neutral">Annulla</button>
								<button type="button" @click="saveEdit('origin')" class="btn-primary">Salva</button>
							</div>
						</div>
					</div>

					<!-- Destinazione -->
					<div class="bg-[#E4E4E4] rounded-[16px] p-[16px] tablet:p-[28px_32px]">
						<div class="flex items-center justify-between mb-[16px]">
							<h2 class="text-[1.125rem] font-bold text-[#252B42] flex items-center gap-[8px]">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#095866" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
								Destinazione
							</h2>
							<!-- In modalità PUDO il modifica riporta a step 2 per cambiare il punto -->
							<button type="button" @click="shipment.pudo ? goBack() : startEdit('destination')" class="sf-action-pill sf-action-pill--soft" title="Modifica destinazione">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
								<span>Modifica</span>
							</button>
						</div>

						<!-- View mode -->
						<div v-if="editingSection !== 'destination'" class="text-[0.875rem] text-[#252B42] space-y-[4px]">
							<p class="font-semibold">{{ shipment.destination_address?.name }}</p>
							<template v-if="!shipment.pudo">
								<p>{{ shipment.destination_address?.address }} {{ shipment.destination_address?.address_number }}</p>
								<p>{{ shipment.destination_address?.postal_code }} {{ shipment.destination_address?.city }} ({{ shipment.destination_address?.province }})</p>
							</template>
							<template v-else>
								<div class="my-[8px] p-[10px] bg-[#095866]/10 rounded-[10px] flex items-start gap-[8px]">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#095866" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-[2px]"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
									<div class="text-[0.8125rem]">
										<span class="font-bold text-[#095866]">Ritiro in Punto BRT</span>
										<p class="text-[#252B42] font-semibold mt-[2px]">{{ shipment.pudo.name }}</p>
										<p class="text-[#737373]">{{ shipment.pudo.address }}, {{ shipment.pudo.zip_code }} {{ shipment.pudo.city }}</p>
									</div>
								</div>
							</template>
							<p v-if="shipment.destination_address?.telephone_number && shipment.destination_address.telephone_number !== '0000000000'" class="text-[#737373]">Tel: {{ shipment.destination_address.telephone_number }}</p>
							<p v-if="shipment.destination_address?.email" class="text-[#737373]">{{ shipment.destination_address.email }}</p>
						</div>

						<!-- Edit mode -->
						<div v-else class="space-y-[10px]">
							<div>
								<label class="text-[0.75rem] text-[#737373]">Nome e Cognome</label>
								<input type="text" v-model="editDestination.name" class="w-full bg-white rounded-[8px] h-[44px] px-[10px] text-[1rem] border border-[#D0D0D0] transition-[border-color,box-shadow,background-color] duration-200 focus:border-[#095866] focus:shadow-[0_0_0_3px_rgba(9,88,102,0.1)]" />
							</div>
							<div class="grid grid-cols-1 tablet:grid-cols-2 gap-[10px]">
								<div>
									<label class="text-[0.75rem] text-[#737373]">Indirizzo</label>
									<input type="text" v-model="editDestination.address" class="w-full bg-white rounded-[8px] h-[44px] px-[10px] text-[1rem] border border-[#D0D0D0] transition-[border-color,box-shadow,background-color] duration-200 focus:border-[#095866] focus:shadow-[0_0_0_3px_rgba(9,88,102,0.1)]" />
								</div>
								<div>
									<label class="text-[0.75rem] text-[#737373]">N. Civico</label>
									<input type="text" v-model="editDestination.address_number" class="w-full bg-white rounded-[8px] h-[44px] px-[10px] text-[1rem] border border-[#D0D0D0] transition-[border-color,box-shadow,background-color] duration-200 focus:border-[#095866] focus:shadow-[0_0_0_3px_rgba(9,88,102,0.1)]" />
								</div>
							</div>
							<div class="grid grid-cols-2 tablet:grid-cols-3 gap-[10px]">
								<div>
									<label class="text-[0.75rem] text-[#737373]">Città</label>
									<input type="text" v-model="editDestination.city" class="w-full bg-white rounded-[8px] h-[44px] px-[10px] text-[1rem] border border-[#D0D0D0] transition-[border-color,box-shadow,background-color] duration-200 focus:border-[#095866] focus:shadow-[0_0_0_3px_rgba(9,88,102,0.1)]" required />
								</div>
								<div>
									<label class="text-[0.75rem] text-[#737373]">CAP</label>
									<input
										type="text"
										v-model="editDestination.postal_code"
										maxlength="5"
										inputmode="numeric"
										pattern="[0-9]{5}"
										@input="editDestination.postal_code = editDestination.postal_code.replace(/\D/g, '')"
										class="w-full bg-white rounded-[8px] h-[44px] px-[10px] text-[1rem] border border-[#D0D0D0] transition-[border-color,box-shadow,background-color] duration-200 focus:border-[#095866] focus:shadow-[0_0_0_3px_rgba(9,88,102,0.1)]"
										required />
								</div>
								<div class="col-span-2 tablet:col-span-1">
									<label class="text-[0.75rem] text-[#737373]">Provincia</label>
									<input type="text" v-model="editDestination.province" class="w-full bg-white rounded-[8px] h-[44px] px-[10px] text-[1rem] border border-[#D0D0D0] transition-[border-color,box-shadow,background-color] duration-200 focus:border-[#095866] focus:shadow-[0_0_0_3px_rgba(9,88,102,0.1)]" required />
								</div>
							</div>
							<div class="grid grid-cols-1 tablet:grid-cols-2 gap-[10px]">
								<div>
									<label class="text-[0.75rem] text-[#737373]">Telefono</label>
									<input type="tel" v-model="editDestination.telephone_number" class="w-full bg-white rounded-[8px] h-[44px] px-[10px] text-[1rem] border border-[#D0D0D0] transition-[border-color,box-shadow,background-color] duration-200 focus:border-[#095866] focus:shadow-[0_0_0_3px_rgba(9,88,102,0.1)]" />
								</div>
								<div>
									<label class="text-[0.75rem] text-[#737373]">Email</label>
									<input type="email" v-model="editDestination.email" class="w-full bg-white rounded-[8px] h-[44px] px-[10px] text-[1rem] border border-[#D0D0D0] transition-[border-color,box-shadow,background-color] duration-200 focus:border-[#095866] focus:shadow-[0_0_0_3px_rgba(9,88,102,0.1)]" />
								</div>
							</div>
							<div class="flex gap-[10px] justify-end">
								<button type="button" @click="cancelEdit" class="sf-action-pill sf-action-pill--neutral">Annulla</button>
								<button type="button" @click="saveEdit('destination')" class="btn-primary">Salva</button>
							</div>
						</div>
					</div>
				</div>

				<!-- Servizi + Data -->
				<div class="bg-[#E4E4E4] rounded-[16px] p-[12px] tablet:p-[24px_28px] mb-[12px] tablet:mb-[16px]">
					<div class="flex items-center justify-between mb-[10px] tablet:mb-[12px]">
						<h2 class="text-[1rem] tablet:text-[1.125rem] font-bold text-[#252B42]">Servizi e data ritiro</h2>
						<button type="button" @click="goToServicesEdit" class="sf-action-pill sf-action-pill--soft" title="Modifica servizi">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
							<span>Modifica</span>
						</button>
					</div>
					<div class="space-y-[8px]">
						<div class="mb-[6px] tablet:mb-[8px]">
							<p class="text-[0.6875rem] tablet:text-[0.75rem] font-bold text-[#737373] uppercase tracking-wider mb-[4px] tablet:mb-[6px]">Servizi</p>
							<div v-if="shipment.services?.service_type && shipment.services.service_type !== 'Nessuno'" class="flex flex-wrap gap-[5px]">
								<span
									v-for="svc in shipment.services.service_type.split(', ').filter(Boolean)"
									:key="svc"
									class="inline-block px-[10px] py-[3px] bg-[#095866]/10 text-[#095866] rounded-[8px] text-[0.75rem] tablet:text-[0.8125rem] font-semibold">
									{{ formatServiceDisplayName(svc) }}
								</span>
							</div>
							<p v-else class="text-[0.875rem] tablet:text-[0.9375rem] text-[#737373]">Nessun servizio attivo</p>
						</div>
						<div v-if="shipment.services?.date" class="mt-[8px] tablet:mt-[10px]">
							<p class="text-[0.6875rem] tablet:text-[0.75rem] font-bold text-[#737373] uppercase tracking-wider mb-[3px] tablet:mb-[4px]">Data ritiro</p>
							<p class="text-[0.875rem] tablet:text-[0.9375rem] text-[#252B42] font-semibold">{{ shipment.services.date }}</p>
						</div>
					</div>
				</div>

				<!-- Totale -->
				<div class="sf-surface-card border-[#f1d8cb] bg-[linear-gradient(135deg,#ffffff_0%,#fff8f3_72%,#fff1e8_100%)] p-[16px] tablet:p-[24px_32px] mb-[24px]">
						<div class="flex items-center justify-between gap-[12px]">
							<span class="text-[#252B42] text-[1rem] tablet:text-[1.125rem] font-semibold">Totale</span>
						<span class="text-[#E44203] text-[1.5rem] tablet:text-[1.75rem] font-bold">{{ totalPrice }}€</span>
					</div>
					<!-- Promo badge -->
					<div v-if="promoSettings?.active && promoSettings?.label_text" class="flex justify-end mt-[8px]">
						<span
							:style="{ backgroundColor: promoSettings.label_color || '#E44203' }"
							class="inline-flex items-center gap-[5px] px-[10px] py-[4px] rounded-[6px] text-white text-[0.75rem] font-bold tracking-wide">
							<!-- Ottimizzazione: lazy loading + decoding async + dimensioni per CLS -->
							<img v-if="promoSettings.label_image" :src="promoSettings.label_image" alt="" loading="lazy" decoding="async" width="30" height="14" class="h-[14px] w-auto" />
							{{ promoSettings.label_text }}
						</span>
					</div>
				</div>

				<!-- Error -->
				<div v-if="submitError" class="ux-alert ux-alert--soft mb-[16px]">
					<Icon name="mdi:alert-circle-outline" class="ux-alert__icon" />
					<span>{{ submitError }}</span>
				</div>

				<!-- Indietro + Procedi al pagamento -->
				<div class="flex flex-col tablet:flex-row items-stretch tablet:items-center justify-between gap-[12px] mb-[24px]">
					<button @click="goBack" class="btn-secondary sf-nav-button inline-flex items-center justify-center gap-[8px]">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
						{{ isEditFromCart ? 'Torna al carrello' : 'Indietro' }}
					</button>
					<button
						v-if="!isEditFromCart"
						@click="proceedToCheckout"
						:disabled="isSubmitting"
						class="btn-cta sf-nav-button inline-flex items-center justify-center gap-[8px] disabled:opacity-60 disabled:cursor-not-allowed">
						<span v-if="isSubmitting">Caricamento...</span>
						<span v-else>Procedi al pagamento</span>
						<svg v-if="!isSubmitting" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/></svg>
					</button>
				</div>

				<!-- Azioni secondarie -->
				<div
					class="flex gap-[10px] overflow-x-auto snap-x snap-mandatory pb-[6px] -mx-[2px] px-[2px] tablet:grid tablet:grid-cols-3 tablet:overflow-visible tablet:gap-[14px] desktop:gap-[18px] tablet:pb-0"
				>
					<!-- In edit mode dal carrello: solo "Salva modifiche" -->
					<button
						v-if="isEditFromCart"
						@click="goToCart"
						:disabled="isSubmitting"
							class="sf-action-card w-full disabled:opacity-60 tablet:col-span-3">
						<div class="sf-action-card__icon-shell">
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="text-[#095866]"><path fill="currentColor" d="M15 9H5V5h10m-3 14a3 3 0 0 1-3-3a3 3 0 0 1 3-3a3 3 0 0 1 3 3a3 3 0 0 1-3 3m5-16H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7z"/></svg>
						</div>
								<div class="text-left flex-1">
									<p class="sf-action-card__title text-[#095866]">Salva modifiche</p>
								</div>
							<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" class="sf-action-card__arrow"><path fill="currentColor" d="M8.59 16.59L13.17 12L8.59 7.41L10 6l6 6l-6 6z"/></svg>
						</button>
						<template v-if="!isEditFromCart">
							<button
								@click="goToCart"
								:disabled="isSubmitting"
								class="sf-action-card w-[min(84vw,292px)] tablet:w-full shrink-0 snap-start tablet:min-h-[94px] disabled:opacity-60">
							<div class="sf-action-card__icon-shell">
								<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="text-[#095866]"><path fill="currentColor" d="M17 18a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2c0-1.11.89-2 2-2M1 2h3.27l.94 2H20a1 1 0 0 1 1 1c0 .17-.05.34-.12.5l-3.58 6.47c-.34.61-1 1.03-1.75 1.03H8.1l-.9 1.63l-.03.12a.25.25 0 0 0 .25.25H19v2H7a2 2 0 0 1-2-2c0-.35.09-.68.24-.96l1.36-2.45L3 4H1zm6 16a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2c0-1.11.89-2 2-2"/></svg>
							</div>
								<div class="text-left flex-1">
									<p class="sf-action-card__title">Aggiungi al carrello</p>
								</div>
							<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" class="sf-action-card__arrow"><path fill="currentColor" d="M8.59 16.59L13.17 12L8.59 7.41L10 6l6 6l-6 6z"/></svg>
						</button>

						<button
							@click="goToSavedShipments"
							:disabled="isSubmitting"
							:class="['sf-action-card w-[min(84vw,292px)] tablet:w-full shrink-0 snap-start tablet:min-h-[94px] disabled:opacity-60', !isAuthenticated ? 'sf-action-card--locked' : '']">
							<div class="sf-action-card__icon-shell">
								<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="text-[#095866]"><path fill="currentColor" d="M21 16.5c0 .38-.21.71-.53.88l-7.9 4.44c-.16.12-.36.18-.57.18c-.21 0-.41-.06-.57-.18l-7.9-4.44A.99.99 0 0 1 3 16.5v-9c0-.38.21-.71.53-.88l7.9-4.44c.16-.12.36-.18.57-.18c.21 0 .41.06.57.18l7.9 4.44c.32.17.53.5.53.88zM12 4.15L6.04 7.5L12 10.85l5.96-3.35zM5 15.91l6 3.37v-6.73L5 9.18zm14 0V9.18l-6 3.37v6.73z"/></svg>
							</div>
								<div class="text-left flex-1">
									<p class="sf-action-card__title">{{ isAuthenticated ? 'Salva configurazione' : 'Salva configurazione' }}</p>
								</div>
							<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" class="sf-action-card__arrow"><path fill="currentColor" d="M8.59 16.59L13.17 12L8.59 7.41L10 6l6 6l-6 6z"/></svg>
						</button>

						<button
							@click="addAnotherShipment"
							:disabled="isSubmitting"
							class="sf-action-card w-[min(84vw,292px)] tablet:w-full shrink-0 snap-start tablet:min-h-[94px] disabled:opacity-60">
							<div class="sf-action-card__icon-shell sf-action-card__icon-shell--accent">
								<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="text-orange-600"><path fill="currentColor" d="M17 13h-4v4h-2v-4H7v-2h4V7h2v4h4m-5-9A10 10 0 0 0 2 12a10 10 0 0 0 10 10a10 10 0 0 0 10-10A10 10 0 0 0 12 2"/></svg>
							</div>
								<div class="text-left flex-1">
									<p class="sf-action-card__title">Nuova spedizione</p>
								</div>
							<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" class="sf-action-card__arrow"><path fill="currentColor" d="M8.59 16.59L13.17 12L8.59 7.41L10 6l6 6l-6 6z"/></svg>
						</button>
					</template>
				</div>
			</div>
			</template>
		</div>
	</section>
</template>
