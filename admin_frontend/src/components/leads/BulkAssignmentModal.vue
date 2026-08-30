<template>
  <Teleport to="body">
    <transition name="modal">
      <div v-if="modelValue" class="bulk-assignment-modal" @click.self="close">
        <div class="bulk-assignment-modal-content">
          <div class="bulk-assignment-modal-header">
            <h3>
              <i class="fas fa-user-tie"></i>
              {{ headerTitle }}
            </h3>
            <button class="bulk-assignment-modal-close" @click="close">
              ×
            </button>
          </div>

          <div class="bulk-assignment-modal-body">
            <div class="selected-items-list">
              <h4>
                <i class="fas fa-users"></i>
                {{ selectedTitle }}
                <span class="count-badge">{{ totalItems }}</span>
              </h4>

              <div v-if="totalItems" class="selected-items-container">
                <div
                  v-for="item in displayItems"
                  :key="itemKey(item)"
                  class="selected-item"
                >
                  <div class="selected-item-avatar">
                    {{ getInitials(item.firstName, item.lastName) }}
                  </div>
                  <div class="selected-item-info">
                    <div class="selected-item-name">
                      {{ item.firstName }} {{ item.lastName }}
                    </div>
                    <div class="selected-item-email">{{ item.email }}</div>
                  </div>
                </div>
              </div>
              <div v-else class="selected-items-empty">
                <i class="fas fa-user-slash"></i>
                <span>{{ emptySelectionText }}</span>
              </div>
            </div>

            <div class="bulk-assignment-section">
              <div class="bulk-assignment-content">
                <div class="bulk-assignment-field">
                  <label for="bulk-manager">{{ assignLabel }}</label>
                  <select
                    id="bulk-manager"
                    v-model="selectedManagerId"
                    :disabled="loadingManagers"
                  >
                    <option value="">
                      {{ t("bulkAssign_selectManager") }}
                    </option>
                    <option
                      v-for="manager in normalizedManagers"
                      :key="manager.id"
                      :value="manager.id"
                    >
                      {{ formatManagerLabel(manager) }}
                    </option>
                  </select>
                </div>

                <div class="bulk-assignment-info">
                  <div class="bulk-assignment-info-item">
                    <span class="bulk-assignment-info-label">{{
                      totalLabel
                    }}</span>
                    <span class="bulk-assignment-info-value">{{
                      totalItems
                    }}</span>
                  </div>
                  <div class="bulk-assignment-info-item">
                    <span class="bulk-assignment-info-label">{{
                      t("bulkAssign_assignmentDate")
                    }}</span>
                    <span class="bulk-assignment-info-value">{{
                      todayDate
                    }}</span>
                  </div>
                  <div class="bulk-assignment-info-item">
                    <span class="bulk-assignment-info-label">{{
                      t("bulkAssign_assignedBy")
                    }}</span>
                    <span class="bulk-assignment-info-value">{{
                      assignedBy
                    }}</span>
                  </div>
                </div>

                <div class="bulk-assignment-field" style="grid-column: 1 / -1">
                  <label for="bulk-assignment-notes">{{
                    t("bulkAssign_notesLabel")
                  }}</label>
                  <textarea
                    id="bulk-assignment-notes"
                    v-model="notes"
                    :placeholder="notesPlaceholder"
                    rows="4"
                  ></textarea>
                </div>
              </div>
            </div>
          </div>

          <div class="bulk-assignment-modal-footer">
            <button class="btn-modal-bulk btn-modal-bulk-cancel" @click="close">
              {{ t("common_cancel") }}
            </button>
            <button
              class="btn-modal-bulk btn-modal-bulk-assign"
              @click="confirm"
              :disabled="!selectedManagerId || !totalItems || loadingManagers"
            >
              <i class="fas fa-user-check"></i>
              {{ confirmText }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { useAuthStore } from "@/stores/auth";
import { clientService } from "@/services/clientListService";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams, languageStore } = useAdminI18n();

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  selectedItems: {
    type: Array,
    default: () => [],
  },
  selectedLeads: {
    type: Array,
    default: () => [],
  },
  managerOptions: {
    type: Array,
    default: () => [],
  },
  context: {
    type: String,
    default: "lead",
  },
});

const emit = defineEmits(["update:modelValue", "confirm"]);

const authStore = useAuthStore();

const selectedManagerId = ref("");
const notes = ref("");
const internalManagers = ref([]);
const loadingManagers = ref(false);
const managersLoaded = ref(false);

