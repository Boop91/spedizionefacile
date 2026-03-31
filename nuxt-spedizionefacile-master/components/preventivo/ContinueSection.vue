<!--
	COMPONENTE: ContinueSection (preventivo/ContinueSection.vue)
	SCOPO: Bottone continua, prezzo live e trust pills.
	DOVE SI USA: components/Preventivo.vue
-->
<script setup>
defineProps({
	isCalculating: { type: Boolean, default: false },
	isAdvancingToServices: { type: Boolean, default: false },
	continueButtonLabel: { type: String, default: 'Continua' },
	liveQuotePrice: { type: String, default: '' },
	isStandalonePreventivoRoute: { type: Boolean, default: false },
	promoActive: { type: Boolean, default: false },
});

const emit = defineEmits(['continue']);
</script>

<template>
	<div
		class="continue-button-wrapper bg-[#E44203] w-full text-white overflow-hidden"
		:class="[
			'h-[56px] tablet:h-[60px]',
			promoActive ? 'mt-[12px]' : 'mt-[18px] desktop:mt-[20px]',
			isStandalonePreventivoRoute ? 'continue-button-wrapper--sticky' : ''
		]">
			<button
				v-if="!isCalculating"
				type="button"
				@click="$emit('continue')"
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

	<!-- Trust pills -->
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
</template>

<style scoped>
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
		transform var(--sf-motion-base, 200ms) var(--sf-ease-soft, ease),
		box-shadow var(--sf-motion-base, 200ms) var(--sf-ease-soft, ease);
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
	transition: transform var(--sf-motion-fast, 150ms) var(--sf-ease-press, ease);
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

@media (min-width: 640px) {
	.continue-cta-button__label {
		font-size: 1.0625rem;
	}
}

@media (min-width: 1024px) {
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
	.continue-button-wrapper--sticky {
		border-radius: 18px;
		box-shadow: 0 16px 28px rgba(228, 66, 3, 0.22), inset 0 1px 0 rgba(255, 255, 255, 0.1);
	}
}
</style>
