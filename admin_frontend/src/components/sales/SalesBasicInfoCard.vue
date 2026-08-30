<template>
  <div class="sales-detail-card">
    <div class="sales-detail-card-header">
      <div class="sales-detail-card-icon"><i class="fas fa-user-tie"></i></div>
      <h3 class="sales-detail-card-title">
        {{ t("salesList_section_basicInfo", "Basic Information") }}
      </h3>
    </div>
    <div v-if="loading" class="sales-detail-loading">
      {{ t("customReport_loading", "Loading...") }}
    </div>
    <div v-else class="sales-detail-fields">
      <div class="sales-detail-field">
        <span class="sales-detail-label">{{
          t("salesList_label_fullName", "Full Name")
        }}</span>
        <span class="sales-detail-value">{{ info.fullName || "—" }}</span>
      </div>
      <div class="sales-detail-field">
        <span class="sales-detail-label">{{
          t("salesList_label_salesCode", "Sales Code")
        }}</span>
        <span class="sales-detail-value">{{ info.salesCode || "—" }}</span>
      </div>
      <div class="sales-detail-field">
        <span class="sales-detail-label">{{
          t("salesList_label_email", "Email")
        }}</span>
        <span class="sales-detail-value">{{ info.email || "—" }}</span>
      </div>
      <div class="sales-detail-field">
        <span class="sales-detail-label">{{
          t("salesList_label_joinDate", "Join Date")
        }}</span>
        <span class="sales-detail-value">{{
          formatJoinDate(info.joinDate)
        }}</span>
      </div>
      <div class="sales-detail-field">
        <span class="sales-detail-label">{{
          t("salesList_label_department", "Department")
        }}</span>
        <span class="sales-detail-value">{{ info.departmentName || "—" }}</span>
      </div>
      <div class="sales-detail-field">
        <span class="sales-detail-label">{{
          t("salesList_label_position", "Position")
        }}</span>
        <span class="sales-detail-value">{{ info.positionName || "—" }}</span>
      </div>
      <div class="sales-detail-field sales-detail-field--full">
        <span class="sales-detail-label">{{
          t("salesList_label_referralUrl", "Referral URL")
        }}</span>
        <span class="sales-detail-value sales-detail-value--url-wrap">
          <span class="sales-detail-value--url">{{
            info.personalReferralUrl || "—"
          }}</span>
          <button
            v-if="info.personalReferralUrl"
            type="button"
            class="sales-referral-btn sales-referral-btn--copy"
            :title="t('salesList_title_copyUrl', 'Copy URL')"
            @click="copyUrl"
          >
            <i class="fas fa-copy"></i> {{ t("ibDetail_btnCopy", "Copy") }}
          </button>
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import salesApi from "@/services/salesApi";
import { useAdminI18n } from "@/composables/useAdminI18n";

const props = defineProps({
  salesId: { type: [Number, String], required: true },
  salesName: { type: String, default: "" },
  salesEmail: { type: String, default: "" },
  joinDate: { type: [String, Date], default: "" },
});

const { t } = useAdminI18n();
const loading = ref(false);
const info = ref({
  fullName: props.salesName,
  salesCode: String(props.salesId || ""),
  email: props.salesEmail,
  joinDate: props.joinDate,
  departmentName: "",
  positionName: "",
  personalReferralUrl: "",
});

const formatJoinDate = (value) => {
  if (!value) return "—";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const applyFallback = () => {
  info.value = {
    fullName: props.salesName || "—",
    salesCode: String(props.salesId || ""),
    email: props.salesEmail || "—",
    joinDate: props.joinDate,
    departmentName: "",
    positionName: "",
    personalReferralUrl: "",
  };
};

const loadInfo = async () => {
  const salesId = Number(props.salesId);
  if (!salesId) {
    applyFallback();
    return;
  }
  loading.value = true;
  applyFallback();
  try {
    const { items } = await salesApi.getList({
      page: 1,
      per_page: 10,
      search: String(salesId),
    });
    const match = (items || []).find((item) => Number(item.id) === salesId);
    if (match) {
      info.value = {
        fullName: match.fullName || match.salesName || props.salesName,
        salesCode: match.salesCode || String(salesId),
        email: match.email || props.salesEmail,
        joinDate: match.joinDate || props.joinDate,
        departmentName: match.departmentName || "",
        positionName: match.positionName || "",
        personalReferralUrl: match.personalReferralUrl || "",
      };
    }
  } catch (err) {
    applyFallback();
  } finally {
    loading.value = false;
  }
};

const copyUrl = async () => {
  const url = info.value.personalReferralUrl;
  if (!url) return;
  try {
    await navigator.clipboard.writeText(url);
    alert(t("salesList_alert_urlCopied", "URL copied"));
  } catch (err) {
    alert(t("salesList_alert_copyFailed", "Copy failed"));
  }
};

watch(
  () => props.salesId,
  () => {
    loadInfo();
  },
);

onMounted(() => {
  loadInfo();
});
</script>

<style scoped>
.sales-detail-card {
  background: var(--color-surface);
  padding: 25px;
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  border: 2px solid var(--color-border);
}

.sales-detail-card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--color-border);
}

.sales-detail-card-icon {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-md);
  background: var(--color-brand-solid);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
}

.sales-detail-card-title {
  font-size: 16px;
  color: var(--color-ink);
  font-weight: 600;
  margin: 0;
}

.sales-detail-loading {
  padding: 12px 0;
  color: var(--color-muted);
  font-size: 14px;
}

.sales-detail-fields {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 16px 20px;
}

.sales-detail-field {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid var(--color-border);
  gap: 12px;
}

.sales-detail-field--full {
  grid-column: 1 / -1;
}

.sales-detail-label {
  font-weight: 600;
  color: var(--color-muted);
  font-size: 14px;
  flex-shrink: 0;
}

.sales-detail-value {
  color: var(--color-ink);
  font-size: 14px;
}

.sales-detail-value--url {
  font-family: "Courier New", monospace;
  color: var(--color-brand);
  word-break: break-all;
}

.sales-detail-value--url-wrap {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.sales-referral-btn {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.sales-referral-btn--copy {
  background: var(--color-brand-solid);
  color: white;
}

.sales-referral-btn--copy:hover {
  background: var(--color-brand-strong);
}

@media (max-width: 900px) {
  .sales-detail-fields {
    grid-template-columns: 1fr;
  }
}
</style>
