<!--
  FILE: components/checkout/CheckoutBillingSection.vue
  SCOPO: Sezione documento fiscale — ricevuta/fattura, azienda/privato, campi form.
  PARENT: pages/checkout.vue
-->
<script setup>
const props = defineProps({
	fatturazioneType: { type: String, required: true },
	invoiceSubjectType: { type: String, required: true },
	fatturaData: { type: Object, required: true },
	billingShippingFullAddress: { type: String, default: '' },
});

const emit = defineEmits([
	'update:fatturazioneType',
	'update:invoiceSubjectType',
	'update:fatturaData',
]);

/* Proxy per scrivere direttamente nei campi fatturaData senza mutare la prop */
const fd = computed(() => props.fatturaData);
function updateField(field, value) {
	emit('update:fatturaData', { ...props.fatturaData, [field]: value });
}
</script>

<template>
	<div class="checkout-stage-card checkout-stage-card--billing checkout-motion-card" style="--checkout-delay: 140ms;">
		<div class="checkout-panel-head">
			<span class="checkout-panel-head__icon">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8"/><path d="M8 11h8"/><path d="M8 15h5"/></svg>
			</span>
			<div class="checkout-panel-head__copy">
				<p class="checkout-panel-head__title">Documento fiscale</p>
			</div>
		</div>

		<div class="checkout-billing-segment">
			<div class="checkout-billing-pill-row">
				<button
					type="button"
					@click="emit('update:fatturazioneType', 'ricevuta')"
					:class="fatturazioneType === 'ricevuta' ? 'checkout-billing-pill--active' : 'checkout-billing-pill--idle'"
					class="checkout-billing-pill no-radius">
					Ricevuta
				</button>
				<button
					type="button"
					@click="emit('update:fatturazioneType', 'fattura')"
					:class="fatturazioneType === 'fattura' ? 'checkout-billing-pill--active' : 'checkout-billing-pill--idle'"
					class="checkout-billing-pill no-radius">
					Fattura
				</button>
			</div>
		</div>

		<Transition name="payment-panel">
			<div v-if="fatturazioneType === 'fattura'" key="fattura" class="checkout-billing-reveal">
				<div class="checkout-billing-context-note">
					<p class="checkout-billing-context-note__title">Intestazione</p>
					<p class="checkout-billing-context-note__text">Modifica solo se serve.</p>
					<p v-if="billingShippingFullAddress" class="checkout-billing-context-note__prefill">
						Base attuale: {{ billingShippingFullAddress }}
					</p>
				</div>
				<div class="checkout-billing-segment checkout-billing-segment--sub">
					<div class="checkout-billing-subpill-row">
						<button
							type="button"
							@click="emit('update:invoiceSubjectType', 'azienda')"
							:class="invoiceSubjectType === 'azienda' ? 'checkout-billing-subpill--active' : 'checkout-billing-subpill--idle'"
							class="checkout-billing-subpill no-radius">
							Azienda
						</button>
						<button
							type="button"
							@click="emit('update:invoiceSubjectType', 'privato')"
							:class="invoiceSubjectType === 'privato' ? 'checkout-billing-subpill--active' : 'checkout-billing-subpill--idle'"
							class="checkout-billing-subpill no-radius">
							Privato
						</button>
					</div>
				</div>

				<Transition name="payment-panel">
					<div v-if="invoiceSubjectType === 'azienda'" key="azienda" class="checkout-billing-fields">
						<div class="checkout-billing-grid checkout-billing-grid--company-top">
							<div>
								<label class="checkout-billing-label">Ragione Sociale</label>
								<input :value="fd.ragione_sociale" @input="updateField('ragione_sociale', $event.target.value)" type="text" placeholder="SpediamoFacile S.r.l." class="checkout-billing-input" />
							</div>
							<div>
								<label class="checkout-billing-label">Partita IVA</label>
								<input :value="fd.p_iva" @input="updateField('p_iva', $event.target.value)" type="text" placeholder="IT 01234567890" class="checkout-billing-input" />
							</div>
							<div>
								<label class="checkout-billing-label">Codice Fiscale</label>
								<input :value="fd.codice_fiscale" @input="updateField('codice_fiscale', $event.target.value)" type="text" placeholder="01234567890" class="checkout-billing-input" />
							</div>
						</div>

						<div class="checkout-billing-grid checkout-billing-grid--company-mid">
							<div>
								<label class="checkout-billing-label">Codice SDI</label>
								<input :value="fd.codice_sdi" @input="updateField('codice_sdi', $event.target.value)" type="text" maxlength="7" placeholder="XXXXXXX" class="checkout-billing-input" />
							</div>
							<div>
								<label class="checkout-billing-label">PEC (alternativa)</label>
								<input :value="fd.pec" @input="updateField('pec', $event.target.value)" type="email" placeholder="fattura@pec.azienda.it" class="checkout-billing-input" />
							</div>
						</div>

						<div class="checkout-billing-grid checkout-billing-grid--address">
							<div>
								<label class="checkout-billing-label">Indirizzo</label>
								<input :value="fd.indirizzo" @input="updateField('indirizzo', $event.target.value)" type="text" placeholder="Indirizzo" class="checkout-billing-input" />
							</div>
							<div>
								<label class="checkout-billing-label">Città</label>
								<input :value="fd.city" @input="updateField('city', $event.target.value)" type="text" placeholder="Città" class="checkout-billing-input" />
							</div>
							<div>
								<label class="checkout-billing-label">Prov.</label>
								<input :value="fd.province" @input="updateField('province', $event.target.value)" type="text" maxlength="2" placeholder="Prov." class="checkout-billing-input" />
							</div>
							<div>
								<label class="checkout-billing-label">CAP</label>
								<input :value="fd.postal_code" @input="updateField('postal_code', $event.target.value)" type="text" maxlength="10" placeholder="CAP" class="checkout-billing-input" />
							</div>
						</div>
					</div>

					<div v-else key="privato" class="checkout-billing-fields">
						<div class="checkout-billing-grid checkout-billing-grid--private-top">
							<div>
								<label class="checkout-billing-label">Nome completo</label>
								<input :value="fd.nome_completo" @input="updateField('nome_completo', $event.target.value)" type="text" placeholder="Nome e Cognome" class="checkout-billing-input" />
							</div>
							<div>
								<label class="checkout-billing-label">Codice Fiscale</label>
								<input :value="fd.codice_fiscale" @input="updateField('codice_fiscale', $event.target.value)" type="text" placeholder="Codice Fiscale" class="checkout-billing-input" />
							</div>
						</div>

						<div class="checkout-billing-grid checkout-billing-grid--address">
							<div>
								<label class="checkout-billing-label">Indirizzo</label>
								<input :value="fd.indirizzo" @input="updateField('indirizzo', $event.target.value)" type="text" placeholder="Indirizzo" class="checkout-billing-input" />
							</div>
							<div>
								<label class="checkout-billing-label">Città</label>
								<input :value="fd.city" @input="updateField('city', $event.target.value)" type="text" placeholder="Città" class="checkout-billing-input" />
							</div>
							<div>
								<label class="checkout-billing-label">Prov.</label>
								<input :value="fd.province" @input="updateField('province', $event.target.value)" type="text" maxlength="2" placeholder="Prov." class="checkout-billing-input" />
							</div>
							<div>
								<label class="checkout-billing-label">CAP</label>
								<input :value="fd.postal_code" @input="updateField('postal_code', $event.target.value)" type="text" maxlength="10" placeholder="CAP" class="checkout-billing-input" />
							</div>
						</div>
					</div>
				</Transition>
			</div>
			<div v-else key="ricevuta" class="checkout-billing-receipt-note">
				<p>Usiamo i dati del checkout.</p>
			</div>
		</Transition>
	</div>
