<template>
  <div class="webmcp-export-page">
    <section class="webmcp-export-card" aria-live="polite">
      <div class="webmcp-export-orbit" aria-hidden="true">
        <span></span><span></span><span></span>
      </div>

      <p class="webmcp-export-kicker">WebMCP export</p>
      <h1>{{ title }}</h1>

      <p v-if="invalidJob" class="webmcp-export-message is-error">
        This export link is missing a valid job ID.
      </p>
      <template v-else>
        <p class="webmcp-export-message">{{ message }}</p>

        <section class="webmcp-export-details" aria-label="Export details">
          <div class="webmcp-export-details-heading">
            <span>Export details</span>
            <span class="webmcp-export-readonly">
              <i class="fas fa-lock" aria-hidden="true"></i>
              Read-only
            </span>
          </div>
          <dl class="webmcp-export-details-grid">
            <div>
              <dt>What is being exported</dt>
              <dd>{{ exportDetails.subject }}</dd>
            </div>
            <div>
              <dt>Record type</dt>
              <dd>{{ exportDetails.recordLabel }}</dd>
            </div>
            <div>
              <dt>File format</dt>
              <dd>{{ exportDetails.format }}</dd>
            </div>
            <div>
              <dt>File name</dt>
              <dd>{{ fileName || "Assigned when export is queued" }}</dd>
            </div>
            <div>
              <dt>Data type</dt>
              <dd>{{ exportDetails.dataType }}</dd>
            </div>
            <div class="webmcp-export-details-wide">
              <dt>Access scope</dt>
              <dd>{{ exportDetails.scope }}</dd>
            </div>
          </dl>
          <div v-if="exportDetails.fields.length" class="webmcp-export-fields">
            <span class="webmcp-export-fields-label">Included columns</span>
            <div class="webmcp-export-field-list">
              <span v-for="field in exportDetails.fields" :key="field">
                {{ field }}
              </span>
            </div>
          </div>
        </section>

        <div class="webmcp-export-progress-track" aria-hidden="true">
          <div
            class="webmcp-export-progress-value"
            :style="{ width: `${percent}%` }"
          ></div>
        </div>
        <div class="webmcp-export-progress-meta">
          <strong>{{ percent }}%</strong>
          <span>{{ processed }} / {{ total }} rows</span>
        </div>

        <div
          v-if="errorMessage"
          class="webmcp-export-alert is-error"
          role="alert"
        >
          {{ errorMessage }}
        </div>
        <div v-else-if="isDone" class="webmcp-export-alert is-ready">
          <i class="fas fa-circle-check" aria-hidden="true"></i>
          <template v-if="isDownloading"
            >Sending the download to your browser…</template
          >
          <template v-else-if="downloadRequestedAt">
            Download sent to your browser. No further action is needed.
            <button type="button" @click="retryDownload">
              Retry only if no file appeared
            </button>
          </template>
          <template v-else>Preparing the automatic download…</template>
        </div>
        <div v-else class="webmcp-export-alert is-running">
          <i class="fas fa-arrows-rotate" aria-hidden="true"></i>
          Keep this tab open while the export is being prepared.
        </div>
      </template>

      <code v-if="jobId" class="webmcp-export-job">Job {{ jobId }}</code>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import { useRoute } from "vue-router";
import { adminWebMcpApi } from "@/services/adminWebMcpApi";
import {
  getExportDetails,
  shouldAutoDownloadExport,
} from "@/services/webMcpExportDetails";

const route = useRoute();
const jobId = computed(() => String(route.query.jobId || "").trim());
const exportType = ref("");
const status = ref("");
const message = ref("Connecting to the export worker...");
const errorMessage = ref("");
const percent = ref(0);
const processed = ref(0);
const total = ref(0);
const fileName = ref("");
const isDownloading = ref(false);
const downloadRequestedAt = ref("");
const downloadRequestCount = ref(0);
const autoDownloadAttempted = ref(false);
let pollTimer = null;
let pollInFlight = false;

const invalidJob = computed(() => !/^[A-Za-z0-9._-]{1,80}$/.test(jobId.value));
const isDone = computed(() => status.value === "done");
const exportDetails = computed(() => getExportDetails(exportType.value));
const title = computed(() =>
  exportType.value === "transactions"
    ? "Client transaction export"
    : "Client export",
);

