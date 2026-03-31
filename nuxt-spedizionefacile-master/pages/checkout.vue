<!--
  FILE: pages/checkout.vue
  SCOPO: Checkout — orchestratore. I sotto-componenti sono in components/checkout/.

  API: POST /api/stripe/create-order, POST /api/stripe/create-payment-intent,
       POST /api/stripe/order-paid, POST /api/stripe/mark-order-completed,
       POST /api/stripe/create-payment, POST /api/stripe/existing-order-payment,
       POST /api/calculate-coupon, POST /api/wallet/pay, POST /api/referral/apply,
       GET /api/wallet/balance, GET /api/referral/my-discount,
       GET /api/stripe/default-payment-method, GET /api/orders/{id}.
  COMPOSABLE: useCart (dati carrello), useSanctumAuth (utente autenticato), usePriceBands (promo).
  ROUTE: /checkout (protetta, middleware app-auth).

  DATI IN INGRESSO: ?order_id=XXX (query param per pagamento ordine esistente).
  DATI IN USCITA: pagamento completato -> svuotamento carrello, navigazione a successo.

  VINCOLI: Stripe viene caricato in modo dinamico (import asincrono), NON nel bundle iniziale.
           I prezzi nel DB sono in centesimi. Il codice referral viene applicato DOPO il pagamento.

  COMPONENTI:
  - CheckoutSummary: riepilogo ordine, pacchi, totali, coupon panel
  - CheckoutPaymentMethods: selettore metodo pagamento, form carta, carta salvata
  - CheckoutBillingSection: fatturazione (ricevuta/fattura, azienda/privato)
  - CheckoutSuccessMessage: schermata successo post-pagamento
  - CheckoutConfirmModal: modal conferma prima del pagamento
  - CheckoutPaymentFooter: pulsante paga, termini, errori/progress
-->
<script setup>
// Preconnect to Stripe on this page only
useHead({ link: [
	{ rel: 'preconnect', href: 'https://js.stripe.com', crossorigin: '' },
	{ rel: 'preconnect', href: 'https://api.stripe.com', crossorigin: '' },
] });
useSeoMeta({ title: 'Checkout | SpediamoFacile', ogTitle: 'Checkout | SpediamoFacile' });

definePageMeta({ middleware: ["app-auth", "shipment-validation"] });

const {
	// page state
	pageReady, existingOrderId, existingOrder, initCheckoutPage,
	// stripe
	stripeLoading, stripeReady, stripeConfigured,
	cardPaymentsUnavailable, cardPaymentsNotice, initStripe,
	// promo
	loadPriceBands, promoSettings,
	// packages & totals
	displayPackages, addressGroups, hasMultipleGroups, mergeGroupsCount,
	getTotal, getNumberTotal, totalPackages, contentDescription,
	formatPrice, finalTotal, finalTotalFormatted,
	// billing
	fatturazioneType, invoiceSubjectType, fatturaData, billingShippingFullAddress,
	// wallet
	walletFormatted, walletLoaded, walletSufficient,
	// coupon
	couponCode, couponLoading, couponError, couponApplied, couponPanelOpen,
	validateCoupon, removeCoupon, autoApplyReferral,
	// payment method
	paymentMethod, paymentMethodOptions, selectPaymentMethod,
	// card element
	cardElementContainer, cardMounted, cardComplete, cardError,
	shouldShowCardForm, useNewCard, saveCardForFuture, hasSavedCard, defaultPayment,
	// payment flow
	termsAccepted, showConfirmModal, confirmPayment, proceedWithPayment,
	isProcessing, paymentError, paymentSuccess, successOrderId,
	paymentStep, paymentActionLabel, canPay, payButtonTooltip,
	// fallback
	fallbackFlowRoute,
} = useCheckout();

pageReady.value = await initCheckoutPage();

// Callback to sync the composable's cardElementContainer with the child's DOM element
function onCardElementRef(el) { cardElementContainer.value = el ?? null; }

// Init Stripe and promo when mounted
onMounted(async () => {
	loadPriceBands();
	await initStripe();
	autoApplyReferral();
});
</script>

