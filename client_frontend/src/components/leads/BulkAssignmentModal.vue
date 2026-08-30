<template>
  <Teleport to="body">
    <transition name="modal">
      <div v-if="modelValue" class="bulk-assignment-modal" @click.self="close">
        <div class="bulk-assignment-modal-content">
          <div class="bulk-assignment-modal-header">
            <h3>
              <i class="fas fa-user-tie"></i> Bulk Assign Leads to Sales
              Representative
            </h3>
            <button class="bulk-assignment-modal-close" @click="close">
              ×
            </button>
          </div>

          <div class="bulk-assignment-modal-body">
            <!-- Selected Leads List -->
            <div class="selected-leads-list">
              <h4>
                <i class="fas fa-users"></i> Selected Leads
                <span class="count-badge">{{ selectedLeads.length }}</span>
              </h4>
              <div class="selected-leads-container">
                <div
                  v-for="lead in selectedLeads"
                  :key="lead.leadId"
                  class="selected-lead-item"
                >
                  <div class="selected-lead-avatar">
                    {{ getInitials(lead.firstName, lead.lastName) }}
                  </div>
                  <div class="selected-lead-info">
                    <div class="selected-lead-name">
                      {{ lead.firstName }} {{ lead.lastName }}
                    </div>
                    <div class="selected-lead-email">{{ lead.email }}</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Assignment Section -->
            <div class="bulk-assignment-section">
              <div class="bulk-assignment-content">
                <div class="bulk-assignment-field">
                  <label for="bulk-sales-rep"
                    >Assign to Sales Representative</label
                  >
                  <select id="bulk-sales-rep" v-model="salesRep">
                    <option value="">-- Select Sales Rep --</option>
                    <option value="john-williams">John Williams</option>
                    <option value="sarah-davis">Sarah Davis</option>
                    <option value="michael-brown">Michael Brown</option>
                    <option value="emily-taylor">Emily Taylor</option>
                    <option value="david-wilson">David Wilson</option>
                  </select>
                </div>

                <div class="bulk-assignment-info">
                  <div class="bulk-assignment-info-item">
                    <span class="bulk-assignment-info-label">Total Leads:</span>
                    <span class="bulk-assignment-info-value">{{
                      selectedLeads.length
                    }}</span>
                  </div>
                  <div class="bulk-assignment-info-item">
                    <span class="bulk-assignment-info-label"
                      >Assignment Date:</span
                    >
                    <span class="bulk-assignment-info-value">{{
                      todayDate
                    }}</span>
                  </div>
                  <div class="bulk-assignment-info-item">
                    <span class="bulk-assignment-info-label">Assigned By:</span>
                    <span class="bulk-assignment-info-value">{{
                      assignedBy
                    }}</span>
                  </div>
                </div>

                <div class="bulk-assignment-field" style="grid-column: 1 / -1">
                  <label for="bulk-assignment-notes"
                    >Follow-up Notes (Optional)</label
                  >
                  <textarea
                    id="bulk-assignment-notes"
                    v-model="notes"
                    placeholder="Add notes for the assigned sales representative..."
                    rows="4"
                  ></textarea>
                </div>
              </div>
            </div>
          </div>

          <div class="bulk-assignment-modal-footer">
            <button class="btn-modal-bulk btn-modal-bulk-cancel" @click="close">
              Cancel
            </button>
            <button
              class="btn-modal-bulk btn-modal-bulk-assign"
              @click="confirm"
              :disabled="!salesRep"
            >
              <i class="fas fa-user-check"></i> Assign Leads
            </button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<script setup>
import { ref, computed } from "vue";
import { useAuthStore } from "@/stores/auth";

defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  selectedLeads: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["update:modelValue", "confirm"]);

const authStore = useAuthStore();

const salesRep = ref("");
const notes = ref("");

const assignedBy = computed(() => {
  return authStore.user?.username || "Admin User";
});

const todayDate = computed(() => {
  const today = new Date();
  return today.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
});

const getInitials = (firstName, lastName) => {
  const first = firstName ? firstName.charAt(0) : "";
  const last = lastName ? lastName.charAt(0) : "";
  return (first + last).toUpperCase();
};

