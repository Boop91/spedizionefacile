<!--
  FILE: components/shipment/ShipmentPickupDateSection.vue
  SCOPO: Sezione data di ritiro con alert errore e carosello Swiper.
-->
<script setup>
import { Swiper, SwiperSlide } from "swiper/vue";
import "swiper/css";
import "swiper/css/navigation";
import { Navigation } from "swiper/modules";

defineProps({
	dateError: { type: String, default: null },
	daysInMonth: { type: Array, required: true },
	services: { type: Object, required: true },
	chooseDate: { type: Function, required: true },
});
</script>

<template>
	<div class="scroll-mt-[120px] w-full">
		<div
			v-if="dateError"
			data-pickup-date-alert
			class="mb-[14px] rounded-[14px] border border-[#F0D28E] bg-[#FFF7E2] px-[16px] py-[14px] text-[#8A5E2E] shadow-[0_8px_18px_rgba(184,134,51,0.08)]"
			role="alert"
			aria-live="polite">
			<div class="flex items-start gap-[10px]">
				<svg xmlns="http://www.w3.org/2000/svg" class="mt-[1px] h-[18px] w-[18px] shrink-0 text-[#C28122]" viewBox="0 0 24 24">
					<path fill="currentColor" d="M11 15h2v2h-2zm0-8h2v6h-2z"/><path fill="currentColor" d="M1 21h22L12 2z"/>
				</svg>
				<div class="min-w-0">
					<p class="text-[0.9375rem] font-bold leading-[1.2]">Imposta il giorno di ritiro</p>
					<p class="mt-[4px] text-[0.875rem] leading-[1.45]">{{ dateError }}</p>
				</div>
			</div>
		</div>
		<div class="pickup-date-card flow-section-shell sf-section-block">
			<div class="pickup-date-card__header sf-section-block__header">
				<h2 class="pickup-date-card__heading sf-section-title text-[#252B42] font-bold font-montserrat tracking-[0.1px]">
					<span>Imposta giorno di ritiro</span>
				</h2>
				<p class="pickup-date-card__note sf-section-description">Scegli il ritiro.</p>
			</div>

			<ClientOnly>
			<div class="pickup-date-slider-shell sf-section-block__body py-[12px]">
				<div class="pickup-date-slider-track relative px-[8px] tablet:px-[35px]">
					<Swiper
						class="my-swiper h-[96px] tablet:h-[108px]"
						:modules="[Navigation]"
						:slides-per-view="3.8"
						:breakpoints="{
							320: { slidesPerView: 3.4, spaceBetween: 8 },
							375: { slidesPerView: 4.1, spaceBetween: 10 },
							520: { slidesPerView: 4.9, spaceBetween: 12 },
							720: { slidesPerView: 5.8, spaceBetween: 14 },
							1024: { slidesPerView: 7, spaceBetween: 14 }
						}"
						space-between="8"
						:navigation="{
							nextEl: '.custom-next',
							prevEl: '.custom-prev',
						}">
						<SwiperSlide v-for="(day, index) in daysInMonth" :key="index">
							<label
								:key="day.date.toISOString()"
								class="pickup-date-option sf-choice-tile"
								:class="{
									'sf-choice-tile--selected': services.date == day.formattedDate,
									'is-selected': services.date == day.formattedDate,
									'is-available': services.date != day.formattedDate && day.weekday !== 'Sab' && day.weekday !== 'Dom',
									'is-disabled': day.weekday === 'Sab' || day.weekday === 'Dom'
								}">
								<span class="pickup-date-option__weekday">
									{{ day.weekday }}
								</span>
								<div class="pickup-date-option__body">
									<span class="pickup-date-option__day">{{ day.dayNumber }}</span>
									<span class="pickup-date-option__month">{{ day.monthAbbr }}</span>
								</div>

								<input
									type="checkbox"
									v-if="day.weekday !== 'Sab' && day.weekday !== 'Dom'"
									@input="chooseDate(day)"
									class="opacity-0 pointer-events-none absolute bottom-0"
									:id="`date-${day.dayNumber}-${day.monthAbbr}`"
									:checked="services.date == day.formattedDate" />
							</label>
						</SwiperSlide>
					</Swiper>

					<!-- Frecce navigazione con touch target 48x48px -->
					<button class="pickup-date-nav custom-prev absolute top-1/2 left-[8px] -translate-y-1/2 cursor-pointer bg-white rounded-[50px] px-[14px] py-[10px] flex items-center justify-center shadow-sm hover:shadow-md transition-shadow duration-300 z-10 border border-[#D0D0D0] hover:border-[#6da8b4]">
						<NuxtImg src="/img/quote/second-step/arrow-left.png" alt="Precedente" width="11" height="19" loading="lazy" decoding="async" />
					</button>
					<button class="pickup-date-nav custom-next absolute top-1/2 right-[8px] -translate-y-1/2 cursor-pointer bg-white rounded-[50px] px-[14px] py-[10px] flex items-center justify-center shadow-sm hover:shadow-md transition-shadow duration-300 z-10 border border-[#D0D0D0] hover:border-[#6da8b4]">
						<NuxtImg src="/img/quote/second-step/arrow-right.png" alt="Successivo" width="11" height="19" loading="lazy" decoding="async" />
					</button>
				</div>
			</div>
			</ClientOnly>
		</div>
	</div>
</template>

<style scoped>
.swiper-slide {
	background: transparent;
	border-radius: 12px;
	overflow: hidden;
}

.pickup-date-card {
	width: 100%;
	min-width: 0;
	padding: 18px 18px 16px;
	border-radius: 22px;
	border: 1.5px solid #d7e3e8;
	background: #ffffff;
	box-shadow: 0 14px 28px rgba(20, 37, 48, 0.06);
}

