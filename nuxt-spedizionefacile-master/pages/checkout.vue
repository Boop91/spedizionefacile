<!--
  FILE: pages/checkout.vue
  SCOPO: Checkout — pagamento spedizioni (Stripe carta/bonifico, wallet, coupon/referral).

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

  BUGFIX COMPLETATI (Agent 4):
  ✅ 3D Secure moderno: confirmCardPayment con gestione completa SCA
  ✅ Validazione importo minimo: 0,50€ per pagamenti carta
  ✅ Validazione P.IVA: formato 11 cifre per fatture
  ✅ Ordini multipli atomici: tracking pagamenti parziali con messaggi chiari
  ✅ Errori Stripe user-friendly: mappatura codici errore comuni
  ✅ Progress indicator: feedback visivo durante elaborazione
  ✅ Modal conferma: conferma prima del pagamento finale
  ✅ Success animation: animazione completamento pagamento
  ✅ Inline SVG: rimossi tutti i componenti Icon

  PUNTI DI MODIFICA SICURI: metodi di pagamento, layout fatturazione, stili.
  COLLEGAMENTI: composables/useCart.js, pages/carrello.vue, pages/account/spedizioni/.
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
			<div v-if="paymentSuccess" class="max-w-[600px] mx-auto text-center py-[60px]">
				<div class="w-[80px] h-[80px] mx-auto mb-[20px] bg-emerald-100 rounded-full flex items-center justify-center animate-[success-bounce_0.6s_ease-out]">
					<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="animate-[check-draw_0.5s_ease-out_0.2s_both]">
						<polyline points="20 6 9 17 4 12"/>
					</svg>
				</div>
				<h1 class="text-[1.75rem] font-bold text-[#252B42] mb-[12px]">Pagamento completato!</h1>
				<p class="text-[#737373] text-[1rem] leading-[1.6] mb-[8px]">
					<template v-if="String(successOrderId).includes(',')">
						I tuoi ordini <span class="font-semibold text-[#252B42]">#{{ successOrderId }}</span> sono stati creati con successo.
					</template>
					<template v-else>
						Il tuo ordine <span class="font-semibold text-[#252B42]">#{{ successOrderId }}</span> è stato creato con successo.
					</template>
				</p>
				<p v-if="paymentMethod === 'bonifico'" class="text-[#737373] text-[0.9375rem] mb-[24px]">
					Riceverai le coordinate bancarie via email per completare il pagamento.
				</p>
				<p v-else class="text-[#737373] text-[0.9375rem] mb-[24px]">
					Il pagamento è stato elaborato correttamente.
				</p>
				<div class="flex flex-col tablet:flex-row gap-[12px] justify-center">
					<NuxtLink to="/account/spedizioni" class="inline-flex items-center justify-center gap-[6px] px-[24px] py-[12px] min-h-[48px] bg-[#095866] text-white rounded-[50px] font-semibold text-[0.9375rem] hover:bg-[#074a56] transition">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
						Vedi le tue spedizioni
					</NuxtLink>
					<NuxtLink to="/" class="inline-flex items-center justify-center gap-[6px] px-[24px] py-[12px] min-h-[48px] border border-[#E9EBEC] text-[#737373] rounded-[50px] font-medium text-[0.9375rem] hover:bg-white transition">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
						Torna alla home
					</NuxtLink>
				</div>
			</div>

			<!-- Checkout form -->
			<div v-else class="mx-auto space-y-[24px]">

				<!-- Riepilogo -->
				<div class="bg-[#E6E6E6] rounded-[20px] p-[16px_12px] tablet:p-[24px_20px] desktop:p-[30px_36px]">
					<!-- Header -->
					<div class="flex items-center justify-between mb-[20px]">
						<div class="flex items-center gap-[10px]">
							<div class="w-[36px] h-[36px] bg-[#095866] rounded-[50px] flex items-center justify-center shrink-0">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="22" height="18" rx="2"/><path d="M1 9h22"/></svg>
							</div>
							<div>
								<h2 class="sf-section-title !text-[1.25rem] leading-tight">{{ displayPackages.length <= 1 ? 'Riepilogo ordine' : 'Riepilogo ordini' }}</h2>
								<p class="text-[0.8125rem] text-[#737373]">{{ totalPackages }} {{ totalPackages === 1 ? 'spedizione' : 'spedizioni' }}<span v-if="contentDescription"> &middot; {{ contentDescription }}</span></p>
							</div>
						</div>
						<NuxtLink v-if="!existingOrderId" to="/carrello" class="sf-action-pill sf-action-pill--accent">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
							Modifica
						</NuxtLink>
						<span v-else class="text-[0.8125rem] font-semibold text-[#737373] bg-white px-[14px] py-[6px] rounded-[8px]">
							Ordine #{{ existingOrderId }}
						</span>
					</div>

					<!-- Merge info banner -->
					<div v-if="!existingOrderId && hasMultipleGroups" class="bg-[#095866]/10 border border-[#095866]/20 rounded-[50px] p-[12px_16px] mb-[14px] flex items-center gap-[10px]">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#095866" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="M16 3h5v5"/><path d="M4 20L21 3"/><path d="M21 16v5h-5"/><path d="M15 15l6 6"/><path d="M4 4l5 5"/></svg>
						<p class="text-[0.8125rem] text-[#095866] font-medium">
							Verranno creati <span class="font-bold">{{ mergeGroupsCount }} ordini separati</span> in base agli indirizzi. I pacchi con stessi indirizzi saranno uniti in una singola spedizione.
						</p>
					</div>
					<div v-else-if="!existingOrderId && addressGroups.some(g => g.count > 1)" class="bg-emerald-50 border border-emerald-200 rounded-[50px] p-[12px_16px] mb-[14px] flex items-center gap-[10px]">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
						<p class="text-[0.8125rem] text-emerald-700 font-medium">
							Tutti i pacchi hanno gli stessi indirizzi e verranno spediti come un'unica spedizione multi-collo.
						</p>
					</div>

					<!-- Package cards -->
					<div class="space-y-[14px] mb-[20px]">
						<div v-for="(pkg, pkgIdx) in displayPackages" :key="pkg.id || pkgIdx"
							class="bg-white rounded-[14px] p-[18px_20px] border border-[#E9EBEC] shadow-[0_1px_3px_rgba(0,0,0,0.04)]">

							<!-- Package header row: type + price -->
							<div class="flex flex-wrap items-start gap-[8px] mb-[14px]">
								<div class="flex items-center gap-[8px] min-w-0 flex-1">
									<span class="inline-flex items-center justify-center w-[28px] h-[28px] bg-[#095866]/10 text-[#095866] rounded-[8px] text-[0.75rem] font-bold">{{ pkgIdx + 1 }}</span>
									<span class="text-[0.9375rem] font-semibold text-[#252B42]">{{ pkg.package_type || 'Pacco' }}</span>
									<span v-if="pkg.content_description" class="text-[0.75rem] text-[#737373] bg-[#F5F5F5] px-[8px] py-[2px] rounded-[4px] max-w-[150px] tablet:max-w-[240px] truncate">{{ pkg.content_description }}</span>
								</div>
								<span class="text-[1.0625rem] font-bold text-[#095866] shrink-0 ml-auto"
									:title="'Prezzo unitario per questo collo: ' + formatPrice(pkg.single_price) + (pkg.quantity > 1 ? ' x ' + pkg.quantity + ' = ' + formatPrice(pkg.single_price * pkg.quantity) : '')">
									{{ formatPrice(pkg.single_price) }}
								</span>
							</div>

							<!-- Package specs row -->
							<div class="flex flex-wrap gap-[8px] mb-[14px]">
								<span class="inline-flex items-center gap-[4px] bg-[#F5F5F5] text-[0.8125rem] text-[#252B42] px-[10px] py-[5px] rounded-[6px]"
									:title="'Peso del pacco: ' + pkg.weight + ' chilogrammi'">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#737373" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
									<span class="font-medium">{{ pkg.weight }} kg</span>
								</span>
								<span class="inline-flex items-center gap-[4px] bg-[#F5F5F5] text-[0.8125rem] text-[#252B42] px-[10px] py-[5px] rounded-[6px]"
									:title="'Dimensioni: larghezza ' + pkg.first_size + ' cm x altezza ' + pkg.second_size + ' cm x profondità ' + pkg.third_size + ' cm'">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#737373" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v3"/></svg>
									<span class="font-medium">{{ pkg.first_size }}&times;{{ pkg.second_size }}&times;{{ pkg.third_size }} cm</span>
								</span>
								<span v-if="(pkg.quantity || 1) > 1" class="inline-flex items-center gap-[4px] bg-[#F5F5F5] text-[0.8125rem] text-[#252B42] px-[10px] py-[5px] rounded-[6px]"
									title="Numero di colli identici in questa spedizione">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#737373" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="8" height="8" rx="1"/><rect x="14" y="2" width="8" height="8" rx="1"/><rect x="2" y="14" width="8" height="8" rx="1"/><rect x="14" y="14" width="8" height="8" rx="1"/></svg>
									<span class="font-medium">Qtà: {{ pkg.quantity }}</span>
								</span>
							</div>

							<!-- Addresses section -->
							<div v-if="pkg.origin_address || pkg.destination_address" class="border-t border-[#F0F0F0] pt-[14px] mb-[14px]">
								<div class="grid grid-cols-1 desktop:grid-cols-2 gap-[12px]">
									<!-- Sender -->
									<div v-if="pkg.origin_address" class="flex gap-[10px]">
										<div class="w-[32px] h-[32px] bg-[#095866]/10 rounded-[8px] flex items-center justify-center shrink-0 mt-[2px]"
											title="Indirizzo del mittente">
											<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#095866" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="10" r="3"/><path d="M12 21.7C17.3 17 20 13 20 10a8 8 0 1 0-16 0c0 3 2.7 7 8 11.7z"/></svg>
										</div>
										<div class="min-w-0">
											<p class="text-[0.75rem] font-semibold text-[#095866] uppercase tracking-wider mb-[2px]">Da:</p>
											<p class="text-[0.8125rem] font-medium text-[#252B42] leading-snug">{{ pkg.origin_address.name }}</p>
											<p class="text-[0.8125rem] text-[#737373] leading-snug">{{ pkg.origin_address.address }} {{ pkg.origin_address.address_number }}</p>
											<p class="text-[0.8125rem] text-[#737373] leading-snug">{{ pkg.origin_address.postal_code }} {{ pkg.origin_address.city }} ({{ pkg.origin_address.province }})</p>
										</div>
									</div>
									<!-- Recipient -->
									<div v-if="pkg.destination_address" class="flex gap-[10px]">
										<div class="w-[32px] h-[32px] bg-[#E44203]/10 rounded-[8px] flex items-center justify-center shrink-0 mt-[2px]"
											title="Indirizzo del destinatario">
											<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#E44203" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="10" r="3"/><path d="M12 21.7C17.3 17 20 13 20 10a8 8 0 1 0-16 0c0 3 2.7 7 8 11.7z"/></svg>
										</div>
										<div class="min-w-0">
											<p class="text-[0.75rem] font-semibold text-[#E44203] uppercase tracking-wider mb-[2px]">A:</p>
											<p class="text-[0.8125rem] font-medium text-[#252B42] leading-snug">{{ pkg.destination_address.name }}</p>
											<p class="text-[0.8125rem] text-[#737373] leading-snug">{{ pkg.destination_address.address }} {{ pkg.destination_address.address_number }}</p>
											<p class="text-[0.8125rem] text-[#737373] leading-snug">{{ pkg.destination_address.postal_code }} {{ pkg.destination_address.city }} ({{ pkg.destination_address.province }})</p>
										</div>
									</div>
								</div>
							</div>

							<!-- Services & Pickup date -->
							<div v-if="pkg.services && ((pkg.services.service_type && pkg.services.service_type !== 'Nessuno') || pkg.services.date)"
								class="border-t border-[#F0F0F0] pt-[12px] flex flex-wrap items-center gap-[12px]">
								<span v-if="pkg.services.service_type && pkg.services.service_type !== 'Nessuno'"
									class="inline-flex items-center gap-[6px] text-[0.8125rem] text-[#252B42]"
									title="Servizio aggiuntivo selezionato per questa spedizione">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#095866" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
									<span class="font-medium">{{ pkg.services.service_type }}</span>
								</span>
								<span v-if="pkg.services.date"
									class="inline-flex items-center gap-[6px] text-[0.8125rem] text-[#252B42]"
									title="Data programmata per il ritiro del pacco">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#095866" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
									<span class="font-medium">Ritiro: {{ pkg.services.date }}</span>
								</span>
							</div>
						</div>
					</div>

					<!-- Totals summary -->
					<div class="bg-white rounded-[14px] p-[18px_20px] border border-[#E9EBEC]">
						<!-- Subtotal -->
						<div class="flex items-center justify-between py-[8px]">
							<span class="text-[0.9375rem] text-[#737373]">Subtotale ({{ totalPackages }} {{ totalPackages === 1 ? 'spedizione' : 'spedizioni' }})</span>
							<span class="text-[0.9375rem] font-medium text-[#252B42]">{{ getTotal }}</span>
						</div>

						<!-- Discount row -->
						<div v-if="couponApplied" class="flex items-center justify-between py-[8px] border-t border-[#F0F0F0]">
							<span class="text-[0.9375rem] text-emerald-700 flex items-center gap-[6px]"
								:title="'Sconto ' + couponApplied.discount_percent + '% applicato con il codice ' + couponApplied.code">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
								Sconto {{ couponApplied.discount_percent }}% ({{ couponApplied.code }})
							</span>
							<span class="text-[0.9375rem] font-semibold text-emerald-700">-{{ couponApplied.discount_amount.toFixed(2).replace('.', ',') }}€</span>
						</div>

						<!-- Divider before total -->
						<div class="border-t-2 border-[#E0E0E0] mt-[4px] mb-[4px]"></div>

						<!-- Final total -->
						<div class="flex items-center justify-between py-[8px]">
							<div class="flex items-center gap-[8px]">
								<span class="text-[1.0625rem] font-bold text-[#252B42]">Totale da pagare</span>
								<!-- Promo badge -->
								<span v-if="promoSettings?.active && promoSettings?.label_text"
									:style="{ backgroundColor: promoSettings.label_color || '#E44203' }"
									class="inline-flex items-center gap-[4px] px-[8px] py-[3px] rounded-[6px] text-white text-[0.6875rem] font-bold tracking-wide">
									<!-- Ottimizzazione: lazy loading + decoding async + dimensioni per CLS -->
									<img v-if="promoSettings.label_image" :src="promoSettings.label_image" alt="" loading="lazy" decoding="async" width="24" height="12" class="h-[12px] w-auto" />
									{{ promoSettings.label_text }}
								</span>
							</div>
							<span class="text-[1.25rem] font-bold text-[#095866]"
								:title="couponApplied ? `Totale originale: ${getTotal} - Sconto: ${couponApplied.discount_amount.toFixed(2).replace('.', ',')}€ = ${finalTotalFormatted}` : 'Totale ordine IVA inclusa'">
								{{ finalTotalFormatted }}
							</span>
						</div>

						<div class="mt-[8px] border-t border-[#F0F0F0] pt-[12px]">
							<div class="flex flex-col tablet:flex-row tablet:items-center tablet:justify-between gap-[10px]">
								<div class="min-w-0">
									<p class="text-[0.875rem] font-medium text-[#252B42]">Codice promozionale o referral</p>
									<p class="text-[0.75rem] text-[#6B7280] leading-[1.5]">Mostralo solo se hai davvero un codice da applicare.</p>
								</div>
								<button
									type="button"
									@click="couponPanelOpen = !couponPanelOpen"
									class="inline-flex items-center gap-[8px] text-[0.8125rem] font-semibold text-[#095866] hover:opacity-80 transition cursor-pointer">
									<span>{{ couponApplied ? 'Gestisci codice' : (couponPanelOpen ? 'Nascondi codice' : 'Hai un codice?') }}</span>
									<svg
										:class="couponPanelOpen ? 'rotate-180' : ''"
										width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
										class="transition-transform duration-200 shrink-0">
										<polyline points="6 9 12 15 18 9" />
									</svg>
								</button>
							</div>

							<Transition name="payment-panel">
								<div v-if="couponPanelOpen" class="mt-[12px]">
									<div v-if="couponApplied" class="flex flex-col tablet:flex-row tablet:items-center gap-[10px] rounded-[16px] border border-emerald-200 bg-emerald-50 px-[14px] py-[12px]">
										<div class="flex items-start gap-[10px] min-w-0 flex-1">
											<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-[2px] shrink-0"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
											<div class="min-w-0">
												<p class="text-[0.875rem] font-semibold text-emerald-800">Codice {{ couponApplied.code }} applicato</p>
												<p class="text-[0.75rem] text-emerald-700 leading-[1.5]">Sconto del {{ couponApplied.discount_percent }}% già incluso nel totale.</p>
											</div>
										</div>
										<button type="button" @click="removeCoupon" class="inline-flex items-center gap-[4px] text-[0.8125rem] text-red-500 hover:underline font-medium cursor-pointer shrink-0">
											<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
											Rimuovi
										</button>
									</div>
									<div v-else class="flex flex-col tablet:flex-row gap-[10px]">
										<input
											v-model="couponCode"
											type="text"
											placeholder="Inserisci codice promozionale"
											maxlength="20"
											class="flex-1 bg-white p-[12px_14px] border border-[#D0D0D0] rounded-[14px] text-[0.9375rem] placeholder:text-[#A0A5AB] uppercase tracking-[0.04em] focus:border-[#095866] focus:outline-none"
											@keyup.enter="validateCoupon" />
										<button
											type="button"
											@click="validateCoupon"
											:disabled="couponLoading || !couponCode.trim()"
											class="inline-flex items-center justify-center gap-[6px] px-[20px] min-h-[48px] bg-[#095866] text-white rounded-[14px] font-semibold text-[0.875rem] hover:bg-[#074a56] transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
											<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
											{{ couponLoading ? 'Verifica...' : 'Applica' }}
										</button>
									</div>
									<div class="min-h-[20px] mt-[8px]">
										<p v-if="couponError" class="text-red-500 text-[0.8125rem]">{{ couponError }}</p>
									</div>
								</div>
							</Transition>
						</div>
					</div>
				</div>

					<div class="checkout-payment-stack">
						<!-- Metodi di pagamento -->
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
									@click="selectPaymentMethod(option.key)"
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
												@click="useNewCard = false"
												:class="['checkout-payment-choice no-radius', !useNewCard ? 'checkout-payment-choice--selected' : 'checkout-payment-choice--idle']">
												<span class="checkout-payment-choice__brand">{{ defaultPayment.card.brand?.toUpperCase() }}</span>
												<div class="checkout-payment-choice__copy">
													<p class="checkout-payment-choice__eyebrow">Carta salvata</p>
													<p class="checkout-payment-choice__title">•••• •••• •••• {{ defaultPayment.card.last4 }}</p>
													<p class="checkout-payment-choice__text">Scade {{ defaultPayment.card.exp_month }}/{{ defaultPayment.card.exp_year }}</p>
												</div>
												<span :class="['checkout-payment-choice__radio', !useNewCard ? 'checkout-payment-choice__radio--selected' : '']"></span>
											</button>

											<div
												role="button"
												tabindex="0"
												@click="useNewCard = true"
												@keydown.enter.prevent="useNewCard = true"
												@keydown.space.prevent="useNewCard = true"
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

														<div id="card-element" ref="cardElementContainer" class="checkout-payment-card-form__element"></div>
														<p v-if="stripeLoading" class="checkout-payment-card-form__helper">Preparazione del modulo carta in corso...</p>
														<p v-if="cardError" class="checkout-payment-card-form__error">{{ cardError }}</p>
														<label class="checkout-payment-card-form__save" @click.stop>
															<input type="checkbox" v-model="saveCardForFuture" class="checkout-payment-card-form__checkbox" />
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

						<!-- Documento fiscale -->
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
										@click="fatturazioneType = 'ricevuta'"
										:class="fatturazioneType === 'ricevuta' ? 'checkout-billing-pill--active' : 'checkout-billing-pill--idle'"
										class="checkout-billing-pill no-radius">
										Ricevuta
									</button>
									<button
										type="button"
										@click="fatturazioneType = 'fattura'"
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
												@click="invoiceSubjectType = 'azienda'"
												:class="invoiceSubjectType === 'azienda' ? 'checkout-billing-subpill--active' : 'checkout-billing-subpill--idle'"
												class="checkout-billing-subpill no-radius">
												Azienda
											</button>
											<button
												type="button"
												@click="invoiceSubjectType = 'privato'"
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
													<input v-model="fatturaData.ragione_sociale" type="text" placeholder="SpediamoFacile S.r.l." class="checkout-billing-input" />
												</div>
												<div>
													<label class="checkout-billing-label">Partita IVA</label>
													<input v-model="fatturaData.p_iva" type="text" placeholder="IT 01234567890" class="checkout-billing-input" />
												</div>
												<div>
													<label class="checkout-billing-label">Codice Fiscale</label>
													<input v-model="fatturaData.codice_fiscale" type="text" placeholder="01234567890" class="checkout-billing-input" />
												</div>
											</div>

											<div class="checkout-billing-grid checkout-billing-grid--company-mid">
												<div>
													<label class="checkout-billing-label">Codice SDI</label>
													<input v-model="fatturaData.codice_sdi" type="text" maxlength="7" placeholder="XXXXXXX" class="checkout-billing-input" />
												</div>
												<div>
													<label class="checkout-billing-label">PEC (alternativa)</label>
													<input v-model="fatturaData.pec" type="email" placeholder="fattura@pec.azienda.it" class="checkout-billing-input" />
												</div>
											</div>

											<div class="checkout-billing-grid checkout-billing-grid--address">
												<div>
													<label class="checkout-billing-label">Indirizzo</label>
													<input v-model="fatturaData.indirizzo" type="text" placeholder="Indirizzo" class="checkout-billing-input" />
												</div>
												<div>
													<label class="checkout-billing-label">Città</label>
													<input v-model="fatturaData.city" type="text" placeholder="Città" class="checkout-billing-input" />
												</div>
												<div>
													<label class="checkout-billing-label">Prov.</label>
													<input v-model="fatturaData.province" type="text" maxlength="2" placeholder="Prov." class="checkout-billing-input" />
												</div>
												<div>
													<label class="checkout-billing-label">CAP</label>
													<input v-model="fatturaData.postal_code" type="text" maxlength="10" placeholder="CAP" class="checkout-billing-input" />
												</div>
											</div>
										</div>

										<div v-else key="privato" class="checkout-billing-fields">
											<div class="checkout-billing-grid checkout-billing-grid--private-top">
												<div>
													<label class="checkout-billing-label">Nome completo</label>
													<input v-model="fatturaData.nome_completo" type="text" placeholder="Nome e Cognome" class="checkout-billing-input" />
												</div>
												<div>
													<label class="checkout-billing-label">Codice Fiscale</label>
													<input v-model="fatturaData.codice_fiscale" type="text" placeholder="Codice Fiscale" class="checkout-billing-input" />
												</div>
											</div>

											<div class="checkout-billing-grid checkout-billing-grid--address">
												<div>
													<label class="checkout-billing-label">Indirizzo</label>
													<input v-model="fatturaData.indirizzo" type="text" placeholder="Indirizzo" class="checkout-billing-input" />
												</div>
												<div>
													<label class="checkout-billing-label">Città</label>
													<input v-model="fatturaData.city" type="text" placeholder="Città" class="checkout-billing-input" />
												</div>
												<div>
													<label class="checkout-billing-label">Prov.</label>
													<input v-model="fatturaData.province" type="text" maxlength="2" placeholder="Prov." class="checkout-billing-input" />
												</div>
												<div>
													<label class="checkout-billing-label">CAP</label>
													<input v-model="fatturaData.postal_code" type="text" maxlength="10" placeholder="CAP" class="checkout-billing-input" />
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

						<div class="checkout-payment-footer checkout-motion-card" style="--checkout-delay: 200ms;">
							<div class="checkout-payment-footer__summary">
								<div class="checkout-payment-footer__summary-copy">
									<p class="checkout-payment-footer__summary-label">Totale da pagare</p>
									<p class="checkout-payment-footer__summary-value">{{ finalTotalFormatted }}</p>
								</div>
								<span class="checkout-payment-footer__summary-chip">{{ paymentMethod === 'bonifico' ? 'Pagamento differito' : 'Pagamento sicuro' }}</span>
							</div>

							<button type="button" @click="confirmPayment" :disabled="!canPay"
								:class="['checkout-payment-submit', canPay ? 'checkout-payment-submit--active' : 'checkout-payment-submit--disabled']">
								<svg v-if="isProcessing" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg>
								<svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
								<span>{{ paymentActionLabel }}</span>
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
							</button>

							<div class="checkout-payment-footer__support">
								<label class="checkout-payment-footer__terms">
									<input type="checkbox" v-model="termsAccepted" class="checkout-payment-footer__checkbox" />
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
				</div>

				<!-- Confirmation Modal -->
				<Teleport to="body">
					<div v-if="showConfirmModal" class="fixed inset-0 z-[9999] flex items-center justify-center p-[20px] bg-[#09131c]/36 backdrop-blur-[6px]" @click.self="showConfirmModal = false">
						<div class="sf-modal-surface w-full max-w-[480px] animate-[scale-in_0.2s_ease-out]">
							<div class="sf-modal-content">
								<div class="sf-modal-header">
									<div class="sf-modal-header__main">
										<div class="sf-modal-icon sf-modal-icon--accent">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#E44203" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
										</div>
										<div>
											<h3 class="sf-modal-title">Conferma pagamento</h3>
											<p class="sf-modal-description">
								Stai per pagare <span class="font-bold text-[#252B42]">{{ finalTotalFormatted }}</span>
								<template v-if="paymentMethod === 'carta'">con carta di credito</template>
								<template v-else-if="paymentMethod === 'bonifico'">tramite bonifico bancario</template>
								<template v-else-if="paymentMethod === 'wallet'">con il tuo wallet</template>
								per <span class="font-bold text-[#252B42]">{{ totalPackages }} {{ totalPackages === 1 ? 'spedizione' : 'spedizioni' }}</span>.
											</p>
										</div>
									</div>
								</div>
								<div class="sf-modal-divider" />
								<div class="sf-modal-actions">
								<button type="button" @click="showConfirmModal = false" class="btn-secondary flex-1 min-h-[48px]">
									Annulla
								</button>
								<button type="button" @click="proceedWithPayment" class="btn-cta flex-1 min-h-[48px]">
									Conferma
								</button>
								</div>
							</div>
						</div>
					</div>
				</Teleport>
			</template>
		</div>
	</section>
