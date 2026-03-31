<!--
	COMPONENTE: Preventivo (Preventivo.vue)
	SCOPO: Modulo principale per creare un preventivo di spedizione — il cuore del sito.

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
} = usePreventivo();
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
						<section class="preventivo-section" aria-labelledby="preventivo-tratta-title">
							<h3 id="preventivo-tratta-title" class="preventivo-section__title">
								Inserisci la tratta
							</h3>
							<p class="preventivo-section__lead">
								Inserisci comune o CAP di ritiro e consegna. Per l'Italia puoi usare uno dei due.
							</p>

							<div class="route-composer">
								<div class="route-composer__grid">
									<div class="route-card route-card--origin">
										<div class="route-card__header">
											<div class="route-card__heading">
												<div class="route-card__badge route-card__badge--origin" aria-hidden="true">
													<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
														<path d="M12 21s-6-4.35-6-10a6 6 0 1 1 12 0c0 5.65-6 10-6 10Z"></path>
														<circle cx="12" cy="11" r="2.5"></circle>
													</svg>
												</div>
												<p class="route-card__title">Partenza</p>
											</div>
											<div class="route-card__header-side route-card__header-side--country">
												<label for="origin_country_code" class="sr-only">Paese di partenza</label>
												<select
													id="origin_country_code"
													v-model="userStore.shipmentDetails.origin_country_code"
													class="route-card__country-chip"
													@change="applyOriginCountrySelection(true)">
													<option
														v-for="country in europeCountryOptions"
														:key="country.code"
														:value="country.code">
														{{ country.label }}
													</option>
												</select>
											</div>
										</div>
										<div class="route-card__field">
											<label for="origin_city" class="sr-only">Città o CAP di ritiro</label>
											<div class="route-card__input-wrap relative" :class="{ 'is-open': showOriginSuggestions && originSuggestions.length }">
												<input
													id="origin_city"
													v-model="originQuery"
													type="text"
													required
													autocomplete="off"
													:placeholder="originPlaceholder"
													class="input-preventivo-rapido input-preventivo-rapido--location"
													@focus="isOriginItaly ? onOriginQueryFocus() : hideOriginSuggestions()"
													@input="isOriginItaly ? onOriginQueryInput() : onOriginManualInput()"
													@blur="isOriginItaly ? settleOriginQuery() : onOriginManualBlur()" />
												<input type="hidden" v-model="userStore.shipmentDetails.origin_postal_code" id="origin_postal_code" />
												<ul v-if="showOriginSuggestions && originSuggestions.length" role="listbox" class="location-suggestions-list">
													<li
														v-for="loc in originSuggestions"
														:key="locationKey(loc)"
														role="option"
														aria-selected="false"
														@mousedown.prevent="selectOriginLocation(loc)"
														class="location-suggestion">
														<span class="location-suggestion__city">{{ loc.place_name }}</span>
														<span class="location-suggestion__meta">
															{{ loc.postal_code }}
															<template v-if="getProvinceLabel(loc)"> · {{ getProvinceLabel(loc) }}</template>
														</span>
													</li>
												</ul>
											</div>
											<div class="route-card__feedback">
												<p v-if="originLocationError" class="route-card__error">
													{{ originLocationError }}
												</p>
											</div>
										</div>
									</div>

									<div class="route-composer__connector" aria-hidden="true">
										<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
											<path d="M5 12h14"></path>
											<path d="M12 5l7 7-7 7"></path>
										</svg>
									</div>

									<div class="route-card route-card--destination">
										<div class="route-card__header route-card__header--destination">
											<div class="route-card__heading">
												<div class="route-card__badge route-card__badge--destination" aria-hidden="true">
													<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
														<path d="M12 21s-6-4.35-6-10a6 6 0 1 1 12 0c0 5.65-6 10-6 10Z"></path>
														<circle cx="12" cy="11" r="2.5"></circle>
													</svg>
												</div>
												<p class="route-card__title">Destinazione</p>
											</div>
											<div class="route-card__header-side route-card__header-side--country">
												<label for="destination_country_code" class="sr-only">Paese di destinazione</label>
												<select
													id="destination_country_code"
													v-model="userStore.shipmentDetails.destination_country_code"
													class="route-card__country-chip"
													@change="applyDestinationCountrySelection(true)">
													<option
														v-for="country in europeCountryOptions"
														:key="country.code"
														:value="country.code">
														{{ country.label }}
													</option>
												</select>
											</div>
										</div>
										<div class="route-card__field route-card__field--destination">
											<label for="destination_city" class="sr-only">Città o CAP di consegna</label>
											<div class="route-card__input-wrap relative" :class="{ 'is-open': showDestSuggestions && destSuggestions.length }">
												<input
													id="destination_city"
													v-model="destQuery"
													type="text"
													required
													autocomplete="off"
													:placeholder="destinationPlaceholder"
													class="input-preventivo-rapido input-preventivo-rapido--location"
													@focus="isDestinationItaly ? onDestQueryFocus() : hideDestSuggestions()"
													@input="isDestinationItaly ? onDestQueryInput() : onDestManualInput()"
													@blur="isDestinationItaly ? settleDestQuery() : onDestManualBlur()" />
												<input type="hidden" v-model="userStore.shipmentDetails.destination_postal_code" id="destination_postal_code" />
												<ul v-if="showDestSuggestions && destSuggestions.length" role="listbox" class="location-suggestions-list">
													<li
														v-for="loc in destSuggestions"
														:key="locationKey(loc)"
														role="option"
														aria-selected="false"
														@mousedown.prevent="selectDestLocation(loc)"
														class="location-suggestion">
														<span class="location-suggestion__city">{{ loc.place_name }}</span>
														<span class="location-suggestion__meta">
															{{ loc.postal_code }}
															<template v-if="getProvinceLabel(loc)"> · {{ getProvinceLabel(loc) }}</template>
														</span>
													</li>
												</ul>
											</div>
											<div class="route-card__feedback">
												<p v-if="destLocationError" class="route-card__error">
													{{ destLocationError }}
												</p>
											</div>
										</div>
									</div>
								</div>
								<p v-if="!isDestinationItaly" class="route-composer__note" aria-live="polite">
									<span class="route-composer__note-label">Europa monocollo</span>
									<span class="route-composer__note-text">Prezzo basato su paese, peso e volume.</span>
								</p>
							</div>
						</section>

						<section class="preventivo-section preventivo-section--packages" aria-labelledby="preventivo-colli-title">
							<h3 id="preventivo-colli-title" class="preventivo-section__title">
								Inserisci misure e peso
							</h3>

							<Transition name="dimensions-section" mode="out-in">
								<div v-if="userStore.packages.length > 0" class="dimensions-wrapper">
									<p
										v-if="isEuropeMonocollo"
										class="package-restriction-note">
										{{ europeRestrictionMessage }}
									</p>

									<ul class="package-entry-list">
										<li
											v-for="(pack, packIndex) in userStore.packages"
											:key="pack._qid || packIndex"
											class="package-entry">
											<div class="package-entry__header">
												<div class="package-type-switcher" :aria-label="`Tipo collo ${packIndex + 1}`">
													<button
														v-for="packageType in packageTypeList"
														:key="packageType.text"
														type="button"
														@click="updatePackageType(pack, packageType.text)"
														:aria-pressed="pack.package_type === packageType.text"
														:class="[
															'package-type-switcher__button',
															pack.package_type === packageType.text ? 'package-type-switcher__button--active' : ''
														]">
														<span class="package-type-switcher__icon-wrap">
															<img
																:src="`/img/quote/first-step/${packageType.img}`"
																alt=""
																:width="packageType.width"
																:height="packageType.height"
																class="package-type-switcher__icon-image"
																loading="eager"
																decoding="async"
																draggable="false" />
														</span>
														<span>{{ packageType.text }}</span>
													</button>
												</div>

												<button v-if="userStore.packages.length > 1" type="button" class="package-entry__delete" @click="deletePack(pack._qid || packIndex)" :aria-label="'Elimina pacco ' + (packIndex + 1)">
													<NuxtImg src="/img/quote/first-step/trash.png" alt="" width="18" height="22" class="package-entry__delete-icon" loading="lazy" decoding="async" />
												</button>
											</div>

											<div class="package-entry__grid">
												<div class="package-field-card package-field-card--quantity">
													<label :for="'quantity_' + packIndex" class="package-field-card__label">Q.tà</label>
													<div class="package-field-card__input-wrap package-field-card__input-wrap--stepper">
														<div class="quantity-stepper quantity-stepper--embedded">
															<button
																type="button"
																class="quantity-stepper__button"
																@click="decrementQuantity(pack)"
																:aria-label="`Riduci quantità collo ${packIndex + 1}`"
																:disabled="isEuropeMonocollo">
																<span class="quantity-stepper__symbol" aria-hidden="true">−</span>
															</button>
															<input
																:id="'quantity_' + packIndex"
																v-model="pack.quantity"
																type="text"
																inputmode="numeric"
																pattern="[0-9]*"
																class="quantity-stepper__input"
																:aria-describedby="`quantity_help_${packIndex}`"
																:aria-label="`Quantità collo ${packIndex + 1}`"
																:readonly="isEuropeMonocollo"
																@input="calcQuantity(pack)"
																@blur="calcQuantity(pack)" />
															<button
																type="button"
																class="quantity-stepper__button"
																@click="incrementQuantity(pack)"
																:aria-label="`Aumenta quantità collo ${packIndex + 1}`"
																:disabled="isEuropeMonocollo">
																<span class="quantity-stepper__symbol" aria-hidden="true">+</span>
															</button>
														</div>
													</div>
													<span :id="`quantity_help_${packIndex}`" class="sr-only">
														Numero di colli identici da spedire. Il prezzo viene moltiplicato per la quantità.
													</span>
													<div class="package-field-card__feedback">
														<p v-if="messageError?.[`packages.${packIndex}.quantity`]" class="package-field-card__error">
															{{ messageError[`packages.${packIndex}.quantity`][0] }}
														</p>
													</div>
												</div>

												<div class="package-field-card">
													<label :for="'weight_' + packIndex" class="package-field-card__label">Peso</label>
													<div class="package-field-card__input-wrap">
														<input type="text" placeholder="0" v-model="pack.weight" :id="'weight_' + packIndex" :class="sv.errorClass(`peso_${packIndex}`, 'package-metric-input')" @input="onWeightInput(pack, packIndex)" @blur="onWeightBlur(pack, packIndex)" required />
														<span class="package-field-card__unit">kg</span>
													</div>
													<div class="package-field-card__feedback">
														<p v-if="sv.getError(`peso_${packIndex}`)" class="package-field-card__error">{{ sv.getError(`peso_${packIndex}`) }}</p>
														<p v-else-if="messageError?.[`packages.${packIndex}.weight`]" class="package-field-card__error">
															{{ messageError[`packages.${packIndex}.weight`][0] }}
														</p>
													</div>
												</div>

												<div class="package-field-card">
													<label :for="'first_size_' + packIndex" class="package-field-card__label">Lung.</label>
													<div class="package-field-card__input-wrap">
														<input type="text" placeholder="0" v-model="pack.first_size" :id="'first_size_' + packIndex" :class="sv.errorClass(`first_size_${packIndex}`, 'package-metric-input')" @input="onDimInput(pack, packIndex, 'first_size', 'Lato 1')" @blur="onDimBlur(pack, packIndex, 'first_size', 'Lato 1')" required />
														<span class="package-field-card__unit">cm</span>
													</div>
													<div class="package-field-card__feedback">
														<p v-if="sv.getError(`first_size_${packIndex}`)" class="package-field-card__error">{{ sv.getError(`first_size_${packIndex}`) }}</p>
														<p v-else-if="messageError?.[`packages.${packIndex}.first_size`]" class="package-field-card__error">
															{{ messageError[`packages.${packIndex}.first_size`][0] }}
														</p>
													</div>
												</div>

												<div class="package-field-card">
													<label :for="'second_size_' + packIndex" class="package-field-card__label">Larg.</label>
													<div class="package-field-card__input-wrap">
														<input type="text" placeholder="0" v-model="pack.second_size" :id="'second_size_' + packIndex" :class="sv.errorClass(`second_size_${packIndex}`, 'package-metric-input')" @input="onDimInput(pack, packIndex, 'second_size', 'Lato 2')" @blur="onDimBlur(pack, packIndex, 'second_size', 'Lato 2')" required />
														<span class="package-field-card__unit">cm</span>
													</div>
													<div class="package-field-card__feedback">
														<p v-if="sv.getError(`second_size_${packIndex}`)" class="package-field-card__error">{{ sv.getError(`second_size_${packIndex}`) }}</p>
														<p v-else-if="messageError?.[`packages.${packIndex}.second_size`]" class="package-field-card__error">
															{{ messageError[`packages.${packIndex}.second_size`][0] }}
														</p>
													</div>
												</div>

												<div class="package-field-card">
													<label :for="'third_size_' + packIndex" class="package-field-card__label">Alt.</label>
													<div class="package-field-card__input-wrap">
														<input type="text" placeholder="0" v-model="pack.third_size" :id="'third_size_' + packIndex" :class="sv.errorClass(`third_size_${packIndex}`, 'package-metric-input')" @input="onDimInput(pack, packIndex, 'third_size', 'Lato 3')" @blur="onDimBlur(pack, packIndex, 'third_size', 'Lato 3')" required />
														<span class="package-field-card__unit">cm</span>
													</div>
													<div class="package-field-card__feedback">
														<p v-if="sv.getError(`third_size_${packIndex}`)" class="package-field-card__error">{{ sv.getError(`third_size_${packIndex}`) }}</p>
														<p v-else-if="messageError?.[`packages.${packIndex}.third_size`]" class="package-field-card__error">
															{{ messageError[`packages.${packIndex}.third_size`][0] }}
														</p>
													</div>
												</div>
											</div>
										</li>
									</ul>

									<div v-if="!isEuropeMonocollo" class="add-package-button-wrapper">
										<button
											type="button"
											@click="addPackageInline()"
											class="add-package-btn">
											<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.35" stroke-linecap="round" stroke-linejoin="round">
												<path d="M12 5v14"/>
												<path d="M5 12h14"/>
											</svg>
											Aggiungi collo
										</button>
									</div>

									<p
										v-if="messageError?.packages && userStore.packages.length > 0"
										class="preventivo-inline-error">
										{{ messageError.packages[0] }}
									</p>
								</div>
							</Transition>
						</section>
					</div>
						<!-- Promo banner sopra il CTA -->
					<div v-if="promoSettings?.active && promoSettings?.label_text" class="flex justify-center mt-[20px] desktop:mt-[16px]">
						<span
							:style="{ backgroundColor: promoSettings.label_color || '#E44203' }"
							class="inline-flex items-center gap-[6px] px-[14px] py-[6px] rounded-[8px] text-white text-[0.875rem] font-bold tracking-wide shadow-sm">
							<!-- Ottimizzazione: lazy loading + decoding async + dimensioni per prevenire CLS -->
							<img v-if="promoSettings.label_image" :src="promoSettings.label_image" alt="" loading="lazy" decoding="async" width="40" height="18" class="h-[18px] w-auto" />
							{{ promoSettings.label_text }}
						</span>
					</div>

					<div
						class="continue-button-wrapper bg-[#E44203] w-full text-white overflow-hidden"
						:class="[
							'h-[56px] tablet:h-[60px]',
							promoSettings?.active && promoSettings?.label_text ? 'mt-[12px]' : 'mt-[18px] desktop:mt-[20px]',
							isStandalonePreventivoRoute ? 'continue-button-wrapper--sticky' : ''
						]">
							<button
								v-if="!isCalculating"
								type="button"
								@click="continueToNextStep"
								:disabled="isCalculating || isAdvancingToServices"
								class="continue-cta-button w-full h-full cursor-pointer disabled:opacity-70 disabled:cursor-not-allowed">
								<span class="continue-cta-button__label">{{ continueButtonLabel }}</span>
								<span class="continue-cta-button__tail">
									<span
										v-if="liveQuotePrice"
										class="continue-cta-button__price"
										aria-label="Prezzo aggiornato">
										{{ liveQuotePrice }}
									</span>
									<span class="continue-cta-button__arrow-shell">
										<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" class="continue-cta-button__arrow" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
									</span>
								</span>
							</button>
						<p v-if="isCalculating || isAdvancingToServices" class="h-full flex justify-center items-center">
							<svg class="animate-spin h-[60px] w-[60px] text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
								<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
								<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
							</svg>
						</p>
					</div>
					<div class="preventivo-trust-row">
						<span class="preventivo-trust-pill">
							<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
							Pagamento sicuro
						</span>
						<span class="preventivo-trust-pill">
							<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
							Corriere BRT
						</span>
						<span class="preventivo-trust-pill">
							<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
							Ritiro 24h
						</span>
					</div>
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

