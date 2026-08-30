<template>
  <div class="modal-overlay show" @click="$emit('close')">
    <div class="modal" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          <i class="fas fa-plus-circle"></i>
          {{
            template
              ? t("kycTplModal_title_edit")
              : t("kycTplModal_title_create")
          }}
        </h2>
        <button class="modal-close" @click="$emit('close')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <form @submit.prevent="handleSubmit">
          <div class="form-group">
            <label class="form-label" for="templateName">{{
              t("kycTplModal_label_name")
            }}</label>
            <input
              type="text"
              class="form-input"
              id="templateName"
              v-model="formData.name"
              :placeholder="t('kycTplModal_placeholder_name')"
              required
            />
          </div>

          <div class="form-group">
            <label class="form-label" for="templateDescription">{{
              t("kycTplModal_label_description")
            }}</label>
            <textarea
              class="form-textarea"
              id="templateDescription"
              v-model="formData.description"
              :placeholder="t('kycTplModal_placeholder_description')"
            ></textarea>
          </div>

          <div class="form-group">
            <label class="form-label" for="templateCountries">{{
              t("kycTplModal_label_countries")
            }}</label>
            <CountryMultiSelectPanel
              v-model="formData.countries"
              :countries="availableCountries"
              :taken-country-codes="takenCountryCodes"
              :search-placeholder="t('kycTplCountry_searchDefault')"
            />
            <small
              style="
                color: var(--color-muted);
                font-size: 12px;
                margin-top: 5px;
                display: block;
              "
            >
              {{ t("kycTplModal_hint_countries") }}
            </small>
          </div>

          <div class="form-group">
            <label class="form-label" for="templateStatus">{{
              t("kycTplModal_label_status")
            }}</label>
            <select
              class="form-select"
              id="templateStatus"
              v-model="formData.status"
            >
              <option value="draft">{{ t("kycTpl_status_draft") }}</option>
              <option value="active">{{ t("kycTpl_status_active") }}</option>
              <option value="inactive">
                {{ t("kycTpl_status_inactive") }}
              </option>
            </select>
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" @click="$emit('close')">
          {{ t("kycTplModal_btn_cancel") }}
        </button>
        <button class="btn btn-primary" @click="handleSubmit">
          <i class="fas fa-save"></i>
          {{
            template ? t("kycTplModal_btn_update") : t("kycTplModal_btn_create")
          }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed, onMounted } from "vue";
import { useCountryStore } from "@/stores/countryStore";
import { kycTemplateService } from "@/services/kycTemplateService";
import CountryMultiSelectPanel from "@/components/kyc/CountryMultiSelectPanel.vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();

const props = defineProps({
  template: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(["close", "save"]);

const countryStore = useCountryStore();
const availableCountries = computed(() => countryStore.countries);
const takenCountryCodes = ref([]);

const formData = ref({
  name: "",
  description: "",
  countries: [],
  status: "draft",
});

const fetchTakenCodes = async () => {
  try {
    const excludeId = props.template?.id ?? props.template?.templateId ?? null;
    const res = await kycTemplateService.getTakenCountryCodes(excludeId);
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
  () => props.template,
  async (newVal) => {
    if (newVal) {
      formData.value = {
        name: newVal.name || "",
        description: newVal.description || "",
        countries: newVal.countries?.map((c) => c.code || c) || [],
        status: newVal.status || "draft",
      };
    } else {
      formData.value = {
        name: "",
        description: "",
        countries: [],
        status: "draft",
      };
    }
    await fetchTakenCodes();
  },
  { immediate: true },
);

const handleSubmit = () => {
  if (!formData.value.name || formData.value.countries.length === 0) {
    alert(t("kycTplModal_alert_required"));
    return;
  }

  // 将 countries 从 code 数组转为 { code, name } 对象数组，使用 availableCountries 查找名称
  const countriesPayload = formData.value.countries.map((code) => {
    const found = availableCountries.value.find((c) => c.code === code);
    return {
      code: (code || "").toString(),
      name: found ? found.name : code,
    };
  });

  emit("save", { ...formData.value, countries: countriesPayload });
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
  max-width: 600px;
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
  font-size: 14px;
  font-weight: 800;
  color: var(--color-ink);
  margin-bottom: 8px;
  letter-spacing: 0.01em;
}

.form-input,
.form-select,
.form-textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.form-textarea {
  resize: vertical;
  min-height: 80px;
}

.option-disabled,
option:disabled {
  color: var(--color-faint);
  background: var(--color-surface-soft);
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
