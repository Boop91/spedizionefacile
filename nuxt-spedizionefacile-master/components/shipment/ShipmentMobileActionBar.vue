<!--
  FILE: components/shipment/ShipmentMobileActionBar.vue
  SCOPO: Barra azioni fissa in basso su mobile (totale stimato + bottoni).
-->
<script setup>
defineProps({
	showAddressFields: { type: Boolean, required: true },
	isSubmitting: { type: Boolean, default: false },
	editCartId: { type: [Number, null], default: null },
	canOpenAddressFields: { type: Boolean, default: false },
	summaryTotalPrice: { type: [String, Number], default: '0' },
	goBackToServices: { type: Function, required: true },
	openAddressFields: { type: Function, required: true },
});
</script>

<template>
	<div class="shipment-mobile-actionbar tablet:hidden">
		<div class="shipment-mobile-actionbar__shell">
			<div class="shipment-mobile-actionbar__meta">
				<span class="shipment-mobile-actionbar__label">Totale stimato</span>
				<span class="shipment-mobile-actionbar__value">{{ summaryTotalPrice }}&euro;</span>
			</div>
			<div class="shipment-mobile-actionbar__actions">
				<template v-if="showAddressFields">
					<button
						type="button"
						@click="goBackToServices"
						class="shipment-mobile-actionbar__secondary btn-secondary sf-nav-button sf-nav-button--compact">
						Indietro
					</button>
					<button
						type="submit"
						:disabled="isSubmitting"
						class="shipment-mobile-actionbar__primary btn-cta sf-nav-button sf-nav-button--compact">
						{{ isSubmitting ? 'Salvataggio...' : 'Vai al riepilogo' }}
					</button>
				</template>
				<template v-else>
					<NuxtLink
						:to="editCartId ? '/carrello' : { path: '/', hash: '#preventivo' }"
						class="shipment-mobile-actionbar__secondary btn-secondary sf-nav-button sf-nav-button--compact">
						{{ editCartId ? 'Carrello' : 'Indietro' }}
					</NuxtLink>
					<button
						type="button"
						@click="openAddressFields"
						:disabled="!canOpenAddressFields"
						class="shipment-mobile-actionbar__primary btn-cta sf-nav-button sf-nav-button--compact">
						Vai agli indirizzi
					</button>
				</template>
			</div>
		</div>
	</div>
</template>

<style scoped>
.shipment-mobile-actionbar {
	position: fixed;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: 55;
	padding: 8px 10px calc(env(safe-area-inset-bottom, 0px) + 10px);
	background: linear-gradient(180deg, rgba(245, 247, 249, 0) 0%, rgba(245, 247, 249, 0.92) 18%, #f5f7f9 100%);
	backdrop-filter: blur(10px);
}

.shipment-mobile-actionbar__shell {
	display: grid;
	gap: 8px;
	padding: 10px;
	border-radius: 18px;
	background: rgba(255, 255, 255, 0.96);
	border: 1px solid rgba(200, 214, 220, 0.92);
	box-shadow: 0 18px 34px rgba(18, 38, 63, 0.14);
}

.shipment-mobile-actionbar__meta {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
}

.shipment-mobile-actionbar__label {
	font-size: 0.6875rem;
	font-weight: 700;
	letter-spacing: 0.02em;
	text-transform: uppercase;
	color: #6b7d90;
}

.shipment-mobile-actionbar__value {
	font-size: 1rem;
	line-height: 1;
	font-weight: 800;
	color: #095866;
}

.shipment-mobile-actionbar__actions {
	display: grid;
	grid-template-columns: minmax(0, 104px) minmax(0, 1fr);
	gap: 10px;
}

.shipment-mobile-actionbar__secondary,
.shipment-mobile-actionbar__primary {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-height: 48px;
	width: 100%;
	transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

.shipment-mobile-actionbar__primary:disabled {
	opacity: 0.6;
	box-shadow: none;
}

@media (max-width: 44.99rem) {
	:deep(.shipment-step-shell) {
		padding-bottom: 108px;
	}
}
</style>