</template>

<style scoped>
@keyframes checkout-fade-up {
	from { opacity: 0; transform: translateY(18px); }
	to { opacity: 1; transform: translateY(0); }
}

.checkout-motion-card {
	animation: checkout-fade-up 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
	animation-delay: var(--checkout-delay, 0ms);
}

.payment-panel-enter-active,
.payment-panel-leave-active {
	transition: opacity 0.2s cubic-bezier(0.22, 1, 0.36, 1), transform 0.2s cubic-bezier(0.22, 1, 0.36, 1);
}

.payment-panel-enter-from,
.payment-panel-leave-to {
	opacity: 0;
	transform: translateY(8px);
}

.checkout-stage-card {
	background: #fff;
	border: 1px solid #e8eef1;
	box-shadow: 0 14px 34px rgba(24, 39, 75, 0.06);
}

.checkout-stage-card--billing {
	padding: 22px 24px;
	border-radius: 18px;
}

.checkout-panel-head {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	margin-bottom: 14px;
}

.checkout-panel-head__icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 42px;
	height: 42px;
	border-radius: 12px;
	background: #f3f6f7;
	color: #095866;
	flex-shrink: 0;
}

.checkout-panel-head__copy { min-width: 0; }

.checkout-panel-head__title {
	font-size: 1.1875rem;
	font-weight: 700;
	line-height: 1.05;
	color: #252b42;
}

.checkout-billing-segment {
	display: flex;
	padding: 4px;
	border-radius: 16px;
	border: 1px solid #edf1f4;
	background: #f7fafb;
}

.checkout-billing-segment--sub {
	padding: 3px;
	border-radius: 14px;
	background: #fbfcfd;
}

.checkout-billing-pill-row,
.checkout-billing-subpill-row {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	width: 100%;
}

