<!--
  FILE: components/shipment/ShipmentRegularServicesGrid.vue
  SCOPO: Griglia servizi regolari con pannelli inline (contrassegno, assicurazione),
         campo contenuto pacco e toggle notifiche SMS/Email.
-->
<script setup>
const props = defineProps({
	regularServices: { type: Array, required: true },
	serviceIconFilterIdle: { type: String, required: true },
	serviceIconFilterActive: { type: String, required: true },
	isServiceExpanded: { type: Function, required: true },
	isServiceSelected: { type: Function, required: true },
	canConfigureService: { type: Function, required: true },
	shouldShowServiceToggle: { type: Function, required: true },
	shouldShowConfigureButton: { type: Function, required: true },
	canActivateConfiguredService: { type: Function, required: true },
	getServiceStateLabel: { type: Function, required: true },
	getServiceConfigureLabel: { type: Function, required: true },
	handleServicePrimaryAction: { type: Function, required: true },
	toggleRegularService: { type: Function, required: true },
	serviceData: { type: Object, required: true },
	serviceCardErrors: { type: Object, required: true },
	normalizeCurrencyInput: { type: Function, required: true },
	contrassegnoIncassoOptions: { type: Array, required: true },
	contrassegnoRimborsoOptions: { type: Array, required: true },
	requiresContrassegnoDettaglio: { type: Boolean, required: true },
	insurancePackages: { type: Array, required: true },
	contentError: { type: [String, Object], default: null },
	contentFieldHint: { type: String, default: '' },
	userStore: { type: Object, required: true },
	smsEmailNotification: { type: Boolean, required: true },
	notificationPriceLabel: { type: String, default: '' },
});

const emit = defineEmits([
	'update:smsEmailNotification',
	'update:contentError',
	'activate-configured-service',
]);

const activateConfiguredService = (service) => {
	emit('activate-configured-service', service);
};
</script>

<template>
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
								'--service-icon-filter': service.isSelected ? serviceIconFilterActive : serviceIconFilterIdle,
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
					<!-- Contrassegno -->
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
									<span class="service-inline-field__suffix">&euro;</span>
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
							<div class="service-inline-choice-wrap" role="group" aria-label="Modalita incasso contrassegno">
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
							<div class="service-inline-choice-wrap" role="group" aria-label="Modalita rimborso contrassegno">
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

					<!-- Assicurazione -->
					<div v-else-if="service.name === 'Assicurazione'" class="service-inline-panel">
						<div class="service-inline-insurance-list">
							<div
								v-for="(pack, indexPopup) in insurancePackages"
								:key="`${service.name}-${indexPopup}`"
								class="service-inline-insurance-card">
								<div class="service-inline-insurance-card__head">
									<span class="service-inline-insurance-card__title">Collo {{ indexPopup + 1 }}</span>
									<span class="service-inline-insurance-card__meta">
										{{ pack.weight || '0' }} kg &middot; {{ pack.first_size || '0' }}&times;{{ pack.second_size || '0' }}&times;{{ pack.third_size || '0' }} cm
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
									<span class="service-inline-field__suffix">&euro;</span>
								</div>
								<p v-if="serviceCardErrors.assicurazione[indexPopup]" class="service-inline-field__error">{{ serviceCardErrors.assicurazione[indexPopup] }}</p>
							</div>
						</div>
					</div>

					<!-- Actions -->
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

	<!-- Content description + SMS notification -->
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
				@input="$emit('update:contentError', null)"
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
							:checked="smsEmailNotification"
							@change="$emit('update:smsEmailNotification', $event.target.checked)"
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
</template>

<style scoped>
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

.service-card-tile--selected .service-card-tile__icon {
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

/* Service inline panel */
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

/* Support grid */
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

/* Notification card */
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

@media (max-width: 44.99rem) {
	.service-support-field__notification-card {
		grid-template-columns: minmax(0, 1fr) auto;
		row-gap: 10px;
	}

	.service-support-field__notification-side {
		justify-self: end;
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
