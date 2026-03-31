<!--
	COMPONENTE: RouteComposer (preventivo/RouteComposer.vue)
	SCOPO: Selezione partenza/destinazione con autocomplete per il preventivo.
	DOVE SI USA: components/Preventivo.vue
-->
<script setup>
defineProps({
	originQuery: { type: String, required: true },
	destQuery: { type: String, required: true },
	originSuggestions: { type: Array, default: () => [] },
	destSuggestions: { type: Array, default: () => [] },
	showOriginSuggestions: { type: Boolean, default: false },
	showDestSuggestions: { type: Boolean, default: false },
	originCountryCode: { type: String, required: true },
	destinationCountryCode: { type: String, required: true },
	europeCountryOptions: { type: Array, default: () => [] },
	isOriginItaly: { type: Boolean, default: true },
	isDestinationItaly: { type: Boolean, default: true },
	originPlaceholder: { type: String, default: '' },
	destinationPlaceholder: { type: String, default: '' },
	originLocationError: { type: String, default: '' },
	destLocationError: { type: String, default: '' },
	originPostalCode: { type: String, default: '' },
	destinationPostalCode: { type: String, default: '' },
	locationKeyFn: { type: Function, required: true },
	getProvinceLabelFn: { type: Function, required: true },
});

defineEmits([
	'update:originQuery',
	'update:destQuery',
	'update:originCountryCode',
	'update:destinationCountryCode',
	'selectOriginLocation',
	'selectDestLocation',
	'settleOriginQuery',
	'settleDestQuery',
	'originQueryFocus',
	'originQueryInput',
	'destQueryFocus',
	'destQueryInput',
	'hideOriginSuggestions',
	'hideDestSuggestions',
	'originManualInput',
	'originManualBlur',
	'destManualInput',
	'destManualBlur',
	'applyOriginCountrySelection',
	'applyDestinationCountrySelection',
]);
</script>

