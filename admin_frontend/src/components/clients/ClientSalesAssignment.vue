<template>
  <div class="detail-card sales-assignment-section">
    <div class="detail-card-header">
      <div class="detail-card-icon">
        <i class="fas fa-user-tie"></i>
      </div>
      <div class="section-header">
        <h3 class="detail-card-title">{{ t("leadDetail_sectionSales") }}</h3>
        <button
          v-if="canEdit"
          class="btn-assign"
          @click="assignToSales"
          :disabled="assigning"
        >
          <i class="fas fa-user-check"></i>
          {{ t("clientDetail_btnAssignClient") }}
        </button>
      </div>
    </div>
    <div class="assignment-content">
      <div class="assignment-field">
        <label>{{ t("leadDetail_assignToSales") }}</label>
        <select v-model="salesRep" :disabled="assigning || !canEdit">
          <option value="">{{ t("leadDetail_selectSalesRep") }}</option>
          <option
            v-for="manager in managerOptions"
            :key="manager.id"
            :value="String(manager.id)"
          >
            {{ formatManagerDisplay(manager) }}
          </option>
        </select>
        <div
          v-if="!loadingManagers && managerOptions.length === 0"
          class="assignment-helper"
        >
          <small>{{ t("leadDetail_noSalesReps") }}</small>
        </div>
        <div v-if="loadingManagers" class="assignment-helper">
          <small>{{ t("leadDetail_loadingSalesReps") }}</small>
        </div>
      </div>
      <div class="assignment-info">
        <div class="assignment-info-item">
          <span class="assignment-info-label">{{
            t("leadDetail_assignmentStatus")
          }}</span>
          <span :class="['assignment-status', assignmentStatusClass]">
            {{ assignmentStatus }}
          </span>
        </div>
        <div class="assignment-info-item">
          <span class="assignment-info-label">{{
            t("leadDetail_assignedTo")
          }}</span>
          <span class="assignment-info-value">{{ assignedToDisplay }}</span>
        </div>
        <div class="assignment-info-item">
          <span class="assignment-info-label">{{
            t("leadDetail_assignedDate")
          }}</span>
          <span class="assignment-info-value">{{ assignedDateDisplay }}</span>
        </div>
      </div>
      <div class="assignment-field assignment-field--full">
        <label>{{ t("leadDetail_followUpNotes") }}</label>
        <textarea
          v-model="assignmentNotes"
          :placeholder="t('leadDetail_notesPlaceholder')"
          rows="4"
          :disabled="assigning || !canEdit"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { clientService } from "@/services/clientListService";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";
import { getSubModuleKey } from "@/config/operationLogPages";

const props = defineProps({
  client: { type: Object, required: true },
  canEdit: { type: Boolean, default: true },
  logSubModuleKey: {
    type: String,
    default: () => getSubModuleKey("page_clients_list"),
  },
});

const emit = defineEmits(["assigned"]);

const { t, tParams, languageStore } = useAdminI18n();

const managerOptions = ref([]);
const loadingManagers = ref(false);
const salesRep = ref("");
const assignmentNotes = ref("");
const assigning = ref(false);
const assignedManager = ref(null);
const assignedAt = ref(null);