.checkout-billing-subpill-row {
	margin-top: 2px;
}

.checkout-billing-pill,
.checkout-billing-subpill {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex: 1 1 0;
	min-height: 40px;
	padding: 0 18px;
	border-radius: 11px;
	border: 1px solid #e9edf1;
	background: #fff;
	font-size: 0.9375rem;
	font-weight: 600;
	color: #4e5869;
	cursor: pointer;
	transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.35s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.35s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.35s cubic-bezier(0.4, 0, 0.2, 1), color 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.checkout-billing-pill:hover,
.checkout-billing-subpill:hover {
	transform: translateY(-1px);
}

.checkout-billing-pill--active {
	border-color: #095866;
	background: rgba(9, 88, 102, 0.04);
	color: #095866;
	box-shadow: 0 10px 20px rgba(9, 88, 102, 0.08);
}

.checkout-billing-pill--idle {
	color: #596172;
	background: #fff;
}

.checkout-billing-subpill {
	min-height: 36px;
	padding: 0 14px;
	font-size: 0.875rem;
	border-radius: 10px;
}

.checkout-billing-subpill--active {
	border-color: #095866;
	background: #095866;
	color: #fff;
	box-shadow: 0 10px 18px rgba(9, 88, 102, 0.16);
}

.checkout-billing-subpill--idle {
	background: #fff;
	color: #596172;
}

.checkout-billing-context-note {
	padding: 12px 14px;
	border-radius: 14px;
	border: 1px solid #e8eef1;
	background: #fbfcfd;
}

.checkout-billing-context-note__title {
	font-size: 0.875rem;
	font-weight: 700;
	color: #252b42;
}

.checkout-billing-context-note__text {
	margin-top: 4px;
	font-size: 0.8125rem;
	line-height: 1.55;
	color: #667085;
}

.checkout-billing-context-note__prefill {
	margin-top: 8px;
	font-size: 0.8125rem;
	line-height: 1.45;
	font-weight: 600;
	color: #095866;
}

.checkout-billing-reveal {
	margin-top: 14px;
	display: flex;
	flex-direction: column;
	gap: 14px;
}

.checkout-billing-fields {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.checkout-billing-grid {
	display: grid;
	gap: 10px;
}

.checkout-billing-grid--company-top {
	grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr) minmax(0, 1fr);
}

.checkout-billing-grid--company-mid {
	grid-template-columns: repeat(2, minmax(0, 1fr));
}

.checkout-billing-grid--private-top {
	grid-template-columns: repeat(2, minmax(0, 1fr));
}

.checkout-billing-grid--address {
	grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr) 80px 100px;
}

.checkout-billing-label {
	display: block;
	margin-bottom: 4px;
	font-size: 0.8125rem;
	font-weight: 600;
	color: #4e5869;
}

.checkout-billing-input {
	width: 100%;
	height: 46px;
	padding: 0 14px;
	border-radius: 12px;
	border: 1px solid transparent;
	background: #f8f9fa;
	color: #252b42;
	font-size: 0.9375rem;
	transition: border-color 0.25s ease, box-shadow 0.25s ease, background-color 0.25s ease;
}

.checkout-billing-input::placeholder {
	color: #a0a5ab;
}

.checkout-billing-input:focus {
	outline: none;
	border-color: #095866;
	box-shadow: inset 0 0 0 1px #095866;
	background: #fff;
}

.checkout-billing-receipt-note {
	margin-top: 14px;
	padding: 12px 14px;
	border-radius: 14px;
	background: #f7f9fb;
	color: #667085;
	font-size: 0.875rem;
	line-height: 1.55;
}

@media (max-width: 1279px) {
	.checkout-billing-grid--company-mid,
	.checkout-billing-grid--private-top {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}

	.checkout-billing-grid--company-top {
		grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr);
	}

	.checkout-billing-grid--company-top > :nth-child(3) {
		grid-column: 1 / -1;
	}

	.checkout-billing-grid--address {
		grid-template-columns: minmax(0, 1.7fr) minmax(0, 1.15fr) 88px 96px;
	}
}

@media (max-width: 767px) {
	.checkout-stage-card--billing {
		padding: 18px 14px;
		border-radius: 16px;
	}

	.checkout-panel-head {
		align-items: center;
		margin-bottom: 12px;
		gap: 10px;
	}

	.checkout-panel-head__icon {
		width: 38px;
		height: 38px;
		border-radius: 11px;
	}

	.checkout-panel-head__title {
		font-size: 1.0625rem;
	}

	.checkout-billing-grid--company-top,
	.checkout-billing-grid--company-mid,
	.checkout-billing-grid--private-top,
	.checkout-billing-grid--address {
		grid-template-columns: 1fr;
	}

	.checkout-billing-context-note {
		padding: 11px 12px;
	}
}
</style>