.route-composer {
	padding: 0.7rem;
	border-radius: var(--quote-card-radius);
	background: var(--quote-shell-bg);
	box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.04);
}

.route-composer__grid {
	display: grid;
	gap: 0.62rem;
}

.route-card {
	min-width: 0;
}

.route-card__header {
	display: flex;
	align-items: center;
	gap: 0.5rem;
	margin-bottom: 0.45rem;
}

.route-card__heading {
	display: inline-flex;
	align-items: center;
	gap: 0.45rem;
	min-width: 0;
	flex: 1 1 auto;
}

.route-card__header-side--country {
	margin-left: auto;
}

.route-card__badge {
	width: 1.375rem;
	height: 1.375rem;
	border-radius: 999px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex: 0 0 auto;
}

.route-card__badge--origin {
	background: rgba(228, 66, 3, 0.1);
	color: #e44203;
}

.route-card__badge--destination {
	background: rgba(9, 88, 102, 0.1);
	color: #095866;
}

.route-card__title {
	margin: 0;
	font-size: 0.95rem;
	line-height: 1.2;
	font-weight: 700;
	letter-spacing: -0.01em;
	color: var(--quote-text-body);
}

.route-card__field {
	display: grid;
	gap: 0.35rem;
}

.route-composer__note {
	margin: 0.7rem 0 0;
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: flex-end;
	gap: 0.35rem 0.5rem;
	font-size: 0.78rem;
	line-height: 1.25;
	color: #095866;
}

