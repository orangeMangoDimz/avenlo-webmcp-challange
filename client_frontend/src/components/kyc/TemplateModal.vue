<template>
  <div class="modal-overlay show" @click="$emit('close')">
    <div class="modal" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          <i class="fas fa-plus-circle"></i>
          {{ template ? "Edit" : "Create New" }} KYC Template
        </h2>
        <button class="modal-close" @click="$emit('close')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <form @submit.prevent="handleSubmit">
          <div class="form-group">
            <label class="form-label" for="templateName">Template Name *</label>
            <input
              type="text"
              class="form-input"
              id="templateName"
              v-model="formData.name"
              placeholder="Enter template name..."
              required
            />
          </div>

          <div class="form-group">
            <label class="form-label" for="templateDescription"
              >Description</label
            >
            <textarea
              class="form-textarea"
              id="templateDescription"
              v-model="formData.description"
              placeholder="Enter template description..."
            ></textarea>
          </div>

          <div class="form-group">
            <label class="form-label" for="templateCountries"
              >Applied Countries *</label
            >
            <select
              class="form-select"
              id="templateCountries"
              v-model="formData.countries"
              multiple
              style="min-height: 120px"
              required
            >
              <option value="all">All Countries</option>
              <option value="us">United States</option>
              <option value="uk">United Kingdom</option>
              <option value="ca">Canada</option>
              <option value="au">Australia</option>
              <option value="de">Germany</option>
              <option value="fr">France</option>
              <option value="sg">Singapore</option>
              <option value="hk">Hong Kong</option>
              <option value="jp">Japan</option>
            </select>
            <small
              style="
                color: var(--color-muted);
                font-size: 12px;
                margin-top: 5px;
                display: block;
              "
            >
              Hold Ctrl (Cmd on Mac) to select multiple countries
            </small>
          </div>

          <div class="form-group">
            <label class="form-label" for="templateStatus">Status</label>
            <select
              class="form-select"
              id="templateStatus"
              v-model="formData.status"
            >
              <option value="draft">Draft</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" @click="$emit('close')">
          Cancel
        </button>
        <button class="btn btn-primary" @click="handleSubmit">
          <i class="fas fa-save"></i>
          {{ template ? "Update" : "Create" }} Template
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  template: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(["close", "save"]);

const formData = ref({
  name: "",
  description: "",
  countries: [],
  status: "draft",
});

watch(
  () => props.template,
  (newVal) => {
    if (newVal) {
      formData.value = {
        name: newVal.name || "",
        description: newVal.description || "",
        countries: newVal.countries?.map((c) => c.code || c) || [],
        status: newVal.status || "draft",
      };
    }
  },
  { immediate: true },
);

const handleSubmit = () => {
  if (!formData.value.name || formData.value.countries.length === 0) {
    alert("Please fill in all required fields.");
    return;
  }

  emit("save", formData.value);
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
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text);
  margin-bottom: 8px;
}

.form-input,
.form-select,
.form-textarea {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  border-color: var(--color-brand);
  box-shadow: none;
}

.form-textarea {
  resize: vertical;
  min-height: 80px;
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
