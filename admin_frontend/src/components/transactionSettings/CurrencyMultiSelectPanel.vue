<template>
  <div class="currency-selector">
    <div class="currency-selector-toolbar">
      <div class="currency-selector-search">
        <i class="fas fa-search"></i>
        <input
          v-model="search"
          type="text"
          :placeholder="searchPlaceholder"
          :disabled="disabled"
        />
      </div>
      <div class="currency-selector-meta">
        <span>{{
          tParams("txnSettings_currencyMulti_selected", "{n} selected", {
            n: selectedValues.length,
          })
        }}</span>
        <span>{{
          tParams("txnSettings_currencyMulti_shown", "{n} shown", {
            n: filteredCurrencies.length,
          })
        }}</span>
      </div>
    </div>

    <div v-if="selectedDetails.length > 0" class="currency-selector-selected">
      <button
        v-for="currency in selectedDetails"
        :key="currency.code"
        type="button"
        class="selected-currency-chip"
        :class="{
          'selected-currency-chip--crypto': currency.type === 'crypto',
        }"
        :disabled="disabled"
        @click="toggleCurrency(currency.code)"
      >
        <span>{{ currency.code }}</span>
        <i class="fas fa-times"></i>
      </button>
    </div>

    <div class="currency-selector-list">
      <button
        v-for="currency in filteredCurrencies"
        :key="currency.code"
        type="button"
        :class="[
          'currency-option',
          {
            'currency-option--active': isSelected(currency.code),
            'currency-option--crypto': currency.type === 'crypto',
          },
        ]"
        :disabled="disabled"
        @click="toggleCurrency(currency.code)"
      >
        <span class="currency-option-check">
          <i
            :class="
              isSelected(currency.code)
                ? 'fas fa-check-square'
                : 'far fa-square'
            "
          ></i>
        </span>
        <span class="currency-option-text">
          <span class="currency-option-name">{{ currency.code }}</span>
          <span class="currency-option-label">{{ currency.name }}</span>
        </span>
        <span class="currency-option-type">{{ currency.type }}</span>
      </button>

      <div
        v-if="filteredCurrencies.length === 0"
        class="currency-selector-empty"
      >
        <i class="fas fa-inbox"></i>
        <span>No currencies match your search.</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { tParams } = useAdminI18n();

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  currencies: {
    type: Array,
    default: () => [],
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  searchPlaceholder: {
    type: String,
    default: "Search by currency code...",
  },
});

const emit = defineEmits(["update:modelValue"]);

const search = ref("");

const normalizeCode = (code) =>
  String(code || "")
    .trim()
    .toUpperCase();

const selectedValues = computed(() =>
  Array.isArray(props.modelValue)
    ? props.modelValue.map(normalizeCode).filter(Boolean)
    : [],
);

const normalizedCurrencies = computed(() =>
  (Array.isArray(props.currencies) ? props.currencies : [])
    .map((currency) => ({
      code: normalizeCode(currency.code || currency.currencyCode),
      name: String(
        currency.name ||
          currency.currencyName ||
          currency.code ||
          currency.currencyCode ||
          "",
      ).trim(),
      type:
        String(currency.type || "fiat").toLowerCase() === "crypto"
          ? "crypto"
          : "fiat",
    }))
    .filter((currency) => currency.code),
);

const filteredCurrencies = computed(() => {
  const keyword = search.value.trim().toLowerCase();
  if (!keyword) return normalizedCurrencies.value;

  return normalizedCurrencies.value.filter(
    (currency) =>
      currency.code.toLowerCase().includes(keyword) ||
      currency.name.toLowerCase().includes(keyword),
  );
});

const selectedDetails = computed(() =>
  selectedValues.value.map(
    (code) =>
      normalizedCurrencies.value.find((currency) => currency.code === code) || {
        code,
        name: code,
        type: "fiat",
      },
  ),
);

const isSelected = (code) => selectedValues.value.includes(normalizeCode(code));

const toggleCurrency = (code) => {
  const normalized = normalizeCode(code);
  if (props.disabled || !normalized) return;

  const nextValues = selectedValues.value.includes(normalized)
    ? selectedValues.value.filter((value) => value !== normalized)
    : [...selectedValues.value, normalized];

  emit("update:modelValue", nextValues);
};
</script>

<style scoped>
.currency-selector {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.currency-selector-toolbar {
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
}

.currency-selector-search {
  position: relative;
  flex: 1;
  min-width: 240px;
}

.currency-selector-search i {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-faint);
}

.currency-selector-search input {
  width: 100%;
  padding: 11px 14px 11px 40px;
  border: 1px solid #dbe4f0;
  border-radius: var(--radius-md);
  font-size: 14px;
  color: var(--color-ink);
  background: var(--color-surface);
}

.currency-selector-search input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.14);
}

.currency-selector-meta {
  display: flex;
  gap: 10px;
  font-size: 14px;
  color: var(--color-muted);
}

.currency-selector-selected {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.selected-currency-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 1px solid var(--color-brand-soft);
  background: var(--color-brand-soft);
  color: var(--color-brand);
  border-radius: 999px;
  padding: 7px 12px;
  font-size: 14px;
  cursor: pointer;
}

.selected-currency-chip--crypto {
  border-color: #fcd34d;
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.currency-selector-list {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 10px;
  max-height: 320px;
  overflow-y: auto;
  background: var(--color-surface-soft);
}

.currency-option {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 12px;
  border: none;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  padding: 11px 12px;
  margin-bottom: 8px;
  cursor: pointer;
  text-align: left;
  transition: all 0.18s ease;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.currency-option:last-child {
  margin-bottom: 0;
}

.currency-option:hover {
  transform: translateY(-1px);
  box-shadow:
    inset 0 0 0 1px #cbd5e1,
    0 8px 20px rgba(148, 163, 184, 0.14);
}

.currency-option--active {
  background: var(--color-brand-soft);
  box-shadow: inset 0 0 0 1px #818cf8;
}

.currency-option--active.currency-option--crypto {
  background: var(--color-warning-soft);
  box-shadow: inset 0 0 0 1px var(--color-warning);
}

.currency-option-check {
  width: 18px;
  color: var(--color-brand);
  flex-shrink: 0;
}

.currency-option--crypto .currency-option-check {
  color: var(--color-warning);
}

.currency-option-text {
  min-width: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
}

.currency-option-name {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-ink);
}

.currency-option-label {
  font-size: 14px;
  color: var(--color-muted);
}

.currency-option-type {
  border-radius: 999px;
  padding: 4px 8px;
  font-size: 14px;
  font-weight: 700;
  text-transform: uppercase;
  background: var(--color-info-soft);
  color: var(--color-info);
}

.currency-option--crypto .currency-option-type {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.currency-selector-empty {
  min-height: 120px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  color: var(--color-muted);
  font-size: 14px;
}

@media (max-width: 640px) {
  .currency-selector-toolbar {
    align-items: stretch;
  }

  .currency-selector-search {
    min-width: 100%;
  }
}
</style>