const stopPolling = () => {
  if (pollTimer !== null) {
    window.clearInterval(pollTimer);
    pollTimer = null;
  }
};

const parseDownloadError = async (blob) => {
  if (!(blob instanceof Blob) || !blob.type.includes("application/json")) {
    return "Unable to download the export.";
  }
  try {
    const payload = JSON.parse(await blob.text());
    return payload.message || "Unable to download the export.";
  } catch {
    return "Unable to download the export.";
  }
};

const saveBlob = (blob) => {
  const safeFileName =
    fileName.value.replace(/[^A-Za-z0-9._-]/g, "") || "webmcp_export.xls";
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = safeFileName;
  link.style.display = "none";
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 0);
};

const downloadNow = async ({ retry = false } = {}) => {
  if (
    isDownloading.value ||
    !isDone.value ||
    (!retry && downloadRequestedAt.value)
  )
    return;
  isDownloading.value = true;
  errorMessage.value = "";
  try {
    const blob = await adminWebMcpApi.downloadExport(jobId.value);
    if (blob instanceof Blob && blob.type.includes("application/json")) {
      errorMessage.value = await parseDownloadError(blob);
      return;
    }
    saveBlob(blob);
    downloadRequestedAt.value =
      downloadRequestedAt.value || new Date().toISOString();
    downloadRequestCount.value += 1;
  } catch {
    errorMessage.value = "Unable to download the export.";
  } finally {
    isDownloading.value = false;
  }
};

const retryDownload = () => downloadNow({ retry: true });

const poll = async () => {
  if (invalidJob.value || pollInFlight || isDone.value || errorMessage.value)
    return;
  pollInFlight = true;
  try {
    const payload = await adminWebMcpApi.getExportStatus(jobId.value);
    exportType.value = payload?.exportType || "";
    status.value = payload?.status || "";
    message.value = payload?.message || "Preparing export...";
    percent.value = Math.max(0, Math.min(100, Number(payload?.percent) || 0));
    processed.value = Number(payload?.processed) || 0;
    total.value = Number(payload?.total) || 0;
    fileName.value = payload?.fileName || "";
    downloadRequestedAt.value = String(payload?.downloadRequestedAt || "");
    downloadRequestCount.value = Number(payload?.downloadRequestCount) || 0;

    if (status.value === "done") {
      stopPolling();
      if (
        shouldAutoDownloadExport({
          status: status.value,
          downloadRequestedAt: downloadRequestedAt.value,
          autoDownloadAttempted: autoDownloadAttempted.value,
        })
      ) {
        autoDownloadAttempted.value = true;
        await downloadNow();
      }
    } else if (["error", "cancelled"].includes(status.value)) {
      stopPolling();
      errorMessage.value = payload?.message || "The export did not complete.";
    }
  } catch {
    stopPolling();
    errorMessage.value = "Unable to read export progress.";
  } finally {
    pollInFlight = false;
  }
};

onMounted(() => {
  if (invalidJob.value) return;
  poll();
  pollTimer = window.setInterval(poll, 1500);
});

onUnmounted(stopPolling);
</script>

<style scoped>
.webmcp-export-page {
  min-height: calc(100vh - 80px);
  display: grid;
  place-items: center;
  padding: 28px;
  background:
    radial-gradient(
      circle at 15% 20%,
      rgba(45, 100, 214, 0.1),
      transparent 34%
    ),
    linear-gradient(145deg, var(--color-surface-soft), var(--color-background));
}

.webmcp-export-card {
  position: relative;
  width: min(620px, 100%);
  overflow: hidden;
  padding: 42px 44px 36px;
  border: 1px solid var(--color-border);
  border-radius: 18px;
  background: var(--color-surface);
  box-shadow: 0 22px 60px rgba(15, 23, 42, 0.12);
}

.webmcp-export-orbit {
  position: absolute;
  top: -70px;
  right: -46px;
  width: 190px;
  height: 190px;
  border: 1px solid rgba(45, 100, 214, 0.16);
  border-radius: 50%;
}

.webmcp-export-orbit::before,
.webmcp-export-orbit::after {
  content: "";
  position: absolute;
  inset: 22px;
  border: 1px solid rgba(45, 100, 214, 0.13);
  border-radius: 50%;
}