<template>
	<section class="min-h-[600px] py-[30px] desktop:py-[50px] bg-[#F0F0F0]">
		<div class="my-container">
			<!-- Loading skeleton -->
			<div v-if="!pageReady" class="space-y-[16px] animate-pulse">
				<div class="h-[64px] rounded-[16px] border border-[#E5EAEC] bg-white/90"></div>
				<div class="grid grid-cols-1 desktop:grid-cols-[minmax(0,1.1fr)_360px] gap-[18px]">
					<div class="rounded-[18px] border border-[#E5EAEC] bg-white p-[18px] tablet:p-[22px] space-y-[14px]">
						<div class="h-[24px] w-[42%] rounded-[10px] bg-[#EEF3F5]"></div>
						<div class="grid grid-cols-1 tablet:grid-cols-2 gap-[12px]">
							<div class="h-[118px] rounded-[16px] bg-[#F4F7F9]"></div>
							<div class="h-[118px] rounded-[16px] bg-[#F4F7F9]"></div>
						</div>
						<div class="h-[184px] rounded-[16px] bg-[#EEF3F5]"></div>
					</div>
					<div class="rounded-[18px] border border-[#E5EAEC] bg-white p-[18px] space-y-[14px]">
						<div class="h-[22px] w-[58%] rounded-[10px] bg-[#EEF3F5]"></div>
						<div class="h-[92px] rounded-[16px] bg-[#F4F7F9]"></div>
						<div class="h-[56px] rounded-[999px] bg-[#D8DEE5]"></div>
					</div>
				</div>
			</div>

			<template v-else>
				<!-- Steps -->
				<Steps :current-step="4" />

				<!-- Success -->
				<CheckoutSuccessMessage
					v-if="paymentSuccess"
					:success-order-id="successOrderId"
					:payment-method="paymentMethod"
				/>

				<!-- Checkout form -->
				<div v-else class="mx-auto space-y-[24px]">

					<!-- Riepilogo ordine -->
					<CheckoutSummary
						:display-packages="displayPackages"
						:address-groups="addressGroups"
						:has-multiple-groups="hasMultipleGroups"
						:merge-groups-count="mergeGroupsCount"
						:total-packages="totalPackages"
						:content-description="contentDescription"
						:existing-order-id="existingOrderId"
						:get-total="getTotal"
						:final-total-formatted="finalTotalFormatted"
						:coupon-applied="couponApplied"
						:coupon-code="couponCode"
						:coupon-loading="couponLoading"
						:coupon-error="couponError"
						:coupon-panel-open="couponPanelOpen"
						:promo-settings="promoSettings"
						:format-price="formatPrice"
						@update:coupon-code="couponCode = $event"
						@update:coupon-panel-open="couponPanelOpen = $event"
						@validate-coupon="validateCoupon"
						@remove-coupon="removeCoupon"
					/>

					<div class="checkout-payment-stack">
						<!-- Metodi di pagamento -->
						<CheckoutPaymentMethods
							:payment-method="paymentMethod"
							:payment-method-options="paymentMethodOptions"
							:card-payments-unavailable="cardPaymentsUnavailable"
							:card-payments-notice="cardPaymentsNotice"
							:has-saved-card="hasSavedCard"
							:default-payment="defaultPayment"
							:use-new-card="useNewCard"
							:should-show-card-form="shouldShowCardForm"
							:stripe-loading="stripeLoading"
							:card-error="cardError"
							:save-card-for-future="saveCardForFuture"
							:wallet-formatted="walletFormatted"
							:wallet-loaded="walletLoaded"
							:wallet-sufficient="walletSufficient"
							@select-payment-method="selectPaymentMethod"
							@update:use-new-card="useNewCard = $event"
							@update:save-card-for-future="saveCardForFuture = $event"
							@card-element-ref="onCardElementRef"
						/>

						<!-- Documento fiscale -->
						<CheckoutBillingSection
							:fatturazione-type="fatturazioneType"
							:invoice-subject-type="invoiceSubjectType"
							:fattura-data="fatturaData"
							:billing-shipping-full-address="billingShippingFullAddress"
							@update:fatturazione-type="fatturazioneType = $event"
							@update:invoice-subject-type="invoiceSubjectType = $event"
							@update:fattura-data="Object.assign(fatturaData, $event)"
						/>

						<!-- Footer con pulsante paga -->
						<CheckoutPaymentFooter
							:final-total-formatted="finalTotalFormatted"
							:payment-method="paymentMethod"
							:payment-action-label="paymentActionLabel"
							:pay-button-tooltip="payButtonTooltip"
							:can-pay="canPay"
							:is-processing="isProcessing"
							:payment-error="paymentError"
							:payment-step="paymentStep"
							:terms-accepted="termsAccepted"
							@confirm-payment="confirmPayment"
							@update:terms-accepted="termsAccepted = $event"
						/>
					</div>
				</div>

				<!-- Confirmation Modal -->
				<CheckoutConfirmModal
					:show="showConfirmModal"
					:final-total-formatted="finalTotalFormatted"
					:payment-method="paymentMethod"
					:total-packages="totalPackages"
					@close="showConfirmModal = false"
					@confirm="proceedWithPayment"
				/>
			</template>
		</div>
	</section>
</template>

<style scoped>
.checkout-payment-stack {
	display: flex;
	flex-direction: column;
	gap: 16px;
}
</style>