const todayDate = computed(() => {
  const today = new Date();
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return today.toLocaleDateString(loc, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
});

const assignedBy = computed(
  () =>
    authStore.user?.fullName ||
    authStore.user?.username ||
    t("bulkAssign_adminUser"),
);

const contextType = computed(() =>
  props.context === "client" ? "client" : "lead",
);
const isClientContext = computed(() => contextType.value === "client");

const headerTitle = computed(() =>
  isClientContext.value
    ? t("bulkAssign_headerClients")
    : t("bulkAssign_headerLeads"),
);
const selectedTitle = computed(() =>
  isClientContext.value
    ? t("bulkAssign_selectedClients")
    : t("bulkAssign_selectedLeads"),
);
const emptySelectionText = computed(() =>
  isClientContext.value
    ? t("bulkAssign_noClientsSelected")
    : t("bulkAssign_noLeadsSelected"),
);
const assignLabel = computed(() => t("bulkAssign_assignLabel"));
const notesPlaceholder = computed(() => t("bulkAssign_notesPlaceholder"));
const totalLabel = computed(() =>
  isClientContext.value
    ? t("bulkAssign_totalClients")
    : t("bulkAssign_totalLeads"),
);
const confirmText = computed(() =>
  isClientContext.value
    ? t("bulkAssign_confirmClients")
    : t("bulkAssign_confirmLeads"),
);

const itemsSource = computed(() => {
  if (props.selectedItems && props.selectedItems.length) {
    return props.selectedItems;
  }
  return props.selectedLeads;
});

const displayItems = computed(() =>
  (itemsSource.value || []).map((item) => ({
    id: item.id ?? item.leadId ?? item.clientId ?? null,
    firstName: item.firstName ?? "",
    lastName: item.lastName ?? "",
    email: item.email ?? "",
  })),
);

const totalItems = computed(() => displayItems.value.length);

const normalizedManagers = computed(() => {
  const source =
    props.managerOptions && props.managerOptions.length
      ? props.managerOptions
      : internalManagers.value;

  return (source || []).map((manager) => ({
    id: manager.id ?? manager.value ?? null,
    name: manager.name ?? manager.fullName ?? manager.label ?? null,
    email: manager.email ?? null,
  }));
});

const itemKey = (item) =>
  item.id ?? `${item.firstName}-${item.lastName}-${item.email}`;

const getInitials = (firstName, lastName) => {
  const first = firstName ? firstName.charAt(0) : "";
  const last = lastName ? lastName.charAt(0) : "";
  const initials = (first + last).toUpperCase();
  return initials || "ID";
};

const formatManagerLabel = (manager) => {
  if (manager.name && manager.email) {
    return `${manager.name} (${manager.email})`;
  }
  return (
    manager.name ||
    manager.email ||
    tParams("leadDetail_managerNum", "Manager #{n}", {
      n: String(manager.id ?? ""),
    })
  );
};

const resetForm = () => {
  selectedManagerId.value = "";
  notes.value = "";
};

const close = () => {
  emit("update:modelValue", false);
  resetForm();
};

const confirm = () => {
  if (!selectedManagerId.value) {
    alert(t("bulkAssign_alertSelectManager"));
    return;
  }
  if (!totalItems.value) {
    alert(t("bulkAssign_alertSelectItems"));
    return;
  }

  emit("confirm", {
    managerId: Number(selectedManagerId.value),
    notes: notes.value.trim(),
  });

  close();
};

const loadManagers = async () => {
  if (loadingManagers.value || managersLoaded.value) return;

  loadingManagers.value = true;
  try {
    const response = await clientService.getAssignableSales();
    const managers = response?.data?.managers ?? response?.managers ?? [];
    internalManagers.value = Array.isArray(managers) ? managers : [];
    managersLoaded.value = true;
  } catch (error) {
    console.error("Failed to load sales representative list:", error);
    internalManagers.value = [];
  } finally {
    loadingManagers.value = false;
  }
};

watch(
  () => props.modelValue,
  async (isOpen) => {
    if (isOpen) {
      if (
        (!props.managerOptions || !props.managerOptions.length) &&
        !managersLoaded.value
      ) {
        await loadManagers();
      }
    } else {
      resetForm();
    }
  },
);
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
  border-bottom: 2px solid var(--color-border);
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

.selected-items-list {
  background: var(--color-surface-soft);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 25px;
}

.selected-items-list h4 {
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

.selected-items-container {
  max-height: 200px;
  overflow-y: auto;
}

.selected-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  margin-bottom: 8px;
  border: 1px solid var(--color-border);
}

.selected-item:last-child {
  margin-bottom: 0;
}

.selected-item-avatar {
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

.selected-item-info {
  flex: 1;
}

.selected-item-name {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
  margin-bottom: 2px;
}

.selected-item-email {
  font-size: 12px;
  color: var(--color-muted);
}

.selected-items-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 20px;
  color: var(--color-faint);
}

.selected-items-empty i {
  font-size: 28px;
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
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  outline: none;
  transition: all 0.3s ease;
  background: var(--color-surface);
}

.bulk-assignment-field select:focus,
.bulk-assignment-field textarea:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
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
  border-top: 2px solid var(--color-border);
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
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-modal-bulk-cancel {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  color: var(--color-text);
}

.btn-modal-bulk-cancel:hover {
  border-color: var(--color-faint);
}

.btn-modal-bulk-assign {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 10px 20px rgba(var(--color-brand-rgb), 0.25);
}

.btn-modal-bulk-assign:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  box-shadow: none;
}

.btn-modal-bulk-assign:hover:not(:disabled) {
  box-shadow: 0 12px 24px rgba(var(--color-brand-rgb), 0.35);
  transform: translateY(-1px);
}
</style>
