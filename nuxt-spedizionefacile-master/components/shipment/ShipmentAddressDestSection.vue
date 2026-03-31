<!--
  FILE: components/shipment/ShipmentAddressDestSection.vue
  SCOPO: Sezione indirizzo destinazione con dropdown spedizioni configurate,
         indirizzi salvati, alert PUDO e form fields.
-->
<script setup>
defineProps({
	deliveryMode: { type: String, required: true },
	destinationAddress: { type: Object, required: true },
	userStore: { type: Object, required: true },
	canSaveDestAddress: { type: Boolean, default: false },
	savingDestAddress: { type: Boolean, default: false },
	destSaveSuccess: { type: Boolean, default: false },
	saveAddressToBook: { type: Function, required: true },
	isAuthenticated: { type: Boolean, default: false },
	loadingConfigs: { type: Boolean, default: false },
	showDefaultDropdown: { type: Boolean, default: false },
	showDefaultDropdownTarget: { type: String, default: '' },
	savedConfigs: { type: Array, default: () => [] },
	showDestConfigGuestPrompt: { type: Boolean, default: false },
	showDestAddressSelector: { type: Boolean, default: false },
	showDestGuestPrompt: { type: Boolean, default: false },
	loadingSavedAddresses: { type: Boolean, default: false },
	savedAddresses: { type: Array, default: () => [] },
	destinationSectionHint: { type: String, default: '' },
	routeWarningMessage: { type: String, default: '' },
	loadSavedConfigs: { type: Function, required: true },
	applyConfig: { type: Function, required: true },
	toggleAddressSelectorWithDefaultClose: { type: Function, required: true },
	applySavedAddress: { type: Function, required: true },
	openShipmentAuthModal: { type: Function, required: true },
	// AddressFormFields props pass-through
	fieldClass: { type: Function, required: true },
	getFieldError: { type: Function, required: true },
	fieldErrorText: { type: Function, required: true },
	getFieldAssist: { type: Function, required: true },
	applyFieldAssist: { type: Function, required: true },
	smartBlur: { type: Function, required: true },
	onNameInput: { type: Function, required: true },
	onCityInput: { type: Function, required: true },
	onCityFocus: { type: Function, required: true },
	onProvinciaInput: { type: Function, required: true },
	onProvinceFocus: { type: Function, required: true },
	onCapInput: { type: Function, required: true },
	onCapFocus: { type: Function, required: true },
	onTelefonoInput: { type: Function, required: true },
	selectCity: { type: Function, required: true },
	selectProvincia: { type: Function, required: true },
	selectCap: { type: Function, required: true },
	formatCitySuggestionLabel: { type: Function, required: true },
	formatCapSuggestionLabel: { type: Function, required: true },
	sv: { type: Object, required: true },
	destCitySuggestions: { type: Array, default: () => [] },
	destProvinceSuggestions: { type: Array, default: () => [] },
	destCapSuggestions: { type: Array, default: () => [] },
});

const destDefaultDropdownRef = ref(null);
const destSelectorRef = ref(null);

defineExpose({ destDefaultDropdownRef, destSelectorRef });
</script>

<template>
	<div class="bg-[#E4E4E4] rounded-[16px] text-[#252B42] mt-[20px] px-[16px] tablet:px-[40px] pt-[24px] tablet:pt-[35px] pb-[24px] tablet:pb-[43px]">
		<div class="flex items-center justify-between mb-[20px] tablet:mb-[39px]">
			<div class="flex items-center gap-[10px]">
				<h2 class="font-bold text-[1.125rem] tracking-[0.1px]">
					{{ deliveryMode === 'pudo' ? 'Destinazione (Punto BRT)' : 'Destinazione' }}
				</h2>
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
				<!-- Spedizioni configurate dest -->
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
				<!-- Indirizzi salvati dest -->
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

		<!-- PUDO alerts -->
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
</template>

<style scoped>
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
</style>