.webmcp-export-orbit::after {
  inset: 54px;
  background: var(--color-brand-solid);
  border: 0;
  box-shadow: 0 0 0 8px rgba(45, 100, 214, 0.08);
}

.webmcp-export-orbit span {
  position: absolute;
  z-index: 1;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #d59b36;
}

.webmcp-export-orbit span:nth-child(1) {
  top: 30px;
  left: 36px;
}
.webmcp-export-orbit span:nth-child(2) {
  right: 23px;
  bottom: 48px;
}
.webmcp-export-orbit span:nth-child(3) {
  top: 77px;
  left: 13px;
}

.webmcp-export-kicker {
  position: relative;
  margin: 0 0 11px;
  color: var(--color-brand);
  font-size: 14px;
  font-weight: 800;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.webmcp-export-card h1 {
  position: relative;
  max-width: 420px;
  margin: 0;
  color: var(--color-ink);
  font-size: clamp(25px, 4vw, 36px);
  letter-spacing: -0.04em;
}

.webmcp-export-message {
  position: relative;
  margin: 15px 0 28px;
  color: var(--color-muted);
  font-size: 14px;
  line-height: 1.6;
}

.webmcp-export-details {
  position: relative;
  margin: 0 0 26px;
  padding: 15px 16px 16px;
  border: 1px solid var(--color-border);
  border-radius: 10px;
  background: var(--color-surface-soft);
}

.webmcp-export-details-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.webmcp-export-readonly {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: #23734a;
  font-size: 14px;
  letter-spacing: 0.04em;
}

.webmcp-export-details-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px 18px;
  margin: 0;
}

.webmcp-export-details-grid > div {
  min-width: 0;
}

.webmcp-export-details-grid dt {
  margin-bottom: 4px;
  color: var(--color-muted);
  font-size: 14px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.webmcp-export-details-grid dd {
  margin: 0;
  color: var(--color-ink);
  font-size: 14px;
  line-height: 1.45;
}

.webmcp-export-details-wide {
  grid-column: 1 / -1;
}

.webmcp-export-fields {
  margin-top: 14px;
  padding-top: 13px;
  border-top: 1px solid var(--color-border);
}

.webmcp-export-fields-label {
  display: block;
  margin-bottom: 8px;
  color: var(--color-muted);
  font-size: 14px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.webmcp-export-field-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.webmcp-export-field-list span {
  padding: 4px 7px;
  color: var(--color-ink);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 5px;
  font-size: 14px;
}

.webmcp-export-progress-track {
  height: 12px;
  overflow: hidden;
  border-radius: 999px;
  background: var(--color-surface-soft);
}

.webmcp-export-progress-value {
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, var(--color-brand), #5a91ed);
  transition: width 300ms ease;
}

.webmcp-export-progress-meta {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  margin-top: 11px;
  color: var(--color-muted);
  font-size: 14px;
}

.webmcp-export-progress-meta strong {
  color: var(--color-ink);
}

.webmcp-export-alert {
  display: flex;
  align-items: center;
  gap: 9px;
  margin-top: 27px;
  padding: 13px 15px;
  border-radius: 10px;
  font-size: 14px;
}

.webmcp-export-alert.is-running {
  color: var(--color-muted);
  background: var(--color-surface-soft);
}
.webmcp-export-alert.is-ready {
  color: #23734a;
  background: rgba(35, 115, 74, 0.09);
}
.webmcp-export-alert.is-error,
.webmcp-export-message.is-error {
  color: #a23b3b;
}

.webmcp-export-alert button {
  margin-left: auto;
  padding: 5px 9px;
  border: 1px solid currentColor;
  border-radius: 6px;
  color: inherit;
  background: transparent;
  cursor: pointer;
  font-size: 14px;
  font-weight: 700;
}

.webmcp-export-job {
  display: block;
  margin-top: 25px;
  color: var(--color-muted);
  font-size: 14px;
  overflow-wrap: anywhere;
}

@media (max-width: 600px) {
  .webmcp-export-page {
    padding: 14px;
  }
  .webmcp-export-card {
    padding: 32px 24px 28px;
  }
  .webmcp-export-details-grid {
    grid-template-columns: 1fr;
  }
  .webmcp-export-details-wide {
    grid-column: auto;
  }
}
</style>