</template>

<style scoped>
@keyframes scale-in {
	from {
		opacity: 0;
		transform: scale(0.95);
	}
	to {
		opacity: 1;
		transform: scale(1);
	}
}

@keyframes success-bounce {
	0% {
		opacity: 0;
		transform: scale(0);
	}
	50% {
		transform: scale(1.1);
	}
	100% {
		opacity: 1;
		transform: scale(1);
	}
}

@keyframes check-draw {
	0% {
		stroke-dasharray: 0 100;
		stroke-dashoffset: 0;
	}
	100% {
		stroke-dasharray: 100 100;
		stroke-dashoffset: 0;
	}
}

@keyframes checkout-fade-up {
	from {
		opacity: 0;
		transform: translateY(18px);
	}
	to {
		opacity: 1;
		transform: translateY(0);
	}
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

.checkout-payment-stack {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.checkout-motion-card {
	animation: checkout-fade-up 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
	animation-delay: var(--checkout-delay, 0ms);
}

.checkout-stage-card {
	background: #fff;
	border: 1px solid #e8eef1;
	box-shadow: 0 14px 34px rgba(24, 39, 75, 0.06);
}

.checkout-stage-card--payment,
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

.checkout-panel-head__copy {
	min-width: 0;
}

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
	transition:
		transform 0.35s cubic-bezier(0.4, 0, 0.2, 1),
		box-shadow 0.35s cubic-bezier(0.4, 0, 0.2, 1),
		border-color 0.35s cubic-bezier(0.4, 0, 0.2, 1),
		background-color 0.35s cubic-bezier(0.4, 0, 0.2, 1),
		color 0.35s cubic-bezier(0.4, 0, 0.2, 1);
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
	transition:
		background-color 0.35s cubic-bezier(0.4, 0, 0.2, 1),
		border-color 0.35s cubic-bezier(0.4, 0, 0.2, 1),
		color 0.35s cubic-bezier(0.4, 0, 0.2, 1),
		box-shadow 0.35s cubic-bezier(0.4, 0, 0.2, 1);
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
	transition:
		transform 0.35s cubic-bezier(0.4, 0, 0.2, 1),
		border-color 0.35s cubic-bezier(0.4, 0, 0.2, 1),
		box-shadow 0.35s cubic-bezier(0.4, 0, 0.2, 1),
		background-color 0.35s cubic-bezier(0.4, 0, 0.2, 1);
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

.checkout-payment-card-form__intro {
	min-width: 0;
}

.checkout-payment-card-form__title {
	font-size: 0.9375rem;
	font-weight: 700;
	color: #252b42;
}

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
	transition:
		transform 0.35s cubic-bezier(0.4, 0, 0.2, 1),
		border-color 0.35s cubic-bezier(0.4, 0, 0.2, 1),
		background-color 0.35s cubic-bezier(0.4, 0, 0.2, 1),
		box-shadow 0.35s cubic-bezier(0.4, 0, 0.2, 1),
		color 0.35s cubic-bezier(0.4, 0, 0.2, 1);
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
	transition:
		border-color 0.25s ease,
		box-shadow 0.25s ease,
		background-color 0.25s ease;
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
	transition:
		transform 0.3s cubic-bezier(0.22, 1, 0.36, 1),
		background-color 0.3s cubic-bezier(0.22, 1, 0.36, 1),
		box-shadow 0.3s cubic-bezier(0.22, 1, 0.36, 1),
		opacity 0.3s ease;
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
	.checkout-payment-options-grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}

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

	.checkout-payment-footer {
		position: sticky;
		bottom: calc(env(safe-area-inset-bottom, 0px) + 12px);
		z-index: 18;
		align-items: start;
	}
}

@media (max-width: 767px) {
	.checkout-stage-card--payment,
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

	.checkout-billing-grid--company-top,
	.checkout-billing-grid--company-mid,
	.checkout-billing-grid--private-top,
	.checkout-billing-grid--address {
		grid-template-columns: 1fr;
	}

	.checkout-billing-context-note {
		padding: 11px 12px;
	}

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
	}

	.checkout-payment-footer__hint {
		padding-left: 0;
		max-width: none;
	}

	.checkout-payment-submit {
		width: 100%;
		min-width: 0;
		align-self: stretch;
	}
}
</style>
