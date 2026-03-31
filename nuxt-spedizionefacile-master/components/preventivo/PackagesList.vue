<!--
	COMPONENTE: PackagesList (preventivo/PackagesList.vue)
	SCOPO: Lista colli con tipo, quantita, dimensioni e peso.
	DOVE SI USA: components/Preventivo.vue
-->
<script setup>
const props = defineProps({
	packages: { type: Array, required: true },
	packageTypeList: { type: Array, required: true },
	isEuropeMonocollo: { type: Boolean, default: false },
	europeRestrictionMessage: { type: String, default: '' },
	messageError: { type: Object, default: null },
	sv: { type: Object, required: true },
});

const emit = defineEmits([
	'updatePackageType',
	'deletePack',
	'addPackageInline',
	'calcQuantity',
	'incrementQuantity',
	'decrementQuantity',
	'onWeightInput',
	'onWeightBlur',
	'onDimInput',
	'onDimBlur',
]);
</script>

<template>
	<section class="preventivo-section preventivo-section--packages" aria-labelledby="preventivo-colli-title">
		<h3 id="preventivo-colli-title" class="preventivo-section__title">
			Inserisci misure e peso
		</h3>

		<Transition name="dimensions-section" mode="out-in">
			<div v-if="packages.length > 0" class="dimensions-wrapper">
				<p
					v-if="isEuropeMonocollo"
					class="package-restriction-note">
					{{ europeRestrictionMessage }}
				</p>

				<ul class="package-entry-list">
					<li
						v-for="(pack, packIndex) in packages"
						:key="pack._qid || packIndex"
						class="package-entry">
						<div class="package-entry__header">
							<div class="package-type-switcher" :aria-label="`Tipo collo ${packIndex + 1}`">
								<button
									v-for="packageType in packageTypeList"
									:key="packageType.text"
									type="button"
									@click="$emit('updatePackageType', pack, packageType.text)"
									:aria-pressed="pack.package_type === packageType.text"
									:class="[
										'package-type-switcher__button',
										pack.package_type === packageType.text ? 'package-type-switcher__button--active' : ''
									]">
									<span class="package-type-switcher__icon-wrap">
										<img
											:src="`/img/quote/first-step/${packageType.img}`"
											alt=""
											:width="packageType.width"
											:height="packageType.height"
											class="package-type-switcher__icon-image"
											loading="eager"
											decoding="async"
											draggable="false" />
									</span>
									<span>{{ packageType.text }}</span>
								</button>
							</div>

							<button v-if="packages.length > 1" type="button" class="package-entry__delete" @click="$emit('deletePack', pack._qid || packIndex)" :aria-label="'Elimina pacco ' + (packIndex + 1)">
								<NuxtImg src="/img/quote/first-step/trash.png" alt="" width="18" height="22" class="package-entry__delete-icon" loading="lazy" decoding="async" />
							</button>
						</div>

						<div class="package-entry__grid">
							<!-- QUANTITY -->
							<div class="package-field-card package-field-card--quantity">
								<label :for="'quantity_' + packIndex" class="package-field-card__label">Q.ta</label>
								<div class="package-field-card__input-wrap package-field-card__input-wrap--stepper">
									<div class="quantity-stepper quantity-stepper--embedded">
										<button
											type="button"
											class="quantity-stepper__button"
											@click="$emit('decrementQuantity', pack)"
											:aria-label="`Riduci quantita collo ${packIndex + 1}`"
											:disabled="isEuropeMonocollo">
											<span class="quantity-stepper__symbol" aria-hidden="true">&minus;</span>
										</button>
										<input
											:id="'quantity_' + packIndex"
											v-model="pack.quantity"
											type="text"
											inputmode="numeric"
											pattern="[0-9]*"
											class="quantity-stepper__input"
											:aria-describedby="`quantity_help_${packIndex}`"
											:aria-label="`Quantita collo ${packIndex + 1}`"
											:readonly="isEuropeMonocollo"
											@input="$emit('calcQuantity', pack)"
											@blur="$emit('calcQuantity', pack)" />
										<button
											type="button"
											class="quantity-stepper__button"
											@click="$emit('incrementQuantity', pack)"
											:aria-label="`Aumenta quantita collo ${packIndex + 1}`"
											:disabled="isEuropeMonocollo">
											<span class="quantity-stepper__symbol" aria-hidden="true">+</span>
										</button>
									</div>
								</div>
								<span :id="`quantity_help_${packIndex}`" class="sr-only">
									Numero di colli identici da spedire. Il prezzo viene moltiplicato per la quantita.
								</span>
								<div class="package-field-card__feedback">
									<p v-if="messageError?.[`packages.${packIndex}.quantity`]" class="package-field-card__error">
										{{ messageError[`packages.${packIndex}.quantity`][0] }}
									</p>
								</div>
							</div>

							<!-- WEIGHT -->
							<div class="package-field-card">
								<label :for="'weight_' + packIndex" class="package-field-card__label">Peso</label>
								<div class="package-field-card__input-wrap">
									<input type="text" placeholder="0" v-model="pack.weight" :id="'weight_' + packIndex" :class="sv.errorClass(`peso_${packIndex}`, 'package-metric-input')" @input="$emit('onWeightInput', pack, packIndex)" @blur="$emit('onWeightBlur', pack, packIndex)" required />
									<span class="package-field-card__unit">kg</span>
								</div>
								<div class="package-field-card__feedback">
									<p v-if="sv.getError(`peso_${packIndex}`)" class="package-field-card__error">{{ sv.getError(`peso_${packIndex}`) }}</p>
									<p v-else-if="messageError?.[`packages.${packIndex}.weight`]" class="package-field-card__error">
										{{ messageError[`packages.${packIndex}.weight`][0] }}
									</p>
								</div>
							</div>

							<!-- FIRST SIZE -->
							<div class="package-field-card">
								<label :for="'first_size_' + packIndex" class="package-field-card__label">Lung.</label>
								<div class="package-field-card__input-wrap">
									<input type="text" placeholder="0" v-model="pack.first_size" :id="'first_size_' + packIndex" :class="sv.errorClass(`first_size_${packIndex}`, 'package-metric-input')" @input="$emit('onDimInput', pack, packIndex, 'first_size', 'Lato 1')" @blur="$emit('onDimBlur', pack, packIndex, 'first_size', 'Lato 1')" required />
									<span class="package-field-card__unit">cm</span>
								</div>
								<div class="package-field-card__feedback">
									<p v-if="sv.getError(`first_size_${packIndex}`)" class="package-field-card__error">{{ sv.getError(`first_size_${packIndex}`) }}</p>
									<p v-else-if="messageError?.[`packages.${packIndex}.first_size`]" class="package-field-card__error">
										{{ messageError[`packages.${packIndex}.first_size`][0] }}
									</p>
								</div>
							</div>

							<!-- SECOND SIZE -->
							<div class="package-field-card">
								<label :for="'second_size_' + packIndex" class="package-field-card__label">Larg.</label>
								<div class="package-field-card__input-wrap">
									<input type="text" placeholder="0" v-model="pack.second_size" :id="'second_size_' + packIndex" :class="sv.errorClass(`second_size_${packIndex}`, 'package-metric-input')" @input="$emit('onDimInput', pack, packIndex, 'second_size', 'Lato 2')" @blur="$emit('onDimBlur', pack, packIndex, 'second_size', 'Lato 2')" required />
									<span class="package-field-card__unit">cm</span>
								</div>
								<div class="package-field-card__feedback">
									<p v-if="sv.getError(`second_size_${packIndex}`)" class="package-field-card__error">{{ sv.getError(`second_size_${packIndex}`) }}</p>
									<p v-else-if="messageError?.[`packages.${packIndex}.second_size`]" class="package-field-card__error">
										{{ messageError[`packages.${packIndex}.second_size`][0] }}
									</p>
								</div>
							</div>

							<!-- THIRD SIZE -->
							<div class="package-field-card">
								<label :for="'third_size_' + packIndex" class="package-field-card__label">Alt.</label>
								<div class="package-field-card__input-wrap">
									<input type="text" placeholder="0" v-model="pack.third_size" :id="'third_size_' + packIndex" :class="sv.errorClass(`third_size_${packIndex}`, 'package-metric-input')" @input="$emit('onDimInput', pack, packIndex, 'third_size', 'Lato 3')" @blur="$emit('onDimBlur', pack, packIndex, 'third_size', 'Lato 3')" required />
									<span class="package-field-card__unit">cm</span>
								</div>
								<div class="package-field-card__feedback">
									<p v-if="sv.getError(`third_size_${packIndex}`)" class="package-field-card__error">{{ sv.getError(`third_size_${packIndex}`) }}</p>
									<p v-else-if="messageError?.[`packages.${packIndex}.third_size`]" class="package-field-card__error">
										{{ messageError[`packages.${packIndex}.third_size`][0] }}
									</p>
								</div>
							</div>
						</div>
					</li>
				</ul>

				<div v-if="!isEuropeMonocollo" class="add-package-button-wrapper">
					<button
						type="button"
						@click="$emit('addPackageInline')"
						class="add-package-btn">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.35" stroke-linecap="round" stroke-linejoin="round">
							<path d="M12 5v14"/>
							<path d="M5 12h14"/>
						</svg>
						Aggiungi collo
					</button>
				</div>

				<p
					v-if="messageError?.packages && packages.length > 0"
					class="preventivo-inline-error">
					{{ messageError.packages[0] }}
				</p>
			</div>
		</Transition>
	</section>