.route-composer__note-label {
	display: inline-flex;
	align-items: center;
	height: 1.5rem;
	padding: 0 0.65rem;
	border-radius: 999px;
	background: rgba(9, 88, 102, 0.1);
	font-weight: 700;
	white-space: nowrap;
}

.route-composer__note-text {
	font-weight: 600;
}

.route-card__country-chip {
	flex: 0 0 auto;
	min-width: 6.125rem;
	height: 2.125rem;
	padding: 0 1.4rem 0 0.65rem;
	border-radius: var(--quote-small-radius);
	border: 1.5px solid var(--quote-neutral-ring);
	background: #fff;
	color: var(--quote-text-strong);
	font-size: 0.875rem;
	font-weight: 600;
	cursor: pointer;
	appearance: none;
	background-image:
		linear-gradient(45deg, transparent 50%, #999 50%),
		linear-gradient(135deg, #999 50%, transparent 50%);
	background-position:
		calc(100% - 0.88rem) calc(50% - 2px),
		calc(100% - 0.62rem) calc(50% - 2px);
	background-size: 5px 5px, 5px 5px;
	background-repeat: no-repeat;
	transition: border-color 180ms ease, background-color 180ms ease, box-shadow 180ms ease;
}

.route-card__country-chip--static {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	padding-right: 1.4rem;
	cursor: default;
}

.route-card__country-chip:hover {
	background: #f5f6f8;
}

.route-card__country-chip:focus {
	outline: none;
	border-color: rgba(9, 88, 102, 0.25);
	box-shadow: 0 0 0 3px rgba(9, 88, 102, 0.1);
}

.route-card__country-chip:disabled,
.route-card__country-chip--static:hover {
	background: #ffffff;
	box-shadow: none;
}

.route-card__field .input-preventivo-rapido--location,
.package-metric-input,
.quantity-stepper {
	height: 3rem;
	min-height: 3rem;
	border-radius: var(--quote-control-radius);
}

.route-card__field .input-preventivo-rapido--location {
	margin-top: 0;
	padding: 0 0.875rem;
	border: 1.5px solid var(--quote-neutral-ring);
	background: #fff;
	color: var(--quote-text-strong);
	font-size: 1rem;
	font-weight: 600;
	line-height: 1.2;
	box-shadow: none;
	transition: border-color 180ms ease, box-shadow 180ms ease, background-color 180ms ease;
}

.route-card__field .input-preventivo-rapido--location::placeholder {
	color: #aab2bc;
	font-weight: 600;
}

.route-card__field .input-preventivo-rapido--location:focus {
	border-color: rgba(9, 88, 102, 0.25);
	box-shadow: 0 0 0 3px rgba(9, 88, 102, 0.1);
	outline: none;
}

.route-card__input-wrap {
	position: relative;
}

.route-card__input-wrap.is-open .input-preventivo-rapido--location {
	border-color: rgba(9, 88, 102, 0.25);
	box-shadow: 0 0 0 3px rgba(9, 88, 102, 0.08);
}

.location-suggestions-list {
	position: absolute;
	top: calc(100% + 8px);
	left: 0;
	right: 0;
	z-index: 30;
	display: grid;
	gap: 0.15rem;
	padding: 0.32rem;
	border-radius: 0.95rem;
	border: 1px solid var(--quote-neutral-ring);
	background: #fff;
	box-shadow: 0 14px 28px rgba(29, 39, 56, 0.08);
}

.location-suggestion {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 0.75rem;
	padding: 0.7rem 0.85rem;
	border-radius: 0.8rem;
	cursor: pointer;
	transition: background-color 160ms ease;
}

.location-suggestion:hover {
	background: #f5f6f8;
}

.location-suggestion__city {
	min-width: 0;
	font-size: 0.98rem;
	font-weight: 700;
	color: var(--quote-text-strong);
}

.location-suggestion__meta {
	flex: 0 0 auto;
	font-size: 0.82rem;
	font-weight: 700;
	color: #7b8797;
}

.route-card__error,
.package-field-card__error,
.preventivo-inline-error {
	margin-top: 0;
	font-size: 0.8125rem;
	font-weight: 600;
	color: var(--quote-error-fg);
}

.route-card__feedback,
.package-field-card__feedback {
	min-height: 1.35rem;
	display: flex;
	align-items: flex-start;
	padding-top: 0.35rem;
}

.preventivo-inline-error {
	text-align: center;
}

.route-composer__connector {
	display: none;
	align-items: flex-end;
	justify-content: center;
	padding-bottom: 0.8rem;
	color: #7a8190;
}

.package-restriction-note {
	margin: 0 0 0.75rem;
	padding: 0.65rem 0.9rem;
	border-radius: 999px;
	background: rgba(228, 66, 3, 0.08);
	color: #e44203;
	font-size: 0.84rem;
	font-weight: 700;
	text-align: center;
}

.package-entry-list {
	display: grid;
	gap: 0.625rem;
	margin: 0;
	padding: 0;
	list-style: none;
}

.package-entry {
	padding: 0.75rem 0.875rem 0.95rem;
	border-radius: var(--quote-card-radius);
	background: var(--quote-shell-bg);
	box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.04);
}

.package-entry__header {
	display: flex;
	align-items: center;
	justify-content: flex-start;
	gap: 0.75rem;
	margin-bottom: 0.75rem;
}

.package-type-switcher {
	display: inline-flex;
	align-items: center;
	gap: 3px;
	width: fit-content;
	max-width: 100%;
	min-width: 0;
	padding: 3px;
	border-radius: 999px;
	background: var(--quote-selector-shell-bg);
}

.package-entry__header .package-type-switcher {
	flex: 0 0 auto;
	max-width: 100%;
}

.package-type-switcher__button {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 5px;
	flex: 0 0 auto;
	min-height: 36px;
	min-width: 0;
	padding: 0 14px;
	border-radius: 999px;
	border: 0;
	background: transparent;
	color: var(--quote-text-muted);
	font-size: 14px;
	font-weight: 500;
	line-height: 1;
	cursor: pointer;
	transition:
		transform var(--sf-motion-base) var(--sf-ease-soft),
		background-color var(--sf-motion-base) var(--sf-ease-soft),
		color var(--sf-motion-base) var(--sf-ease-soft),
		box-shadow var(--sf-motion-base) var(--sf-ease-soft);
}

.package-type-switcher__button span:last-child {
	white-space: nowrap;
}

.package-type-switcher__button:not(.package-type-switcher__button--active):hover,
.package-type-switcher__button:not(.package-type-switcher__button--active):focus-visible {
	background: var(--quote-selector-hover-bg);
	color: #333;
	transform: translateY(-1px);
	outline: none;
}

.package-type-switcher__button--active {
	background: var(--quote-selector-active-bg);
	color: var(--quote-selector-active-fg);
	font-weight: 700;
	box-shadow: 0 2px 8px rgba(228, 66, 3, 0.25);
}

.package-type-switcher__button--active:hover,
.package-type-switcher__button--active:focus-visible {
	background: var(--quote-selector-active-bg);
	color: var(--quote-selector-active-fg);
	transform: translateY(-1px);
	outline: none;
}

.package-type-switcher__icon-wrap {
	width: 17px;
	height: 17px;
	flex: 0 0 17px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
}

.package-type-switcher__icon-image {
	display: block;
	max-width: 17px;
	max-height: 17px;
	width: auto;
	height: auto;
	object-fit: contain;
	object-position: center;
	filter: none;
	opacity: 1;
	flex-shrink: 0;
	transition: filter var(--sf-motion-fast) var(--sf-ease-soft), transform var(--sf-motion-fast) var(--sf-ease-soft);
}

.package-type-switcher__button--active .package-type-switcher__icon-image {
	filter: var(--quote-active-icon-filter);
}

.package-entry__delete {
	margin-left: auto;
	width: 2.125rem;
	height: 2.125rem;
	min-width: 2.125rem;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	border-radius: 999px;
	border: 0;
	background: transparent;
	color: #c0c5cc;
	cursor: pointer;
	transition:
		background-color var(--sf-motion-base) var(--sf-ease-soft),
		color var(--sf-motion-base) var(--sf-ease-soft),
		transform var(--sf-motion-fast) var(--sf-ease-soft);
}

.package-entry__delete:hover {
	color: #e44203;
	background: var(--quote-error-bg);
	transform: translateY(-1px);
}

.package-entry__delete-icon {
	display: block;
	width: 0.9rem;
	height: auto;
	object-fit: contain;
}

.package-entry__grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 0.5rem;
	align-items: start;
}