const formatDate = (dateString) => {
  if (!dateString) return t("leads_na");
  const date = new Date(dateString);
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return date.toLocaleDateString(loc, {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const loadManagerOptions = async () => {
  loadingManagers.value = true;
  try {
    const response = await clientService.getAssignableSales();
    const optionsData = response?.data ?? response ?? {};
    managerOptions.value = Array.isArray(optionsData.managers)
      ? optionsData.managers
      : [];
  } catch (error) {
    console.error("Failed to load sales representative options:", error);
  } finally {
    loadingManagers.value = false;
  }
};

const formatManagerDisplay = (manager) => {
  const name = manager.name ?? manager.fullName ?? "";
  const email = manager.email ?? "";
  if (name && email) return `${name} (${email})`;
  return (
    name ||
    email ||
    tParams("clientDetail_salesNum", "Sales #{n}", { n: manager.id })
  );
};

const currentManagerId = computed(
  () => assignedManager.value?.id ?? props.client.accountManagerId ?? null,
);
const assignmentStatus = computed(() =>
  currentManagerId.value
    ? t("leadDetail_statusAssigned")
    : t("leadDetail_statusUnassigned"),
);
const assignmentStatusClass = computed(() =>
  currentManagerId.value ? "assigned" : "unassigned",
);
const assignedToDisplay = computed(() => {
  if (assignedManager.value) return formatManagerDisplay(assignedManager.value);
  if (currentManagerId.value) {
    const found = managerOptions.value.find(
      (m) => Number(m.id) === Number(currentManagerId.value),
    );
    if (found) return formatManagerDisplay(found);
    return tParams("clientDetail_salesNum", "Sales #{n}", {
      n: currentManagerId.value,
    });
  }
  return t("leadDetail_notAssigned");
});
const assignedDateDisplay = computed(() => {
  const at = assignedAt.value || props.client.accountManagerAssignedAt;
  return at ? formatDate(at) : t("leads_na");
});

const syncFromClient = (client) => {
  salesRep.value = client?.accountManagerId
    ? String(client.accountManagerId)
    : "";
  assignmentNotes.value =
    client?.accountManagerNote || client?.managerNote || "";
  assignedManager.value = null;
  assignedAt.value = client?.accountManagerAssignedAt || null;
};

const assignToSales = async () => {
  if (!salesRep.value) {
    alert(t("bulkAssign_alertSelectManager"));
    return;
  }
  assigning.value = true;
  try {
    const response = await clientService.bulkAssign([props.client.id], {
      assigneeId: Number(salesRep.value),
      notes:
        assignmentNotes.value && assignmentNotes.value.trim() !== ""
          ? assignmentNotes.value.trim()
          : undefined,
      logSubModuleKey: props.logSubModuleKey,
    });
    const data = response?.data ?? response ?? {};
    assignedManager.value = data.accountManager || null;
    assignedAt.value = data.assignedAt || null;
    emit("assigned", {
      accountManagerId: data.accountManager?.id ?? Number(salesRep.value),
      accountManager: data.accountManager || null,
      accountManagerNote: data.managerNote ?? assignmentNotes.value,
      managerNote: data.managerNote ?? assignmentNotes.value,
      accountManagerAssignedAt: data.assignedAt || null,
    });
    alert(t("clientDetail_assignOk"));
  } catch (error) {
    const data = error?.response?.data ?? error;
    const rawMsg = data?.message || error?.message || t("common_unknownError");
    const message = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams("clientDetail_assignFailed", "Failed to assign client: {msg}", {
        msg: message,
      }),
    );
  } finally {
    assigning.value = false;
  }
};

onMounted(() => {
  loadManagerOptions();
});

watch(() => props.client, syncFromClient, { immediate: true });
</script>

<style scoped>
.detail-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
}

.sales-assignment-section .detail-card-header {
  flex-wrap: wrap;
  gap: 12px;
}

.detail-card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 18px;
}

.detail-card-icon {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-brand-soft);
  color: var(--color-brand-strong);
  flex-shrink: 0;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  width: 100%;
}

.detail-card-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--color-ink);
  margin: 0;
}

.assignment-content {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.assignment-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.assignment-field--full {
  grid-column: 1 / -1;
}

.assignment-field label {
  font-weight: 600;
  color: var(--color-text);
  font-size: 14px;
}

.assignment-field select,
.assignment-field textarea {
  padding: 10px 14px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  outline: none;
  transition: all 0.3s ease;
  background: var(--color-surface);
}

.assignment-field select {
  cursor: pointer;
}

.assignment-field select:focus,
.assignment-field textarea:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.assignment-field select:disabled,
.assignment-field textarea:disabled {
  background: var(--color-surface-soft);
  color: var(--color-text);
  cursor: not-allowed;
  opacity: 1;
}

.assignment-field textarea {
  resize: vertical;
  min-height: 80px;
  font-family: inherit;
}

.assignment-info {
  background: var(--color-surface-soft);
  padding: 15px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
}

.assignment-info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid var(--color-border);
}

.assignment-info-item:last-child {
  border-bottom: none;
}

.assignment-info-label {
  font-weight: 600;
  color: var(--color-muted);
  font-size: 14px;
}

.assignment-info-value {
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 500;
}

.assignment-helper {
  font-size: 14px;
  color: var(--color-muted);
}

.assignment-status {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
}

.assignment-status.unassigned {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.assignment-status.assigned {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.btn-assign {
  padding: 10px 20px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.btn-assign:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.btn-assign:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}

@media (max-width: 768px) {
  .assignment-content {
    grid-template-columns: 1fr;
  }

  .section-header {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
