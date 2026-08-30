<template>
  <img v-if="logoUrl" :src="logoUrl" :alt="alt" class="currency-logo-image" />
  <span
    v-else-if="flagCode"
    :class="flagClasses"
    :title="alt"
    aria-hidden="true"
  ></span>
  <span v-else-if="symbol" class="currency-logo-symbol">{{ symbol }}</span>
  <i v-else :class="iconClass || 'fas fa-coins'"></i>
</template>

<script setup>
import { computed } from "vue";
import { getCurrencyLogo, getFiatFlagCode } from "@/utils/currencyLogoRegistry";

const props = defineProps({
  code: {
    type: [String, Number],
    default: "",
  },
  assetType: {
    type: String,
    default: "crypto",
  },
  symbol: {
    type: String,
    default: "",
  },
  iconClass: {
    type: String,
    default: "fas fa-coins",
  },
  alt: {
    type: String,
    default: "Currency logo",
  },
});

const logoUrl = computed(() => getCurrencyLogo(props.code));
const flagCode = computed(() =>
  String(props.assetType || "").toLowerCase() === "fiat"
    ? getFiatFlagCode(props.code)
    : "",
);
const flagClasses = computed(() =>
  flagCode.value ? ["fi", `fi-${flagCode.value}`, "currency-logo-flag"] : [],
);
</script>

<style scoped>
.currency-logo-image {
  width: 24px;
  height: 24px;
  object-fit: contain;
}

.currency-logo-symbol {
  font-size: 18px;
  font-weight: 800;
  line-height: 1;
}

.currency-logo-flag {
  display: block;
  width: 24px;
  height: 18px;
  border-radius: 4px;
  background-position: center;
  background-repeat: no-repeat;
  background-size: cover;
}
</style>