.package-field-card {
	padding: 0;
	border: 0;
	background: transparent;
	min-width: 0;
}

.package-field-card--quantity {
	min-width: 0;
}

.package-field-card__label {
	display: block;
	margin-bottom: 0.3125rem;
	font-size: 0.75rem;
	line-height: 1;
	font-weight: 700;
	letter-spacing: 0.3px;
	text-transform: uppercase;
	color: var(--quote-text-subtle);
}

.package-field-card__input-wrap {
	position: relative;
}

.package-field-card__unit {
	position: absolute;
	right: 0.625rem;
	top: 50%;
	transform: translateY(-50%);
	font-size: 0.625rem;
	font-weight: 600;
	color: var(--quote-text-soft);
	pointer-events: none;
}

.package-metric-input {
	width: 100%;
	padding: 0 1.75rem 0 0.625rem;
	border: 1.5px solid var(--quote-neutral-ring);
	background: #fff;
	color: var(--quote-text-strong);
	font-size: 1rem;
	font-weight: 700;
	line-height: 1;
	transition: border-color 180ms ease, box-shadow 180ms ease;
}

.package-metric-input:focus {
	outline: none;
	border-color: rgba(9, 88, 102, 0.25);
	box-shadow: 0 0 0 3px rgba(9, 88, 102, 0.1);
}

