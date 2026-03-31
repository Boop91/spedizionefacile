<!--
  FILE: components/checkout/CheckoutPaymentMethods.vue
  SCOPO: Selettore metodo di pagamento (carta/wallet/bonifico), form carta, carta salvata.
  PARENT: pages/checkout.vue
-->
<script setup>
defineProps({
	paymentMethod: { type: String, required: true },
	paymentMethodOptions: { type: Array, required: true },
	cardPaymentsUnavailable: { type: Boolean, default: false },
	cardPaymentsNotice: { type: String, default: '' },
	hasSavedCard: { type: Boolean, default: false },
	defaultPayment: { type: Object, default: null },
	useNewCard: { type: Boolean, default: true },
	shouldShowCardForm: { type: Boolean, default: false },
	stripeLoading: { type: Boolean, default: false },
	cardError: { type: String, default: '' },
	saveCardForFuture: { type: Boolean, default: false },
	walletFormatted: { type: String, default: '0,00 €' },
	walletLoaded: { type: Boolean, default: false },
	walletSufficient: { type: Boolean, default: false },
});

const emit = defineEmits([
	'select-payment-method',
	'update:useNewCard',
	'update:saveCardForFuture',
	'card-element-ref',
]);

/* Template ref for Stripe card element — emits to parent when DOM element appears/disappears */
const internalCardRef = ref(null);
watch(internalCardRef, (el) => emit('card-element-ref', el), { flush: 'post' });
</script>

