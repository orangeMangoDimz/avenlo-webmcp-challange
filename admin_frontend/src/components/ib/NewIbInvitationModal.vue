<template>
  <div class="ib-inv-modal-overlay" @click="$emit('close')">
    <div class="ib-inv-modal" @click.stop>
      <div class="ib-inv-modal__header">
        <h2 class="ib-inv-modal__title">{{ t("ibInvModal_title") }}</h2>
        <button
          type="button"
          class="ib-inv-modal__close"
          @click="$emit('close')"
          :aria-label="t('ibInvModal_ariaClose')"
        >
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="ib-inv-modal__body">
        <form @submit.prevent="submitInvitation" class="ib-inv-form">
          <!-- Client: dropdown search single select -->
          <div class="ib-inv-form__group">
            <label class="ib-inv-form__label ib-inv-form__label--required">{{
              t("ibInvModal_label_selectClient")
            }}</label>
            <div class="ib-inv-client-select">
              <div class="ib-inv-client-select__input-wrap">
                <i class="fas fa-search ib-inv-client-select__icon"></i>
                <input
                  ref="clientInputRef"
                  type="text"
                  class="ib-inv-client-select__input"
                  :value="clientInputDisplay"
                  :placeholder="clientSelectPlaceholder"
                  :readonly="!!selectedClient"
                  autocomplete="off"
                  @input="onClientInput"
                  @focus="showClientDropdown = true"
                  @blur="onClientBlur"
                />
                <button
                  v-if="selectedClient"
                  type="button"
                  class="ib-inv-client-select__clear"
                  aria-label="Clear selection"
                  @click.prevent="clearClient"
                >
                  <i class="fas fa-times"></i>
                </button>
              </div>
              <div
                v-show="showClientDropdown"
                class="ib-inv-client-select__dropdown"
                @mousedown.prevent
              >
                <div
                  v-if="loading && availableClients.length === 0"
                  class="ib-inv-client-select__loading"
                >
                  <i class="fas fa-spinner fa-spin"></i>
                  <span>{{ t("ibInvModal_loading") }}</span>
                </div>
                <div
                  v-else-if="!loading && availableClients.length === 0"
                  class="ib-inv-client-select__empty"
                >
                  {{
                    searchKeyword
                      ? t("ibInvModal_clientsEmptySearch")
                      : t("ibInvModal_clientsEmpty")
                  }}
                </div>
                <div
                  v-else
                  class="ib-inv-client-select__list"
                  @scroll="handleScroll"
                >
                  <div
                    v-for="client in availableClients"
                    :key="client.id"
                    class="ib-inv-client-option"
                    :class="{
                      'ib-inv-client-option--selected':
                        selectedClientId === client.id,
                    }"
                    @mousedown.prevent="selectClient(client)"
                  >
                    <span class="ib-inv-client-option__avatar">{{
                      client.initials
                    }}</span>
                    <div class="ib-inv-client-option__info">
                      <span class="ib-inv-client-option__name">{{
                        client.name
                      }}</span>
                      <span class="ib-inv-client-option__email">{{
                        client.email
                      }}</span>
                    </div>
                    <i
                      v-if="selectedClientId === client.id"
                      class="fas fa-check ib-inv-client-option__check"
                    ></i>
                  </div>
                  <div
                    v-if="loadingMore"
                    class="ib-inv-client-select__loading-more"
                  >
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>{{ t("ibInvModal_loadingMore") }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="ib-inv-form__group">
            <label class="ib-inv-skip-docs">
              <input
                v-model="skipRequiredDocuments"
                type="checkbox"
                class="ib-inv-skip-docs__input"
              />
              <span class="ib-inv-skip-docs__box">
                <i v-if="skipRequiredDocuments" class="fas fa-check"></i>
              </span>
              <span class="ib-inv-skip-docs__text">
                {{
                  t(
                    "ibInvModal_skipDocsLabel",
                    "Skip signing and send to Initial Review",
                  )
                }}
              </span>
            </label>
          </div>

          <!-- Required IB Documents + selected count -->
          <div v-if="!skipRequiredDocuments" class="ib-inv-form__group">
            <label class="ib-inv-form__label ib-inv-form__label--required">
              {{ t("ibInvModal_label_requiredDocs") }}
              <span class="ib-inv-form__count">{{
                tParams("ibInvModal_selectedCount", "{n} selected", {
                  n: selectedDocumentIds.length,
                })
              }}</span>
            </label>
            <div class="ib-inv-docs-box">
              <div class="ib-inv-docs">
                <template v-for="doc in documentsList" :key="doc.id">
                  <div
                    class="ib-inv-doc"
                    :class="{
                      'ib-inv-doc--selected': selectedDocumentIds.includes(
                        doc.id,
                      ),
                    }"
                  >
                    <div
                      class="ib-inv-doc__checkbox"
                      @click="toggleDocument(doc.id)"
                    >
                      <i
                        v-if="selectedDocumentIds.includes(doc.id)"
                        class="fas fa-check"
                      ></i>
                    </div>
                    <div class="ib-inv-doc__main">
                      <a
                        href="#"
                        class="ib-inv-doc__title"
                        @click.prevent="openDocInNewTab(doc)"
                        >{{
                          doc.documentTitle ||
                          doc.title ||
                          t("ibInvModal_docFallback")
                        }}</a
                      >
                      <span
                        v-if="doc.isRequired == 1 || doc.isRequired === true"
                        class="ib-inv-doc__required"
                        >{{ t("ibInvModal_docRequired") }}</span
                      >
                    </div>
                  </div>
                </template>
                <div
                  v-if="documentsList.length === 0"
                  class="ib-inv-docs__empty"
                >
                  <i class="fas fa-file-alt"></i>
                  <p>{{ t("ibInvModal_docsEmpty") }}</p>
                </div>
              </div>
              <p class="ib-inv-docs__hint">{{ t("ibInvModal_docsHint") }}</p>
            </div>
          </div>

          <!-- Invitation Message (Optional) -->
          <div class="ib-inv-form__group">
            <label class="ib-inv-form__label">{{
              t("ibInvModal_label_message")
            }}</label>
            <textarea
              class="ib-inv-form__textarea"
              v-model="invitationMessage"
              :placeholder="t('ibInvModal_ph_message')"
              rows="4"
            ></textarea>
          </div>
        </form>
      </div>

      <div class="ib-inv-modal__footer">
        <button
          type="button"
          class="ib-inv-btn ib-inv-btn--secondary"
          @click="$emit('close')"
        >
          {{ t("common_cancel") }}
        </button>
        <button
          type="button"
          class="ib-inv-btn ib-inv-btn--primary"
          :disabled="!canSendInvitation || sending"
          @click="submitInvitation"
        >
          <i
            class="fas"
            :class="sending ? 'fa-spinner fa-spin' : 'fa-paper-plane'"
          ></i>
          {{ sending ? t("ibInvModal_sending") : t("ibInvModal_btn_send") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from "vue";
import { clientService } from "@/services/clientListService";
import ibInvitationsApi from "@/services/ibInvitationsApi";
import { formatNumber } from "@/utils/helpers";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams } = useAdminI18n();

const props = defineProps({
  documents: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["close", "send"]);

const searchKeyword = ref("");
const selectedClientId = ref(null);
const selectedClient = ref(null);
const selectedDocumentIds = ref([]);
const skipRequiredDocuments = ref(false);
const invitationMessage = ref("");
const sending = ref(false);

const showClientDropdown = ref(false);
const clientInputRef = ref(null);

// 客户数据（KYC 认证用户）
const availableClients = ref([]);
const loading = ref(false);
const loadingMore = ref(false);
const hasMore = ref(true);
const currentPage = ref(1);
const totalClients = ref(0);
const searchTimeout = ref(null);
const perPage = ref(50);
const shouldLoadAll = ref(false);

const clientInputDisplay = computed(() =>
  selectedClient.value ? selectedClient.value.name : searchKeyword.value,
);
const clientSelectPlaceholder = computed(() =>
  selectedClient.value ? "" : t("ibInvModal_ph_searchClient"),
);

/** 规范为数组，避免父组件传入非数组导致不渲染 */
const documentsList = computed(() =>
  Array.isArray(props.documents) ? props.documents : [],
);

/** 带 Required 标识的文档 id 列表 */
const requiredDocumentIds = computed(() =>
  documentsList.value
    .filter((d) => d.isRequired === true || d.isRequired === 1)
    .map((d) => d.id),
);

/** 是否已选齐所有必选文档 */
const allRequiredDocsSelected = computed(() => {
  const selected = selectedDocumentIds.value;
  return requiredDocumentIds.value.every((id) => selected.includes(id));
});

const canSendInvitation = computed(() => {
  if (!selectedClientId.value) return false;
  if (skipRequiredDocuments.value) return true;
  return selectedDocumentIds.value.length > 0 && allRequiredDocsSelected.value;
});

/**
 * 加载客户列表
 */
const loadClients = async (page = 1, append = false) => {
  try {
    if (page === 1) {
      loading.value = true;
    } else {
      loadingMore.value = true;
    }

    const params = {
      page,
      per_page: perPage.value,
      search: searchKeyword.value.trim(),
    };

    const response = await clientService.getClientsForIbSelection(params);

    // API拦截器已经返回了response.data，所以直接访问response.success和response.data
    if (response && response.success && response.data) {
      const data = response.data;
      const items = Array.isArray(data.items)
        ? data.items
        : Array.isArray(data)
          ? data
          : [];
      const total = typeof data.total === "number" ? data.total : items.length;
      const page = typeof data.page === "number" ? data.page : 1;
      const totalPages =
        typeof data.total_pages === "number" ? data.total_pages : 1;

      if (append) {
        availableClients.value = [...availableClients.value, ...items];
      } else {
        availableClients.value = items;
      }

      totalClients.value = total;
      currentPage.value = page;
      hasMore.value = page < totalPages;

      // 如果总数小于500，标记为可以一次性加载
      if (total <= 500 && !shouldLoadAll.value) {
        shouldLoadAll.value = true;
        if (hasMore.value) {
          await loadAllClients();
        }
      }
    }
  } catch (error) {
    console.error("Failed to load clients:", error);
    alert(t("ibInvModal_alert_loadClientsFailed"));
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
};

/**
 * 一次性加载所有客户（当总数可控时）
 */
const loadAllClients = async () => {
  if (!shouldLoadAll.value || !hasMore.value) return;

  try {
    loadingMore.value = true;
    let page = currentPage.value + 1;
    const allClients = [...availableClients.value];

    while (
      hasMore.value &&
      page <= Math.ceil(totalClients.value / perPage.value)
    ) {
      const params = {
        page,
        per_page: perPage.value,
        search: searchKeyword.value.trim(),
      };

      const response = await clientService.getClientsForIbSelection(params);

      // API拦截器已经返回了response.data，所以直接访问response.success和response.data
      if (response && response.success && response.data) {
        const data = response.data;
        const nextItems = Array.isArray(data.items)
          ? data.items
          : Array.isArray(data)
            ? data
            : [];
        allClients.push(...nextItems);
        hasMore.value =
          typeof data.page === "number" && typeof data.total_pages === "number"
            ? data.page < data.total_pages
            : false;
        page++;
      } else {
        break;
      }
    }

    availableClients.value = allClients;
  } catch (error) {
    console.error("Failed to load all clients:", error);
  } finally {
    loadingMore.value = false;
  }
};

/**
 * 滚动加载更多
 */
const handleScroll = (event) => {
  const container = event.target;
  const scrollBottom =
    container.scrollHeight - container.scrollTop - container.clientHeight;

  // 当滚动到底部附近（50px）时加载更多
  if (
    scrollBottom < 50 &&
    hasMore.value &&
    !loadingMore.value &&
    !shouldLoadAll.value
  ) {
    loadClients(currentPage.value + 1, true);
  }
};

/**
 * 搜索防抖处理
 */
watch(searchKeyword, () => {
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
  }

  searchTimeout.value = setTimeout(() => {
    currentPage.value = 1;
    hasMore.value = true;
    shouldLoadAll.value = false;
    loadClients(1, false);
  }, 300);
});

watch(skipRequiredDocuments, (skip) => {
  if (skip) {
    selectedDocumentIds.value = [];
  }
});

const onClientInput = (e) => {
  const v = e.target.value;
  if (selectedClient.value) {
    clearClient();
  }
  searchKeyword.value = v;
};

const onClientBlur = () => {
  setTimeout(() => {
    showClientDropdown.value = false;
  }, 200);
};

const clearClient = () => {
  selectedClientId.value = null;
  selectedClient.value = null;
  searchKeyword.value = "";
  showClientDropdown.value = true;
  nextTick(() => clientInputRef.value?.focus());
};

/**
 * 选择客户（下拉单选）
 */
const selectClient = (client) => {
  selectedClientId.value = client.id;
  selectedClient.value = {
    id: client.id,
    name: client.name,
    email: client.email,
    initials: client.initials,
  };
  showClientDropdown.value = false;
  searchKeyword.value = "";
};

onMounted(() => {
  loadClients(1, false);
});

/**
 * 在新标签页打开文档内容
 */
const openDocInNewTab = (doc) => {
  const html =
    doc.documentContent ||
    doc.content ||
    `<p>${t("ibInvModal_previewNoContent")}</p>`;
  const blob = new Blob(
    [
      `<!DOCTYPE html><html><head><meta charset="utf-8"><title>${(doc.documentTitle || doc.title || t("ibInvModal_docFallback")).replace(/</g, "&lt;")}</title><style>body{font-family:system-ui,sans-serif;padding:24px;line-height:1.6;max-width:800px;margin:0 auto;} a{color:var(--color-brand);}</style></head><body>${html}</body></html>`,
    ],
    { type: "text/html" },
  );
  const url = URL.createObjectURL(blob);
  window.open(url, "_blank", "noopener");
  URL.revokeObjectURL(url);
};

/**
 * 切换文档选择
 */
const toggleDocument = (docId) => {
  const index = selectedDocumentIds.value.indexOf(docId);
  if (index > -1) {
    selectedDocumentIds.value.splice(index, 1);
  } else {
    selectedDocumentIds.value.push(docId);
  }
};

/**
 * 提交邀请
 */
const submitInvitation = async () => {
  if (!selectedClientId.value) {
    alert(t("ibInvModal_alert_selectClient"));
    return;
  }
  if (!skipRequiredDocuments.value && selectedDocumentIds.value.length === 0) {
    alert(t("ibInvModal_alert_selectDoc"));
    return;
  }
  if (!skipRequiredDocuments.value && !allRequiredDocsSelected.value) {
    const requiredTitles = documentsList.value
      .filter(
        (d) =>
          (d.isRequired === true || d.isRequired === 1) &&
          !selectedDocumentIds.value.includes(d.id),
      )
      .map(
        (d) =>
          d.documentTitle ||
          d.title ||
          tParams("ibInvModal_docNum", "Document #{id}", { id: d.id }),
      )
      .join(", ");
    alert(
      tParams(
        "ibInvModal_alert_requiredDocs",
        "Please select all required documents:\n\n{list}\n\nRequired documents must be selected before sending the invitation.",
        { list: requiredTitles },
      ),
    );
    return;
  }

  try {
    sending.value = true;

    // 调用实际API
    // 传递 useHashMode: true 表示使用 Hash 模式路由
    // 后端会根据此参数决定生成的链接是否包含 #
    const response = await ibInvitationsApi.createInvitation({
      clientId: selectedClientId.value,
      selectedDocuments: skipRequiredDocuments.value
        ? []
        : selectedDocumentIds.value,
      invitationMessage: invitationMessage.value || null,
      skipSign: skipRequiredDocuments.value,
      useHashMode: true, // 客户端使用 Hash 模式路由
    });

    if (response && response.success) {
      // 成功发送邀请
      emit("send", {
        clientId: selectedClientId.value,
        documents: skipRequiredDocuments.value ? [] : selectedDocumentIds.value,
        message: invitationMessage.value,
        skipSign: skipRequiredDocuments.value,
      });
    } else {
      throw new Error(response?.message || "Failed to send invitation");
    }
  } catch (error) {
    console.error("Failed to send invitation:", error);
    const raw = error?.message || error?.response?.data?.message || "";
    const msg = raw || t("ibInvModal_sendErrFallback");
    alert(
      tParams(
        "ibInvModal_alert_sendFailed",
        "Failed to send invitation: {msg}",
        { msg },
      ),
    );
  } finally {
    sending.value = false;
  }
};
</script>

<style scoped>
.ib-inv-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  z-index: 9998;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: ib-inv-fadeIn 0.3s ease;
}

.ib-inv-modal {
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  width: 90%;
  max-width: 700px;
  max-height: 85vh;
  overflow: hidden;
  animation: ib-inv-slideUp 0.3s ease;
}

@keyframes ib-inv-fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes ib-inv-slideUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.ib-inv-modal__header {
  padding: 20px 24px;
  border-bottom: 2px solid var(--color-border);
  background: var(--color-brand-solid);
  color: white;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.ib-inv-modal__title {
  font-size: 20px;
  font-weight: 700;
  margin: 0;
}

.ib-inv-modal__close {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  transition: background 0.2s;
}

.ib-inv-modal__close:hover {
  background: rgba(255, 255, 255, 0.3);
}

.ib-inv-modal__body {
  padding: 24px;
  max-height: calc(85vh - 160px);
  overflow-y: auto;
}

.ib-inv-form__group {
  margin-bottom: 22px;
}

.ib-inv-form__label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 8px;
}

.ib-inv-form__label--required::after {
  content: " *";
  color: var(--color-danger);
}

.ib-inv-form__count {
  margin-left: 8px;
  padding: 2px 8px;
  background: var(--color-brand-solid);
  color: white;
  border-radius: var(--radius-md);
  font-size: 11px;
  font-weight: 600;
}

.ib-inv-skip-docs {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  user-select: none;
}

.ib-inv-skip-docs__input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.ib-inv-skip-docs__box {
  width: 22px;
  height: 22px;
  border: 2px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  font-size: 12px;
  flex-shrink: 0;
  transition: all 0.2s;
}

.ib-inv-skip-docs__input:checked + .ib-inv-skip-docs__box {
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
}

.ib-inv-skip-docs__input:focus + .ib-inv-skip-docs__box {
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.14);
}