<template>
	<section class="preventivo-section" aria-labelledby="preventivo-tratta-title">
		<h3 id="preventivo-tratta-title" class="preventivo-section__title">
			Inserisci la tratta
		</h3>
		<p class="preventivo-section__lead">
			Inserisci comune o CAP di ritiro e consegna. Per l'Italia puoi usare uno dei due.
		</p>

		<div class="route-composer">
			<div class="route-composer__grid">
				<!-- ORIGIN -->
				<div class="route-card route-card--origin">
					<div class="route-card__header">
						<div class="route-card__heading">
							<div class="route-card__badge route-card__badge--origin" aria-hidden="true">
								<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
									<path d="M12 21s-6-4.35-6-10a6 6 0 1 1 12 0c0 5.65-6 10-6 10Z"></path>
									<circle cx="12" cy="11" r="2.5"></circle>
								</svg>
							</div>
							<p class="route-card__title">Partenza</p>
						</div>
						<div class="route-card__header-side route-card__header-side--country">
							<label for="origin_country_code" class="sr-only">Paese di partenza</label>
							<select
								id="origin_country_code"
								:value="originCountryCode"
								class="route-card__country-chip"
								@change="$emit('update:originCountryCode', ($event.target).value); $emit('applyOriginCountrySelection')">
								<option
									v-for="country in europeCountryOptions"
									:key="country.code"
									:value="country.code">
									{{ country.label }}
								</option>
							</select>
						</div>
					</div>
					<div class="route-card__field">
						<label for="origin_city" class="sr-only">Citta o CAP di ritiro</label>
						<div class="route-card__input-wrap relative" :class="{ 'is-open': showOriginSuggestions && originSuggestions.length }">
							<input
								id="origin_city"
								:value="originQuery"
								type="text"
								required
								autocomplete="off"
								:placeholder="originPlaceholder"
								class="input-preventivo-rapido input-preventivo-rapido--location"
								@input="$emit('update:originQuery', ($event.target).value); isOriginItaly ? $emit('originQueryInput') : $emit('originManualInput')"
								@focus="isOriginItaly ? $emit('originQueryFocus') : $emit('hideOriginSuggestions')"
								@blur="isOriginItaly ? $emit('settleOriginQuery') : $emit('originManualBlur')" />
							<input type="hidden" :value="originPostalCode" id="origin_postal_code" />
							<ul v-if="showOriginSuggestions && originSuggestions.length" role="listbox" class="location-suggestions-list">
								<li
									v-for="loc in originSuggestions"
									:key="locationKeyFn(loc)"
									role="option"
									aria-selected="false"
									@mousedown.prevent="$emit('selectOriginLocation', loc)"
									class="location-suggestion">
									<span class="location-suggestion__city">{{ loc.place_name }}</span>
									<span class="location-suggestion__meta">
										{{ loc.postal_code }}
										<template v-if="getProvinceLabelFn(loc)"> &middot; {{ getProvinceLabelFn(loc) }}</template>
									</span>
								</li>
							</ul>
						</div>
						<div class="route-card__feedback">
							<p v-if="originLocationError" class="route-card__error">
								{{ originLocationError }}
							</p>
						</div>
					</div>
				</div>

				<!-- CONNECTOR ARROW -->
				<div class="route-composer__connector" aria-hidden="true">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M5 12h14"></path>
						<path d="M12 5l7 7-7 7"></path>
					</svg>
				</div>

				<!-- DESTINATION -->
				<div class="route-card route-card--destination">
					<div class="route-card__header route-card__header--destination">
						<div class="route-card__heading">
							<div class="route-card__badge route-card__badge--destination" aria-hidden="true">
								<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
									<path d="M12 21s-6-4.35-6-10a6 6 0 1 1 12 0c0 5.65-6 10-6 10Z"></path>
									<circle cx="12" cy="11" r="2.5"></circle>
								</svg>
							</div>
							<p class="route-card__title">Destinazione</p>
						</div>
						<div class="route-card__header-side route-card__header-side--country">
							<label for="destination_country_code" class="sr-only">Paese di destinazione</label>
							<select
								id="destination_country_code"
								:value="destinationCountryCode"
								class="route-card__country-chip"
								@change="$emit('update:destinationCountryCode', ($event.target).value); $emit('applyDestinationCountrySelection')">
								<option
									v-for="country in europeCountryOptions"
									:key="country.code"
									:value="country.code">
									{{ country.label }}
								</option>
							</select>
						</div>
					</div>
					<div class="route-card__field route-card__field--destination">
						<label for="destination_city" class="sr-only">Citta o CAP di consegna</label>
						<div class="route-card__input-wrap relative" :class="{ 'is-open': showDestSuggestions && destSuggestions.length }">
							<input
								id="destination_city"
								:value="destQuery"
								type="text"
								required
								autocomplete="off"
								:placeholder="destinationPlaceholder"
								class="input-preventivo-rapido input-preventivo-rapido--location"
								@input="$emit('update:destQuery', ($event.target).value); isDestinationItaly ? $emit('destQueryInput') : $emit('destManualInput')"
								@focus="isDestinationItaly ? $emit('destQueryFocus') : $emit('hideDestSuggestions')"
								@blur="isDestinationItaly ? $emit('settleDestQuery') : $emit('destManualBlur')" />
							<input type="hidden" :value="destinationPostalCode" id="destination_postal_code" />
							<ul v-if="showDestSuggestions && destSuggestions.length" role="listbox" class="location-suggestions-list">
								<li
									v-for="loc in destSuggestions"
									:key="locationKeyFn(loc)"
									role="option"
									aria-selected="false"
									@mousedown.prevent="$emit('selectDestLocation', loc)"
									class="location-suggestion">
									<span class="location-suggestion__city">{{ loc.place_name }}</span>
									<span class="location-suggestion__meta">
										{{ loc.postal_code }}
										<template v-if="getProvinceLabelFn(loc)"> &middot; {{ getProvinceLabelFn(loc) }}</template>
									</span>
								</li>
							</ul>
						</div>
						<div class="route-card__feedback">
							<p v-if="destLocationError" class="route-card__error">
								{{ destLocationError }}
							</p>
						</div>
					</div>
				</div>
			</div>
			<p v-if="!isDestinationItaly" class="route-composer__note" aria-live="polite">
				<span class="route-composer__note-label">Europa monocollo</span>
				<span class="route-composer__note-text">Prezzo basato su paese, peso e volume.</span>
			</p>
		</div>
	</section>
</template>

<style scoped>
.route-composer {
	padding: 0.7rem;
	border-radius: var(--quote-card-radius, 16px);
	background: var(--quote-shell-bg, #e6e9ee);
	box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.04);
}

.route-composer__grid {
	display: grid;
	gap: 0.62rem;
}

.route-card {
	min-width: 0;
}

.route-card__header {
	display: flex;
	align-items: center;
	gap: 0.5rem;
	margin-bottom: 0.45rem;
}

.route-card__heading {
	display: inline-flex;
	align-items: center;
	gap: 0.45rem;
	min-width: 0;
	flex: 1 1 auto;
}

.route-card__header-side--country {
	margin-left: auto;
}

.route-card__badge {
	width: 1.375rem;
	height: 1.375rem;
	border-radius: 999px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex: 0 0 auto;
}

.route-card__badge--origin {
	background: rgba(228, 66, 3, 0.1);
	color: #e44203;
}

.route-card__badge--destination {
	background: rgba(9, 88, 102, 0.1);
	color: #095866;
}

