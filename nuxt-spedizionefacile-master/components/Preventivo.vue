<!--
	COMPONENTE: Preventivo (Preventivo.vue)
	SCOPO: Modulo principale per creare un preventivo di spedizione — il cuore del sito.
	       Orchestratore che compone i sub-componenti RouteComposer, PackagesList,
	       PromoBanner e ContinueSection.

	DOVE SI USA: pages/index.vue (homepage), pages/preventivo.vue (pagina dedicata)
	PROPS: nessuna
	EMITS: nessuno

	DATI IN INGRESSO: userStore (stato globale Pinia), useSession (sessione server),
	                  usePriceBands (fasce prezzo da API), useSmartValidation (validazione campi)
	DATI IN USCITA: POST /api/session/first-step (salva preventivo nel server),
	                navigazione a /la-tua-spedizione/2 (step successivo)

	VINCOLI: non modificare la formula di calcolo prezzo senza aggiornare anche
	         il backend (SessionController::firstStep). I due DEVONO dare lo stesso risultato.
	PUNTI DI MODIFICA SICURI: packageTypeList (tipi di collo), template HTML/CSS
	COLLEGAMENTI: composables/usePriceBands.js, composables/useSmartValidation.js,
	              stores/userStore.js, docs/guide/MODIFICARE-REGOLA-PREZZO.md

	FLUSSO UTENTE:
	1. L'utente sceglie il tipo di collo (Pacco, Pallet, Valigia)
	2. Inserisce partenza e destinazione con un solo campo per lato (citta' o CAP, con suggerimenti automatici)
	3. Inserisce peso e dimensioni (3 lati in cm) per ogni collo
	4. Il sistema calcola automaticamente il prezzo in base a peso e volume
	   (viene usato il prezzo PIU' ALTO tra peso e volume)
	5. Cliccando "Continua", i dati vengono inviati al server per validazione
	6. Se tutto va bene, l'utente passa subito allo step successivo dei servizi

	FORMULA PREZZO:
	- Si calcola un prezzo basato sul peso (fasce dinamiche da API + eventuali scaglioni extra)
	- Si calcola un prezzo basato sul volume (fasce dinamiche da API + eventuali scaglioni extra)
	- Si prende il prezzo PIU' ALTO tra i due → MAX(peso, volume)
	- Si aggiungono gli eventuali supplementi CAP configurati da admin
	- Si moltiplica per la quantita' di colli uguali

	I dati del preventivo vengono salvati nello "store" Pinia (memoria condivisa del sito)
	e nella sessione del server, cosi' si mantengono navigando tra le pagine.
-->
<script setup>
const {
  formRef, messageError, isCalculating, isSyncingQuote, isAdvancingToServices,
  userStore,
  isHomepageLikeRoute, isDestinationItaly, isOriginItaly,
  originLocationError, destLocationError, liveQuotePrice,
  continueButtonLabel, preventivoSubtitle, packageCountLabel,
  originPlaceholder, destinationPlaceholder, isStandalonePreventivoRoute,
  europeCountryOptions, hasFormData, isEuropeMonocollo, europeRestrictionMessage,
  originQuery, originSuggestions, showOriginSuggestions,
  destQuery, destSuggestions, showDestSuggestions,
  locationKey, getProvinceLabel,
  selectOriginLocation, selectDestLocation,
  settleOriginQuery, settleDestQuery,
  onOriginQueryFocus, onOriginQueryInput,
  onDestQueryFocus, onDestQueryInput,
  hideOriginSuggestions, hideDestSuggestions,
  onOriginManualInput, onOriginManualBlur,
  onDestManualInput, onDestManualBlur,
  applyOriginCountrySelection, applyDestinationCountrySelection,
  packageTypeList, addPackageInline, deletePack, updatePackageType,
  calcQuantity, incrementQuantity, decrementQuantity,
  sv, onWeightInput, onWeightBlur, onDimInput, onDimBlur,
  promoSettings,
  continueToNextStep, resetForm,
} = await usePreventivo();

/* ---------- origin country v-model bridge ---------- */
function onOriginCountryUpdate(val) {
  userStore.shipmentDetails.origin_country_code = val;
}
function onDestCountryUpdate(val) {
  userStore.shipmentDetails.destination_country_code = val;
}
</script>

<template>
	<section :class="isHomepageLikeRoute ? 'mt-[28px] tablet:mt-[36px] desktop:mt-[40px] relative z-10' : 'pt-[24px]'">
		<div class="my-container">
			<div
				class="preventivo-shell bg-white w-full max-w-[1280px] rounded-[22px] relative z-10 overflow-hidden p-[15px_12px_16px] tablet:p-[24px_28px_28px] desktop:p-[24px_32px_30px] mx-auto"
			:class="isHomepageLikeRoute
				? 'shadow-[0_4px_20px_rgba(9,88,102,0.06),0_16px_48px_rgba(9,88,102,0.04)]'
				: 'mt-[20px] shadow-[0_4px_20px_rgba(9,88,102,0.06),0_16px_48px_rgba(9,88,102,0.04)]'">
				<div class="preventivo-shell__accent" aria-hidden="true"></div>
				<div class="preventivo-heading">
					<div class="preventivo-heading__copy">
						<div class="preventivo-heading__icon" aria-hidden="true">
							<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.15" stroke-linecap="round" stroke-linejoin="round">
								<path d="M14 16.5V6a1 1 0 0 0-1-1H4.5a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1H14" />
								<path d="M14 8h3.2a1 1 0 0 1 .8.4l2.1 2.8a1 1 0 0 1 .2.6V17a1 1 0 0 1-1 1H14" />
								<circle cx="7.5" cy="18.5" r="1.5" />
								<circle cx="16.5" cy="18.5" r="1.5" />
							</svg>
						</div>
						<div class="preventivo-heading__text">
							<h2 class="preventivo-heading__title">Preventivo Rapido</h2>
							<p class="preventivo-heading__subtitle">{{ preventivoSubtitle }}</p>
						</div>
					</div>
					<button
						v-if="hasFormData"
						type="button"
						@click="resetForm"
						class="preventivo-heading__reset flex items-center gap-[4px] text-[0.75rem] text-[#999] hover:text-[#E44203] transition cursor-pointer group">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="group-hover:rotate-[-180deg] transition-transform duration-300"><path d="M2.5 2v6h6"/><path d="M2.66 15.57a10 10 0 1 0 .57-8.38L2.5 8"/></svg>
						<span class="hidden tablet:inline">Azzera</span>
					</button>
				</div>
				<form ref="formRef" class="preventivo-form" @submit.prevent="continueToNextStep">
					<div class="preventivo-steps-strip">
						<Steps :current-step="0" />
					</div>

					<div class="preventivo-layout">
						<!-- ROUTE COMPOSER -->
						<PreventivoRouteComposer
							:origin-query="originQuery"
							:dest-query="destQuery"
							:origin-suggestions="originSuggestions"
							:dest-suggestions="destSuggestions"
							:show-origin-suggestions="showOriginSuggestions"
							:show-dest-suggestions="showDestSuggestions"
							:origin-country-code="userStore.shipmentDetails.origin_country_code"
							:destination-country-code="userStore.shipmentDetails.destination_country_code"
							:europe-country-options="europeCountryOptions"
							:is-origin-italy="isOriginItaly"
							:is-destination-italy="isDestinationItaly"
							:origin-placeholder="originPlaceholder"
							:destination-placeholder="destinationPlaceholder"
							:origin-location-error="originLocationError"
							:dest-location-error="destLocationError"
							:origin-postal-code="userStore.shipmentDetails.origin_postal_code"
							:destination-postal-code="userStore.shipmentDetails.destination_postal_code"
							:location-key-fn="locationKey"
							:get-province-label-fn="getProvinceLabel"
							@update:origin-query="originQuery = $event"
							@update:dest-query="destQuery = $event"
							@update:origin-country-code="onOriginCountryUpdate"
							@update:destination-country-code="onDestCountryUpdate"
							@select-origin-location="selectOriginLocation"
							@select-dest-location="selectDestLocation"
							@settle-origin-query="settleOriginQuery"
							@settle-dest-query="settleDestQuery"
							@origin-query-focus="onOriginQueryFocus"
							@origin-query-input="onOriginQueryInput"
							@dest-query-focus="onDestQueryFocus"
							@dest-query-input="onDestQueryInput"
							@hide-origin-suggestions="hideOriginSuggestions"
							@hide-dest-suggestions="hideDestSuggestions"
							@origin-manual-input="onOriginManualInput"
							@origin-manual-blur="onOriginManualBlur"
							@dest-manual-input="onDestManualInput"
							@dest-manual-blur="onDestManualBlur"
							@apply-origin-country-selection="applyOriginCountrySelection(true)"
							@apply-destination-country-selection="applyDestinationCountrySelection(true)"
						/>

						<!-- PACKAGES LIST -->
						<PreventivoPackagesList
							:packages="userStore.packages"
							:package-type-list="packageTypeList"
							:is-europe-monocollo="isEuropeMonocollo"
							:europe-restriction-message="europeRestrictionMessage"
							:message-error="messageError"
							:sv="sv"
							@update-package-type="updatePackageType"
							@delete-pack="deletePack"
							@add-package-inline="addPackageInline"
							@calc-quantity="calcQuantity"
							@increment-quantity="incrementQuantity"
							@decrement-quantity="decrementQuantity"
							@on-weight-input="onWeightInput"
							@on-weight-blur="onWeightBlur"
							@on-dim-input="onDimInput"
							@on-dim-blur="onDimBlur"
						/>
					</div>

					<!-- PROMO BANNER -->
					<PreventivoPromoBanner :promo-settings="promoSettings" />

					<!-- CONTINUE + TRUST PILLS -->
					<PreventivoContinueSection
						:is-calculating="isCalculating"
						:is-advancing-to-services="isAdvancingToServices"
						:continue-button-label="continueButtonLabel"
						:live-quote-price="liveQuotePrice"
						:is-standalone-preventivo-route="isStandalonePreventivoRoute"
						:promo-active="!!(promoSettings?.active && promoSettings?.label_text)"
						@continue="continueToNextStep"
					/>
				</form>
			</div>
		</div>
	</section>
</template>

<style>
:root {
	--quote-shell-radius: 22px;
	--quote-card-radius: 16px;
	--quote-control-radius: 12px;
	--quote-small-radius: 10px;
	--quote-header-bg: #f8f9fb;
	--quote-body-bg-start: #f8f9fb;
	--quote-body-bg-end: #eef0f3;
	--quote-shell-bg: #e6e9ee;
	--quote-neutral-ring: #dfe2e7;
	--quote-text-strong: #1d2738;
	--quote-text-body: #555;
	--quote-text-muted: #666;
	--quote-text-subtle: #777;
	--quote-text-soft: #b8bcc4;
	--quote-selector-shell-bg: #d5d9e0;
	--quote-selector-active-bg: #e44203;
	--quote-selector-active-fg: #ffffff;
	--quote-selector-hover-bg: rgba(255, 255, 255, 0.42);
	--quote-error-bg: #fff5f2;
	--quote-error-fg: #e44203;
	--quote-active-icon-filter: brightness(0) saturate(100%) invert(100%);
}

.preventivo-shell__accent {
	height: 4px;
	margin: -15px -12px 12px;
	background: linear-gradient(90deg, #095866 0%, #0b9ab3 50%, #095866 100%);
}

.preventivo-form {
	display: block;
	background: linear-gradient(180deg, var(--quote-body-bg-start) 0%, var(--quote-body-bg-end) 100%);
	margin: 0 -12px -16px;
	padding: 0 12px 16px;
}

.preventivo-heading {
	position: relative;
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: 10px;
	padding: 0 0 0.2rem;
	background: var(--quote-header-bg);
}

.preventivo-heading__copy {
	display: flex;
	align-items: center;
	justify-content: flex-start;
	gap: 0.55rem;
	min-width: 0;
	text-align: left;
}

.preventivo-heading__icon {
	width: 2.25rem;
	height: 2.25rem;
	border-radius: 999px;
	background: linear-gradient(135deg, #095866, #0b7d92);
	color: #fff;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex: 0 0 auto;
}

.preventivo-heading__text {
	min-width: 0;
}

.preventivo-heading__title {
	margin: 0;
	font-size: clamp(1.08rem, 2vw, 1.56rem);
	line-height: 1.08;
	font-weight: 800;
	letter-spacing: -0.03em;
	color: var(--quote-text-strong);
}

.preventivo-heading__subtitle {
	margin: 0.12rem 0 0;
	font-size: 0.78rem;
	line-height: 1.3;
	font-weight: 500;
	color: #5a5a5a;
}

.preventivo-heading__reset {
	position: relative;
	height: 2.125rem;
	padding: 0 0.875rem;
	border-radius: 999px;
	background: #f0f1f4;
	color: #999;
	font-size: 0.8125rem;
	font-weight: 700;
	transition: all 0.35s cubic-bezier(0.22, 1, 0.36, 1);
}

.preventivo-heading__reset:hover {
	background: #ffe8e0;
	color: #e44203;
}

.preventivo-steps-strip {
	margin-top: 0;
	padding-bottom: 0.12rem;
	background: var(--quote-header-bg);
}

.preventivo-layout {
	display: grid;
	gap: 0.95rem;
	margin-top: 0.7rem;
	padding: 0 0 0.25rem;
}

.preventivo-section {
	display: grid;
	gap: 0.48rem;
}

.preventivo-section__title {
	margin: 0;
	text-align: left;
	color: #095866;
	font-size: 1rem;
	line-height: 1.25;
	font-weight: 600;
	letter-spacing: -0.02em;
}

.preventivo-section__lead {
	margin: 0;
	font-size: 0.8rem;
	line-height: 1.42;
	color: #6b7280;
}

@media (min-width: 640px) {
	.preventivo-shell__accent {
		margin: -24px -28px 20px;
	}

	.preventivo-form {
		margin: 0 -28px -28px;
		padding: 0 28px 28px;
	}

	.preventivo-heading {
		justify-content: center;
		align-items: center;
		padding: 0.25rem 0 0.6rem;
	}

	.preventivo-heading__copy {
		justify-content: center;
		text-align: center;
	}

	.preventivo-heading__title {
		font-size: 1.5rem;
	}

	.preventivo-heading__reset {
		position: absolute;
		right: 0;
		top: 0;
	}

	.preventivo-section__title {
		text-align: center;
		font-size: 1.25rem;
	}
}

@media (min-width: 1024px) {
	.preventivo-shell__accent {
		margin-top: -24px;
	}

	.preventivo-layout {
		gap: 2rem;
		margin-top: 1.5rem;
	}
}

@media (max-width: 639px) {
	.preventivo-heading__icon {
		width: 2rem;
		height: 2rem;
	}

	.preventivo-heading__reset {
		height: 1.95rem;
		padding: 0 0.72rem;
		font-size: 0.75rem;
	}
}
</style>
