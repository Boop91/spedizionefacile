<!--
	COMPONENTE: PromoBanner (preventivo/PromoBanner.vue)
	SCOPO: Banner promozionale condizionale sopra il CTA.
	DOVE SI USA: components/Preventivo.vue
-->
<script setup>
defineProps({
	promoSettings: { type: Object, default: null },
});
</script>

<template>
	<div v-if="promoSettings?.active && promoSettings?.label_text" class="flex justify-center mt-[20px] desktop:mt-[16px]">
		<span
			:style="{ backgroundColor: promoSettings.label_color || '#E44203' }"
			class="inline-flex items-center gap-[6px] px-[14px] py-[6px] rounded-[8px] text-white text-[0.875rem] font-bold tracking-wide shadow-sm">
			<img v-if="promoSettings.label_image" :src="promoSettings.label_image" alt="" loading="lazy" decoding="async" width="40" height="18" class="h-[18px] w-auto" />
			{{ promoSettings.label_text }}
		</span>
	</div>
</template>