.pickup-date-card__header {
	width: 100%;
	min-width: 0;
	display: grid;
	gap: 6px;
	padding-inline: 4px;
}

.pickup-date-card__heading {
	font-size: 1.6rem;
	line-height: 1.04;
	letter-spacing: -0.04em;
	color: #1f2a3c;
}

.pickup-date-card__note {
	font-size: 0.86rem;
	line-height: 1.35;
	color: #617385;
}

.pickup-date-slider-shell {
	width: 100%;
	padding-top: 8px;
	padding-bottom: 2px;
	overflow: visible;
}

.pickup-date-slider-track {
	width: 100%;
}

.pickup-date-slider-track,
.my-swiper,
.my-swiper .swiper-wrapper,
.my-swiper .swiper-slide {
	overflow: visible !important;
}

.my-swiper .swiper-slide {
	height: auto;
}

.my-swiper {
	padding-block: 6px;
	margin-block: -6px;
}

.pickup-date-option {
	display: flex;
	flex-direction: column;
	height: 100%;
	overflow: hidden;
	cursor: pointer;
	transition:
		transform var(--sf-motion-base) var(--sf-ease-soft),
		border-color var(--sf-motion-base) var(--sf-ease-soft),
		box-shadow var(--sf-motion-base) var(--sf-ease-soft),
		background-color var(--sf-motion-base) var(--sf-ease-soft);
	border: 1.5px solid #a7bcc5;
	border-radius: var(--sf-radius-control);
	background: #fbfcfd;
	box-shadow: 0 6px 14px rgba(20, 37, 48, 0.04);
}

.pickup-date-option.is-available:hover,
.pickup-date-option.is-available:focus-within {
	border-color: #6b8794;
	box-shadow: 0 10px 18px rgba(20, 37, 48, 0.075);
}

.pickup-date-option__weekday {
	display: block;
	width: 100%;
	text-align: center;
	height: 30px;
	line-height: 30px;
	background: #e8eef2;
	color: #223447;
	font-size: 0.8rem;
	font-weight: 800;
}

.pickup-date-option__body {
	display: flex;
	flex: 1 1 auto;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 1px;
	padding: 10px 0;
	background: #fbfcfd;
}

.pickup-date-option__day {
	font-size: 1.55rem;
	line-height: 1;
	font-weight: 800;
	color: #26384a;
}

.pickup-date-option__month {
	font-size: 0.78rem;
	line-height: 1.1;
	font-weight: 700;
	color: #5d6f82;
}

.pickup-date-option.is-selected {
	border: 2px solid #0b5965;
	background: #ffffff;
	box-shadow:
		0 0 0 1px rgba(11, 89, 101, 0.14),
		0 14px 24px rgba(11, 89, 101, 0.12);
}

.pickup-date-option.is-disabled {
	opacity: 0.48;
	cursor: not-allowed;
	pointer-events: none;
}

.pickup-date-option.is-selected .pickup-date-option__weekday {
	background: #0b5965;
	color: #ffffff;
}

.pickup-date-option.is-selected .pickup-date-option__body {
	background: #ffffff;
}

.pickup-date-option.is-selected .pickup-date-option__day,
.pickup-date-option.is-selected .pickup-date-option__month {
	color: #0b5965;
}

.pickup-date-nav {
	min-width: 40px;
	min-height: 40px;
	border: 1px solid #d5e2e7;
	background: rgba(255, 255, 255, 0.98);
	box-shadow: 0 10px 20px rgba(20, 37, 48, 0.08);
}

.custom-prev img,
.custom-next img {
	margin: 0 auto;
}

.swiper-button-disabled {
	opacity: 0.3;
	cursor: not-allowed;
}

@media (max-width: 44.99rem) {
	.pickup-date-card {
		padding-inline: 12px;
	}

	.pickup-date-card__header {
		padding-inline: 4px;
	}

	.pickup-date-card__heading {
		margin: 0;
		font-size: 1.125rem;
		line-height: 1.1;
	}

	.pickup-date-card__note {
		margin-top: 4px;
		font-size: 0.8125rem;
		line-height: 1.35;
		color: #5f6f81;
		max-width: 17rem;
	}

	.pickup-date-slider-track {
		padding-inline: 0;
	}

	.my-swiper {
		padding-inline: 4px;
	}

	.my-swiper :deep(.swiper-slide label) {
		border-radius: 12px;
	}

	.my-swiper :deep(.swiper-slide span:first-child) {
		height: 30px;
		line-height: 30px;
		font-size: 0.78125rem;
	}

	.my-swiper :deep(.swiper-slide div.flex-1) {
		min-height: 58px;
	}

	.my-swiper :deep(.swiper-slide div.flex-1 > span:first-child) {
		font-size: 2rem;
		line-height: 1;
	}

	.my-swiper :deep(.swiper-slide div.flex-1 > span:last-child) {
		font-size: 0.8125rem;
		line-height: 1.1;
	}

	.custom-prev,
	.custom-next {
		display: none !important;
	}
}

@media (min-width: 45rem) {
	.pickup-date-card {
		padding-inline: 0;
	}

	.pickup-date-card__header {
		padding-left: 78px;
		padding-right: 78px;
		max-width: 34rem;
	}

	.pickup-date-card__heading {
		margin: 0 0 12px;
		font-size: 1.8125rem;
		line-height: 1.05;
	}

	.pickup-date-card__note {
		margin: 0 0 2px;
		font-size: 0.9375rem;
		line-height: 1.45;
		color: #5f6f81;
		max-width: 28rem;
	}

	.pickup-date-slider-track {
		padding-inline: 20px;
	}
}
</style>
