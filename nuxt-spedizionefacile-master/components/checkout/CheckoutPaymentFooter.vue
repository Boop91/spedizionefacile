<!--
  FILE: components/checkout/CheckoutPaymentFooter.vue
  SCOPO: Pulsante paga, checkbox termini, errori e progress display.
  PARENT: pages/checkout.vue
-->
<script setup>
defineProps({
	finalTotalFormatted: { type: String, required: true },
	paymentMethod: { type: String, required: true },
	paymentActionLabel: { type: String, required: true },
	payButtonTooltip: { type: String, default: '' },
	canPay: { type: Boolean, default: false },
	isProcessing: { type: Boolean, default: false },
	paymentError: { type: String, default: '' },
	paymentStep: { type: String, default: '' },
	termsAccepted: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm-payment', 'update:termsAccepted']);
</script>

<template>
	<div>
		<div class="checkout-payment-footer checkout-motion-card" style="--checkout-delay: 200ms;">
			<div class="checkout-payment-footer__summary">
				<div class="checkout-payment-footer__summary-copy">
					<p class="checkout-payment-footer__summary-label">Totale da pagare</p>
					<p class="checkout-payment-footer__summary-value">{{ finalTotalFormatted }}</p>
				</div>
				<span class="checkout-payment-footer__summary-chip">{{ paymentMethod === 'bonifico' ? 'Pagamento differito' : 'Pagamento sicuro' }}</span>
			</div>

			<button type="button" @click="emit('confirm-payment')" :disabled="!canPay"
				:class="['checkout-payment-submit', canPay ? 'checkout-payment-submit--active' : 'checkout-payment-submit--disabled']">
				<svg v-if="isProcessing" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg>
				<svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
				<span>{{ paymentActionLabel }}</span>
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
			</button>

			<div class="checkout-payment-footer__support">
				<label class="checkout-payment-footer__terms">
					<input type="checkbox" :checked="termsAccepted" @change="emit('update:termsAccepted', $event.target.checked)" class="checkout-payment-footer__checkbox" />
					<span>
						Ho letto e accetto i
						<NuxtLink to="/termini-condizioni" class="checkout-payment-footer__terms-link">Termini e condizioni</NuxtLink>
					</span>
				</label>
				<p class="checkout-payment-footer__hint">{{ payButtonTooltip || 'Controlla i dati e conferma.' }}</p>
			</div>
		</div>

		<div class="checkout-payment-status">
			<p v-if="paymentError" class="checkout-payment-status__error">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
				<span>{{ paymentError }}</span>
			</p>

			<div v-if="isProcessing && paymentStep" class="checkout-payment-status__progress">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 animate-spin"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg>
				<span>{{ paymentStep }}</span>
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

.checkout-payment-footer {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(292px, 340px);
	grid-template-areas:
		"summary submit"
		"support submit";
	align-items: start;
	gap: 14px 18px;
	padding: 18px 20px;
	border-radius: 18px;
	border: 1px solid #e5eaec;
	background: #fff;
	box-shadow: 0 14px 34px rgba(24, 39, 75, 0.06);
}

.checkout-payment-footer__summary {
	grid-area: summary;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	min-height: 86px;
	padding: 10px 12px;
	border-radius: 14px;
	background: linear-gradient(135deg, rgba(9, 88, 102, 0.06), rgba(228, 66, 3, 0.06));
	border: 1px solid rgba(9, 88, 102, 0.08);
}

.checkout-payment-footer__summary-copy {
	display: flex;
	flex-direction: column;
	gap: 2px;
	min-width: 0;
}

.checkout-payment-footer__summary-label {
	font-size: 0.75rem;
	font-weight: 700;
	letter-spacing: 0.04em;
	text-transform: uppercase;
	color: #6b7280;
}

.checkout-payment-footer__summary-value {
	margin-top: 2px;
	font-size: 1.3125rem;
	font-weight: 800;
	line-height: 1;
	color: #252b42;
}

.checkout-payment-footer__summary-chip {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-height: 28px;
	padding: 0 10px;
	border-radius: 999px;
	background: rgba(255, 255, 255, 0.82);
	border: 1px solid rgba(9, 88, 102, 0.1);
	font-size: 0.75rem;
	font-weight: 700;
	color: #095866;
	text-align: center;
}

.checkout-payment-footer__support {
	grid-area: support;
	display: flex;
	flex-direction: column;
	gap: 6px;
	padding: 2px 2px 0;
	max-width: 560px;
}

.checkout-payment-footer__terms {
	display: inline-flex;
	align-items: flex-start;
	gap: 10px;
	cursor: pointer;
	color: #4b5563;
	font-size: 0.875rem;
	line-height: 1.55;
}

.checkout-payment-footer__checkbox {
	margin-top: 2px;
	width: 20px;
	height: 20px;
	min-width: 20px;
	accent-color: #095866;
	cursor: pointer;
}

.checkout-payment-footer__terms-link {
	color: #095866;
	font-weight: 600;
}

.checkout-payment-footer__hint {
	font-size: 0.8125rem;
	line-height: 1.5;
	color: #6b7280;
	max-width: 480px;
	margin: 0;
}

.checkout-payment-submit {
	grid-area: submit;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	min-height: 54px;
	min-width: 240px;
	padding: 0 20px;
	border-radius: 999px;
	font-size: 1rem;
	font-weight: 700;
	color: #fff;
	align-self: start;
	justify-self: stretch;
	transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1), background-color 0.3s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.3s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.3s ease;
}

.checkout-payment-submit--active {
	background: #e44203;
	box-shadow: 0 16px 32px rgba(228, 66, 3, 0.22);
}

.checkout-payment-submit--active:hover {
	transform: translateY(-1px);
	box-shadow: 0 18px 36px rgba(228, 66, 3, 0.26);
}

.checkout-payment-submit--disabled {
	background: #c8cdd5;
	cursor: not-allowed;
}

.checkout-payment-status {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.checkout-payment-status__error,
.checkout-payment-status__progress {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 14px;
	border-radius: 16px;
	font-size: 0.875rem;
}

.checkout-payment-status__error {
	border: 1px solid #fecaca;
	background: #fef2f2;
	color: #dc2626;
}

.checkout-payment-status__progress {
	border: 1px solid #bfdbfe;
	background: #eff6ff;
	color: #2563eb;
	font-weight: 600;
}

@media (max-width: 1279px) {
	.checkout-payment-footer {
		position: sticky;
		bottom: calc(env(safe-area-inset-bottom, 0px) + 12px);
		z-index: 18;
		align-items: start;
	}
}

@media (max-width: 767px) {
	.checkout-payment-footer {
		display: flex;
		flex-direction: column;
		align-items: stretch;
		padding: 14px 12px;
		bottom: calc(env(safe-area-inset-bottom, 0px) + 8px);
	}

	.checkout-payment-footer__summary {
		flex-direction: column;
		align-items: flex-start;
		gap: 8px;
	}

	.checkout-payment-footer__summary-chip {
		align-self: flex-start;
	}

	.checkout-payment-footer__support {
		padding: 0;
	}

	.checkout-payment-submit {
		align-self: stretch;
		width: 100%;
		min-width: 0;
	}

	.checkout-payment-footer__hint {
		padding-left: 0;
		max-width: none;
	}
}
</style>