.add-package-button-wrapper {
	display: flex;
	justify-content: center;
	margin-top: 0.5rem;
}

.add-package-btn {
	display: inline-flex;
	align-items: center;
	gap: 0.4375rem;
	height: 2.625rem;
	padding: 0 1.375rem;
	border-radius: 999px;
	border: 1.5px solid rgba(9, 88, 102, 0.2);
	background: transparent;
	color: #095866;
	font-size: 0.875rem;
	font-weight: 600;
	transition:
		transform var(--sf-motion-base) var(--sf-ease-soft),
		background-color var(--sf-motion-base) var(--sf-ease-soft),
		border-color var(--sf-motion-base) var(--sf-ease-soft),
		box-shadow var(--sf-motion-base) var(--sf-ease-soft);
	cursor: pointer;
}

.add-package-btn:hover {
	background: rgba(9, 88, 102, 0.06);
	border-color: rgba(9, 88, 102, 0.35);
	box-shadow: 0 10px 20px rgba(9, 88, 102, 0.1);
	transform: translateY(-1px);
}

.quantity-stepper {
	display: flex;
	align-items: center;
	width: 100%;
	border: 1.5px solid var(--quote-neutral-ring);
	background: #fff;
	border-radius: 12px;
	overflow: hidden;
	transition: border-color 180ms ease, box-shadow 180ms ease;
}

