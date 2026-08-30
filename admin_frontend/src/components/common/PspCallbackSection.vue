<template>
  <!-- 没有 orderId/recordId、未拉到 / 拉到空数组时整段隐藏，符合「没有就不显示」的产品要求 -->
  <div v-if="show" class="psp-callback-section">
    <button
      type="button"
      class="psp-callback-toggle"
      @click="expanded = !expanded"
    >
      <i class="fas fa-satellite-dish"></i>
      <span class="psp-callback-toggle-title">{{
        t("pspCallback_title", "PSP Status")
      }}</span>
      <span class="psp-callback-count">{{ callbacks.length }}</span>
      <i :class="['fas', expanded ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
    </button>

    <div v-if="expanded" class="psp-callback-body">
      <div v-for="cb in callbacks" :key="cb.id" class="psp-callback-card">
        <div class="psp-callback-card-header">
          <span
            v-if="cb.processResult"
            :class="[
              'psp-callback-pill',
              `psp-callback-pill-result-${cb.processResult}`,
            ]"
            >{{ formatProcessResult(cb.processResult) }}</span
          >
        </div>

        <div class="psp-callback-fields">
          <div
            v-if="cb.amount !== null && cb.amount !== ''"
            class="psp-callback-field"
          >
            <span class="psp-callback-label">{{
              t("pspCallback_amount", "Amount")
            }}</span>
            <span class="psp-callback-value">{{ cb.amount }}</span>
          </div>
          <div
            v-if="cb.errorMessage"
            class="psp-callback-field psp-callback-field-full"
          >
            <span class="psp-callback-label">{{
              t("pspCallback_errorMessage", "Error Message")
            }}</span>
            <span class="psp-callback-value psp-callback-error">{{
              cb.errorMessage
            }}</span>
          </div>
        </div>

        <div v-if="cb.rawPayload" class="psp-callback-payload">
          <div class="psp-callback-payload-label">
            {{ t("pspCallback_rawPayload", "Raw Payload") }}
          </div>
          <pre class="psp-callback-payload-body">{{
            formatPayload(cb.rawPayload)
          }}</pre>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import pspCallbackApi from "@/services/pspCallbackApi";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();

const props = defineProps({
  // deposit / withdrawal 的 transactionId（PSP callback 落库到 orderId 字段）
  orderId: {
    type: [String, Number],
    default: "",
  },
  transactionType: {
    type: String,
    default: "",
  },
  recordId: {
    type: [String, Number],
    default: 0,
  },
});

const callbacks = ref([]);
const loaded = ref(false);
const expanded = ref(false);

// orderId/recordId 为空 / 未拉过 / 拉到 0 条 -> 整段不显示
const show = computed(() => {
  return loaded.value && callbacks.value.length > 0;
});

const formatProcessResult = (result) => {
  const map = {
    pending: t("pspCallback_result_pending", "Pending"),
    success: t("pspCallback_result_success", "Success"),
    failed: t("pspCallback_result_failed", "Failed"),
  };
  return map[result] || result;
};

// rawPayload 是 PSP 原样存的字符串，能 parse 成 JSON 就 pretty print，不能就原样展示
const formatPayload = (raw) => {
  if (raw == null) return "";
  if (typeof raw === "object") {
    try {
      return JSON.stringify(raw, null, 2);
    } catch (e) {
      return String(raw);
    }
  }
  const str = String(raw);
  try {
    const parsed = JSON.parse(str);
    return JSON.stringify(parsed, null, 2);
  } catch (e) {
    return str;
  }
};

const load = async () => {
  const id = String(props.orderId || "").trim();
  const recordId = Number(props.recordId || 0);
  if (!id && recordId <= 0) {
    callbacks.value = [];
    loaded.value = true;
    return;
  }
  try {
    const response = await pspCallbackApi.lookupPspCallback(id, {
      transactionType: props.transactionType,
      recordId,
    });
    callbacks.value =
      response?.success && Array.isArray(response.data?.callbacks)
        ? response.data.callbacks
        : [];
  } catch (err) {
    // 这是辅助信息区域，失败时静默隐藏，不打扰主详情
    console.warn("PSP callback lookup failed:", err);
    callbacks.value = [];
  } finally {
    loaded.value = true;
  }
};

watch(
  () => [props.orderId, props.transactionType, props.recordId],
  () => {
    loaded.value = false;
    expanded.value = false;
    load();
  },
  { immediate: true },
);
</script>

<style scoped>
.psp-callback-section {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  margin-top: 12px;
}

.psp-callback-toggle {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  background: none;
  border: none;
  padding: 12px 16px;
  cursor: pointer;
  color: var(--color-ink);
  font-weight: 600;
  font-size: 14px;
}

.psp-callback-toggle:hover {
  background: var(--color-surface-soft);
}

.psp-callback-toggle-title {
  flex: 0 0 auto;
}

.psp-callback-count {
  background: var(--color-surface-muted);
  color: var(--color-text);
  border-radius: 999px;
  padding: 2px 10px;
  font-size: 14px;
  font-weight: 600;
}

.psp-callback-toggle .fa-chevron-up,
.psp-callback-toggle .fa-chevron-down {
  margin-left: auto;
  color: var(--color-muted);
}

.psp-callback-body {
  padding: 0 16px 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.psp-callback-card {
  border: 1px solid var(--color-surface-muted);
  border-radius: var(--radius-sm);
  padding: 12px 14px;
  background: var(--color-surface-soft);
}

.psp-callback-card-header {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 10px;
}

.psp-callback-pill {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 999px;
  font-size: 14px;
  font-weight: 600;
  background: var(--color-surface-muted);
  color: var(--color-ink);
}

.psp-callback-pill-result-success {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.psp-callback-pill-result-failed {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.psp-callback-pill-result-pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.psp-callback-fields {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 6px 16px;
}

.psp-callback-field {
  display: flex;
  flex-direction: column;
  font-size: 14px;
}

.psp-callback-field-full {
  grid-column: 1 / -1;
}

.psp-callback-label {
  color: var(--color-muted);
  font-size: 14px;
  margin-bottom: 2px;
}

.psp-callback-value {
  color: var(--color-ink);
  word-break: break-word;
}

.psp-callback-error {
  color: var(--color-danger);
}

.psp-callback-payload {
  margin-top: 10px;
}

.psp-callback-payload-label {
  color: var(--color-muted);
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 4px;
}

.psp-callback-payload-body {
  background: var(--color-ink-strong);
  color: var(--color-surface-soft);
  border-radius: 4px;
  padding: 10px 12px;
  margin: 6px 0 0;
  font-size: 14px;
  max-height: 320px;
  overflow: auto;
  white-space: pre-wrap;
  word-break: break-word;
}
</style>