.ib-inv-skip-docs__text {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}

/* Client dropdown search */
.ib-inv-client-select {
  position: relative;
}

.ib-inv-client-select__input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.ib-inv-client-select__icon {
  position: absolute;
  left: 14px;
  color: var(--color-faint);
  font-size: 14px;
  pointer-events: none;
}

.ib-inv-client-select__input {
  width: 100%;
  padding: 10px 40px 10px 40px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition:
    border-color 0.2s,
    box-shadow 0.2s;
}

.ib-inv-client-select__input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.ib-inv-client-select__input::placeholder {
  color: var(--color-faint);
}

.ib-inv-client-select__clear {
  position: absolute;
  right: 10px;
  width: 28px;
  height: 28px;
  border: none;
  background: var(--color-border);
  color: var(--color-text);
  border-radius: var(--radius-sm);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  transition:
    background 0.2s,
    color 0.2s;
}

.ib-inv-client-select__clear:hover {
  background: var(--color-border-strong);
  color: var(--color-ink);
}

.ib-inv-client-select__dropdown {
  position: absolute;
  left: 0;
  right: 0;
  top: 100%;
  margin-top: 4px;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
  z-index: 10;
  max-height: 280px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.ib-inv-client-select__loading,
.ib-inv-client-select__empty {
  padding: 20px;
  text-align: center;
  font-size: 13px;
  color: var(--color-muted);
}

.ib-inv-client-select__loading i,
.ib-inv-client-select__loading-more i {
  margin-right: 8px;
}

.ib-inv-client-select__list {
  overflow-y: auto;
  max-height: 260px;
}

.ib-inv-client-select__list::-webkit-scrollbar {
  width: 6px;
}

.ib-inv-client-select__list::-webkit-scrollbar-thumb {
  background: var(--color-border-strong);
  border-radius: 3px;
}

.ib-inv-client-select__loading-more {
  padding: 12px;
  text-align: center;
  font-size: 12px;
  color: var(--color-brand);
  border-top: 1px solid var(--color-border);
}

.ib-inv-client-option {
  padding: 12px 14px;
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  transition: background 0.2s;
  border-bottom: 1px solid #f1f1f1;
}

.ib-inv-client-option:last-child {
  border-bottom: none;
}

.ib-inv-client-option:hover {
  background: var(--color-surface-soft);
}

.ib-inv-client-option--selected {
  background: var(--color-brand-soft);
}

.ib-inv-client-option__avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--color-brand-solid);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 13px;
  flex-shrink: 0;
}