.route-card__title {
	margin: 0;
	font-size: 0.95rem;
	line-height: 1.2;
	font-weight: 700;
	letter-spacing: -0.01em;
	color: var(--quote-text-body, #555);
}

.route-card__field {
	display: grid;
	gap: 0.35rem;
}

.route-composer__note {
	margin: 0.7rem 0 0;
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: flex-end;
	gap: 0.35rem 0.5rem;
	font-size: 0.78rem;
	line-height: 1.25;
	color: #095866;
}

.route-composer__note-label {
	display: inline-flex;
	align-items: center;
	height: 1.5rem;
	padding: 0 0.65rem;
	border-radius: 999px;
	background: rgba(9, 88, 102, 0.1);
	font-weight: 700;
	white-space: nowrap;
}

.route-composer__note-text {
	font-weight: 600;
}

.route-card__country-chip {
	flex: 0 0 auto;
	min-width: 6.125rem;
	height: 2.125rem;
	padding: 0 1.4rem 0 0.65rem;
	border-radius: var(--quote-small-radius, 10px);
	border: 1.5px solid var(--quote-neutral-ring, #dfe2e7);
	background: #fff;
	color: var(--quote-text-strong, #1d2738);
	font-size: 0.875rem;
	font-weight: 600;
	cursor: pointer;
	appearance: none;
	background-image:
		linear-gradient(45deg, transparent 50%, #999 50%),
		linear-gradient(135deg, #999 50%, transparent 50%);
	background-position:
		calc(100% - 0.88rem) calc(50% - 2px),
		calc(100% - 0.62rem) calc(50% - 2px);
	background-size: 5px 5px, 5px 5px;
	background-repeat: no-repeat;
	transition: border-color 180ms ease, background-color 180ms ease, box-shadow 180ms ease;
}

.route-card__country-chip:hover {
	background-color: #f5f6f8;
}

.route-card__country-chip:focus {
	outline: none;
	border-color: rgba(9, 88, 102, 0.25);
	box-shadow: 0 0 0 3px rgba(9, 88, 102, 0.1);
}

.route-card__country-chip:disabled {
	background-color: #ffffff;
	box-shadow: none;
}

.route-card__field .input-preventivo-rapido--location {
	width: 100%;
	height: 3rem;
	min-height: 3rem;
	border-radius: var(--quote-control-radius, 12px);
	margin-top: 0;
	padding: 0 0.875rem;
	border: 1.5px solid var(--quote-neutral-ring, #dfe2e7);
	background: #fff;
	color: var(--quote-text-strong, #1d2738);
	font-size: 1rem;
	font-weight: 600;
	line-height: 1.2;
	box-shadow: none;
	transition: border-color 180ms ease, box-shadow 180ms ease, background-color 180ms ease;
}

.route-card__field .input-preventivo-rapido--location::placeholder {
	color: #aab2bc;
	font-weight: 600;
}

.route-card__field .input-preventivo-rapido--location:focus {
	border-color: rgba(9, 88, 102, 0.25);
	box-shadow: 0 0 0 3px rgba(9, 88, 102, 0.1);
	outline: none;
}

.route-card__input-wrap {
	position: relative;
}

.route-card__input-wrap.is-open .input-preventivo-rapido--location {
	border-color: rgba(9, 88, 102, 0.25);
	box-shadow: 0 0 0 3px rgba(9, 88, 102, 0.08);
}

.location-suggestions-list {
	position: absolute;
	top: calc(100% + 8px);
	left: 0;
	right: 0;
	z-index: 30;
	display: grid;
	gap: 0.15rem;
	padding: 0.32rem;
	border-radius: 0.95rem;
	border: 1px solid var(--quote-neutral-ring, #dfe2e7);
	background: #fff;
	box-shadow: 0 14px 28px rgba(29, 39, 56, 0.08);
	list-style: none;
	margin: 0;
}

.location-suggestion {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 0.75rem;
	padding: 0.7rem 0.85rem;
	border-radius: 0.8rem;
	cursor: pointer;
	transition: background-color 160ms ease;
}

.location-suggestion:hover {
	background: #f5f6f8;
}

.location-suggestion__city {
	min-width: 0;
	font-size: 0.98rem;
	font-weight: 700;
	color: var(--quote-text-strong, #1d2738);
}

.location-suggestion__meta {
	flex: 0 0 auto;
	font-size: 0.82rem;
	font-weight: 700;
	color: #7b8797;
}

.route-card__error {
	margin-top: 0;
	font-size: 0.8125rem;
	font-weight: 600;
	color: var(--quote-error-fg, #e44203);
}

.route-card__feedback {
	min-height: 1.35rem;
	display: flex;
	align-items: flex-start;
	padding-top: 0.35rem;
}

.route-composer__connector {
	display: none;
	align-items: flex-end;
	justify-content: center;
	padding-bottom: 0.8rem;
	color: #7a8190;
}

@media (min-width: 640px) {
	.route-composer {
		padding: 1.125rem;
	}

	.route-composer__grid {
		grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
		align-items: end;
		gap: 0.75rem;
	}

	.route-composer__note {
		margin-top: 0.6rem;
	}

	.route-composer__connector {
		display: flex;
	}

	.route-card__field .input-preventivo-rapido--location {
		height: 3.125rem;
		min-height: 3.125rem;
	}
}

@media (min-width: 1024px) {
	.route-composer {
		padding: 1.125rem 1.25rem;
	}

	.route-composer__grid {
		gap: 0.9rem;
	}
}
</style>
