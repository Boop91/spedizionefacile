<!--
  FILE: components/shipment/ShipmentFeaturedServiceCard.vue
  SCOPO: Card "Senza Etichetta" hero — servizio in evidenza con toggle attiva/disattiva.
-->
<script setup>
defineProps({
	featuredService: { type: Object, required: true },
	serviceIconFilterIdle: { type: String, required: true },
	serviceIconFilterActive: { type: String, required: true },
	toggleFeaturedService: { type: Function, required: true },
});
</script>

<template>
	<div v-if="featuredService">
		<article
			class="senza-etichetta-card service-card-tile service-card-tile--featured sf-card no-radius"
			:class="{
				'sf-card--selected': featuredService.isSelected,
				'is-selected': featuredService.isSelected,
				'is-idle': !featuredService.isSelected,
			}">
			<div class="service-card-tile__body-hit no-radius">
				<div class="service-card-tile__top">
					<div
						class="service-card-tile__icon-shell sf-icon-shell"
						:class="{ 'service-card-tile__icon-shell--selected': featuredService.isSelected }">
						<div
							class="service-card-tile__icon"
							:style="{
								'--service-icon-bg': 'url(/img/quote/second-step/no-label.png)',
								'--service-icon-width': '28px',
								'--service-icon-height': '24px',
								'--service-icon-filter': featuredService.isSelected ? serviceIconFilterActive : serviceIconFilterIdle,
							}"></div>
					</div>
					<span
						class="service-card-tile__price"
						:class="{ 'service-card-tile__price--selected': featuredService.isSelected }">
						{{ featuredService.currentPriceLabel }}
					</span>
				</div>
				<div class="service-card-tile__title-row">
					<h3 class="service-card-tile__title">Senza Etichetta</h3>
					<span class="service-card-tile__badge">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="m12 3 1.8 4.2L18 9l-4.2 1.8L12 15l-1.8-4.2L6 9l4.2-1.8z" />
						</svg>
						Consigliato
					</span>
				</div>
				<p class="service-card-tile__description">Etichetta applicata al ritiro.</p>
			</div>
			<div class="service-card-tile__footer-row">
				<div class="service-card-tile__state-pill service-card-tile__state-pill--accent">
					<span class="service-card-tile__state-dot"></span>
					<span>Pronto subito</span>
				</div>
				<div class="service-card-tile__controls">
					<button
						type="button"
						class="service-card-tile__footer no-radius"
						:aria-label="featuredService.isSelected ? 'Disattiva Senza Etichetta' : 'Attiva Senza Etichetta'"
						@click.stop.prevent="toggleFeaturedService">
						<span class="service-card-tile__switch sf-toggle" :class="{ 'is-active': featuredService.isSelected }">
							<span class="service-card-tile__switch-thumb sf-toggle__thumb"></span>
						</span>
						<span
							class="service-card-tile__switch-label"
							:class="{ 'service-card-tile__switch-label--selected': featuredService.isSelected }">
							{{ featuredService.isSelected ? 'Attivo' : 'Non attivo' }}
						</span>
					</button>
				</div>
			</div>
		</article>
	</div>
</template>

<style scoped>
.senza-etichetta-card,
.senza-etichetta-card.is-selected,
.senza-etichetta-card.is-idle {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto;
	gap: 14px;
	padding: 16px 18px;
	width: 100%;
	text-align: left;
	border-radius: 22px;
	border: 1.5px solid #c8d9df;
	background: #ffffff;
	box-shadow: 0 10px 18px rgba(20, 37, 48, 0.05);
	animation: none;
}

.senza-etichetta-card.is-idle {
	border-color: #bfd4db;
	background: #fbfefe;
	box-shadow: 0 12px 20px rgba(20, 37, 48, 0.05);
}

.senza-etichetta-card.is-selected {
	border: 2px solid #0b5965;
	background: #f7fbfc;
	box-shadow:
		0 0 0 1px rgba(11, 89, 101, 0.12),
		0 16px 26px rgba(11, 89, 101, 0.12);
}

.senza-etichetta-card:hover,
.senza-etichetta-card:focus-visible {
	transform: translateY(-1px);
}

.senza-etichetta-card::before,
.senza-etichetta-card::after {
	display: none !important;
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

.senza-etichetta-card.is-selected .service-card-tile__icon {
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

.service-card-tile__badge {
	background: #eef6f8;
	color: #0b5965;
	border-radius: 999px;
	font-size: 0.74rem;
	font-weight: 700;
}

.service-card-tile__description {
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

.service-card-tile__footer-row {
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

.service-card-tile__state-pill--accent {
	background: #f2f8fa;
	border-color: #d2e2e6;
	color: #0b5965;
}

.service-card-tile__state-dot {
	width: 8px;
	height: 8px;
	border-radius: 999px;
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

.service-card-tile__switch-label--selected {
	color: #0b5965;
}

@media (max-width: 44.99rem) {
	.senza-etichetta-card,
	.senza-etichetta-card.is-selected,
	.senza-etichetta-card.is-idle {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