<template>
	<div class="checkout-stage-card checkout-stage-card--payment checkout-motion-card" style="--checkout-delay: 80ms;">
		<div class="checkout-panel-head">
			<span class="checkout-panel-head__icon">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
			</span>
			<div class="checkout-panel-head__copy">
				<p class="checkout-panel-head__title">Metodo di pagamento</p>
				<p class="checkout-panel-head__text">Scegli come pagare.</p>
			</div>
		</div>

		<div class="checkout-payment-options-grid">
			<button
				v-for="option in paymentMethodOptions"
				:key="option.key"
				type="button"
				@click="emit('select-payment-method', option.key)"
				:disabled="option.key === 'carta' && cardPaymentsUnavailable"
				:class="[
					'checkout-payment-option no-radius',
					paymentMethod === option.key ? 'checkout-payment-option--active' : 'checkout-payment-option--idle',
					option.key === 'carta' && cardPaymentsUnavailable ? 'checkout-payment-option--disabled' : '',
				]">
				<span v-if="option.badge" class="checkout-payment-option__badge">{{ option.badge }}</span>
				<span class="checkout-payment-option__icon-shell">
					<svg v-if="option.key === 'carta'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
					<svg v-else-if="option.key === 'bonifico'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10h18"/><path d="M5 10V7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v3"/><rect x="4" y="10" width="16" height="9" rx="2"/><path d="M8 14h2"/><path d="M14 14h2"/></svg>
					<svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
				</span>
				<span class="checkout-payment-option__copy">
					<span class="checkout-payment-option__title">{{ option.title }}</span>
					<span class="checkout-payment-option__text">{{ option.description }}</span>
				</span>
			</button>
		</div>

		<div v-if="cardPaymentsUnavailable" class="checkout-payment-notice">
			{{ cardPaymentsNotice }}
		</div>

		<div class="payment-panel-shell checkout-payment-panel" :data-payment-method="paymentMethod">
			<div v-if="paymentMethod === 'carta' && !cardPaymentsUnavailable" class="space-y-[14px]">
				<div class="checkout-payment-choice-stack">
					<button
						v-if="hasSavedCard"
						type="button"
						@click="emit('update:useNewCard', false)"
						:class="['checkout-payment-choice no-radius', !useNewCard ? 'checkout-payment-choice--selected' : 'checkout-payment-choice--idle']">
						<span class="checkout-payment-choice__brand">{{ defaultPayment.card.brand?.toUpperCase() }}</span>
						<div class="checkout-payment-choice__copy">
							<p class="checkout-payment-choice__eyebrow">Carta salvata</p>
							<p class="checkout-payment-choice__title">&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; {{ defaultPayment.card.last4 }}</p>
							<p class="checkout-payment-choice__text">Scade {{ defaultPayment.card.exp_month }}/{{ defaultPayment.card.exp_year }}</p>
						</div>
						<span :class="['checkout-payment-choice__radio', !useNewCard ? 'checkout-payment-choice__radio--selected' : '']"></span>
					</button>

					<div
						role="button"
						tabindex="0"
						@click="emit('update:useNewCard', true)"
						@keydown.enter.prevent="emit('update:useNewCard', true)"
						@keydown.space.prevent="emit('update:useNewCard', true)"
						:class="[
							'checkout-payment-choice checkout-payment-choice--expandable no-radius',
							(!hasSavedCard || useNewCard) ? 'checkout-payment-choice--selected' : 'checkout-payment-choice--idle'
						]">
						<div class="checkout-payment-choice__header">
							<span class="checkout-payment-choice__icon-shell">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
							</span>
							<div class="checkout-payment-choice__copy">
								<p class="checkout-payment-choice__title">Usa una nuova carta</p>
								<p class="checkout-payment-choice__text">Inserisci una carta diversa per questo pagamento.</p>
							</div>
							<span :class="['checkout-payment-choice__radio', (!hasSavedCard || useNewCard) ? 'checkout-payment-choice__radio--selected' : '']"></span>
						</div>

						<Transition name="payment-panel">
							<div v-if="shouldShowCardForm" class="checkout-payment-card-form checkout-payment-card-form--embedded">
								<div class="checkout-payment-card-form__head">
									<div class="checkout-payment-card-form__intro">
										<p class="checkout-payment-card-form__text">Inserisci la carta qui.</p>
									</div>
								</div>

								<div id="card-element" ref="internalCardRef" class="checkout-payment-card-form__element"></div>
								<p v-if="stripeLoading" class="checkout-payment-card-form__helper">Preparazione del modulo carta in corso...</p>
								<p v-if="cardError" class="checkout-payment-card-form__error">{{ cardError }}</p>
								<label class="checkout-payment-card-form__save" @click.stop>
									<input type="checkbox" :checked="saveCardForFuture" @change="emit('update:saveCardForFuture', $event.target.checked)" class="checkout-payment-card-form__checkbox" />
									<span>Salva per i prossimi pagamenti</span>
								</label>
							</div>
						</Transition>
					</div>
				</div>
			</div>

			<div v-else-if="paymentMethod === 'bonifico'" class="checkout-payment-alt">
				<p class="checkout-payment-alt__title">Pagamento tramite bonifico</p>
				<p class="checkout-payment-alt__text">Riceverai via email le coordinate bancarie appena confermi l'ordine. L'attivazione avviene alla ricezione del bonifico.</p>
			</div>

			<div v-else-if="paymentMethod === 'wallet'" class="checkout-payment-alt">
				<p class="checkout-payment-alt__title">Pagamento tramite Wallet</p>
				<p class="checkout-payment-alt__text">Saldo disponibile: <span class="font-semibold text-[#095866]">{{ walletFormatted }}</span></p>
				<p v-if="walletLoaded && !walletSufficient" class="checkout-payment-alt__error">Saldo insufficiente. Ricarica il wallet per procedere.</p>
				<p v-else-if="walletLoaded" class="checkout-payment-alt__success">Saldo sufficiente per completare il pagamento.</p>
			</div>
		</div>
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