</template>

<style scoped>
.package-restriction-note {
	margin: 0 0 0.75rem;
	padding: 0.65rem 0.9rem;
	border-radius: 999px;
	background: rgba(228, 66, 3, 0.08);
	color: #e44203;
	font-size: 0.84rem;
	font-weight: 700;
	text-align: center;
}

.package-entry-list {
	display: grid;
	gap: 0.625rem;
	margin: 0;
	padding: 0;
	list-style: none;
}

.package-entry {
	padding: 0.75rem 0.875rem 0.95rem;
	border-radius: var(--quote-card-radius, 16px);
	background: var(--quote-shell-bg, #e6e9ee);
	box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.04);
}

.package-entry__header {
	display: flex;
	align-items: center;
	justify-content: flex-start;
	gap: 0.75rem;
	margin-bottom: 0.75rem;
}

.package-type-switcher {
	display: inline-flex;
	align-items: center;
	gap: 3px;
	width: fit-content;
	max-width: 100%;
	min-width: 0;
	padding: 3px;
	border-radius: 999px;
	background: var(--quote-selector-shell-bg, #d5d9e0);
}

.package-entry__header .package-type-switcher {
	flex: 0 0 auto;
	max-width: 100%;
}

.package-type-switcher__button {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 5px;
	flex: 0 0 auto;
	min-height: 36px;
	min-width: 0;
	padding: 0 14px;
	border-radius: 999px;
	border: 0;
	background: transparent;
	color: var(--quote-text-muted, #666);
	font-size: 14px;
	font-weight: 500;
	line-height: 1;
	cursor: pointer;
	transition:
		transform var(--sf-motion-base, 200ms) var(--sf-ease-soft, ease),
		background-color var(--sf-motion-base, 200ms) var(--sf-ease-soft, ease),
		color var(--sf-motion-base, 200ms) var(--sf-ease-soft, ease),
		box-shadow var(--sf-motion-base, 200ms) var(--sf-ease-soft, ease);
}

.package-type-switcher__button span:last-child {
	white-space: nowrap;
}

.package-type-switcher__button:not(.package-type-switcher__button--active):hover,
.package-type-switcher__button:not(.package-type-switcher__button--active):focus-visible {
	background: var(--quote-selector-hover-bg, rgba(255, 255, 255, 0.42));
	color: #333;
	transform: translateY(-1px);
	outline: none;
}

.package-type-switcher__button--active {
	background: var(--quote-selector-active-bg, #e44203);
	color: var(--quote-selector-active-fg, #ffffff);
	font-weight: 700;
	box-shadow: 0 2px 8px rgba(228, 66, 3, 0.25);
}

.package-type-switcher__button--active:hover,
.package-type-switcher__button--active:focus-visible {
	background: var(--quote-selector-active-bg, #e44203);
	color: var(--quote-selector-active-fg, #ffffff);
	transform: translateY(-1px);
	outline: none;
}

.package-type-switcher__icon-wrap {
	width: 17px;
	height: 17px;
	flex: 0 0 17px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
}

.package-type-switcher__icon-image {
	display: block;
	max-width: 17px;
	max-height: 17px;
	width: auto;
	height: auto;
	object-fit: contain;
	object-position: center;
	filter: none;
	opacity: 1;
	flex-shrink: 0;
	transition: filter var(--sf-motion-fast, 150ms) var(--sf-ease-soft, ease), transform var(--sf-motion-fast, 150ms) var(--sf-ease-soft, ease);
}

.package-type-switcher__button--active .package-type-switcher__icon-image {
	filter: var(--quote-active-icon-filter, brightness(0) saturate(100%) invert(100%));
}

.package-entry__delete {
	margin-left: auto;
	width: 2.125rem;
	height: 2.125rem;
	min-width: 2.125rem;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	border-radius: 999px;
	border: 0;
	background: transparent;
	color: #c0c5cc;
	cursor: pointer;
	transition:
		background-color var(--sf-motion-base, 200ms) var(--sf-ease-soft, ease),
		color var(--sf-motion-base, 200ms) var(--sf-ease-soft, ease),
		transform var(--sf-motion-fast, 150ms) var(--sf-ease-soft, ease);
}

.package-entry__delete:hover {
	color: #e44203;
	background: var(--quote-error-bg, #fff5f2);
	transform: translateY(-1px);
}

.package-entry__delete-icon {
	display: block;
	width: 0.9rem;
	height: auto;
	object-fit: contain;
}

.package-entry__grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 0.5rem;
	align-items: start;
}

.package-field-card {
	padding: 0;
	border: 0;
	background: transparent;
	min-width: 0;
}

.package-field-card--quantity {
	min-width: 0;
}

.package-field-card__label {
	display: block;
	margin-bottom: 0.3125rem;
	font-size: 0.75rem;
	line-height: 1;
	font-weight: 700;
	letter-spacing: 0.3px;
	text-transform: uppercase;
	color: var(--quote-text-subtle, #777);
}

.package-field-card__input-wrap {
	position: relative;
}

.package-field-card__unit {
	position: absolute;
	right: 0.625rem;
	top: 50%;
	transform: translateY(-50%);
	font-size: 0.625rem;
	font-weight: 600;
	color: var(--quote-text-soft, #b8bcc4);
	pointer-events: none;
}

.package-field-card__error {
	margin-top: 0;
	font-size: 0.8125rem;
	font-weight: 600;
	color: var(--quote-error-fg, #e44203);
}

.preventivo-inline-error {
	margin-top: 0;
	font-size: 0.8125rem;
	font-weight: 600;
	color: var(--quote-error-fg, #e44203);
	text-align: center;
}

.package-field-card__feedback {
	min-height: 1.35rem;
	display: flex;
	align-items: flex-start;
	padding-top: 0.35rem;
}

.add-package-button-wrapper {
	display: flex;
	justify-content: center;
	margin-top: 0.5rem;
}

.add-package-btn {
	display: inline-flex;
	align-items: center;
	gap: 0.4375rem;
	height: 2.625rem;
	padding: 0 1.375rem;
	border-radius: 999px;
	border: 1.5px solid rgba(9, 88, 102, 0.2);
	background: transparent;
	color: #095866;
	font-size: 0.875rem;
	font-weight: 600;
	transition:
		transform var(--sf-motion-base, 200ms) var(--sf-ease-soft, ease),
		background-color var(--sf-motion-base, 200ms) var(--sf-ease-soft, ease),
		border-color var(--sf-motion-base, 200ms) var(--sf-ease-soft, ease),
		box-shadow var(--sf-motion-base, 200ms) var(--sf-ease-soft, ease);
	cursor: pointer;
}

.add-package-btn:hover {
	background: rgba(9, 88, 102, 0.06);
	border-color: rgba(9, 88, 102, 0.35);
	box-shadow: 0 10px 20px rgba(9, 88, 102, 0.1);
	transform: translateY(-1px);
}

.quantity-stepper {
	display: flex;
	align-items: center;
	width: 100%;
	height: 3rem;
	min-height: 3rem;
	border: 1.5px solid var(--quote-neutral-ring, #dfe2e7);
	background: #fff;
	border-radius: var(--quote-control-radius, 12px);
	overflow: hidden;
	transition: border-color 180ms ease, box-shadow 180ms ease;
}

.quantity-stepper:focus-within {
	border-color: rgba(9, 88, 102, 0.25);
	box-shadow: 0 0 0 3px rgba(9, 88, 102, 0.1);
}

.quantity-stepper__button {
	width: 30px;
	flex: 0 0 30px;
	height: 100%;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	background: transparent;
	color: #b0b5bd;
	border: 0;
	padding: 0;
	min-width: 0;
	cursor: pointer;
	font-size: 1rem;
	font-weight: 700;
	line-height: 1;
	transition:
		color var(--sf-motion-fast, 150ms) var(--sf-ease-soft, ease),
		background-color var(--sf-motion-fast, 150ms) var(--sf-ease-soft, ease),
		transform var(--sf-motion-fast, 150ms) var(--sf-ease-soft, ease);
}

.quantity-stepper__button:hover,
.quantity-stepper__button:focus-visible {
	color: var(--quote-text-strong, #1d2738);
	background: rgba(9, 88, 102, 0.04);
	outline: none;
}

.quantity-stepper__button:disabled {
	color: #c7ccd4;
	cursor: default;
}

.quantity-stepper__symbol {
	display: inline-block;
	line-height: 1;
}

.quantity-stepper__input {
	height: 100%;
	width: 100%;
	flex: 1 1 auto;
	padding: 0;
	border: 0;
	text-align: center;
	color: var(--quote-text-strong, #1d2738);
	font-size: 1.0625rem;
	font-weight: 800;
	line-height: 1;
	background: transparent;
	appearance: textfield;
	-moz-appearance: textfield;
}

.quantity-stepper__input:focus {
	outline: none;
	box-shadow: none;
}

.quantity-stepper__input[readonly] {
	cursor: default;
}

.quantity-stepper__input::-webkit-outer-spin-button,
.quantity-stepper__input::-webkit-inner-spin-button {
	-webkit-appearance: none;
	margin: 0;
}

/* package-metric-input needs global scope because it's applied via sv.errorClass */
:deep(.package-metric-input) {
	width: 100%;
	height: 3rem;
	min-height: 3rem;
	padding: 0 1.75rem 0 0.625rem;
	border: 1.5px solid var(--quote-neutral-ring, #dfe2e7);
	border-radius: var(--quote-control-radius, 12px);
	background: #fff;
	color: var(--quote-text-strong, #1d2738);
	font-size: 1rem;
	font-weight: 700;
	line-height: 1;
	transition: border-color 180ms ease, box-shadow 180ms ease;
}

:deep(.package-metric-input):focus {
	outline: none;
	border-color: rgba(9, 88, 102, 0.25);
	box-shadow: 0 0 0 3px rgba(9, 88, 102, 0.1);
}

.dimensions-section-enter-active,
.dimensions-section-leave-active {
	transition: none;
}

.dimensions-section-enter-from,
.dimensions-section-enter-to,
.dimensions-section-leave-from,
.dimensions-section-leave-to {
	opacity: 1;
}

@media (min-width: 640px) {
	.package-entry {
		padding: 0.8125rem 1rem 1rem;
	}

	.package-entry__header {
		align-items: center;
	}

	.package-type-switcher {
		width: fit-content;
		max-width: 100%;
		min-width: 0;
	}

	.package-type-switcher__button {
		min-height: 36px;
		padding-inline: 18px;
	}

	.package-entry__grid {
		grid-template-columns: 6rem repeat(4, minmax(0, 1fr));
		gap: 0.5rem;
	}

	.package-field-card--quantity {
		max-width: 6rem;
	}

	.quantity-stepper,
	:deep(.package-metric-input) {
		height: 3.125rem;
		min-height: 3.125rem;
	}
}

@media (min-width: 1024px) {
	.package-entry__grid {
		gap: 0.75rem;
	}
}
</style>