const close = () => {
  emit("update:modelValue", false);
  // Reset form
  salesRep.value = "";
  notes.value = "";
};

const confirm = () => {
  if (!salesRep.value) {
    alert("⚠️ Please select a sales representative before assigning.");
    return;
  }

  emit("confirm", {
    salesRep: salesRep.value,
    notes: notes.value,
  });

  close();
};
</script>

<style scoped>
.bulk-assignment-modal {
  display: flex;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 2000;
  align-items: center;
  justify-content: center;
}

.bulk-assignment-modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  max-width: 700px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.bulk-assignment-modal-header {
  padding: 25px 30px;
  border-bottom: 1px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-surface-soft);
}

.bulk-assignment-modal-header h3 {
  font-size: 20px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.bulk-assignment-modal-header h3 i {
  color: var(--color-brand);
  font-size: 22px;
}

.bulk-assignment-modal-close {
  background: none;
  border: none;
  font-size: 28px;
  color: var(--color-faint);
  cursor: pointer;
  transition: all 0.2s ease;
  line-height: 1;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-sm);
}

.bulk-assignment-modal-close:hover {
  color: var(--color-brand);
  background: var(--color-brand-soft);
}

.bulk-assignment-modal-body {
  padding: 30px;
}

.selected-leads-list {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 25px;
}

.selected-leads-list h4 {
  font-size: 14px;
  color: var(--color-text);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.count-badge {
  background: var(--color-brand-solid);
  color: white;
  padding: 2px 10px;
  border-radius: var(--radius-lg);
  font-size: 12px;
}

.selected-leads-container {
  max-height: 200px;
  overflow-y: auto;
}

.selected-lead-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  margin-bottom: 8px;
  border: 1px solid var(--color-border);
}

.selected-lead-item:last-child {
  margin-bottom: 0;
}

.selected-lead-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--color-brand-solid);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  font-size: 13px;
  flex-shrink: 0;
}

.selected-lead-info {
  flex: 1;
}

.selected-lead-name {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
  margin-bottom: 2px;
}

.selected-lead-email {
  font-size: 12px;
  color: var(--color-muted);
}

.bulk-assignment-section {
  background: var(--color-surface);
  border-radius: var(--radius-md);
}

.bulk-assignment-content {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.bulk-assignment-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.bulk-assignment-field label {
  font-weight: 600;
  color: var(--color-text);
  font-size: 13px;
}

.bulk-assignment-field select,
.bulk-assignment-field textarea {
  padding: 10px 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;

  transition: all 0.3s ease;
  background: var(--color-surface);
}

.bulk-assignment-field select:focus,
.bulk-assignment-field textarea:focus {
  border-color: var(--color-brand);
  box-shadow: none;
}

.bulk-assignment-field textarea {
  resize: vertical;
  font-family: inherit;
}

.bulk-assignment-info {
  background: var(--color-surface-soft);
  padding: 15px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
}

.bulk-assignment-info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid var(--color-border);
}

.bulk-assignment-info-item:last-child {
  border-bottom: none;
}

.bulk-assignment-info-label {
  font-weight: 600;
  color: var(--color-muted);
  font-size: 13px;
}

.bulk-assignment-info-value {
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 500;
}

.bulk-assignment-modal-footer {
  padding: 20px 30px;
  border-top: 1px solid var(--color-border);
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  background: var(--color-surface-soft);
}

.btn-modal-bulk {
  padding: 12px 24px;
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

.btn-modal-bulk-cancel {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-modal-bulk-cancel:hover {
  background: var(--color-border-strong);
}

.btn-modal-bulk-assign {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.btn-modal-bulk-assign:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.btn-modal-bulk-assign:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Modal transition */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-active .bulk-assignment-modal-content,
.modal-leave-active .bulk-assignment-modal-content {
  transition: transform 0.3s ease;
}

.modal-enter-from .bulk-assignment-modal-content,
.modal-leave-to .bulk-assignment-modal-content {
  transform: translateY(-20px);
}

@media (max-width: 768px) {
  .bulk-assignment-content {
    grid-template-columns: 1fr;
  }
}
</style>