.payment-panel-shell {
	min-height: 0;
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

.checkout-stage-card--payment {
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

.checkout-panel-head__text {
	margin-top: 4px;
	font-size: 0.84375rem;
	line-height: 1.6;
	color: #6b7280;
}

.checkout-payment-options-grid {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: 10px;
}

.checkout-payment-option {
	position: relative;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 10px;
	min-height: 122px;
	padding: 16px 10px;
	border-radius: 16px;
	border: 1px solid #ebedf0;
	background: #fafafa;
	text-align: center;
	transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.35s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.35s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.35s cubic-bezier(0.4, 0, 0.2, 1), color 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.checkout-payment-option:not(:disabled):hover {
	transform: translateY(-2px);
	border-color: #d7e5e8;
	box-shadow: 0 12px 26px rgba(14, 42, 71, 0.08);
}

.checkout-payment-option--active {
	border-color: #095866;
	background: rgba(9, 88, 102, 0.04);
	box-shadow: 0 2px 10px rgba(9, 88, 102, 0.12);
}

.checkout-payment-option--disabled {
	opacity: 0.5;
	cursor: not-allowed;
}

.checkout-payment-option__badge {
	position: absolute;
	top: -10px;
	left: 12px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-height: 24px;
	padding: 0 10px;
	border-radius: 999px;
	background: #095866;
	color: #fff;
	font-size: 0.6875rem;
	font-weight: 700;
	line-height: 1;
	box-shadow: 0 8px 18px rgba(9, 88, 102, 0.2);
}

.checkout-payment-option__icon-shell {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 40px;
	height: 40px;
	border-radius: 12px;
	border: 1px solid #edf0f2;
	background: #fff;
	color: #7d8492;
	transition: background-color 0.35s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.35s cubic-bezier(0.4, 0, 0.2, 1), color 0.35s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.checkout-payment-option--active .checkout-payment-option__icon-shell {
	background: #095866;
	border-color: transparent;
	color: #fff;
	box-shadow: 0 10px 18px rgba(9, 88, 102, 0.18);
}

.checkout-payment-option__copy {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.checkout-payment-option__title {
	font-size: 0.9375rem;
	font-weight: 700;
	line-height: 1.25;
	color: #252b42;
}

.checkout-payment-option__text {
	font-size: 0.8125rem;
	line-height: 1.45;
	color: #7a8090;
}

.checkout-payment-notice {
	margin-top: 18px;
	padding: 14px 16px;
	border-radius: 14px;
	border: 1px solid #fde6b8;
	background: #fff8e7;
	font-size: 0.875rem;
	line-height: 1.55;
	color: #8f5b00;
}

.checkout-payment-panel {
	margin-top: 14px;
}

.checkout-payment-choice-stack {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.checkout-payment-choice {
	display: flex;
	align-items: center;
	gap: 12px;
	width: 100%;
	padding: 14px 16px;
	border-radius: 16px;
	border: 1px solid #ebedf0;
	background: #fff;
	text-align: left;
	transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.35s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.35s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.checkout-payment-choice:hover {
	transform: translateY(-1px);
	box-shadow: 0 10px 24px rgba(18, 35, 56, 0.06);
}

.checkout-payment-choice__header {
	display: flex;
	align-items: center;
	gap: 12px;
	width: 100%;
}

.checkout-payment-choice--expandable {
	display: flex;
	flex-direction: column;
	align-items: stretch;
	gap: 0;
	cursor: pointer;
}

.checkout-payment-choice--selected {
	border-color: #095866;
	background: rgba(9, 88, 102, 0.03);
	box-shadow: 0 10px 24px rgba(9, 88, 102, 0.09);
}

.checkout-payment-choice__brand {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 48px;
	height: 32px;
	padding: 0 12px;
	border-radius: 10px;
	background: #252b42;
	color: #fff;
	font-size: 0.8125rem;
	font-weight: 800;
	letter-spacing: 0.04em;
	flex-shrink: 0;
}

.checkout-payment-choice__icon-shell {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 36px;
	height: 36px;
	border-radius: 12px;
	background: #f1f3f6;
	color: #7a8090;
	flex-shrink: 0;
}

.checkout-payment-choice__copy {
	flex: 1;
	min-width: 0;
}

.checkout-payment-choice__eyebrow {
	font-size: 0.75rem;
	font-weight: 700;
	letter-spacing: 0.02em;
	text-transform: uppercase;
	color: #7a8090;
}

.checkout-payment-choice__title {
	font-size: 1rem;
	font-weight: 700;
	line-height: 1.25;
	color: #252b42;
}

.checkout-payment-choice__text {
	margin-top: 4px;
	font-size: 0.875rem;
	line-height: 1.45;
	color: #7a8090;
}

.checkout-payment-choice__radio {
	position: relative;
	display: inline-flex;
	width: 24px;
	height: 24px;
	border-radius: 999px;
	border: 1px solid #d9e0e4;
	background: #fff;
	flex-shrink: 0;
}

.checkout-payment-choice__radio::after {
	content: '';
	position: absolute;
	inset: 5px;
	border-radius: 999px;
	background: #095866;
	transform: scale(0);
	transition: transform 0.24s ease;
}

.checkout-payment-choice__radio--selected::after {
	transform: scale(1);
}

.checkout-payment-card-form {
	margin-top: 12px;
	padding: 16px;
	border-radius: 16px;
	border: 1px solid #e8eef1;
	background: #fff;
	box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
}

.checkout-payment-card-form--embedded {
	margin-top: 12px;
	padding: 12px 0 0;
	border: 0;
	border-top: 1px solid rgba(9, 88, 102, 0.12);
	border-radius: 0;
	background: transparent;
	box-shadow: none;
}

.checkout-payment-card-form__head {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16px;
	margin-bottom: 12px;
}

.checkout-payment-card-form__intro { min-width: 0; }

.checkout-payment-card-form__text {
	margin-top: 4px;
	font-size: 0.8125rem;
	line-height: 1.5;
	color: #7a8090;
}

.checkout-payment-card-form__save {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	font-size: 0.8125rem;
	line-height: 1.35;
	color: #4e5869;
	cursor: pointer;
	margin-top: 12px;
}

.checkout-payment-card-form__checkbox {
	width: 16px;
	height: 16px;
	accent-color: #095866;
	cursor: pointer;
}

.checkout-payment-card-form__element {
	min-height: 52px;
	padding: 14px 12px;
	border-radius: 14px;
	border: 1px solid #d7e1e4;
	background: #fff;
}

.checkout-payment-card-form__helper {
	margin-top: 10px;
	font-size: 0.75rem;
	color: #7a8090;
}

.checkout-payment-card-form__error {
	margin-top: 10px;
	font-size: 0.75rem;
	color: #dc2626;
}

.checkout-payment-alt {
	padding: 4px 0 0;
}

.checkout-payment-alt__title {
	font-size: 0.9375rem;
	font-weight: 700;
	color: #252b42;
}

.checkout-payment-alt__text {
	margin-top: 6px;
	font-size: 0.875rem;
	line-height: 1.6;
	color: #5c6676;
}

.checkout-payment-alt__error {
	margin-top: 10px;
	font-size: 0.875rem;
	font-weight: 600;
	color: #dc2626;
}

.checkout-payment-alt__success {
	margin-top: 10px;
	font-size: 0.875rem;
	font-weight: 600;
	color: #059669;
}

@media (max-width: 1279px) {
	.checkout-payment-options-grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}

@media (max-width: 767px) {
	.checkout-stage-card--payment {
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

	.checkout-panel-head__text {
		font-size: 0.8rem;
	}

	.checkout-payment-options-grid {
		grid-template-columns: 1fr;
		gap: 8px;
	}

	.checkout-payment-option {
		min-height: 108px;
		padding: 14px 10px;
	}

	.checkout-payment-choice {
		padding: 13px 14px;
		border-radius: 14px;
	}

	.checkout-payment-card-form {
		padding: 14px;
	}

	.checkout-payment-card-form__head {
		margin-bottom: 8px;
	}
}
</style>
