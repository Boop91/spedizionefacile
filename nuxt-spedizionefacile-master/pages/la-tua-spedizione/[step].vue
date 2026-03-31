<!--
  FILE: pages/la-tua-spedizione/[step].vue
  SCOPO: Configurazione multi-step — Step 1: servizi/data ritiro; Step 2: indirizzi mittente/destinatario.
  API: GET /api/session (dati sessione), GET /api/user-addresses (rubrica),
       GET /api/locations/search (autocompletamento citta'), GET /api/saved-shipments (configurazioni).
  STORE: userStore.pendingShipment (salva dati per riepilogo).
  ROUTE: /la-tua-spedizione/1 e /la-tua-spedizione/2 (middleware shipment-validation).
-->
<script setup>
const userStore = useUserStore();
const route = useRoute();
const { openAuthModal } = useAuthModal();
const { isAuthenticatedForUi } = useAuthUiState();

// Step corrente dalla route
const currentStep = computed(() => Number(route.params.step));

// Importa Swiper per il carosello delle date di ritiro
import { Swiper, SwiperSlide } from "swiper/vue";
import "swiper/css";
import "swiper/css/navigation";
import { Navigation } from "swiper/modules";

// Protegge la pagina: deve esserci una sessione con i dati dei pacchi
definePageMeta({
	middleware: ["shipment-validation"],
});

const { session, status, refresh } = useSession();
const dateError = ref(null);
const submitError = ref(null);
const isAuthenticated = isAuthenticatedForUi;
const sanctumClient = useSanctumClient();
const quoteTransitionLock = useState('shipment-flow-quote-transition-lock', () => false);
const deliveryMode = computed({
	get: () => userStore.deliveryMode,
	set: (value) => { userStore.deliveryMode = value; },
});

const pickupDateSectionRef = ref(null);

const {
	chooseDate,
	chooseService,
	daysInMonth,
	ensureServiceSelected,
	expandedServiceName,
	featuredService,
	regularServices,
	removeServiceFromSidebar,
	resetServicesState,
	serviceData,
	services,
	servicesList,
	smsEmailNotification,
	notificationPriceLabel,
	syncSelectedServicesVisual,
	toggleServiceDetails,
	toggleServiceSelection,
} = useShipmentStepServices({
	userStore,
	dateError,
});

const SERVICE_ICON_FILTER_IDLE = "brightness(0) saturate(100%) invert(23%) sepia(23%) saturate(1100%) hue-rotate(151deg) brightness(92%) contrast(88%)";
const SERVICE_ICON_FILTER_ACTIVE = SERVICE_ICON_FILTER_IDLE;

// editablePackages definito qui per uso nel composable service cards
const editablePackages = computed(() => {
	if (editCartId && userStore.packages?.length > 0 && !session.value?.data?.packages?.length) {
		return userStore.packages;
	}
	if (session.value?.data?.packages?.length) return session.value.data.packages;
	if (userStore.packages?.length) return userStore.packages;
	return [];
});

const {
	serviceCardErrors,
	normalizeCurrencyInput,
	parseCurrencyValue,
	contrassegnoIncassoOptions,
	contrassegnoRimborsoOptions,
	requiresContrassegnoDettaglio,
	insurancePackages,
	validateInlineServiceDetails,
	isConfigurableServiceReady,
	isServiceExpanded,
	isServiceSelected,
	featuredServiceIndex,
	canConfigureService,
	shouldShowServiceToggle,
	shouldShowConfigureButton,
	canActivateConfiguredService,
	getServiceStateLabel,
	getServiceConfigureLabel,
	handleServicePrimaryAction,
	toggleRegularService,
	toggleServiceAccordion,
	toggleFeaturedService,
} = useShipmentStepServiceCards({
	editablePackages,
	ensureServiceSelected,
	expandedServiceName,
	featuredService,
	chooseService,
	resetServicesState,
	serviceData,
	servicesList,
	smsEmailNotification,
	submitError,
	toggleServiceDetails,
	toggleServiceSelection,
	userStore,
});

const hasPersistedServiceSelection = computed(() => {
	const serviceType = String(session.value?.data?.services?.service_type || "").trim();
	const notificationsEnabled = Boolean(
		session.value?.data?.sms_email_notification
		?? session.value?.data?.services?.sms_email_notification
		?? false,
	);

	return Boolean(serviceType) || notificationsEnabled;
});

const showInitialStepLoading = computed(() => {
	if (loadingEditData.value) return true;
	if (status.value !== 'pending') return false;

	const hasSessionSnapshot = Boolean(session.value?.data?.shipment_details)
		|| (Array.isArray(session.value?.data?.packages) && session.value.data.packages.length > 0);
	const hasLocalQuoteSnapshot = Array.isArray(userStore.packages) && userStore.packages.length > 0;
	return !hasSessionSnapshot && !hasLocalQuoteSnapshot;
});

const isOriginDetailsEdited = ref(false);
const isDestinationDetailsEdited = ref(false);

const temporaryShipmentDetails = ref({});

const editOriginDetails = () => {
	temporaryShipmentDetails.value = { ...userStore.shipmentDetails };

	isOriginDetailsEdited.value = !isOriginDetailsEdited.value;
};

const editDestinationDetails = () => {
	temporaryShipmentDetails.value = { ...userStore.shipmentDetails };

	isDestinationDetailsEdited.value = !isDestinationDetailsEdited.value;
};

watch(
	() => [currentStep.value, status.value, userStore.editingCartItemId, hasPersistedServiceSelection.value],
	([step, sessionStatus, editingCartItemId, persistedSelection]) => {
		if (step !== 2) return;
		if (sessionStatus === "pending") return;
		if (editingCartItemId) return;
		if (persistedSelection) return;
		if (!userStore.servicesArray.length && !smsEmailNotification.value) return;

		resetServicesState();
	},
	{ immediate: true },
);
const {
	applySavedAddress,
	canSaveDestAddress,
	canSaveOriginAddress,
	clearAddressSelectorsAndPrompts,
	defaultDropdownRef,
	destDefaultDropdownRef,
	destFromSaved,
	destSaveSuccess,
	destSavedSnapshot,
	destSelectorRef,
	destinationAddress,
	loadSavedAddresses,
	loadingSavedAddresses,
	originAddress,
	originFromSaved,
	originSaveSuccess,
	originSavedSnapshot,
	originSelectorRef,
	saveAddressToBook,
	savedAddresses,
	savingDestAddress,
	savingOriginAddress,
	showDestAddressSelector,
	showDestConfigGuestPrompt,
	showDestGuestPrompt,
	showOriginAddressSelector,
	showOriginConfigGuestPrompt,
	showOriginGuestPrompt,
	shouldAutoShowAddressFields,
	toggleAddressSelector,
} = useShipmentStepAddresses({
	userStore,
	session,
	route,
	isAuthenticated,
	sanctumClient,
	deliveryMode,
	submitError,
});

const { persistShipmentFlowState } = useShipmentStepSessionPersistence({
	sanctumClient,
	refresh,
	session,
	submitError,
	userStore,
	services,
	smsEmailNotification,
	originAddress,
	destinationAddress,
});

const openShipmentAuthModal = (tab = 'login') => {
	openAuthModal({
		redirect: route.fullPath,
		tab,
	});
};

// --- VALIDAZIONE CAMPI (Smart Validation) ---
const contentError = ref(null);
const {
	applyFieldAssist,
	contentFieldHint,
	destCapSuggestions,
	destCitySuggestions,
	destProvinceSuggestions,
	destinationSectionHint,
	fieldClass,
	fieldErrorText,
	focusFormError,
	focusContentDescriptionField,
	focusFirstFormError,
	formErrorSummary,
	formatCapSuggestionLabel,
	formatCitySuggestionLabel,
	getFieldAssist,
	getFieldError,
	normalizeLocationText,
	onCapFocus,
	onCapInput,
	onCityFocus,
	onCityInput,
	onNameInput,
	onProvinciaInput,
	onProvinceFocus,
	onTelefonoInput,
	originCapSuggestions,
	originCitySuggestions,
	originProvinceSuggestions,
	originSectionHint,
	selectCap,
	selectCity,
	selectProvincia,
	showGlobalFormSummary,
	smartBlur,
	softenErrorMessage,
	sv,
	validateForm,
} = useShipmentStepValidation({
	contentError,
	dateError,
	deliveryMode,
	destinationAddress,
	originAddress,
	sanctumClient,
	services,
	userStore,
});

const days = ["Lun", "Mar", "Mer", "Gio", "Ven"];

const formRef = ref(null);
const editingSidebarColli = ref(false);
const focusPickupDateSection = () => {
	nextTick(() => {
		const sectionEl = pickupDateSectionRef.value;
		if (sectionEl && typeof sectionEl.scrollIntoView === 'function') {
			sectionEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
		const firstDateInput = document.querySelector('[id^="date-"]');
		if (firstDateInput && typeof firstDateInput.focus === 'function') {
			firstDateInput.focus({ preventScroll: true });
		}
	});
};

const {
	canOpenAddressFields,
	goBackToServices,
	onPudoDeselected,
	onPudoSelected,
	openAddressFields,
	showAddressFields,
} = useShipmentStepFlow({
	contentError,
	dateError,
	deliveryMode,
	destinationAddress,
	focusContentDescriptionField,
	focusPickupDateSection,
	normalizeLocationText,
	persistServicesStep: () => persistShipmentFlowState({ includeAddresses: false }),
	session,
	services,
	shouldAutoShowAddressFields,
	sv,
	userStore,
});

const stepsRef = ref(null);
const {
	canExpandSummaryDimensions,
	canExpandSummaryServices,
	currentShipmentStep,
	goToSummaryMiniStep,
	routeConsistencyState,
	routeWarningMessage,
	showSummaryMiniSteps,
	summaryDetailPanel,
	summaryDimensionsItems,
	summaryDimensionsLabel,
	summaryDestinationCity,
	summaryExpanded,
	summaryMiniSteps,
	summaryOriginCity,
	summaryPackageLabel,
	summaryPackageTypeInfo,
	summaryRouteLabel,
	summaryServicesItems,
	summaryServicesLabel,
	summaryTotalPrice,
	toggleSummaryDetailPanel,
} = useShipmentStepSummary({
	destinationAddress,
	editablePackages,
	normalizeLocationText,
	originAddress,
	session,
	showAddressFields,
	status,
	stepsRef,
	userStore,
});

// Action handlers moved to /riepilogo page

const { endpoint, refresh: refreshCart } = useCart();

// --- MODIFICA DA CARRELLO ---
// Se la URL contiene ?edit=123, carichiamo i dati del pacco dal carrello e pre-compiliamo tutto
const editCartId = route.query.edit ? Number(route.query.edit) : null;
const loadingEditData = ref(!!editCartId);

const loadCartItemForEdit = async () => {
	if (!editCartId) return;
	if (!isAuthenticated.value) {
		loadingEditData.value = false;
		return;
	}
	try {
		const result = await sanctumClient(`/api/cart/${editCartId}`);
		const item = result?.data || result;

		userStore.editingCartItemId = editCartId;

		if (item.origin_address) {
			originAddress.value.full_name = item.origin_address.name || "";
			originAddress.value.address = item.origin_address.address || "";
			originAddress.value.address_number = item.origin_address.address_number || "";
			originAddress.value.city = item.origin_address.city || "";
			originAddress.value.postal_code = item.origin_address.postal_code || "";
			originAddress.value.province = item.origin_address.province || "";
			originAddress.value.telephone_number = item.origin_address.telephone_number || "";
			originAddress.value.email = item.origin_address.email || "";
			originAddress.value.additional_information = item.origin_address.additional_information || "";
			originAddress.value.intercom_code = item.origin_address.intercom_code || "";
		}

		if (item.destination_address) {
			destinationAddress.value.full_name = item.destination_address.name || "";
			destinationAddress.value.address = item.destination_address.address || "";
			destinationAddress.value.address_number = item.destination_address.address_number || "";
			destinationAddress.value.city = item.destination_address.city || "";
			destinationAddress.value.postal_code = item.destination_address.postal_code || "";
			destinationAddress.value.province = item.destination_address.province || "";
			destinationAddress.value.telephone_number = item.destination_address.telephone_number || "";
			destinationAddress.value.email = item.destination_address.email || "";
			destinationAddress.value.additional_information = item.destination_address.additional_information || "";
			destinationAddress.value.intercom_code = item.destination_address.intercom_code || "";
		}

		if (item.services) {
			services.value.date = item.services.date || "";
			services.value.time = item.services.time || "";
			services.value.service_type = item.services.service_type || "";
			userStore.pickupDate = item.services.date || "";

			const serviceTypes = (item.services.service_type || "").split(", ").filter(s => s && s !== "Nessuno");
			userStore.servicesArray = serviceTypes;
			syncSelectedServicesVisual();
		}

		if (item.content_description) {
			userStore.contentDescription = item.content_description;
		}

		if (item.services?.serviceData) {
			userStore.serviceData = { ...item.services.serviceData };
		}

		const priceInEuro = item.single_price ? (Number(item.single_price) / 100) : 0;
		userStore.packages = [{
			package_type: item.package_type || "Pacco",
			quantity: item.quantity || 1,
			weight: item.weight,
			first_size: item.first_size,
			second_size: item.second_size,
			third_size: item.third_size,
			weight_price: item.weight_price,
			volume_price: item.volume_price,
			single_price: priceInEuro,
		}];

		userStore.shipmentDetails = {
			origin_city: item.origin_address?.city || "",
			origin_postal_code: item.origin_address?.postal_code || "",
			destination_city: item.destination_address?.city || "",
			destination_postal_code: item.destination_address?.postal_code || "",
			date: item.services?.date || "",
		};

		showAddressFields.value = true;

	} catch (e) {
	} finally {
		loadingEditData.value = false;
	}
};

onMounted(() => {
	const hasSessionSnapshot = Boolean(session.value?.data?.shipment_details)
		|| (Array.isArray(session.value?.data?.packages) && session.value.data.packages.length > 0);
	const hasLocalSnapshot = Boolean(userStore.pendingShipment)
		|| (Array.isArray(userStore.packages) && userStore.packages.length > 0);

	// Evitiamo un secondo refresh immediato se il middleware o il passaggio dal
	// preventivo hanno gia' riallineato la sessione. Quel doppio fetch era una
	// fonte concreta di repaint e micro-flash nella transizione step 1 -> step 2.
	if (status.value === 'idle' && !quoteTransitionLock.value && !hasSessionSnapshot && !hasLocalSnapshot) {
		refresh().catch(() => {
		});
	}

	if (editCartId && isAuthenticated.value) {
		loadCartItemForEdit();
	} else if (editCartId && !isAuthenticated.value) {
		loadingEditData.value = false;
	}
});

// --- SPEDIZIONI CONFIGURATE (DATI DEFAULT) ---
// Permette di caricare indirizzi da spedizioni precedentemente salvate
const {
	applyConfig,
	loadingConfigs,
	loadSavedConfigs,
	savedConfigs,
	showDefaultDropdown,
	showDefaultDropdownTarget,
	toggleAddressSelectorWithDefaultClose,
} = useShipmentStepSavedConfigs({
	clearAddressSelectorsAndPrompts,
	defaultDropdownRef,
	destDefaultDropdownRef,
	destFromSaved,
	destSaveSuccess,
	destSavedSnapshot,
	destSelectorRef,
	destinationAddress,
	deliveryMode,
	isAuthenticated,
	originAddress,
	originFromSaved,
	originSaveSuccess,
	originSavedSnapshot,
	originSelectorRef,
	sanctumClient,
	showDestConfigGuestPrompt,
	showOriginConfigGuestPrompt,
	toggleAddressSelector,
});
const uiFeedback = useUiFeedback();

const {
	continueToCart: persistAndContinueToCart,
	isSubmitting,
} = useShipmentStepSubmit({
	destinationAddress,
	editablePackages,
	editCartId,
	focusFirstFormError,
	focusPickupDateSection,
	formRef,
	normalizeLocationText,
	originAddress,
	persistSecondStep: (payload) => persistShipmentFlowState({ includeAddresses: true, payload }),
	routeConsistencyState,
	smsEmailNotification,
	services,
	submitError,
	uiFeedback,
	userStore,
	validateForm,
});

const continueToCart = async () => {
	if (!validateInlineServiceDetails()) {
		return;
	}

	await persistAndContinueToCart();
};

</script>

<template>
	<section>
		<div class="my-container shipment-step-shell mt-[48px] tablet:mt-[72px] mb-[96px] tablet:mb-[120px]">
			<div v-if="showInitialStepLoading" class="min-h-[560px] bg-[#E4E4E4] rounded-[16px] animate-pulse"></div>
			<form v-else ref="formRef" @submit.prevent="continueToCart">
				<div ref="stepsRef" class="mb-[16px] tablet:mb-[18px]">
					<Steps :current-step="currentShipmentStep - 1" />
				</div>

				<!-- STEP FORM: Servizi + Indirizzi -->
				<div>

				<!-- Summary Box Collapsabile STICKY -->
				<ShipmentStepSummaryCard
					v-if="currentStep === 2"
					:expanded="summaryExpanded"
					:compact-mobile="true"
					:detail-panel="summaryDetailPanel"
					:show-mini-steps="showSummaryMiniSteps"
					:summary-mini-steps="summaryMiniSteps"
					:summary-package-label="summaryPackageLabel"
					:summary-package-type-info="summaryPackageTypeInfo"
					:summary-dimensions-label="summaryDimensionsLabel"
					:summary-route-label="summaryRouteLabel"
					:summary-total-price="summaryTotalPrice"
					:route-warning-message="routeWarningMessage"
					:summary-origin-city="summaryOriginCity"
					:summary-destination-city="summaryDestinationCity"
					:can-expand-summary-dimensions="canExpandSummaryDimensions"
					:can-expand-summary-services="canExpandSummaryServices"
					:summary-services-label="summaryServicesLabel"
					:summary-dimensions-items="summaryDimensionsItems"
					:summary-services-items="summaryServicesItems"
					@go-mini-step="goToSummaryMiniStep"
					@toggle-detail-panel="toggleSummaryDetailPanel"
					@update:expanded="summaryExpanded = $event" />

					<div
						class="services-stage-block sf-stack-section"
						:class="{ 'is-collapsed-on-mobile': showAddressFields }">
						<div ref="pickupDateSectionRef" class="scroll-mt-[120px] w-full">
						<div
							v-if="dateError"
							data-pickup-date-alert
							class="mb-[14px] rounded-[14px] border border-[#F0D28E] bg-[#FFF7E2] px-[16px] py-[14px] text-[#8A5E2E] shadow-[0_8px_18px_rgba(184,134,51,0.08)]"
							role="alert"
							aria-live="polite">
							<div class="flex items-start gap-[10px]">
								<svg xmlns="http://www.w3.org/2000/svg" class="mt-[1px] h-[18px] w-[18px] shrink-0 text-[#C28122]" viewBox="0 0 24 24">
									<path fill="currentColor" d="M11 15h2v2h-2zm0-8h2v6h-2z"/><path fill="currentColor" d="M1 21h22L12 2z"/>
								</svg>
								<div class="min-w-0">
									<p class="text-[0.9375rem] font-bold leading-[1.2]">Imposta il giorno di ritiro</p>
									<p class="mt-[4px] text-[0.875rem] leading-[1.45]">{{ dateError }}</p>
								</div>
							</div>
						</div>
						<div class="pickup-date-card flow-section-shell sf-section-block">
						<div class="pickup-date-card__header sf-section-block__header">
							<h2 class="pickup-date-card__heading sf-section-title text-[#252B42] font-bold font-montserrat tracking-[0.1px]">
								<span>Imposta giorno di ritiro</span>
							</h2>
							<p class="pickup-date-card__note sf-section-description">Scegli il ritiro.</p>
						</div>

						<ClientOnly>
						<div class="pickup-date-slider-shell sf-section-block__body py-[12px]">
							<div class="pickup-date-slider-track relative px-[8px] tablet:px-[35px]">
								<Swiper
									class="my-swiper h-[96px] tablet:h-[108px]"
									:modules="[Navigation]"
									:slides-per-view="3.8"
									:breakpoints="{
										320: { slidesPerView: 3.4, spaceBetween: 8 },
										375: { slidesPerView: 4.1, spaceBetween: 10 },
										520: { slidesPerView: 4.9, spaceBetween: 12 },
										720: { slidesPerView: 5.8, spaceBetween: 14 },
										1024: { slidesPerView: 7, spaceBetween: 14 }
									}"
									space-between="8"
									:navigation="{
										nextEl: '.custom-next',
										prevEl: '.custom-prev',
									}">
									<SwiperSlide v-for="(day, index) in daysInMonth" :key="index">
										<label
											:key="day.date.toISOString()"
											class="pickup-date-option sf-choice-tile"
											:class="{
												'sf-choice-tile--selected': services.date == day.formattedDate,
												'is-selected': services.date == day.formattedDate,
												'is-available': services.date != day.formattedDate && day.weekday !== 'Sab' && day.weekday !== 'Dom',
												'is-disabled': day.weekday === 'Sab' || day.weekday === 'Dom'
											}">
											<span
												class="pickup-date-option__weekday">
												{{ day.weekday }}
											</span>
											<div class="pickup-date-option__body">
												<span class="pickup-date-option__day">{{ day.dayNumber }}</span>
												<span class="pickup-date-option__month">{{ day.monthAbbr }}</span>
											</div>

											<input
												type="checkbox"
												v-if="day.weekday !== 'Sab' && day.weekday !== 'Dom'"
												@input="chooseDate(day)"
												class="opacity-0 pointer-events-none absolute bottom-0"
												:id="`date-${day.dayNumber}-${day.monthAbbr}`"
												:checked="services.date == day.formattedDate"
												 />
										</label>
									</SwiperSlide>
								</Swiper>

								<!-- Frecce navigazione con touch target 48x48px -->
								<button class="pickup-date-nav custom-prev absolute top-1/2 left-[8px] -translate-y-1/2 cursor-pointer bg-white rounded-[50px] px-[14px] py-[10px] flex items-center justify-center shadow-sm hover:shadow-md transition-shadow duration-300 z-10 border border-[#D0D0D0] hover:border-[#6da8b4]">
									<NuxtImg src="/img/quote/second-step/arrow-left.png" alt="Precedente" width="11" height="19" loading="lazy" decoding="async" />
								</button>
								<button class="pickup-date-nav custom-next absolute top-1/2 right-[8px] -translate-y-1/2 cursor-pointer bg-white rounded-[50px] px-[14px] py-[10px] flex items-center justify-center shadow-sm hover:shadow-md transition-shadow duration-300 z-10 border border-[#D0D0D0] hover:border-[#6da8b4]">
									<NuxtImg src="/img/quote/second-step/arrow-right.png" alt="Successivo" width="11" height="19" loading="lazy" decoding="async" />
								</button>
							</div>
						</div>
						</ClientOnly>
					</div>
					</div>

					<section class="services-stage-shell sf-section-block">
					<div class="flow-section-header flow-section-header--services sf-section-block__header">
						<div class="flow-section-header__copy">
							<h2 class="flow-section-header__title sf-section-title">Servizi</h2>
						</div>
					</div>

					<div class="services-stage-shell__content font-montserrat">
						<div class="w-full mx-auto">
						<!-- Layout servizi: Senza etichetta hero + 3 sotto -->
						<div class="w-full">
							<!-- Servizio "Senza etichetta" -->
							<div v-if="featuredService">
								<article
									class="senza-etichetta-card service-card-tile service-card-tile--featured sf-card no-radius"
									:class="{
										'sf-card--selected': featuredService.isSelected,
										'is-selected': featuredService.isSelected,
										'is-idle': !featuredService.isSelected,
									}">
									<div class="service-card-tile__body-hit no-radius">
										<div class="service-card-tile__top">
											<div
												class="service-card-tile__icon-shell sf-icon-shell"
												:class="{ 'service-card-tile__icon-shell--selected': featuredService.isSelected }">
												<div
													class="service-card-tile__icon"
													:style="{
														'--service-icon-bg': 'url(/img/quote/second-step/no-label.png)',
														'--service-icon-width': '28px',
														'--service-icon-height': '24px',
														'--service-icon-filter': featuredService.isSelected ? SERVICE_ICON_FILTER_ACTIVE : SERVICE_ICON_FILTER_IDLE,
													}"></div>
										</div>
											<span
												class="service-card-tile__price"
												:class="{ 'service-card-tile__price--selected': featuredService.isSelected }">
												{{ featuredService.currentPriceLabel }}
											</span>
										</div>
										<div class="service-card-tile__title-row">
											<h3 class="service-card-tile__title">Senza Etichetta</h3>
											<span class="service-card-tile__badge">
												<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
													<path d="m12 3 1.8 4.2L18 9l-4.2 1.8L12 15l-1.8-4.2L6 9l4.2-1.8z" />
												</svg>
												Consigliato
											</span>
										</div>
										<p class="service-card-tile__description">Etichetta applicata al ritiro.</p>
									</div>
									<div class="service-card-tile__footer-row">
										<div class="service-card-tile__state-pill service-card-tile__state-pill--accent">
											<span class="service-card-tile__state-dot"></span>
											<span>Pronto subito</span>
										</div>
										<div class="service-card-tile__controls">
											<button
												type="button"
												class="service-card-tile__footer no-radius"
												:aria-label="featuredService.isSelected ? 'Disattiva Senza Etichetta' : 'Attiva Senza Etichetta'"
												@click.stop.prevent="toggleFeaturedService">
												<span class="service-card-tile__switch sf-toggle" :class="{ 'is-active': featuredService.isSelected }">
													<span class="service-card-tile__switch-thumb sf-toggle__thumb"></span>
												</span>
												<span
													class="service-card-tile__switch-label"
													:class="{ 'service-card-tile__switch-label--selected': featuredService.isSelected }">
													{{ featuredService.isSelected ? 'Attivo' : 'Non attivo' }}
												</span>
											</button>
										</div>
									</div>
								</article>
							</div>

							<!-- Servizi regolari (3 in riga) -->
							<div class="grid grid-cols-1 tablet:grid-cols-2 desktop:grid-cols-3 gap-[16px]">
								<article
									v-for="(service, serviceIndex) in regularServices"
									:key="serviceIndex"
									class="service-card-tile sf-card no-radius"
									:class="{
										'sf-card--selected': service.isSelected,
										'sf-card--expanded': isServiceExpanded(service.name),
										'service-card-tile--selected': service.isSelected,
										'service-card-tile--idle': !service.isSelected,
										'service-card-tile--expanded': isServiceExpanded(service.name),
									}">
									<div class="service-card-tile__body-hit no-radius">
										<div class="service-card-tile__top">
											<div
												class="service-card-tile__icon-shell sf-icon-shell"
												:class="{ 'service-card-tile__icon-shell--selected': service.isSelected }">
												<div
													class="service-card-tile__icon"
													:style="{
														'--service-icon-bg': `url(/img/quote/second-step/${service.img})`,
														'--service-icon-width': `${service.width}px`,
														'--service-icon-height': `${service.height}px`,
														'--service-icon-filter': service.isSelected ? SERVICE_ICON_FILTER_ACTIVE : SERVICE_ICON_FILTER_IDLE,
													}"></div>
											</div>
											<span
												class="service-card-tile__price"
												:class="{ 'service-card-tile__price--selected': service.isSelected }">
												{{ service.priceLabel }}
											</span>
										</div>
										<div class="service-card-tile__title-row">
											<h3 class="service-card-tile__title">
												{{ service.name }}
											</h3>
											<span
												class="service-card-tile__badge"
												:class="{ 'service-card-tile__badge--selected': service.isSelected }">
												<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
													<path d="M7 17 17 7" />
													<path d="M9 7h8v8" />
												</svg>
												{{ service.statusLabel }}
											</span>
										</div>
										<p class="service-card-tile__description">
											{{ service.description }}
										</p>
									</div>
									<div class="service-card-tile__footer-row">
											<div
												class="service-card-tile__state-pill"
												:class="{ 'service-card-tile__state-pill--open': isServiceExpanded(service.name) }">
												<span class="service-card-tile__state-dot"></span>
												<span>{{ getServiceStateLabel(service) }}</span>
											</div>
											<div class="service-card-tile__controls">
												<button
													v-if="shouldShowConfigureButton(service)"
													type="button"
													class="service-card-tile__configure no-radius btn-secondary"
													:class="{ 'is-active': isServiceExpanded(service.name) || service.isSelected }"
													:aria-label="`${isServiceExpanded(service.name) ? 'Chiudi' : 'Apri'} dettagli ${service.name}`"
													:aria-expanded="isServiceExpanded(service.name) ? 'true' : 'false'"
													:aria-controls="`service-inline-panel-${serviceIndex}`"
													@click.stop.prevent="handleServicePrimaryAction(service)"
													@keydown.enter.stop.prevent="handleServicePrimaryAction(service)"
													@keydown.space.stop.prevent="handleServicePrimaryAction(service)">
													<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
														<path d="M6 9h12" />
														<path d="m9 12 3 3 3-3" />
													</svg>
													<span>{{ getServiceConfigureLabel(service) }}</span>
												</button>
												<button
													v-if="shouldShowServiceToggle(service)"
													type="button"
													class="service-card-tile__footer no-radius"
													:aria-label="service.isSelected ? `Disattiva ${service.name}` : `Attiva ${service.name}`"
													@click.stop.prevent="toggleRegularService(service)"
													@keydown.enter.stop.prevent="toggleRegularService(service)"
													@keydown.space.stop.prevent="toggleRegularService(service)">
													<span class="service-card-tile__switch sf-toggle" :class="{ 'is-active': service.isSelected }">
														<span class="service-card-tile__switch-thumb sf-toggle__thumb"></span>
													</span>
													<span
														class="service-card-tile__switch-label"
														:class="{ 'service-card-tile__switch-label--selected': service.isSelected }">
														{{ service.isSelected ? 'Attivo' : 'Non attivo' }}
													</span>
												</button>
											</div>
									</div>
									<transition name="service-inline-expand">
										<div
											v-if="canConfigureService(service) && isServiceExpanded(service.name)"
											:id="`service-inline-panel-${serviceIndex}`"
											class="service-card-tile__accordion">
											<div v-if="service.name === 'Contrassegno'" class="service-inline-panel">
												<div class="service-inline-panel__grid service-inline-panel__grid--double">
													<div class="service-inline-field">
														<label class="service-inline-field__label" :for="`contrassegno-importo-${serviceIndex}`">Importo</label>
														<div class="service-inline-field__input-shell">
															<input
																:id="`contrassegno-importo-${serviceIndex}`"
																v-model="serviceData.contrassegno.importo"
																type="text"
																inputmode="decimal"
																autocomplete="off"
																class="service-inline-field__input"
																placeholder="0,00"
																@input="serviceData.contrassegno.importo = normalizeCurrencyInput($event.target.value); serviceCardErrors.contrassegnoImporto = ''" />
															<span class="service-inline-field__suffix">€</span>
														</div>
														<p v-if="serviceCardErrors.contrassegnoImporto" class="service-inline-field__error">{{ serviceCardErrors.contrassegnoImporto }}</p>
													</div>
													<div v-if="requiresContrassegnoDettaglio" class="service-inline-field">
														<label class="service-inline-field__label" :for="`contrassegno-iban-${serviceIndex}`">IBAN</label>
														<input
															:id="`contrassegno-iban-${serviceIndex}`"
															v-model="serviceData.contrassegno.dettaglio_rimborso"
															type="text"
															class="service-inline-field__input"
															placeholder="IT60X054281110..."
															@input="serviceCardErrors.contrassegnoDettaglio = ''" />
														<p v-if="serviceCardErrors.contrassegnoDettaglio" class="service-inline-field__error">{{ serviceCardErrors.contrassegnoDettaglio }}</p>
													</div>
												</div>
												<div class="service-inline-choice-block">
													<span class="service-inline-field__label">Incasso</span>
													<div class="service-inline-choice-wrap" role="group" aria-label="Modalità incasso contrassegno">
														<button
															v-for="option in contrassegnoIncassoOptions"
															:key="option.value"
															type="button"
															class="service-inline-choice"
															:class="{ 'is-active': serviceData.contrassegno.modalita_incasso === option.value }"
															@click="serviceData.contrassegno.modalita_incasso = option.value; serviceCardErrors.contrassegnoIncasso = ''">
															{{ option.label }}
														</button>
													</div>
													<p v-if="serviceCardErrors.contrassegnoIncasso" class="service-inline-field__error">{{ serviceCardErrors.contrassegnoIncasso }}</p>
												</div>
												<div class="service-inline-choice-block">
													<span class="service-inline-field__label">Rimborso</span>
													<div class="service-inline-choice-wrap" role="group" aria-label="Modalità rimborso contrassegno">
														<button
															v-for="option in contrassegnoRimborsoOptions"
															:key="option.value"
															type="button"
															class="service-inline-choice"
															:class="{ 'is-active': serviceData.contrassegno.modalita_rimborso === option.value }"
															@click="serviceData.contrassegno.modalita_rimborso = option.value; serviceCardErrors.contrassegnoRimborso = ''">
															{{ option.label }}
														</button>
													</div>
													<p v-if="serviceCardErrors.contrassegnoRimborso" class="service-inline-field__error">{{ serviceCardErrors.contrassegnoRimborso }}</p>
												</div>
											</div>

											<div v-else-if="service.name === 'Assicurazione'" class="service-inline-panel">
												<div class="service-inline-insurance-list">
													<div
														v-for="(pack, indexPopup) in insurancePackages"
														:key="`${service.name}-${indexPopup}`"
														class="service-inline-insurance-card">
														<div class="service-inline-insurance-card__head">
															<span class="service-inline-insurance-card__title">Collo {{ indexPopup + 1 }}</span>
															<span class="service-inline-insurance-card__meta">
																{{ pack.weight || '0' }} kg · {{ pack.first_size || '0' }}×{{ pack.second_size || '0' }}×{{ pack.third_size || '0' }} cm
															</span>
														</div>
														<div class="service-inline-field__input-shell">
															<input
																:id="`assicurazione-${indexPopup}`"
																v-model="serviceData.assicurazione[indexPopup]"
																type="text"
																inputmode="decimal"
																autocomplete="off"
																class="service-inline-field__input"
																placeholder="Valore assicurato"
																@input="serviceData.assicurazione[indexPopup] = normalizeCurrencyInput($event.target.value); serviceCardErrors.assicurazione[indexPopup] = ''" />
															<span class="service-inline-field__suffix">€</span>
														</div>
														<p v-if="serviceCardErrors.assicurazione[indexPopup]" class="service-inline-field__error">{{ serviceCardErrors.assicurazione[indexPopup] }}</p>
													</div>
												</div>
											</div>
											<div
												v-if="canConfigureService(service)"
												class="service-inline-panel__actions"
												:class="{ 'service-inline-panel__actions--split': service.isSelected }">
												<button
													v-if="service.isSelected"
													type="button"
													class="btn-secondary btn-compact service-inline-panel__dismiss"
													@click.stop.prevent="toggleRegularService(service)">
													Disattiva
												</button>
												<button
													v-if="!service.isSelected"
													type="button"
													class="btn-primary btn-compact service-inline-panel__submit"
													:disabled="!canActivateConfiguredService(service)"
													@click.stop.prevent="activateConfiguredService(service)">
													Attiva
												</button>
											</div>
										</div>
									</transition>
								</article>
							</div>

									<div class="service-support-grid">
								<div class="service-support-field">
									<div class="service-support-field__label-row">
										<label for="content_description" class="service-support-field__label">
											Contenuto del pacco<span class="text-red-500 ml-[2px]">*</span>
										</label>
										<div class="relative group">
											<button type="button" class="service-support-field__help" aria-label="Esempi di contenuto del pacco">
												?
											</button>
											<div class="service-support-field__tooltip">
												<p class="font-semibold mb-[6px]">Esempi di contenuto:</p>
												<ul class="list-disc list-inside space-y-[2px] text-[0.75rem]">
													<li>Elettronica</li>
													<li>Abbigliamento</li>
													<li>Documenti</li>
													<li>Articoli per la casa</li>
													<li>Prodotti confezionati</li>
												</ul>
												<div class="service-support-field__tooltip-arrow"></div>
											</div>
										</div>
									</div>
									<p v-if="contentError" class="field-gentle-error mb-[8px]">
										{{ contentFieldHint }}
									</p>
									<input
										type="text"
										id="content_description"
										v-model="userStore.contentDescription"
										placeholder="Es. Elettronica, Abbigliamento, Documenti..."
										maxlength="255"
										required
										@input="contentError = null"
										:class="[
											'service-support-field__input',
											contentError ? 'input-preventivo-step-2--warning border-2' : ''
										]" />
								</div>

									<div class="service-support-field">
										<label class="service-support-field__label" for="notification-toggle">Notifiche spedizione</label>
										<div
											class="service-support-field__notification-card"
											:class="{ 'is-active': smsEmailNotification }">
											<div class="service-support-field__notification-main">
												<div class="service-support-field__notification-icon sf-icon-shell">
													<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
														<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
														<path d="M13.73 21a2 2 0 0 1-3.46 0"/>
													</svg>
												</div>
												<div class="service-support-field__notification-copy-wrap">
													<div class="service-support-field__notification-headline">
														<p class="service-support-field__notification-copy">SMS ed Email dal corriere</p>
														<span class="service-support-field__notification-price">{{ notificationPriceLabel }}</span>
													</div>
												</div>
											</div>

											<div class="service-support-field__notification-side">
												<span class="service-support-field__switch-state" :class="{ 'is-active': smsEmailNotification }">
													{{ smsEmailNotification ? 'Attivo' : 'Non attivo' }}
												</span>
												<label class="service-support-field__switch" @click.stop>
													<input
														id="notification-toggle"
													type="checkbox"
													v-model="smsEmailNotification"
													class="opacity-0 w-0 h-0 peer"
													@click.stop
													aria-label="Attiva notifiche SMS/Email" />
													<span class="service-support-field__switch-track sf-toggle"></span>
													<span class="service-support-field__switch-thumb sf-toggle__thumb"></span>
												</label>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</section>

					<div v-if="showAddressFields" class="address-stage-shell sf-stack-block">
							<div class="address-stage-banner flow-section-header flow-section-header--addresses sf-section-block__header">
								<div class="address-stage-banner__copy flow-section-header__copy">
									<h3 class="address-stage-banner__title flow-section-header__title sf-section-title">Indirizzi</h3>
								</div>
							</div>

								<!-- PARTENZA -->
								<div>
									<div
										v-if="showGlobalFormSummary"
										class="ux-alert ux-alert--soft mt-[18px]">
									<svg xmlns="http://www.w3.org/2000/svg" class="ux-alert__icon" viewBox="0 0 24 24"><path fill="currentColor" d="M11 15h2v2h-2zm0-8h2v6h-2z"/><path fill="currentColor" d="M1 21h22L12 2zm12-3h-2v-2h2zm0-4h-2V8h2z"/></svg>
									<div class="min-w-0">
										<span class="ux-alert__title">Controlliamo insieme questi campi</span>
										<ul class="mt-[6px] space-y-[4px]">
										<li v-for="errorItem in formErrorSummary" :key="errorItem.key">
											<button
												type="button"
												class="text-left text-[0.8125rem] text-[#7A5A2C] underline decoration-[#D7B078] hover:decoration-[#B8823B] cursor-pointer"
												@click="focusFormError(errorItem)">
												{{ errorItem.label }}: {{ errorItem.message }}
											</button>
									</li>
								</ul>
									</div>
							</div>
							<div class="bg-[#E4E4E4] rounded-[16px] text-[#252B42] mt-[20px] px-[16px] tablet:px-[40px] pt-[24px] tablet:pt-[35px] pb-[24px] tablet:pb-[43px]">
								<div class="flex items-center justify-between mb-[20px] tablet:mb-[39px] flex-wrap gap-[10px]">
									<div class="flex items-center gap-[10px]">
									<h2 class="font-bold text-[1.125rem] tracking-[0.1px]">
										Partenza
									</h2>
									<!-- Icona salva indirizzo partenza -->
									<button
										v-if="canSaveOriginAddress"
										type="button"
										@click="saveAddressToBook('origin')"
										:disabled="savingOriginAddress"
										class="inline-flex items-center justify-center w-[30px] h-[30px] rounded-[6px] bg-[#095866] text-white hover:bg-[#074a56] transition cursor-pointer disabled:opacity-60"
										title="Salva indirizzo">
										<svg v-if="!savingOriginAddress" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
										<svg v-else class="animate-spin" width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
									</button>
									<span v-if="originSaveSuccess" class="inline-flex items-center gap-[4px] text-[0.75rem] text-green-600 font-semibold">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
										Salvato
									</span>
								</div>
								<div class="flex items-center gap-[10px] flex-wrap">
									<!-- Spedizioni configurate (visibile anche ai guest) -->
									<div ref="defaultDropdownRef" class="relative">
										<button
											type="button"
											@click="loadSavedConfigs('origin')"
											:aria-expanded="((isAuthenticated && showDefaultDropdown && showDefaultDropdownTarget === 'origin') || (!isAuthenticated && showOriginConfigGuestPrompt)) ? 'true' : 'false'"
											aria-controls="origin-config-dropdown"
											:disabled="isAuthenticated && loadingConfigs"
											class="address-utility-button address-utility-button--sand disabled:opacity-60">
											<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
											{{ isAuthenticated && loadingConfigs ? '...' : 'Spedizioni configurate' }}
										</button>
										<div v-if="showDefaultDropdown && showDefaultDropdownTarget === 'origin' && savedConfigs.length > 0" id="origin-config-dropdown" class="absolute z-50 top-full right-0 mt-[4px] bg-white border border-[#D0D0D0] rounded-[12px] shadow-xl max-h-[300px] overflow-y-auto w-[min(92vw,400px)]">
											<div class="p-[12px] border-b border-[#F0F0F0] text-[0.8125rem] font-bold text-[#252B42]">Seleziona una spedizione configurata completa</div>
											<div v-for="item in savedConfigs" :key="item.id" @click="applyConfig(item, 'both')" class="px-[14px] py-[12px] cursor-pointer hover:bg-[#f0fafb] border-b border-[#F0F0F0] last:border-0 transition-colors">
												<div class="flex items-center gap-[8px]">
													<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#996D47" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
													<span class="text-[0.875rem] font-semibold text-[#252B42]">{{ item.origin_address?.city || 'Partenza' }}</span>
													<span class="text-[#737373]">&rarr;</span>
													<span class="text-[0.875rem] font-semibold text-[#252B42]">{{ item.destination_address?.city || 'Destinazione' }}</span>
												</div>
												<p class="text-[0.75rem] text-[#737373] mt-[2px]">{{ item.origin_address?.name || '' }} - {{ item.destination_address?.name || '' }}</p>
											</div>
										</div>
										<div v-if="showDefaultDropdown && showDefaultDropdownTarget === 'origin' && savedConfigs.length === 0 && !loadingConfigs" id="origin-config-dropdown" class="absolute z-50 top-full right-0 mt-[4px] bg-white border border-[#D0D0D0] rounded-[12px] shadow-xl p-[20px] w-[min(92vw,300px)]">
											<p class="text-[0.875rem] text-[#737373]">Nessuna spedizione configurata salvata.</p>
											<NuxtLink to="/account/spedizioni-configurate" class="text-[0.8125rem] text-[#095866] hover:underline font-semibold mt-[8px] inline-block">Vai a spedizioni configurate</NuxtLink>
										</div>
										<div v-if="showOriginConfigGuestPrompt && !isAuthenticated" id="origin-config-dropdown" role="dialog" class="absolute z-50 top-full right-0 mt-[4px] bg-white border border-[#D0D0D0] rounded-[12px] shadow-xl p-[14px] w-[min(92vw,300px)]">
											<p class="text-[0.8125rem] text-[#4B5563] leading-[1.45]">Per usare le spedizioni configurate devi accedere.</p>
											<div class="mt-[10px] flex items-center gap-[8px]">
												<button type="button" class="inline-flex items-center justify-center h-[34px] px-[12px] rounded-[8px] bg-[#095866] text-white text-[0.75rem] font-semibold hover:bg-[#074a56] transition cursor-pointer" @click="openShipmentAuthModal('login')">Accedi</button>
												<button type="button" class="inline-flex items-center justify-center h-[34px] px-[12px] rounded-[8px] border border-[#C8D2D6] text-[#095866] text-[0.75rem] font-semibold hover:bg-[#F3F7F8] transition cursor-pointer" @click="openShipmentAuthModal('register')">Registrati</button>
											</div>
										</div>
									</div>

									<!-- Indirizzi salvati (visibile anche ai guest) -->
									<div ref="originSelectorRef" class="relative">
										<button
											type="button"
											@click="toggleAddressSelectorWithDefaultClose('origin')"
											:aria-expanded="(isAuthenticated ? showOriginAddressSelector : showOriginGuestPrompt) ? 'true' : 'false'"
											aria-controls="origin-addresses-dropdown"
											class="address-utility-button address-utility-button--teal">
											<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
											Indirizzi salvati
										</button>
										<div v-if="showOriginAddressSelector && isAuthenticated" id="origin-addresses-dropdown" class="absolute z-50 top-full right-0 mt-[4px] bg-white border border-[#D0D0D0] rounded-[12px] shadow-xl max-h-[250px] overflow-y-auto w-[min(92vw,320px)]">
											<div v-if="loadingSavedAddresses" class="p-[16px] text-center text-[0.8125rem] text-[#737373]">Caricamento...</div>
											<template v-else-if="savedAddresses.length > 0">
												<div v-for="addr in savedAddresses" :key="addr.id" @click="applySavedAddress(addr, 'origin')" class="px-[14px] py-[10px] cursor-pointer hover:bg-[#f0fafb] border-b border-[#F0F0F0] last:border-0 transition-colors">
													<p class="text-[0.875rem] font-semibold text-[#252B42]">{{ addr.name }}</p>
													<p class="text-[0.75rem] text-[#737373]">{{ addr.address }} {{ addr.address_number }}, {{ addr.postal_code }} {{ addr.city }}</p>
												</div>
											</template>
											<div v-else class="p-[16px]">
												<p class="text-[0.8125rem] text-[#737373]">Nessun indirizzo salvato.</p>
												<NuxtLink to="/account/indirizzi" class="text-[0.8125rem] text-[#095866] hover:underline font-semibold mt-[4px] inline-block">Aggiungi indirizzo</NuxtLink>
											</div>
										</div>
										<div v-if="showOriginGuestPrompt && !isAuthenticated" id="origin-addresses-dropdown" role="dialog" class="absolute z-50 top-full right-0 mt-[4px] bg-white border border-[#D0D0D0] rounded-[12px] shadow-xl p-[14px] w-[min(92vw,280px)]">
											<p class="text-[0.8125rem] text-[#4B5563] leading-[1.45]">Per usare la rubrica indirizzi devi accedere.</p>
											<div class="mt-[10px] flex items-center gap-[8px]">
												<button type="button" class="inline-flex items-center justify-center h-[34px] px-[12px] rounded-[8px] bg-[#095866] text-white text-[0.75rem] font-semibold hover:bg-[#074a56] transition cursor-pointer" @click="openShipmentAuthModal('login')">Accedi</button>
												<button type="button" class="inline-flex items-center justify-center h-[34px] px-[12px] rounded-[8px] border border-[#C8D2D6] text-[#095866] text-[0.75rem] font-semibold hover:bg-[#F3F7F8] transition cursor-pointer" @click="openShipmentAuthModal('register')">Registrati</button>
											</div>
										</div>
									</div>
								</div>
								</div>

								<div v-if="originSectionHint" class="ux-alert ux-alert--soft mb-[12px]">
									<svg xmlns="http://www.w3.org/2000/svg" class="ux-alert__icon" viewBox="0 0 24 24"><path fill="currentColor" d="M11 15h2v2h-2zm0-8h2v6h-2z"/><path fill="currentColor" d="M1 21h22L12 2z"/></svg>
									<span>{{ originSectionHint }}</span>
								</div>

								<ShipmentAddressFormFields
									type="origin"
									:address="originAddress"
									:field-class="fieldClass"
									:get-field-error="getFieldError"
									:field-error-text="fieldErrorText"
									:get-field-assist="getFieldAssist"
									:apply-field-assist="applyFieldAssist"
									:smart-blur="smartBlur"
									:on-name-input="onNameInput"
									:on-city-input="onCityInput"
									:on-city-focus="onCityFocus"
									:on-provincia-input="onProvinciaInput"
									:on-province-focus="onProvinceFocus"
									:on-cap-input="onCapInput"
									:on-cap-focus="onCapFocus"
									:on-telefono-input="onTelefonoInput"
									:select-city="selectCity"
									:select-provincia="selectProvincia"
									:select-cap="selectCap"
									:format-city-suggestion-label="formatCitySuggestionLabel"
									:format-cap-suggestion-label="formatCapSuggestionLabel"
									:sv="sv"
									:city-suggestions="originCitySuggestions"
									:province-suggestions="originProvinceSuggestions"
									:cap-suggestions="originCapSuggestions" />
							</div>

							<!-- TOGGLE MODALITA' CONSEGNA -->
							<!-- L'utente sceglie tra consegna classica a domicilio oppure ritiro
							     in un punto BRT convenzionato (tabaccaio, edicola, negozio) -->
							<div class="mt-[20px] mb-[4px]">
								<p class="text-[0.875rem] font-bold text-[#252B42] mb-[10px]">Modalità di consegna</p>
								<div class="flex flex-col tablet:flex-row gap-[10px]">
									<!-- Bottone "Consegna a domicilio" — modalità classica -->
									<button
										type="button"
										@click="deliveryMode = 'home'"
										class="inline-flex items-center gap-[8px] px-[18px] py-[12px] rounded-[50px] text-[0.875rem] font-semibold border-2 transition-[background-color,color,border-color] duration-200 cursor-pointer"
										:class="deliveryMode === 'home' ? 'bg-[#095866] text-white border-[#095866]' : 'bg-white text-[#252B42] border-[#D0D0D0] hover:border-[#095866]'">
										<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
										Consegna a domicilio
									</button>
									<!-- Bottone "Ritira in un Punto BRT" — consegna presso punto PUDO -->
									<button
										type="button"
										@click="deliveryMode = 'pudo'"
										class="inline-flex items-center gap-[8px] px-[18px] py-[12px] rounded-[50px] text-[0.875rem] font-semibold border-2 transition-[background-color,color,border-color] duration-200 cursor-pointer"
										:class="deliveryMode === 'pudo' ? 'bg-[#095866] text-white border-[#095866]' : 'bg-white text-[#252B42] border-[#D0D0D0] hover:border-[#095866]'">
										<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
										Ritira in un Punto BRT
									</button>
								</div>
							</div>

							<!-- SELETTORE PUDO — visibile solo se l'utente ha scelto "Ritira in un Punto BRT" -->
							<!-- Permette di cercare punti PUDO per città/CAP e selezionarne uno -->
							<div v-if="deliveryMode === 'pudo'" class="bg-[#E4E4E4] rounded-[16px] text-[#252B42] mt-[16px] px-[16px] tablet:px-[40px] pt-[24px] tablet:pt-[35px] pb-[24px] tablet:pb-[43px]">
								<h2 class="font-bold text-[1.125rem] tracking-[0.1px] flex items-center gap-[8px] mb-[4px]">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#095866" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
									Cerca un Punto BRT
								</h2>
								<p class="text-[0.8125rem] text-[#737373] mb-[8px]">Cerca un tabaccaio, edicola o negozio convenzionato BRT vicino alla destinazione.</p>
								<!-- PudoSelector: componente riutilizzabile che gestisce ricerca e selezione -->
								<!-- Riceve città e CAP di destinazione come valori iniziali per la ricerca -->
								<PudoSelector
									:initial-city="destinationAddress.city"
									:initial-zip="destinationAddress.postal_code"
									@select="onPudoSelected"
									@deselect="onPudoDeselected" />
								<!-- Riepilogo PUDO selezionato -->
								<div v-if="userStore.selectedPudo" class="mt-[16px] p-[12px] bg-white rounded-[10px] border-2 border-[#095866] text-[0.875rem]">
									<div class="flex items-center gap-[6px] text-[#095866] font-bold mb-[4px]">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
										Punto selezionato
									</div>
									<p class="font-semibold text-[#252B42]">{{ userStore.selectedPudo.name }}</p>
									<p class="text-[#737373]">{{ userStore.selectedPudo.address }}, {{ userStore.selectedPudo.zip_code }} {{ userStore.selectedPudo.city }}</p>
								</div>
							</div>

							<!-- DESTINAZIONE -->
							<!-- Se modalità PUDO: i campi destinazione sono auto-compilati e read-only -->
							<!-- Se modalità domicilio: i campi sono editabili normalmente -->
							<div class="bg-[#E4E4E4] rounded-[16px] text-[#252B42] mt-[20px] px-[16px] tablet:px-[40px] pt-[24px] tablet:pt-[35px] pb-[24px] tablet:pb-[43px]">
								<div class="flex items-center justify-between mb-[20px] tablet:mb-[39px]">
									<div class="flex items-center gap-[10px]">
									<h2 class="font-bold text-[1.125rem] tracking-[0.1px]">
										{{ deliveryMode === 'pudo' ? 'Destinazione (Punto BRT)' : 'Destinazione' }}
									</h2>
									<!-- Icona salva indirizzo destinazione (nascosta in modalità PUDO perché l'indirizzo è del punto BRT) -->
									<button
										v-if="canSaveDestAddress && deliveryMode !== 'pudo'"
										type="button"
										@click="saveAddressToBook('dest')"
										:disabled="savingDestAddress"
										class="inline-flex items-center justify-center w-[30px] h-[30px] rounded-[6px] bg-[#095866] text-white hover:bg-[#074a56] transition cursor-pointer disabled:opacity-60"
										title="Salva indirizzo">
										<svg v-if="!savingDestAddress" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
										<svg v-else class="animate-spin" width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
									</button>
									<span v-if="destSaveSuccess" class="inline-flex items-center gap-[4px] text-[0.75rem] text-green-600 font-semibold">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
										Salvato
									</span>
								</div>
									<div class="flex items-center gap-[10px] flex-wrap justify-end">
									<div ref="destDefaultDropdownRef" class="relative">
										<button
											type="button"
											@click="loadSavedConfigs('dest')"
											:aria-expanded="((isAuthenticated && showDefaultDropdown && showDefaultDropdownTarget === 'dest') || (!isAuthenticated && showDestConfigGuestPrompt)) ? 'true' : 'false'"
											aria-controls="dest-config-dropdown"
											:disabled="isAuthenticated && loadingConfigs"
											class="address-utility-button address-utility-button--sand disabled:opacity-60">
											<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
											{{ isAuthenticated && loadingConfigs ? '...' : 'Spedizioni configurate' }}
										</button>
										<div v-if="showDefaultDropdown && showDefaultDropdownTarget === 'dest' && savedConfigs.length > 0" id="dest-config-dropdown" class="absolute z-50 top-full right-0 mt-[4px] bg-white border border-[#D0D0D0] rounded-[12px] shadow-xl max-h-[300px] overflow-y-auto w-[min(92vw,400px)]">
											<div class="p-[12px] border-b border-[#F0F0F0] text-[0.8125rem] font-bold text-[#252B42]">Seleziona una spedizione configurata completa</div>
											<div v-for="item in savedConfigs" :key="`dest-config-${item.id}`" @click="applyConfig(item, 'both')" class="px-[14px] py-[12px] cursor-pointer hover:bg-[#f0fafb] border-b border-[#F0F0F0] last:border-0 transition-colors">
												<div class="flex items-center gap-[8px]">
													<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#996D47" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
													<span class="text-[0.875rem] font-semibold text-[#252B42]">{{ item.destination_address?.city || 'Destinazione' }}</span>
												</div>
												<p class="text-[0.75rem] text-[#737373] mt-[2px]">{{ item.destination_address?.name || '' }}</p>
											</div>
										</div>
										<div v-if="showDefaultDropdown && showDefaultDropdownTarget === 'dest' && savedConfigs.length === 0 && !loadingConfigs" id="dest-config-dropdown" class="absolute z-50 top-full right-0 mt-[4px] bg-white border border-[#D0D0D0] rounded-[12px] shadow-xl p-[20px] w-[min(92vw,300px)]">
											<p class="text-[0.875rem] text-[#737373]">Nessuna spedizione configurata salvata.</p>
											<NuxtLink to="/account/spedizioni-configurate" class="text-[0.8125rem] text-[#095866] hover:underline font-semibold mt-[8px] inline-block">Vai a spedizioni configurate</NuxtLink>
										</div>
										<div v-if="showDestConfigGuestPrompt && !isAuthenticated" id="dest-config-dropdown" role="dialog" class="absolute z-50 top-full right-0 mt-[4px] bg-white border border-[#D0D0D0] rounded-[12px] shadow-xl p-[14px] w-[min(92vw,300px)]">
											<p class="text-[0.8125rem] text-[#4B5563] leading-[1.45]">Per usare le spedizioni configurate devi accedere.</p>
											<div class="mt-[10px] flex items-center gap-[8px]">
												<button type="button" class="inline-flex items-center justify-center h-[34px] px-[12px] rounded-[8px] bg-[#095866] text-white text-[0.75rem] font-semibold hover:bg-[#074a56] transition cursor-pointer" @click="openShipmentAuthModal('login')">Accedi</button>
												<button type="button" class="inline-flex items-center justify-center h-[34px] px-[12px] rounded-[8px] border border-[#C8D2D6] text-[#095866] text-[0.75rem] font-semibold hover:bg-[#F3F7F8] transition cursor-pointer" @click="openShipmentAuthModal('register')">Registrati</button>
											</div>
										</div>
									</div>
									<div ref="destSelectorRef" class="relative">
										<button
											type="button"
											@click="toggleAddressSelectorWithDefaultClose('dest')"
											:aria-expanded="(isAuthenticated ? showDestAddressSelector : showDestGuestPrompt) ? 'true' : 'false'"
											aria-controls="dest-addresses-dropdown"
											class="address-utility-button address-utility-button--teal">
											<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
											Indirizzi salvati
										</button>
										<div v-if="showDestAddressSelector && isAuthenticated" id="dest-addresses-dropdown" class="absolute z-50 top-full right-0 mt-[4px] bg-white border border-[#D0D0D0] rounded-[12px] shadow-xl max-h-[250px] overflow-y-auto w-[min(92vw,320px)]">
											<div v-if="loadingSavedAddresses" class="p-[16px] text-center text-[0.8125rem] text-[#737373]">Caricamento...</div>
											<template v-else-if="savedAddresses.length > 0">
												<div v-for="addr in savedAddresses" :key="addr.id" @click="applySavedAddress(addr, 'dest')" class="px-[14px] py-[10px] cursor-pointer hover:bg-[#f0fafb] border-b border-[#F0F0F0] last:border-0 transition-colors">
													<p class="text-[0.875rem] font-semibold text-[#252B42]">{{ addr.name }}</p>
													<p class="text-[0.75rem] text-[#737373]">{{ addr.address }} {{ addr.address_number }}, {{ addr.postal_code }} {{ addr.city }}</p>
												</div>
											</template>
											<div v-else class="p-[16px]">
												<p class="text-[0.8125rem] text-[#737373]">Nessun indirizzo salvato.</p>
												<NuxtLink to="/account/indirizzi" class="text-[0.8125rem] text-[#095866] hover:underline font-semibold mt-[4px] inline-block">Aggiungi indirizzo</NuxtLink>
											</div>
										</div>
										<div v-if="showDestGuestPrompt && !isAuthenticated" id="dest-addresses-dropdown" role="dialog" class="absolute z-50 top-full right-0 mt-[4px] bg-white border border-[#D0D0D0] rounded-[12px] shadow-xl p-[14px] w-[min(92vw,280px)]">
											<p class="text-[0.8125rem] text-[#4B5563] leading-[1.45]">Per usare la rubrica indirizzi devi accedere.</p>
											<div class="mt-[10px] flex items-center gap-[8px]">
												<button type="button" class="inline-flex items-center justify-center h-[34px] px-[12px] rounded-[8px] bg-[#095866] text-white text-[0.75rem] font-semibold hover:bg-[#074a56] transition cursor-pointer" @click="openShipmentAuthModal('login')">Accedi</button>
												<button type="button" class="inline-flex items-center justify-center h-[34px] px-[12px] rounded-[8px] border border-[#C8D2D6] text-[#095866] text-[0.75rem] font-semibold hover:bg-[#F3F7F8] transition cursor-pointer" @click="openShipmentAuthModal('register')">Registrati</button>
											</div>
										</div>
									</div>
								</div>
								</div>

								<div v-if="destinationSectionHint" class="ux-alert ux-alert--soft mb-[12px]">
									<svg xmlns="http://www.w3.org/2000/svg" class="ux-alert__icon" viewBox="0 0 24 24"><path fill="currentColor" d="M11 15h2v2h-2zm0-8h2v6h-2z"/><path fill="currentColor" d="M1 21h22L12 2z"/></svg>
									<span>{{ destinationSectionHint }}</span>
								</div>

								<!-- In modalità PUDO: banner informativo + campi auto-compilati e non editabili -->
								<div v-if="deliveryMode === 'pudo' && userStore.selectedPudo" class="ux-alert ux-alert--info mb-[16px]">
									<svg width="16" height="16" viewBox="0 0 24 24" class="ux-alert__icon" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
									<span>Indirizzo compilato automaticamente dal Punto BRT selezionato.</span>
								</div>
								<div v-if="deliveryMode === 'pudo' && !userStore.selectedPudo" class="ux-alert ux-alert--soft mb-[16px]">
									<svg width="16" height="16" viewBox="0 0 24 24" class="ux-alert__icon" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
									<span>Seleziona un Punto BRT qui sopra per procedere.</span>
								</div>
								<div v-if="routeWarningMessage" class="ux-alert ux-alert--soft mb-[16px]">
									<svg width="16" height="16" viewBox="0 0 24 24" class="ux-alert__icon" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v5"/><path d="M12 16h.01"/></svg>
									<span>{{ routeWarningMessage }}</span>
								</div>

								<!-- Campi destinazione: riutilizza AddressFormFields con supporto PUDO readonly -->
								<p v-if="deliveryMode === 'pudo'" class="text-[0.8125rem] text-[#4B5563] font-semibold mb-[10px]">
									Indirizzo di consegna bloccato dal Punto BRT selezionato
								</p>
								<ShipmentAddressFormFields
									type="dest"
									:address="destinationAddress"
									:field-class="fieldClass"
									:get-field-error="getFieldError"
									:field-error-text="fieldErrorText"
									:get-field-assist="getFieldAssist"
									:apply-field-assist="applyFieldAssist"
									:smart-blur="smartBlur"
									:on-name-input="onNameInput"
									:on-city-input="onCityInput"
									:on-city-focus="onCityFocus"
									:on-provincia-input="onProvinciaInput"
									:on-province-focus="onProvinceFocus"
									:on-cap-input="onCapInput"
									:on-cap-focus="onCapFocus"
									:on-telefono-input="onTelefonoInput"
									:select-city="selectCity"
									:select-provincia="selectProvincia"
									:select-cap="selectCap"
									:format-city-suggestion-label="formatCitySuggestionLabel"
									:format-cap-suggestion-label="formatCapSuggestionLabel"
									:sv="sv"
									:city-suggestions="destCitySuggestions"
									:province-suggestions="destProvinceSuggestions"
									:cap-suggestions="destCapSuggestions"
									:readonly="deliveryMode === 'pudo'"
									:pudo-note="deliveryMode === 'pudo' ? 'Inserisci il nome della persona che ritira il pacco, non il nome del Punto BRT.' : ''" />
							</div>
							</div>
					</div>

						<div class="mt-[28px] hidden tablet:flex flex-col tablet:flex-row flex-wrap gap-[12px] items-stretch tablet:items-center justify-between">
							<template v-if="showAddressFields">
								<button
									type="button"
									@click="goBackToServices"
									class="step-secondary-action btn-secondary sf-nav-button">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
									Indietro
								</button>
									<button
										type="submit"
										:disabled="isSubmitting"
										class="btn-cta sf-nav-button">
										{{ isSubmitting ? 'Salvataggio in corso...' : (editCartId ? 'Continua al riepilogo modifica' : 'Continua al riepilogo') }}
										<svg v-if="!isSubmitting" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
									</button>
							</template>
							<template v-else>
								<NuxtLink :to="editCartId ? '/carrello' : { path: '/', hash: '#preventivo' }" class="step-secondary-action btn-secondary sf-nav-button">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
									{{ editCartId ? 'Torna al carrello' : 'Indietro' }}
								</NuxtLink>
								<button
									type="button"
									@click="openAddressFields"
									:disabled="!canOpenAddressFields"
									class="btn-cta sf-nav-button"
									:class="canOpenAddressFields ? 'cursor-pointer' : 'opacity-55 cursor-not-allowed'">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
									Continua agli indirizzi
								</button>
							</template>
						</div>
						<div v-if="submitError" class="ux-alert ux-alert--soft mt-[16px]">
							<svg xmlns="http://www.w3.org/2000/svg" class="ux-alert__icon" viewBox="0 0 24 24"><path fill="currentColor" d="M13 13h-2V7h2m0 10h-2v-2h2M12 2a10 10 0 0 1 10 10a10 10 0 0 1-10 10A10 10 0 0 1 2 12A10 10 0 0 1 12 2"/></svg>
							<span>{{ softenErrorMessage(submitError) }}</span>
						</div>
				</div>

					<div class="shipment-mobile-actionbar tablet:hidden">
						<div class="shipment-mobile-actionbar__shell">
						<div class="shipment-mobile-actionbar__meta">
							<span class="shipment-mobile-actionbar__label">Totale stimato</span>
							<span class="shipment-mobile-actionbar__value">{{ summaryTotalPrice }}€</span>
						</div>
						<div class="shipment-mobile-actionbar__actions">
							<template v-if="showAddressFields">
								<button
									type="button"
									@click="goBackToServices"
									class="shipment-mobile-actionbar__secondary btn-secondary sf-nav-button sf-nav-button--compact">
									Indietro
								</button>
								<button
									type="submit"
									:disabled="isSubmitting"
									class="shipment-mobile-actionbar__primary btn-cta sf-nav-button sf-nav-button--compact">
									{{ isSubmitting ? 'Salvataggio...' : 'Vai al riepilogo' }}
								</button>
							</template>
							<template v-else>
								<NuxtLink
									:to="editCartId ? '/carrello' : { path: '/', hash: '#preventivo' }"
									class="shipment-mobile-actionbar__secondary btn-secondary sf-nav-button sf-nav-button--compact">
									{{ editCartId ? 'Carrello' : 'Indietro' }}
								</NuxtLink>
								<button
									type="button"
									@click="openAddressFields"
									:disabled="!canOpenAddressFields"
									class="shipment-mobile-actionbar__primary btn-cta sf-nav-button sf-nav-button--compact">
									Vai agli indirizzi
								</button>
							</template>
							</div>
						</div>
					</div>
				</div>
				</form>
			</div>

		<!-- Popup rimosso - la logica è ora nella pagina /riepilogo -->
	</section>
</template>

<style scoped>
/* ==========================================================
   Base / Form styles
   ========================================================== */
.swiper-slide {
	background: transparent;
	border-radius: 12px;
	overflow: hidden;
}

.input-preventivo-step-2 {
	background: #ffffff;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
	border: 1px solid #d9dde3;
	border-radius: 12px;
	padding: 12px 16px;
	color: #252B42;
	transition: border-color 0.2s;
}

.input-preventivo-step-2:focus {
	border-color: #095866;
	outline: none;
}

.input-preventivo-step-2--warning {
	border-color: #f2b66e;
	background: #fffaf4;
}

.field-gentle-error {
	margin-top: 6px;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	font-size: 0.8125rem;
	font-weight: 500;
	color: #8a5e2e;
	line-height: 1.35;
}

.field-gentle-error::before {
	content: "";
	width: 14px;
	height: 14px;
	flex-shrink: 0;
	border-radius: 999px;
	background: radial-gradient(circle at center, #d8862f 36%, #fbe2c3 38%);
}

.field-assist-chip {
	margin-top: 6px;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 6px 10px;
	border-radius: 999px;
	border: 1px solid #e8c79a;
	background: #fff4e6;
	color: #7a5425;
	font-size: 0.75rem;
	font-weight: 600;
	line-height: 1;
	cursor: pointer;
	transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.field-assist-chip:hover {
	background: #ffebd1;
	border-color: #d9a96c;
	color: #68441c;
}

.title-popup::after {
	background-image: var(--before-bg);
	width: 26px;
	height: 28px;
}

/* Smooth transitions for interactive elements */
button, a, input, select, label {
	transition: color 0.3s cubic-bezier(0.4, 0, 0.2, 1),
		background-color 0.3s cubic-bezier(0.4, 0, 0.2, 1),
		border-color 0.3s cubic-bezier(0.4, 0, 0.2, 1),
		box-shadow 0.3s cubic-bezier(0.4, 0, 0.2, 1),
		opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Accessibility focus states */
button:focus-visible,
a:focus-visible,
input:focus-visible,
select:focus-visible {
	outline: 2px solid #095866;
	outline-offset: 2px;
}

/* ==========================================================
   Mobile actionbar
   ========================================================== */
.shipment-mobile-actionbar {
	position: fixed;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: 55;
	padding: 8px 10px calc(env(safe-area-inset-bottom, 0px) + 10px);
	background: linear-gradient(180deg, rgba(245, 247, 249, 0) 0%, rgba(245, 247, 249, 0.92) 18%, #f5f7f9 100%);
	backdrop-filter: blur(10px);
}

.shipment-mobile-actionbar__shell {
	display: grid;
	gap: 8px;
	padding: 10px;
	border-radius: 18px;
	background: rgba(255, 255, 255, 0.96);
	border: 1px solid rgba(200, 214, 220, 0.92);
	box-shadow: 0 18px 34px rgba(18, 38, 63, 0.14);
}

.shipment-mobile-actionbar__meta {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
}

.shipment-mobile-actionbar__label {
	font-size: 0.6875rem;
	font-weight: 700;
	letter-spacing: 0.02em;
	text-transform: uppercase;
	color: #6b7d90;
}

.shipment-mobile-actionbar__value {
	font-size: 1rem;
	line-height: 1;
	font-weight: 800;
	color: #095866;
}

.shipment-mobile-actionbar__actions {
	display: grid;
	grid-template-columns: minmax(0, 104px) minmax(0, 1fr);
	gap: 10px;
}

.shipment-mobile-actionbar__secondary,
.shipment-mobile-actionbar__primary {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-height: 48px;
	width: 100%;
	transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

.shipment-mobile-actionbar__primary:disabled {
	opacity: 0.6;
	box-shadow: none;
}

.step-secondary-action {
	transition:
		background-color 0.2s ease,
		border-color 0.2s ease,
		color 0.2s ease,
		transform 0.2s ease,
		box-shadow 0.2s ease;
	cursor: pointer;
}

.step-secondary-action:hover,
.step-secondary-action:focus-visible {
	transform: translateY(-1px);
}

/* ==========================================================
   Flow section shell / header
   ========================================================== */
.flow-section-shell {
	padding: 18px 16px 16px;
	border: 1px solid #d9e6ea;
	border-radius: 20px;
	background: linear-gradient(180deg, #ffffff 0%, #f7fafb 100%);
	box-shadow: 0 14px 28px rgba(20, 37, 48, 0.06);
}

.flow-section-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 10px;
	padding: 0 2px 2px;
	border: none;
	background: transparent;
	box-shadow: none;
}

.flow-section-header__copy {
	display: grid;
	gap: 4px;
	min-width: 0;
}

.flow-section-header__eyebrow {
	color: #0e6572;
}

.flow-section-header__title {
	margin: 0;
	color: #1f2a3c;
	font-size: 1.65rem;
	line-height: 1;
	letter-spacing: -0.03em;
}

.flow-section-header__text {
	margin: 0;
	color: #627082;
	max-width: 26rem;
	font-size: 0.875rem;
}

.flow-section-header__chip {
	flex: 0 0 auto;
	min-width: 88px;
	height: 34px;
	padding: 0 13px;
	font-size: 0.78rem;
}

.flow-section-header--services {
	margin-top: 0;
	margin-bottom: 2px;
}

/* ==========================================================
   Services stage shell / block
   ========================================================== */
.services-stage-shell {
	display: grid;
	gap: 12px;
	margin-top: 6px;
	padding: 18px;
	border: 1.5px solid #d7e3e8;
	border-radius: 22px;
	background: #ffffff;
	box-shadow: 0 12px 24px rgba(20, 37, 48, 0.05);
}

.services-stage-shell__content {
	display: grid;
	gap: 16px;
}

.services-stage-block {
	display: flex;
	flex-direction: column;
	gap: 24px;
	width: 100%;
	min-width: 0;
	opacity: 1;
	transform: translateY(0);
	max-height: 5000px;
	overflow: hidden;
	transition:
		opacity 0.28s ease,
		transform 0.28s ease,
		max-height 0.32s ease,
		margin-bottom 0.32s ease;
}

/* ==========================================================
   Address section
   ========================================================== */
.address-stage-shell {
	display: grid;
	gap: 14px;
	margin-top: 38px;
}

.address-stage-banner {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
	padding: 14px 16px;
	border-radius: 18px;
	border: 1px solid #d9e6ea;
	background: linear-gradient(180deg, #ffffff 0%, #f8fbfc 100%);
	box-shadow: 0 12px 24px rgba(9, 88, 102, 0.06);
	margin-top: 16px;
}

.address-stage-banner__copy {
	display: grid;
	gap: 4px;
	min-width: 0;
}

.address-stage-banner__eyebrow {
	font-size: 0.6875rem;
	line-height: 1;
	font-weight: 800;
	letter-spacing: 0.04em;
	text-transform: uppercase;
	color: #738295;
}

.address-stage-banner__title {
	margin: 0;
	font-size: 1.0625rem;
	line-height: 1.1;
	font-weight: 800;
	letter-spacing: -0.02em;
	color: #1f2a3c;
}

.address-stage-banner__text {
	margin: 0;
	font-size: 0.875rem;
	line-height: 1.45;
	color: #627082;
}

.address-stage-banner__chip {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 88px;
	height: 32px;
	padding: 0 12px;
	border-radius: 999px;
	background: rgba(234, 244, 246, 0.82);
	border-color: var(--color-brand-secondary-soft-border);
	font-size: 0.8125rem;
	font-weight: 800;
	line-height: 1;
	color: var(--color-brand-secondary-soft-text);
	flex: 0 0 auto;
}

.address-stage-enter-active,
.address-stage-leave-active {
	transition: opacity 0.24s ease, transform 0.28s ease;
}

.address-stage-enter-from,
.address-stage-leave-to {
	opacity: 0;
	transform: translateY(10px);
}

.address-utility-button {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	min-height: 38px;
	padding: 0 14px;
	border-radius: 999px;
	border: 1px solid #d6e7ea;
	font-size: 0.8125rem;
	font-weight: 700;
	line-height: 1;
	transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
	cursor: pointer;
}

.address-utility-button:hover {
	transform: translateY(-1px);
}

.address-utility-button--teal {
	background: #f3f9fa;
	border-color: #d6e7ea;
	color: #0e6572;
}

.address-utility-button--teal:hover {
	background: #ebf5f7;
	border-color: #bcd7de;
}

.address-utility-button--sand {
	background: #ffffff;
	border-color: #d7e4e8;
	color: #4f6072;
}

.address-utility-button--sand:hover {
	background: #f8fbfc;
	border-color: #bfd2d8;
}

/* ==========================================================
   Date picker
   ========================================================== */
.pickup-date-card {
	width: 100%;
	min-width: 0;
	padding: 18px 18px 16px;
	border-radius: 22px;
	border: 1.5px solid #d7e3e8;
	background: #ffffff;
	box-shadow: 0 14px 28px rgba(20, 37, 48, 0.06);
}

.pickup-date-card__header {
	width: 100%;
	min-width: 0;
	display: grid;
	gap: 6px;
	padding-inline: 4px;
}

.pickup-date-card__heading {
	font-size: 1.6rem;
	line-height: 1.04;
	letter-spacing: -0.04em;
	color: #1f2a3c;
}

.pickup-date-card__note {
	font-size: 0.86rem;
	line-height: 1.35;
	color: #617385;
}

.pickup-date-slider-shell {
	width: 100%;
	padding-top: 8px;
	padding-bottom: 2px;
	overflow: visible;
}

.pickup-date-slider-track {
	width: 100%;
}

/* Override Swiper overflow to show shadows */
.pickup-date-slider-track,
.my-swiper,
.my-swiper .swiper-wrapper,
.my-swiper .swiper-slide {
	overflow: visible !important;
}

.my-swiper .swiper-slide {
	height: auto;
}

.my-swiper {
	padding-block: 6px;
	margin-block: -6px;
}

.pickup-date-option {
	display: flex;
	flex-direction: column;
	height: 100%;
	overflow: hidden;
	cursor: pointer;
	transition:
		transform var(--sf-motion-base) var(--sf-ease-soft),
		border-color var(--sf-motion-base) var(--sf-ease-soft),
		box-shadow var(--sf-motion-base) var(--sf-ease-soft),
		background-color var(--sf-motion-base) var(--sf-ease-soft);
	border: 1.5px solid #a7bcc5;
	border-radius: var(--sf-radius-control);
	background: #fbfcfd;
	box-shadow: 0 6px 14px rgba(20, 37, 48, 0.04);
}

.pickup-date-option.is-available:hover,
.pickup-date-option.is-available:focus-within {
	border-color: #6b8794;
	box-shadow: 0 10px 18px rgba(20, 37, 48, 0.075);
}

.pickup-date-option__weekday {
	display: block;
	width: 100%;
	text-align: center;
	height: 30px;
	line-height: 30px;
	background: #e8eef2;
	color: #223447;
	font-size: 0.8rem;
	font-weight: 800;
}

.pickup-date-option__body {
	display: flex;
	flex: 1 1 auto;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 1px;
	padding: 10px 0;
	background: #fbfcfd;
}

.pickup-date-option__day {
	font-size: 1.55rem;
	line-height: 1;
	font-weight: 800;
	color: #26384a;
}

.pickup-date-option__month {
	font-size: 0.78rem;
	line-height: 1.1;
	font-weight: 700;
	color: #5d6f82;
}

.pickup-date-option.is-selected {
	border: 2px solid #0b5965;
	background: #ffffff;
	box-shadow:
		0 0 0 1px rgba(11, 89, 101, 0.14),
		0 14px 24px rgba(11, 89, 101, 0.12);
}

.pickup-date-option.is-disabled {
	opacity: 0.48;
	cursor: not-allowed;
	pointer-events: none;
}

.pickup-date-option.is-selected .pickup-date-option__weekday {
	background: #0b5965;
	color: #ffffff;
}

.pickup-date-option.is-selected .pickup-date-option__body {
	background: #ffffff;
}

.pickup-date-option.is-selected .pickup-date-option__day,
.pickup-date-option.is-selected .pickup-date-option__month {
	color: #0b5965;
}

.pickup-date-nav {
	min-width: 40px;
	min-height: 40px;
	border: 1px solid #d5e2e7;
	background: rgba(255, 255, 255, 0.98);
	box-shadow: 0 10px 20px rgba(20, 37, 48, 0.08);
}

.custom-prev img,
.custom-next img {
	margin: 0 auto;
}

.swiper-button-disabled {
	opacity: 0.3;
	cursor: not-allowed;
}

/* Accordion animation */
.accordion-enter-active,
.accordion-leave-active {
	transition: height 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ==========================================================
   Service cards (senza-etichetta / tile)
   ========================================================== */
.senza-etichetta-card,
.senza-etichetta-card.is-selected,
.senza-etichetta-card.is-idle {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto;
	gap: 14px;
	padding: 16px 18px;
	width: 100%;
	text-align: left;
	border-radius: 22px;
	border: 1.5px solid #c8d9df;
	background: #ffffff;
	box-shadow: 0 10px 18px rgba(20, 37, 48, 0.05);
	animation: none;
}

.senza-etichetta-card.is-idle {
	border-color: #bfd4db;
	background: #fbfefe;
	box-shadow: 0 12px 20px rgba(20, 37, 48, 0.05);
}

.senza-etichetta-card.is-selected {
	border: 2px solid #0b5965;
	background: #f7fbfc;
	box-shadow:
		0 0 0 1px rgba(11, 89, 101, 0.12),
		0 16px 26px rgba(11, 89, 101, 0.12);
}

.senza-etichetta-card:hover,
.senza-etichetta-card:focus-visible {
	transform: translateY(-1px);
}

/* Override potential external pseudo-element animations */
.senza-etichetta-card::before,
.senza-etichetta-card::after {
	display: none !important;
}

.service-card-tile,
.service-card-tile--idle,
.service-card-tile--selected {
	min-height: 214px;
	padding: 18px;
	border: 1.5px solid #cddce1;
	border-radius: var(--sf-radius-card);
	background: #ffffff;
	box-shadow: 0 10px 18px rgba(20, 37, 48, 0.05);
	color: #1f2a3c;
	cursor: default;
}

.service-card-tile--selected {
	border: 2px solid #0b5965;
	background: #f7fbfc;
	box-shadow:
		0 0 0 1px rgba(11, 89, 101, 0.1),
		0 14px 24px rgba(11, 89, 101, 0.1);
}

.service-card-tile--expanded {
	border-color: #91b0b9;
	box-shadow: 0 16px 26px rgba(20, 37, 48, 0.08);
}

.service-card-tile__icon-shell,
.service-card-tile__icon-shell--selected {
	width: 46px;
	height: 46px;
	flex: 0 0 46px;
	border-radius: 14px;
	background: #f6faf9;
	border: 1px solid #c7d8de;
	box-shadow: 0 4px 10px rgba(20, 37, 48, 0.04);
}

.service-card-tile__icon-shell--selected {
	border-color: #0b5965;
	box-shadow: 0 0 0 2px rgba(11, 89, 101, 0.06);
}

.service-card-tile__top {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 10px;
	width: 100%;
	margin-bottom: 12px;
}

/* Override external background-image on icon */
.service-card-tile__icon {
	background-image: none !important;
	background-color: #0b5965;
	-webkit-mask-image: var(--service-icon-bg);
	mask-image: var(--service-icon-bg);
	-webkit-mask-size: contain;
	mask-size: contain;
	-webkit-mask-repeat: no-repeat;
	mask-repeat: no-repeat;
	-webkit-mask-position: center;
	mask-position: center;
	filter: none !important;
}

.service-card-tile--selected .service-card-tile__icon,
.senza-etichetta-card.is-selected .service-card-tile__icon {
	background-color: #0b5965;
}

.service-card-tile__price,
.service-card-tile__price--selected {
	font-size: 0.84rem;
	font-weight: 800;
	color: #d85a1e;
}

.service-card-tile__title-row {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 8px;
}

.service-card-tile__title {
	font-size: 1.1rem;
	line-height: 1.15;
}

.service-card-tile__badge,
.service-card-tile__badge--selected {
	background: #eef6f8;
	color: #0b5965;
	border-radius: 999px;
	font-size: 0.74rem;
	font-weight: 700;
}

.service-card-tile__description,
.service-card-tile--selected .service-card-tile__description {
	margin-top: 4px;
	font-size: 0.875rem;
	line-height: 1.35;
	color: #556679;
	display: -webkit-box;
	-webkit-line-clamp: 1;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

.service-card-tile__body-hit {
	width: 100%;
	padding: 0;
	border: 0;
	background: transparent;
	text-align: left;
	cursor: default;
}

.service-card-tile__footer-row,
.service-card-tile--selected .service-card-tile__footer-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 10px;
	margin-top: 12px;
	padding-top: 12px;
	border-top: 1px solid rgba(11, 89, 101, 0.1);
}

.service-card-tile__controls {
	display: inline-flex;
	align-items: center;
	justify-content: flex-end;
	flex-wrap: wrap;
	gap: 8px;
	margin-left: auto;
}

.service-card-tile__footer {
	display: inline-flex;
	align-items: center;
	gap: 10px;
	flex: 0 0 auto;
	min-height: 38px;
	padding: 0 12px;
	border-radius: var(--sf-radius-pill);
	background: #f7fbfc;
	border: 1px solid #ccdbe1;
	cursor: pointer;
}

.service-card-tile__footer:hover:not(:disabled),
.service-card-tile__footer:focus-visible:not(:disabled) {
	background: #f1f7f8;
	border-color: #b7cfd6;
}

.service-card-tile__footer:disabled {
	opacity: 0.56;
	cursor: not-allowed;
	background: #f1f5f7;
}

.service-card-tile__footer.is-disabled {
	opacity: 0.68;
}

.service-card-tile__state-pill {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	flex: 0 0 auto;
	min-height: 36px;
	padding: 0 11px;
	border-radius: var(--sf-radius-pill);
	background: #f7fbfc;
	border: 1px solid #d7e2e7;
	color: #627487;
	font-size: 0.78rem;
	font-weight: 700;
	line-height: 1;
}

.service-card-tile__state-pill--open {
	background: #eef7f8;
	border-color: #bfd5da;
	color: #0b5965;
}

.service-card-tile__state-pill--accent {
	background: #f2f8fa;
	border-color: #d2e2e6;
	color: #0b5965;
}

.service-card-tile__state-dot {
	width: 8px;
	height: 8px;
	border-radius: 999px;
	background: #0b5965;
	box-shadow: 0 0 0 4px rgba(11, 89, 101, 0.12);
}

.service-card-tile__state-pill--open .service-card-tile__state-dot {
	background: #0b5965;
	box-shadow: 0 0 0 4px rgba(11, 89, 101, 0.12);
}

.service-card-tile__switch-thumb {
	position: absolute;
	top: 3px;
	left: 3px;
	width: 22px;
	height: 22px;
	border-radius: 999px;
	background: #ffffff;
	border: 1px solid rgba(13, 47, 57, 0.08);
	box-shadow: 0 1px 4px rgba(0, 0, 0, 0.18), 0 3px 8px rgba(0, 0, 0, 0.1);
	transition: transform var(--sf-motion-base) var(--sf-ease-press), box-shadow var(--sf-motion-base) var(--sf-ease-soft);
}

.service-card-tile__switch.is-active {
	background: #0b5965;
	box-shadow: inset 0 1px 3px rgba(6, 51, 61, 0.24);
}

.service-card-tile__switch.is-active .service-card-tile__switch-thumb {
	transform: translateX(20px);
}

.service-card-tile__switch-label {
	color: #68798b;
	font-weight: 700;
}

.service-card-tile__switch-label--selected,
.service-card-tile__switch-label--ready {
	color: #0b5965;
}

.service-card-tile__switch-label--pending {
	color: #7b6b53;
}

.service-card-tile__configure {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	flex: 0 0 auto;
	min-height: 38px;
	min-width: 110px;
	padding: 0 14px;
	border-radius: var(--sf-radius-pill);
	background: #ffffff;
	border: 1px solid #bdd0d7;
	color: #0b5965;
	box-shadow: none;
}

.service-card-tile__configure.is-active,
.service-card-tile--expanded .service-card-tile__configure {
	background: #eef6f8;
	border-color: #a8c3cc;
	color: #084c57;
}

.service-card-tile__configure:hover,
.service-card-tile__configure:focus-visible {
	background: #f3f8f9;
	border-color: #a9c5ce;
	color: #084c57;
}

.service-card-tile__accordion {
	margin-top: 12px;
	padding-top: 14px;
	border-top: 1px solid rgba(11, 89, 101, 0.1);
}

/* ==========================================================
   Service inline panel (configuration forms)
   ========================================================== */
.service-inline-panel {
	display: grid;
	gap: 14px;
}

.service-inline-panel__grid {
	display: grid;
	gap: 12px;
}

.service-inline-panel__grid--double {
	grid-template-columns: repeat(2, minmax(0, 1fr));
}

.service-inline-field {
	display: grid;
	gap: 6px;
}

.service-inline-field__label {
	font-size: 0.8125rem;
	font-weight: 700;
	color: #334155;
}

.service-inline-field__input-shell {
	position: relative;
}

.service-inline-field__input {
	width: 100%;
	min-height: 44px;
	padding: 0 14px;
	border-radius: 14px;
	border: 1px solid #d5e5e9;
	background: #ffffff;
	color: #1f2a3c;
	font-size: 0.95rem;
	font-weight: 600;
	outline: none;
	transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.service-inline-field__input:focus {
	border-color: #0b5965;
	box-shadow: 0 0 0 3px rgba(11, 89, 101, 0.12);
}

.service-inline-field__suffix {
	position: absolute;
	top: 50%;
	right: 14px;
	transform: translateY(-50%);
	font-size: 0.875rem;
	font-weight: 700;
	color: #64748b;
}

.service-inline-field__error {
	font-size: 0.75rem;
	font-weight: 600;
	color: #c2410c;
}

.service-inline-choice-block {
	display: grid;
	gap: 8px;
}

.service-inline-choice-wrap {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.service-inline-choice {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-height: 36px;
	padding: 0 14px;
	border-radius: 999px;
	border: 1px solid #cfe0e5;
	background: #ffffff;
	color: #506072;
	font-size: 0.8125rem;
	font-weight: 700;
	cursor: pointer;
	transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.service-inline-choice.is-active {
	border-color: #0b5965;
	background: #e7f2f5;
	color: #0b5965;
}

.service-inline-insurance-list {
	display: grid;
	gap: 12px;
}

.service-inline-insurance-card {
	display: grid;
	gap: 8px;
	padding: 12px;
	border-radius: 16px;
	border: 1px solid #d6e6ea;
	background: #f9fcfd;
}

.service-inline-insurance-card__head {
	display: flex;
	align-items: baseline;
	justify-content: space-between;
	gap: 10px;
}

.service-inline-insurance-card__title {
	font-size: 0.9rem;
	font-weight: 700;
	color: #1f2a3c;
}

.service-inline-insurance-card__meta {
	font-size: 0.75rem;
	font-weight: 600;
	color: #64748b;
}

.service-inline-panel__actions {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 10px;
	margin-top: 14px;
	padding-top: 14px;
	border-top: 1px solid rgba(11, 89, 101, 0.08);
}

.service-inline-panel__actions--split {
	justify-content: space-between;
}

.service-inline-panel__dismiss {
	min-width: 118px;
}

.service-inline-panel__submit {
	min-width: 132px;
}

/* ==========================================================
   Notification / toggle / support controls
   ========================================================== */
.service-support-grid {
	width: 100%;
	display: grid;
	gap: 16px;
	margin-top: 12px;
	padding-top: 0;
}

.service-support-field {
	min-width: 0;
	display: flex;
	flex-direction: column;
	gap: 0.55rem;
}

.service-support-field__label-row {
	display: flex;
	align-items: center;
	gap: 0.45rem;
	flex-wrap: wrap;
}

.service-support-field__label {
	display: block;
	font-size: 0.95rem;
	font-weight: 700;
	color: #252b42;
}

.service-support-field__help {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 1.2rem;
	height: 1.2rem;
	border-radius: 999px;
	border: 0;
	background: #7aaebe;
	color: #ffffff;
	font-size: 0.78rem;
	font-weight: 700;
	line-height: 1;
	padding: 0;
	cursor: help;
}

.service-support-field__tooltip {
	position: absolute;
	left: 0;
	bottom: calc(100% + 0.5rem);
	z-index: 50;
	display: none;
	width: 280px;
	transform: translateX(0);
	border-radius: 0.75rem;
	background: #252b42;
	color: #fff;
	padding: 0.7rem 0.9rem;
	box-shadow: 0 14px 24px rgba(37, 43, 66, 0.18);
}

.group:hover .service-support-field__tooltip,
.group:focus-within .service-support-field__tooltip {
	display: block;
}

.service-support-field__tooltip-arrow {
	position: absolute;
	top: 100%;
	left: 18px;
	transform: translateX(0);
	width: 0;
	height: 0;
	border-left: 6px solid transparent;
	border-right: 6px solid transparent;
	border-top: 6px solid #252b42;
}

.service-support-field__input {
	width: 100%;
	min-height: 3rem;
	border-radius: 1rem;
	border: 1px solid #d7e1e4;
	background: #ffffff;
	padding: 0.9rem 1rem;
	font-size: 0.95rem;
	color: #252b42;
	box-shadow: 0 6px 16px rgba(37, 43, 66, 0.04);
}

.service-support-field__notification-card {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto;
	align-items: center;
	gap: 12px;
	min-height: 56px;
	padding: 12px 14px;
	border: 1.5px solid #cfe0e5;
	border-radius: var(--sf-radius-control);
	background: #ffffff;
	box-shadow: var(--sf-shadow-soft);
	cursor: pointer;
	transition: border-color 180ms ease, box-shadow 180ms ease, background-color 180ms ease;
}

.service-support-field__notification-card:hover,
.service-support-field__notification-card:focus-within {
	border-color: #abc4cd;
	box-shadow: 0 12px 22px rgba(20, 37, 48, 0.08);
	transform: translateY(-1px);
}

.service-support-field__notification-card:focus-visible {
	outline: none;
	box-shadow: 0 0 0 3px rgba(9, 88, 102, 0.14);
}

.service-support-field__notification-card.is-active {
	border: 1.5px solid #0b5965;
	background: #f7fbfc;
	box-shadow: 0 12px 22px rgba(11, 89, 101, 0.1);
}

.service-support-field__notification-main {
	display: flex;
	align-items: center;
	gap: 10px;
	min-width: 0;
}

.service-support-field__notification-headline {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 10px;
}

.service-support-field__notification-copy-wrap {
	display: grid;
	gap: 2px;
	min-width: 0;
}

.service-support-field__notification-icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 2rem;
	height: 2rem;
	flex: 0 0 2rem;
	border-radius: 12px;
	background: #f5faf9;
	border: 1px solid #cfe0e5;
	color: #0b5965;
	transition: color 180ms ease, background-color 180ms ease;
}

.service-support-field__notification-copy {
	min-width: 0;
	font-size: 0.95rem;
	font-weight: 700;
	color: #334155;
	line-height: 1.2;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.service-support-field__notification-side {
	display: inline-flex;
	align-items: center;
	gap: 10px;
	flex: 0 0 auto;
	justify-self: end;
}

.service-support-field__notification-price {
	font-size: 0.78rem;
	font-weight: 800;
	color: #d85a1e;
	white-space: nowrap;
	margin-left: auto;
	transition: color 180ms ease;
}

.service-support-field__switch {
	position: relative;
	display: inline-block;
	width: 52px;
	height: 28px;
	cursor: pointer;
	flex: 0 0 auto;
}

.service-support-field__switch-track {
	position: absolute;
	inset: 0;
	border-radius: 999px;
	background: #c0c0c0;
	box-shadow: inset 0 1px 2px rgba(37, 43, 66, 0.08);
	transition: background-color 180ms ease, box-shadow 180ms ease;
}

.peer:checked + .service-support-field__switch-track {
	background: #0e6572;
	box-shadow: inset 0 2px 5px rgba(6, 51, 61, 0.24);
}

.peer:focus-visible + .service-support-field__switch-track {
	box-shadow: 0 0 0 3px rgba(9, 88, 102, 0.14);
}

.service-support-field__switch-thumb {
	position: absolute;
	top: 3px;
	left: 3px;
	width: 22px;
	height: 22px;
	border-radius: 999px;
	background: #fff;
	box-shadow: 0 2px 4px rgba(37, 43, 66, 0.18);
	transition: transform 180ms ease;
}

.peer:checked ~ .service-support-field__switch-thumb {
	transform: translateX(24px);
}

.service-support-field__switch-state {
	font-size: 0.8125rem;
	font-weight: 700;
	color: #67788a;
}

.service-support-field__switch-state.is-active {
	color: #0b5965;
}

/* ==========================================================
   Responsive / media queries
   ========================================================== */
@media (max-width: 44.99rem) {
	.shipment-step-shell {
		padding-bottom: 108px;
	}

	.pickup-date-card {
		padding-inline: 12px;
	}

	.pickup-date-card__header {
		padding-inline: 4px;
	}

	.pickup-date-card__heading {
		margin: 0;
		font-size: 1.125rem;
		line-height: 1.1;
	}

	.pickup-date-card__note {
		margin-top: 4px;
		font-size: 0.8125rem;
		line-height: 1.35;
		color: #5f6f81;
		max-width: 17rem;
	}

	.pickup-date-slider-track {
		padding-inline: 0;
	}

	.my-swiper {
		padding-inline: 4px;
	}

	.my-swiper :deep(.swiper-slide label) {
		border-radius: 12px;
	}

	.my-swiper :deep(.swiper-slide span:first-child) {
		height: 30px;
		line-height: 30px;
		font-size: 0.78125rem;
	}

	.my-swiper :deep(.swiper-slide div.flex-1) {
		min-height: 58px;
	}

	.my-swiper :deep(.swiper-slide div.flex-1 > span:first-child) {
		font-size: 2rem;
		line-height: 1;
	}

	.my-swiper :deep(.swiper-slide div.flex-1 > span:last-child) {
		font-size: 0.8125rem;
		line-height: 1.1;
	}

	.custom-prev,
	.custom-next {
		display: none !important;
	}

	.senza-etichetta-card,
	.senza-etichetta-card.is-selected,
	.senza-etichetta-card.is-idle {
		grid-template-columns: minmax(0, 1fr);
	}

	.service-support-field__notification-card {
		grid-template-columns: minmax(0, 1fr) auto;
		row-gap: 10px;
	}

	.service-support-field__notification-side {
		justify-self: end;
	}
}

@media (max-width: 63.99rem) {
	.services-stage-block.is-collapsed-on-mobile {
		max-height: 0;
		opacity: 0;
		transform: translateY(-10px);
		pointer-events: none;
		margin-bottom: -4px;
	}
}

@media (min-width: 45rem) {
	.pickup-date-card {
		padding-inline: 0;
	}

	.pickup-date-card__header {
		padding-left: 78px;
		padding-right: 78px;
		max-width: 34rem;
	}

	.pickup-date-card__heading {
		margin: 0 0 12px;
		font-size: 1.8125rem;
		line-height: 1.05;
	}

	.pickup-date-card__note {
		margin: 0 0 2px;
		font-size: 0.9375rem;
		line-height: 1.45;
		color: #5f6f81;
		max-width: 28rem;
	}

	.pickup-date-slider-track {
		padding-inline: 20px;
	}
}

@media (min-width: 768px) {
	.service-support-grid {
		grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
		align-items: start;
		gap: 1.2rem;
	}
}
</style>
