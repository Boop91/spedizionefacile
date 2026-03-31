<!--
  FILE: pages/la-tua-spedizione/[step].vue
  SCOPO: Configurazione multi-step — Step 1: servizi/data ritiro; Step 2: indirizzi mittente/destinatario.
  API: GET /api/session (dati sessione), GET /api/user-addresses (rubrica),
       GET /api/locations/search (autocompletamento citta'), GET /api/saved-shipments (configurazioni).
  STORE: userStore.pendingShipment (salva dati per riepilogo).
  ROUTE: /la-tua-spedizione/1 e /la-tua-spedizione/2 (middleware shipment-validation).
  NOTE: Template sections extracted to components/shipment/Shipment*.vue
-->
<script setup>
const userStore = useUserStore();
const route = useRoute();
const { openAuthModal } = useAuthModal();
const { isAuthenticatedForUi } = useAuthUiState();

// Step corrente dalla route
const currentStep = computed(() => Number(route.params.step));

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
	activateConfiguredService,
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

// Handler per evento activate-configured-service dal componente RegularServicesGrid
const onActivateConfiguredService = (service) => {
	activateConfiguredService(service);
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

						<!-- Data Ritiro -->
						<ShipmentPickupDateSection
							ref="pickupDateSectionRef"
							:date-error="dateError"
							:days-in-month="daysInMonth"
							:services="services"
							:choose-date="chooseDate" />

					<section class="services-stage-shell sf-section-block">
						<div class="flow-section-header flow-section-header--services sf-section-block__header">
							<div class="flow-section-header__copy">
								<h2 class="flow-section-header__title sf-section-title">Servizi</h2>
							</div>
						</div>

						<div class="services-stage-shell__content font-montserrat">
							<div class="w-full mx-auto">
							<div class="w-full">

								<!-- Servizio "Senza etichetta" -->
								<ShipmentFeaturedServiceCard
									:featured-service="featuredService"
									:service-icon-filter-idle="SERVICE_ICON_FILTER_IDLE"
									:service-icon-filter-active="SERVICE_ICON_FILTER_ACTIVE"
									:toggle-featured-service="toggleFeaturedService" />

								<!-- Servizi regolari + contenuto pacco + notifiche -->
								<ShipmentRegularServicesGrid
									:regular-services="regularServices"
									:service-icon-filter-idle="SERVICE_ICON_FILTER_IDLE"
									:service-icon-filter-active="SERVICE_ICON_FILTER_ACTIVE"
									:is-service-expanded="isServiceExpanded"
									:is-service-selected="isServiceSelected"
									:can-configure-service="canConfigureService"
									:should-show-service-toggle="shouldShowServiceToggle"
									:should-show-configure-button="shouldShowConfigureButton"
									:can-activate-configured-service="canActivateConfiguredService"
									:get-service-state-label="getServiceStateLabel"
									:get-service-configure-label="getServiceConfigureLabel"
									:handle-service-primary-action="handleServicePrimaryAction"
									:toggle-regular-service="toggleRegularService"
									:service-data="serviceData"
									:service-card-errors="serviceCardErrors"
									:normalize-currency-input="normalizeCurrencyInput"
									:contrassegno-incasso-options="contrassegnoIncassoOptions"
									:contrassegno-rimborso-options="contrassegnoRimborsoOptions"
									:requires-contrassegno-dettaglio="requiresContrassegnoDettaglio"
									:insurance-packages="insurancePackages"
									:content-error="contentError"
									:content-field-hint="contentFieldHint"
									:user-store="userStore"
									:sms-email-notification="smsEmailNotification"
									:notification-price-label="notificationPriceLabel"
									@update:sms-email-notification="smsEmailNotification = $event"
									@update:content-error="contentError = $event"
									@activate-configured-service="onActivateConfiguredService" />

							</div>
							</div>
						</div>
					</section>
					</div>

					<!-- SEZIONE INDIRIZZI -->
					<div v-if="showAddressFields" class="address-stage-shell sf-stack-block">
						<div class="address-stage-banner flow-section-header flow-section-header--addresses sf-section-block__header">
							<div class="address-stage-banner__copy flow-section-header__copy">
								<h3 class="address-stage-banner__title flow-section-header__title sf-section-title">Indirizzi</h3>
							</div>
						</div>

						<!-- Partenza -->
						<ShipmentAddressOriginSection
							:show-global-form-summary="showGlobalFormSummary"
							:form-error-summary="formErrorSummary"
							:focus-form-error="focusFormError"
							:can-save-origin-address="canSaveOriginAddress"
							:saving-origin-address="savingOriginAddress"
							:origin-save-success="originSaveSuccess"
							:save-address-to-book="saveAddressToBook"
							:is-authenticated="isAuthenticated"
							:loading-configs="loadingConfigs"
							:show-default-dropdown="showDefaultDropdown"
							:show-default-dropdown-target="showDefaultDropdownTarget"
							:saved-configs="savedConfigs"
							:show-origin-config-guest-prompt="showOriginConfigGuestPrompt"
							:show-origin-address-selector="showOriginAddressSelector"
							:show-origin-guest-prompt="showOriginGuestPrompt"
							:loading-saved-addresses="loadingSavedAddresses"
							:saved-addresses="savedAddresses"
							:origin-section-hint="originSectionHint"
							:origin-address="originAddress"
							:load-saved-configs="loadSavedConfigs"
							:apply-config="applyConfig"
							:toggle-address-selector-with-default-close="toggleAddressSelectorWithDefaultClose"
							:apply-saved-address="applySavedAddress"
							:open-shipment-auth-modal="openShipmentAuthModal"
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
							:origin-city-suggestions="originCitySuggestions"
							:origin-province-suggestions="originProvinceSuggestions"
							:origin-cap-suggestions="originCapSuggestions" />

						<!-- Toggle Modalita Consegna + PUDO -->
						<ShipmentDeliveryModeSection
							:delivery-mode="deliveryMode"
							:destination-address="destinationAddress"
							:user-store="userStore"
							:on-pudo-selected="onPudoSelected"
							:on-pudo-deselected="onPudoDeselected"
							@update:delivery-mode="deliveryMode = $event" />

						<!-- Destinazione -->
						<ShipmentAddressDestSection
							:delivery-mode="deliveryMode"
							:destination-address="destinationAddress"
							:user-store="userStore"
							:can-save-dest-address="canSaveDestAddress"
							:saving-dest-address="savingDestAddress"
							:dest-save-success="destSaveSuccess"
							:save-address-to-book="saveAddressToBook"
							:is-authenticated="isAuthenticated"
							:loading-configs="loadingConfigs"
							:show-default-dropdown="showDefaultDropdown"
							:show-default-dropdown-target="showDefaultDropdownTarget"
							:saved-configs="savedConfigs"
							:show-dest-config-guest-prompt="showDestConfigGuestPrompt"
							:show-dest-address-selector="showDestAddressSelector"
							:show-dest-guest-prompt="showDestGuestPrompt"
							:loading-saved-addresses="loadingSavedAddresses"
							:saved-addresses="savedAddresses"
							:destination-section-hint="destinationSectionHint"
							:route-warning-message="routeWarningMessage"
							:load-saved-configs="loadSavedConfigs"
							:apply-config="applyConfig"
							:toggle-address-selector-with-default-close="toggleAddressSelectorWithDefaultClose"
							:apply-saved-address="applySavedAddress"
							:open-shipment-auth-modal="openShipmentAuthModal"
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
							:dest-city-suggestions="destCitySuggestions"
							:dest-province-suggestions="destProvinceSuggestions"
							:dest-cap-suggestions="destCapSuggestions" />
					</div>

					<!-- Desktop Actions -->
					<ShipmentFormActions
						:show-address-fields="showAddressFields"
						:is-submitting="isSubmitting"
						:edit-cart-id="editCartId"
						:can-open-address-fields="canOpenAddressFields"
						:submit-error="submitError"
						:go-back-to-services="goBackToServices"
						:open-address-fields="openAddressFields"
						:soften-error-message="softenErrorMessage" />

				</div>

				<!-- Mobile Action Bar -->
				<ShipmentMobileActionBar
					:show-address-fields="showAddressFields"
					:is-submitting="isSubmitting"
					:edit-cart-id="editCartId"
					:can-open-address-fields="canOpenAddressFields"
					:summary-total-price="summaryTotalPrice"
					:go-back-to-services="goBackToServices"
					:open-address-fields="openAddressFields" />

			</form>
		</div>
	</section>
</template>

<style scoped>
/* ==========================================================
   Base / Form styles (kept in orchestrator for global scope)
   ========================================================== */
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

.address-stage-banner__title {
	margin: 0;
	font-size: 1.0625rem;
	line-height: 1.1;
	font-weight: 800;
	letter-spacing: -0.02em;
	color: #1f2a3c;
}

/* Accordion animation */
.accordion-enter-active,
.accordion-leave-active {
	transition: height 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ==========================================================
   Responsive / media queries
   ========================================================== */
@media (max-width: 44.99rem) {
	.shipment-step-shell {
		padding-bottom: 108px;
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
</style>
