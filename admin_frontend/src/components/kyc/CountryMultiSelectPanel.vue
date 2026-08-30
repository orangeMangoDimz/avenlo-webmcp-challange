<template>
  <div class="country-selector">
    <div class="country-selector-toolbar">
      <div class="country-selector-search">
        <i class="fas fa-search"></i>
        <input
          v-model="search"
          type="text"
          :placeholder="searchPlaceholder || t('kycTplCountry_searchDefault')"
          :disabled="disabled"
        />
      </div>
      <div class="country-selector-meta">
        <span>{{
          tParams("kycTplCountry_selected", "{n} selected", {
            n: selectedValues.length,
          })
        }}</span>
        <span>{{
          tParams("kycTplCountry_shown", "{n} shown", {
            n: filteredCountries.length,
          })
        }}</span>
      </div>
    </div>

    <div v-if="selectedDetails.length > 0" class="country-selector-selected">
      <button
        v-for="country in selectedDetails"
        :key="country.code"
        type="button"
        class="selected-country-chip"
        :disabled="disabled"
        @click="toggleCountry(country.code)"
      >
        <span
          v-if="country.code !== 'ALL'"
          class="country-flag fi"
          :class="`fi-${getFlagCode(country.code)}`"
        ></span>
        <i v-else class="fas fa-globe"></i>
        <span>{{
          country.code === "ALL"
            ? t("kycTplCountry_allCountries")
            : country.name
        }}</span>
        <i class="fas fa-times"></i>
      </button>
    </div>

    <div class="country-selector-list">
      <button
        v-for="country in filteredCountries"
        :key="country.code"
        type="button"
        :class="[
          'country-option',
          {
            'country-option--active': isSelected(country.code),
            'country-option--disabled': isDisabled(country.code),
          },
        ]"
        :disabled="disabled || isDisabled(country.code)"
        @click="toggleCountry(country.code)"
      >
        <span class="country-option-check">
          <i
            :class="
              isSelected(country.code) ? 'fas fa-check-square' : 'far fa-square'
            "
          ></i>
        </span>
        <span
          v-if="country.code !== 'ALL'"
          class="country-flag fi"
          :class="`fi-${getFlagCode(country.code)}`"
        ></span>
        <span v-else class="country-option-all">
          <i class="fas fa-globe"></i>
        </span>
        <span class="country-option-text">
          <span class="country-option-name">{{
            country.code === "ALL"
              ? t("kycTplCountry_allCountries")
              : country.name
          }}</span>
          <span class="country-option-code">{{ country.code }}</span>
        </span>
        <span
          v-if="isTaken(country.code) && !isSelected(country.code)"
          class="country-option-badge"
        >
          {{ t("kycTplCountry_taken") }}
        </span>
      </button>

      <div v-if="filteredCountries.length === 0" class="country-selector-empty">
        <i class="fas fa-inbox"></i>
        <span>{{ t("kycTplCountry_empty") }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams } = useAdminI18n();

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  countries: {
    type: Array,
    default: () => [],
  },
  takenCountryCodes: {
    type: Array,
    default: () => [],
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  searchPlaceholder: {
    type: String,
    default: "",
  },
});

const emit = defineEmits(["update:modelValue"]);

const search = ref("");

const normalizeCode = (code) => String(code || "").toUpperCase();

const selectedValues = computed(() =>
  Array.isArray(props.modelValue)
    ? props.modelValue.map(normalizeCode).filter(Boolean)
    : [],
);

const takenCodes = computed(() =>
  Array.isArray(props.takenCountryCodes)
    ? props.takenCountryCodes.map(normalizeCode).filter(Boolean)
    : [],
);

const normalizedCountries = computed(() =>
  (Array.isArray(props.countries) ? props.countries : [])
    .map((country) => ({
      ...country,
      code: normalizeCode(country.code),
      name: country.name || country.countryName || country.code || "",
    }))
    .filter((country) => country.code && country.name),
);

const filteredCountries = computed(() => {
  const keyword = search.value.trim().toLowerCase();
  if (!keyword) {
    return normalizedCountries.value;
  }

  return normalizedCountries.value.filter(
    (country) =>
      country.name.toLowerCase().includes(keyword) ||
      country.code.toLowerCase().includes(keyword),
  );
});

const selectedDetails = computed(() =>
  selectedValues.value.map(
    (code) =>
      normalizedCountries.value.find((country) => country.code === code) || {
        code,
        name: code === "ALL" ? "All Countries" : code,
      },
  ),
);

const isTaken = (code) => takenCodes.value.includes(normalizeCode(code));
const isSelected = (code) => selectedValues.value.includes(normalizeCode(code));
const isDisabled = (code) => isTaken(code) && !isSelected(code);

const getFlagCode = (code) => {
  const normalized = normalizeCode(code);
  if (normalized === "ALL") return "un";
  if (normalized === "UK") return "gb";
  if (normalized === "EL") return "gr";
  return normalized.toLowerCase();
};

const toggleCountry = (code) => {
  const normalized = normalizeCode(code);

  if (props.disabled || isDisabled(normalized)) {
    return;
  }

  if (normalized === "ALL") {
    emit("update:modelValue", isSelected("ALL") ? [] : ["ALL"]);
    return;
  }

  const nextValues = selectedValues.value.filter((value) => value !== "ALL");
  const alreadySelected = nextValues.includes(normalized);

  emit(
    "update:modelValue",
    alreadySelected
      ? nextValues.filter((value) => value !== normalized)
      : [...nextValues, normalized],
  );
};
</script>

<style scoped>
.country-selector {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.country-selector-toolbar {
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
}

.country-selector-search {
  position: relative;
  flex: 1;
  min-width: 240px;
}

.country-selector-search i {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-faint);
}

.country-selector-search input {
  width: 100%;
  padding: 11px 14px 11px 40px;
  border: 1px solid #dbe4f0;
  border-radius: var(--radius-md);
  font-size: 14px;
  color: var(--color-ink);
  background: var(--color-surface);
}

.country-selector-search input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.14);
}

.country-selector-meta {
  display: flex;
  gap: 10px;
  font-size: 14px;
  color: var(--color-muted);
}

.country-selector-selected {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.selected-country-chip {
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

.country-selector-list {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 10px;
  max-height: 320px;
  overflow-y: auto;
  background: var(--color-surface-soft);
}

.country-option {
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

.country-option:last-child {
  margin-bottom: 0;
}

.country-option:hover {
  transform: translateY(-1px);
  box-shadow:
    inset 0 0 0 1px #cbd5e1,
    0 8px 20px rgba(148, 163, 184, 0.14);
}

.country-option--active {
  background: var(--color-brand-soft);
  box-shadow: inset 0 0 0 1px #818cf8;
}

.country-option--disabled {
  opacity: 0.55;
  cursor: not-allowed;
  box-shadow: inset 0 0 0 1px #e5e7eb;
}

.country-option--disabled:hover {
  transform: none;
}

.country-option-check {
  width: 18px;
  color: var(--color-brand);
  flex-shrink: 0;
}

.country-flag,
.country-option-all {
  width: 20px;
  flex-shrink: 0;
  text-align: center;
}

.country-option-text {
  min-width: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
}

.country-option-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}

.country-option-code {
  font-size: 14px;
  color: var(--color-muted);
}

.country-option-badge {
  background: var(--color-danger-soft);
  color: var(--color-danger);
  border-radius: 999px;
  padding: 4px 8px;
  font-size: 14px;
  font-weight: 600;
}

.country-selector-empty {
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
  .country-selector-toolbar {
    align-items: stretch;
  }

  .country-selector-search {
    min-width: 100%;
  }
}
</style>
