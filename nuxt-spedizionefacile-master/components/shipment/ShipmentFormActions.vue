<!--
  FILE: components/shipment/ShipmentFormActions.vue
  SCOPO: Bottoni desktop (indietro/avanti/submit) + errore submit.
-->
<script setup>
defineProps({
	showAddressFields: { type: Boolean, required: true },
	isSubmitting: { type: Boolean, default: false },
	editCartId: { type: [Number, null], default: null },
	canOpenAddressFields: { type: Boolean, default: false },
	submitError: { type: String, default: null },
	goBackToServices: { type: Function, required: true },
	openAddressFields: { type: Function, required: true },
	softenErrorMessage: { type: Function, required: true },
});
</script>

<template>
	<div>
		<div class="mt-[28px] hidden tablet:flex flex-col tablet:flex-row flex-wrap gap-[12px] items-stretch tablet:items-center justify-between">
			<template v-if="showAddressFields">
				<button
					type="button"
					@click="goBackToServices"
					class="step-secondary-action btn-secondary sf-nav-button">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
					Indietro
				</button>
				<button
					type="submit"
					:disabled="isSubmitting"
					class="btn-cta sf-nav-button">
					{{ isSubmitting ? 'Salvataggio in corso...' : (editCartId ? 'Continua al riepilogo modifica' : 'Continua al riepilogo') }}
					<svg v-if="!isSubmitting" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
				</button>
			</template>
			<template v-else>
				<NuxtLink :to="editCartId ? '/carrello' : { path: '/', hash: '#preventivo' }" class="step-secondary-action btn-secondary sf-nav-button">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
					{{ editCartId ? 'Torna al carrello' : 'Indietro' }}
				</NuxtLink>
				<button
					type="button"
					@click="openAddressFields"
					:disabled="!canOpenAddressFields"
					class="btn-cta sf-nav-button"
					:class="canOpenAddressFields ? 'cursor-pointer' : 'opacity-55 cursor-not-allowed'">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
					Continua agli indirizzi
				</button>
			</template>
		</div>
		<div v-if="submitError" class="ux-alert ux-alert--soft mt-[16px]">
			<svg xmlns="http://www.w3.org/2000/svg" class="ux-alert__icon" viewBox="0 0 24 24"><path fill="currentColor" d="M13 13h-2V7h2m0 10h-2v-2h2M12 2a10 10 0 0 1 10 10a10 10 0 0 1-10 10A10 10 0 0 1 2 12A10 10 0 0 1 12 2"/></svg>
			<span>{{ softenErrorMessage(submitError) }}</span>
		</div>
	</div>
</template>

<style scoped>
.step-secondary-action {
	transition:
		background-color 0.2s ease,
		border-color 0.2s ease,
		color 0.2s ease,
		transform 0.2s ease,
		box-shadow 0.2s ease;
	cursor: pointer;
}

.step-secondary-action:hover,
.step-secondary-action:focus-visible {
	transform: translateY(-1px);
}
</style>
