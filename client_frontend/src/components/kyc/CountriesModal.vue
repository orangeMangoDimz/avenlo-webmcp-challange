<template>
  <div class="modal-overlay show" @click="$emit('close')">
    <div class="modal" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          <i class="fas fa-globe"></i> Edit Applied Countries
        </h2>
        <button class="modal-close" @click="$emit('close')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">
            <i class="fas fa-flag"></i> Select Countries *
          </label>
          <div class="countries-grid">
            <label
              v-for="country in availableCountries"
              :key="country.code"
              class="country-checkbox-item"
            >
              <input
                type="checkbox"
                :value="country.code"
                v-model="selectedCountries"
                :disabled="country.code === 'all' && allCountriesSelected"
              />
              <span>{{ country.name }}</span>
            </label>
          </div>
          <small
            style="
              color: var(--color-muted);
              font-size: 14px;
              margin-top: 10px;
              display: block;
            "
          >
            <i class="fas fa-info-circle"></i>
            Select "All Countries" to apply this template globally, or choose
            specific countries.
          </small>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" @click="$emit('close')">
          Cancel
        </button>
        <button class="btn btn-primary" @click="handleSave">
          <i class="fas fa-save"></i> Save Countries
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { kycTemplateService } from "@/services/kycTemplateService";

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

const availableCountries = [
  { code: "all", name: "All Countries" },
  { code: "us", name: "United States" },
  { code: "uk", name: "United Kingdom" },
  { code: "ca", name: "Canada" },
  { code: "au", name: "Australia" },
  { code: "de", name: "Germany" },
  { code: "fr", name: "France" },
  { code: "it", name: "Italy" },
  { code: "es", name: "Spain" },
  { code: "sg", name: "Singapore" },
  { code: "hk", name: "Hong Kong" },
  { code: "jp", name: "Japan" },
  { code: "cn", name: "China" },
  { code: "in", name: "India" },
  { code: "br", name: "Brazil" },
  { code: "mx", name: "Mexico" },
];

const selectedCountries = ref(props.selectedCountries.map((c) => c.code || c));

const allCountriesSelected = computed(() => {
  return selectedCountries.value.includes("all");
});

watch(allCountriesSelected, (newVal) => {
  if (newVal) {
    // If "All Countries" is selected, clear other selections
    selectedCountries.value = ["all"];
  }
});

const handleSave = async () => {
  if (selectedCountries.value.length === 0) {
    alert("⚠️ Please select at least one country.");
    return;
  }

  const countriesData = selectedCountries.value.map((code) => ({
    code,
    name: availableCountries.find((c) => c.code === code)?.name || code,
  }));

  try {
    const response = await kycTemplateService.updateCountries(
      props.templateId,
      {
        countries: countriesData,
      },
    );

    if (response.success) {
      alert("✓ Countries updated successfully!");
      emit("save", countriesData);
    } else {
      alert(`Failed to update countries: ${response.message}`);
    }
  } catch (error) {
    console.error("Failed to update countries:", error);
    alert("Failed to update countries. Please try again.");
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
  border-bottom: 1px solid var(--color-border);
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

.countries-grid {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 15px;
  max-height: 400px;
  overflow-y: auto;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 10px;
}

.countries-grid::-webkit-scrollbar {
  width: 6px;
}

.countries-grid::-webkit-scrollbar-track {
  background: var(--color-surface-soft);
}

.countries-grid::-webkit-scrollbar-thumb {
  background: var(--color-border-strong);
  border-radius: 3px;
}

.country-checkbox-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px;
  cursor: pointer;
  border-radius: var(--radius-sm);
  transition: all 0.2s ease;
}

.country-checkbox-item:hover {
  background: var(--color-brand-soft);
}

.country-checkbox-item input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: var(--color-brand);
  cursor: pointer;
}

.country-checkbox-item span {
  font-size: 14px;
  color: var(--color-ink);
  font-weight: 500;
}

.modal-footer {
  padding: 20px 30px;
  border-top: 1px solid var(--color-border);
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