.quantity-stepper:focus-within {
	border-color: rgba(9, 88, 102, 0.25);
	box-shadow: 0 0 0 3px rgba(9, 88, 102, 0.1);
}

.quantity-stepper__button {
	width: 30px;
	flex: 0 0 30px;
	height: 100%;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	background: transparent;
	color: #b0b5bd;
	border: 0;
	padding: 0;
	min-width: 0;
	cursor: pointer;
	font-size: 1rem;
	font-weight: 700;
	line-height: 1;
	transition:
		color var(--sf-motion-fast) var(--sf-ease-soft),
		background-color var(--sf-motion-fast) var(--sf-ease-soft),
		transform var(--sf-motion-fast) var(--sf-ease-soft);
}

.quantity-stepper__button:hover,
.quantity-stepper__button:focus-visible {
	color: var(--quote-text-strong);
	background: rgba(9, 88, 102, 0.04);
	outline: none;
}

.quantity-stepper__button:disabled {
	color: #c7ccd4;
	cursor: default;
}

.quantity-stepper__symbol {
	display: inline-block;
	line-height: 1;
}

.quantity-stepper__input {
	height: 100%;
	width: 100%;
	flex: 1 1 auto;
	padding: 0;
	border: 0;
	text-align: center;
	color: var(--quote-text-strong);
	font-size: 1.0625rem;
	font-weight: 800;
	line-height: 1;
	background: transparent;
	appearance: textfield;
	-moz-appearance: textfield;
}