.ib-inv-client-option__info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.ib-inv-client-option__name {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}

.ib-inv-client-option__email {
  font-size: 12px;
  color: var(--color-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ib-inv-client-option__check {
  color: var(--color-brand);
  font-size: 14px;
  flex-shrink: 0;
}

/* Documents list: 整体区域边框+背景，突显区域 */
.ib-inv-docs-box {
  margin-top: 8px;
  padding: 16px 18px;
  background: var(--color-surface-soft);
  border: 2px solid var(--color-border-strong);
  border-radius: var(--radius-md);
  min-height: 80px;
}

.ib-inv-docs {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.ib-inv-doc {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  background: transparent;
  border: none;
  border-radius: var(--radius-sm);
  transition: background 0.2s;
}

.ib-inv-doc:hover {
  background: rgba(0, 0, 0, 0.04);
}

.ib-inv-doc--selected {
  background: rgba(var(--color-brand-rgb), 0.08);
}

.ib-inv-doc__checkbox {
  width: 22px;
  height: 22px;
  border: 2px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 12px;
  cursor: pointer;
  flex-shrink: 0;
  transition: all 0.2s;
}

.ib-inv-doc--selected .ib-inv-doc__checkbox {
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
}

.ib-inv-doc__main {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.ib-inv-doc__title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-brand);
  text-decoration: none;
  cursor: pointer;
  transition:
    color 0.2s,
    text-decoration 0.2s;
}

.ib-inv-doc__title:hover {
  color: var(--color-brand-strong);
  text-decoration: underline;
}

.ib-inv-doc__required {
  padding: 2px 8px;
  background: var(--color-danger-soft);
  color: var(--color-danger);
  border-radius: var(--radius-md);
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
}

.ib-inv-docs__empty {
  text-align: center;
  padding: 24px;
  color: var(--color-muted);
  font-size: 13px;
}

.ib-inv-docs__empty i {
  display: block;
  font-size: 28px;
  color: var(--color-border-strong);
  margin-bottom: 8px;
}

.ib-inv-docs__empty p {
  margin: 0;
}

.ib-inv-docs__hint {
  margin: 14px 0 0 0;
  padding-top: 12px;
  border-top: 1px solid var(--color-border-strong);
  font-size: 12px;
  color: var(--color-muted);
  line-height: 1.5;
}

/* Invitation Message */
.ib-inv-form__textarea {
  width: 100%;
  padding: 12px 14px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  resize: vertical;
  min-height: 100px;
  font-family: inherit;
  transition:
    border-color 0.2s,
    box-shadow 0.2s;
}

.ib-inv-form__textarea:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.ib-inv-form__textarea::placeholder {
  color: var(--color-faint);
}

/* Footer */
.ib-inv-modal__footer {
  padding: 18px 24px;
  border-top: 2px solid var(--color-border);
  background: var(--color-surface-soft);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.ib-inv-btn {
  padding: 12px 22px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s;
}

.ib-inv-btn--secondary {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  color: var(--color-text);
}

.ib-inv-btn--secondary:hover {
  background: var(--color-surface-soft);
  border-color: var(--color-border-strong);
}

.ib-inv-btn--primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.ib-inv-btn--primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.ib-inv-btn--primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}
</style>
