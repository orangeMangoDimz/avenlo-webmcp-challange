<template>
  <div class="modal-overlay show" @click="$emit('close')">
    <div class="modal" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          <i class="fas fa-globe"></i> {{ t("kycTplCountriesModal_title") }}
        </h2>
        <button class="modal-close" @click="$emit('close')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">
            <i class="fas fa-flag"></i>
            {{ t("kycTplCountriesModal_label_select") }}
          </label>
          <CountryMultiSelectPanel
            v-model="selectedCountries"
            :countries="availableCountries"
            :taken-country-codes="takenCountryCodes"
            :search-placeholder="t('kycTplCountry_searchDefault')"
          />
          <small
            style="
              color: var(--color-muted);
              font-size: 12px;
              margin-top: 10px;
              display: block;
            "
          >
            <i class="fas fa-info-circle"></i>
            {{ t("kycTplCountriesModal_hint") }}
          </small>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" @click="$emit('close')">
          {{ t("kycTplModal_btn_cancel") }}
        </button>
        <button class="btn btn-primary" @click="handleSave">
          <i class="fas fa-save"></i> {{ t("kycTplCountriesModal_btn_save") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { kycTemplateService } from "@/services/kycTemplateService";
import { useCountryStore } from "@/stores/countryStore";
import CountryMultiSelectPanel from "@/components/kyc/CountryMultiSelectPanel.vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams } = useAdminI18n();

const props = defineProps({
  templateId: {
    type: Number,
    required: true,
  },
  selectedCountries: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["close", "save"]);

const countryStore = useCountryStore();
const availableCountries = computed(() => countryStore.countries);
const takenCountryCodes = ref([]);

const selectedCountries = ref(
  (Array.isArray(props.selectedCountries) ? props.selectedCountries : [])
    .map((item) => {
      if (!item) return "";
      if (typeof item === "string") return String(item).toUpperCase();
      if (item.countryCode) return String(item.countryCode).toUpperCase();
      if (item.code) return String(item.code).toUpperCase();
      return "";
    })
    .filter(Boolean),
);

const fetchTakenCodes = async () => {
  try {
    const res = await kycTemplateService.getTakenCountryCodes(props.templateId);
    const list =
      res && res.data && res.data.takenCountryCodes
        ? res.data.takenCountryCodes
        : res && res.takenCountryCodes
          ? res.takenCountryCodes
          : [];
    if (Array.isArray(list)) takenCountryCodes.value = list;
  } catch (e) {
    console.error("Failed to load taken country codes", e);
  }
};

onMounted(async () => {
  if (!countryStore.loaded) {
    countryStore.fetchCountries(true);
  }
  await fetchTakenCodes();
});

watch(
  selectedCountries,
  (newVal, oldVal) => {
    const newArr = Array.isArray(newVal)
      ? newVal.map((v) => String(v).toUpperCase())
      : [];
    const oldArr = Array.isArray(oldVal)
      ? oldVal.map((v) => String(v).toUpperCase())
      : [];
    const added = newArr.filter((x) => !oldArr.includes(x));
    const addedNonAll = added.filter((x) => x !== "ALL");

    let target = null;
    if (added.length > 0) {
      if (addedNonAll.length > 0 && newArr.includes("ALL")) {
        target = newArr.filter((v) => v !== "ALL");
      } else if (added.includes("ALL")) {
        target = ["ALL"];
      } else {
        // user added non-ALL countries and ALL not present -> keep newArr as-is
        target = newArr;
      }
    } else {
      target = newArr;
    }

    // only update if different to avoid recursive updates
    const currentNormalized = Array.isArray(selectedCountries.value)
      ? selectedCountries.value.map((v) => String(v).toUpperCase())
      : [];
    const sameLength = currentNormalized.length === target.length;
    const areEqual =
      sameLength && currentNormalized.every((v, i) => v === target[i]);
    if (!areEqual) {
      selectedCountries.value = target;
    }
  },
  { deep: true },
);

const handleSave = async () => {
  if (selectedCountries.value.length === 0) {
    alert(t("kycTplCountriesModal_alert_needCountry"));
    return;
  }

  const countriesData = selectedCountries.value.map((code) => ({
    code,
    name: availableCountries.value.find((c) => c.code === code)?.name || code,
  }));

  try {
    const response = await kycTemplateService.updateCountries(
      props.templateId,
      {
        countries: countriesData,
      },
    );

    if (response.success) {
      alert(t("kycTplCountriesModal_alert_ok"));
      emit("save", response.data.countries);
    } else {
      alert(
        tParams("kycTplCountriesModal_alert_failed", "Failed: {msg}", {
          msg: response.message || t("common_unknownError"),
        }),
      );
    }
  } catch (error) {
    console.error("Failed to update countries:", error);
    alert(t("kycTplCountriesModal_alert_err"));
  }
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-overlay.show {
  display: flex;
}

.modal {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 0;
  max-width: 700px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
  animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-header {
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-surface-soft);
}

.modal-title {
  font-size: 20px;
  font-weight: 600;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.modal-title i {
  color: var(--color-brand);
}

.modal-close {
  background: var(--color-border);
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  font-size: 18px;
  color: var(--color-text);
}

.modal-close:hover {
  background: var(--color-brand-solid);
  color: white;
}

.modal-body {
  padding: 30px;
}

.form-group {
  margin-bottom: 20px;
}

.form-label {
  display: block;
  margin-bottom: 10px;
  color: var(--color-ink);
  font-weight: 600;
  font-size: 14px;
}

.modal-footer {
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  background: var(--color-surface-soft);
}

.btn {
  padding: 12px 20px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.btn-secondary {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-secondary:hover {
  background: var(--color-border-strong);
}
</style>