.quantity-stepper__input:focus {
	outline: none;
	box-shadow: none;
}

.quantity-stepper__input[readonly] {
	cursor: default;
}

.quantity-stepper__input::-webkit-outer-spin-button,
.quantity-stepper__input::-webkit-inner-spin-button {
	-webkit-appearance: none;
	margin: 0;
}

.continue-button-wrapper {
	border-radius: 999px;
	background: linear-gradient(135deg, #e44203 0%, #c73600 100%);
	box-shadow: 0 6px 24px rgba(228, 66, 3, 0.22), inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.continue-button-wrapper--sticky {
	position: sticky;
	bottom: calc(env(safe-area-inset-bottom, 0px) + 12px);
	z-index: 5;
}

.continue-cta-button {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 0.875rem;
	padding: 0 0.5rem 0 1.75rem;
	background: transparent;
	border: 0;
	color: inherit;
	text-align: left;
	transition:
		transform var(--sf-motion-base) var(--sf-ease-soft),
		box-shadow var(--sf-motion-base) var(--sf-ease-soft);
}

.continue-cta-button:hover {
	transform: translateY(-2px);
	box-shadow: 0 12px 24px rgba(199, 54, 0, 0.22);
}

.continue-cta-button:focus-visible {
	outline: none;
	box-shadow: inset 0 0 0 3px rgba(255, 255, 255, 0.18);
}

.continue-cta-button__label {
	min-width: 0;
	font-size: 1rem;
	font-weight: 700;
	letter-spacing: -0.02em;
	line-height: 1;
}

.continue-cta-button__tail {
	display: inline-flex;
	align-items: center;
	gap: 0.625rem;
	margin-left: auto;
}

.continue-cta-button__price {
	font-size: 1.25rem;
	font-weight: 800;
	line-height: 1;
	white-space: nowrap;
}

.continue-cta-button__arrow-shell {
	width: 2.625rem;
	height: 2.625rem;
	border-radius: 999px;
	background: rgba(255, 255, 255, 0.2);
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex: 0 0 auto;
}

.continue-cta-button__arrow {
	transition: transform var(--sf-motion-fast) var(--sf-ease-press);
}

.continue-cta-button:hover .continue-cta-button__arrow,
.continue-cta-button:focus-visible .continue-cta-button__arrow {
	transform: translateX(4px);
}

.preventivo-trust-row {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: flex-start;
	gap: 0.875rem;
	margin-top: 0.75rem;
}

.preventivo-trust-pill {
	display: inline-flex;
	align-items: center;
	gap: 0.25rem;
	color: #b0b5bd;
	font-size: 0.72rem;
	font-weight: 600;
}

.package-selector-enter-active,
.package-selector-leave-active,
.dimensions-section-enter-active,
.dimensions-section-leave-active,
.add-package-btn-fade-enter-active,
.add-package-btn-fade-leave-active {
	transition: none;
}

.package-selector-enter-from,
.package-selector-enter-to,
.package-selector-leave-from,
.package-selector-leave-to,
.dimensions-section-enter-from,
.dimensions-section-enter-to,
.dimensions-section-leave-from,
.dimensions-section-leave-to,
.add-package-btn-fade-enter-from,
.add-package-btn-fade-enter-to,
.add-package-btn-fade-leave-from,
.add-package-btn-fade-leave-to {
	opacity: 1;
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

	.route-composer {
		padding: 1.125rem;
	}

	.route-composer__grid {
		grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
		align-items: end;
		gap: 0.75rem;
	}

	.route-composer__note {
		margin-top: 0.6rem;
	}

	.route-composer__connector {
		display: flex;
	}

	.route-card__field .input-preventivo-rapido--location,
	.package-metric-input,
	.quantity-stepper {
		height: 3.125rem;
		min-height: 3.125rem;
	}

	.package-entry {
		padding: 0.8125rem 1rem 1rem;
	}

	.package-entry__header {
		align-items: center;
	}

	.package-type-switcher {
		width: fit-content;
		max-width: 100%;
		min-width: 0;
	}

	.package-type-switcher__button {
		min-height: 36px;
		padding-inline: 18px;
	}

	.package-entry__grid {
		grid-template-columns: 6rem repeat(4, minmax(0, 1fr));
		gap: 0.5rem;
	}

	.package-field-card--quantity {
		max-width: 6rem;
	}

	.continue-cta-button__label {
		font-size: 1.0625rem;
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

	.route-composer {
		padding: 1.125rem 1.25rem;
	}

	.route-composer__grid {
		gap: 0.9rem;
	}

	.package-entry__grid {
		gap: 0.75rem;
	}

	.continue-cta-button {
		padding: 0 0.5rem 0 1.85rem;
	}

	.continue-cta-button__label {
		font-size: 1.0625rem;
	}

	.continue-cta-button__price {
		font-size: 1.4375rem;
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

	.continue-button-wrapper--sticky {
		border-radius: 18px;
		box-shadow: 0 16px 28px rgba(228, 66, 3, 0.22), inset 0 1px 0 rgba(255, 255, 255, 0.1);
	}
}
</style>
