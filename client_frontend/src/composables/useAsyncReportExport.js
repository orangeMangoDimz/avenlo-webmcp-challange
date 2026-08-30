import { ref, onUnmounted } from "vue";

const EXPORT_POLL_MS = 7000;

export function useAsyncReportExport({
  getActiveExport,
  enqueueExport,
  getExportStatus,
  cancelExport,
  downloadExport,
  buildFilename,
  t,
}) {
  const exportPolling = ref(false);
  const exportJobId = ref("");
  const exportStatusText = ref("");
  const exportBannerVisible = ref(false);
  const exportCancelling = ref(false);
  const lastExportProgress = ref({
    jobId: "",
    percent: 0,
    message: "",
    status: "",
  });
  const exportModal = ref({
    visible: false,
    jobId: "",
    percent: 0,
    message: "",
    busy: false,
  });

  let exportPollTimer = null;
  let discardDownload = false;
  let pollGeneration = 0;

  const stopExportPoll = () => {
    if (exportPollTimer) {
      clearInterval(exportPollTimer);
      exportPollTimer = null;
    }
    exportPolling.value = false;
  };

  const invalidateExportPolls = () => {
    pollGeneration += 1;
    stopExportPoll();
  };

  const closeExportModal = () => {
    exportModal.value = {
      visible: false,
      jobId: "",
      percent: 0,
      message: "",
      busy: false,
    };
  };

  const resetExportUi = () => {
    discardDownload = false;
    exportCancelling.value = false;
    exportStatusText.value = "";
    exportBannerVisible.value = false;
    exportJobId.value = "";
    closeExportModal();
  };

  const isAlreadyFinishedCancelError = (error) => {
    const code = Number(error?.statusCode || 0);
    const message = String(error?.message || "").toLowerCase();
    return (
      code === 400 &&
      (message.includes("already completed") || message.includes("not ready"))
    );
  };

  const openExportModal = (payload) => {
    const data = payload?.data ?? payload ?? {};
    exportBannerVisible.value = false;
    exportModal.value = {
      visible: true,
      jobId: String(data.jobId || ""),
      percent: Number(data.percent || 0),
      message: String(data.message || ""),
      busy: false,
    };
  };

  const applyExportProgress = (payload) => {
    const data = payload?.data ?? payload ?? {};
    const percent = Number(data.percent ?? 0);
    const status = String(data.status || "");
    const message = String(data.message || "");
    const jobId = String(data.jobId || exportJobId.value || "");

    if (exportCancelling.value && ["queued", "running"].includes(status)) {
      const cancellingText = t(
        "ibReport_exportCancelling",
        "Cancelling export...",
      );
      lastExportProgress.value = {
        jobId,
        percent,
        message: cancellingText,
        status: "cancelling",
      };
      exportStatusText.value = `${cancellingText} (${percent}%)`;
      return {
        ...data,
        percent,
        status: "cancelling",
        message: cancellingText,
      };
    }

    if (status === "cancelling") {
      exportCancelling.value = true;
    }
    lastExportProgress.value = { jobId, percent, message, status };
    exportStatusText.value = message
      ? `${message} (${percent}%)`
      : t("ibReport_exportProgress", "Export progress: {percent}%").replace(
          "{percent}",
          String(percent),
        );
    if (
      exportModal.value.visible &&
      exportModal.value.jobId === (data.jobId || exportJobId.value)
    ) {
      exportModal.value = {
        ...exportModal.value,
        percent,
        message: message || exportModal.value.message,
      };
    }
    return { ...data, percent, status, message };
  };

  const triggerBlobDownload = (blob, filename) => {
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute("download", filename);
    link.style.visibility = "hidden";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    setTimeout(() => URL.revokeObjectURL(url), 100);
  };

  const filenameFromContentDisposition = (disposition) => {
    const value = String(disposition || "");
    if (!value) return "";
    const utf = value.match(/filename\*=(?:UTF-8'')?([^;]+)/i);
    if (utf) {
      try {
        return decodeURIComponent(String(utf[1]).replace(/["']/g, "").trim());
      } catch (_) {
        return String(utf[1]).replace(/["']/g, "").trim();
      }
    }
    const plain = value.match(/filename=([^;]+)/i);
    if (!plain) return "";
    return String(plain[1]).replace(/["']/g, "").trim();
  };

  const downloadExportFile = async (jobId) => {
    if (discardDownload) return;
    const response = await downloadExport(jobId);
    if (discardDownload) return;
    const blob =
      response?.data instanceof Blob
        ? response.data
        : new Blob([response?.data || response], {
            type: "text/csv;charset=utf-8;",
          });
    if (blob.type && blob.type.includes("application/json")) {
      const text = await blob.text();
      let parsed = null;
      try {
        parsed = JSON.parse(text);
      } catch (_) {}
      throw new Error(
        parsed?.message ||
          t("ibReport_alert_exportFail", "Failed to export. Please try again."),
      );
    }
    if (discardDownload) return;
    const headerFilename = filenameFromContentDisposition(
      response?.headers?.["content-disposition"] ||
        response?.headers?.["Content-Disposition"],
    );
    const filename =
      headerFilename ||
      (typeof buildFilename === "function"
        ? buildFilename()
        : `ib_commission_detail_${new Date().toISOString().split("T")[0]}.csv`);
    triggerBlobDownload(blob, filename);
  };

  const handleExportTerminal = async (data) => {
    const status = String(data.status || "");
    const jobId = data.jobId || exportJobId.value;
    stopExportPoll();
    if (status === "done") {
      if (discardDownload) {
        resetExportUi();
        return;
      }
      try {
        await downloadExportFile(jobId);
        if (discardDownload) {
          resetExportUi();
          return;
        }
        resetExportUi();
      } catch (error) {
        console.error("Failed to download export:", error);
        alert(
          error?.message ||
            t(
              "ibReport_alert_exportFail",
              "Failed to export. Please try again.",
            ),
        );
      }
      return;
    }
    if (status === "cancelled") {
      resetExportUi();
      return;
    }
    if (status === "error") {
      resetExportUi();
      alert(
        data.message ||
          t("ibReport_alert_exportFail", "Failed to export. Please try again."),
      );
    }
  };

  const pollExportOnce = async (jobId) => {
    const generation = pollGeneration;
    const response = await getExportStatus(jobId);
    if (generation !== pollGeneration) return;
    const data = applyExportProgress(response);
    if (["done", "error", "cancelled"].includes(String(data.status || ""))) {
      await handleExportTerminal(data);
    }
  };

  const startExportPoll = (jobId) => {
    invalidateExportPolls();
    exportJobId.value = jobId;
    exportPolling.value = true;
    pollExportOnce(jobId).catch((error) => {
      console.error("Export poll failed:", error);
    });
    exportPollTimer = setInterval(() => {
      pollExportOnce(jobId).catch((error) => {
        console.error("Export poll failed:", error);
      });
    }, EXPORT_POLL_MS);
  };

  const dismissModalToBanner = () => {
    const jobId = exportModal.value.jobId || exportJobId.value;
    closeExportModal();
    if (!jobId) return;
    exportBannerVisible.value = true;
    if (!exportPolling.value || exportJobId.value !== jobId) {
      startExportPoll(jobId);
    }
  };

  const onExportModalContinue = () => {
    dismissModalToBanner();
  };

  const requestUserCancel = async (jobId, percent) => {
    if (!jobId || exportCancelling.value) return;
    discardDownload = true;
    exportCancelling.value = true;
    invalidateExportPolls();
    exportJobId.value = jobId;
    exportBannerVisible.value = true;
    exportStatusText.value = `${t("ibReport_exportCancelling", "Cancelling export...")} (${percent}%)`;
    try {
      await cancelExport(jobId);
      startExportPoll(jobId);
    } catch (error) {
      if (isAlreadyFinishedCancelError(error)) {
        resetExportUi();
        return;
      }
      discardDownload = false;
      exportCancelling.value = false;
      startExportPoll(jobId);
      alert(
        error?.message ||
          t("ibReport_alert_exportFail", "Failed to export. Please try again."),
      );
    }
  };

  const onExportModalCancel = async () => {
    const jobId = exportModal.value.jobId;
    const percent = Number(exportModal.value.percent || 0);
    closeExportModal();
    await requestUserCancel(jobId, percent);
  };

  const cancelActiveExport = async () => {
    const jobId = exportJobId.value;
    const percentMatch = String(exportStatusText.value || "").match(
      /\((\d+)%\)/,
    );
    const percent = percentMatch ? percentMatch[1] : "0";
    await requestUserCancel(jobId, percent);
  };

  const enqueueNewExport = async (buildParams) => {
    discardDownload = false;
    try {
      const exportParams =
        typeof buildParams === "function" ? buildParams() : buildParams || {};
      const response = await enqueueExport(exportParams);
      const result = response?.data ?? response;
      const jobId = result?.jobId;
      if (!jobId) {
        alert(
          t("ibReport_alert_exportFail", "Failed to export. Please try again."),
        );
        return;
      }
      const queuedMessage = t("ibReport_exportQueued", "Export queued...");
      exportStatusText.value = `${queuedMessage} (0%)`;
      startExportPoll(jobId);
      openExportModal({
        jobId,
        status: "queued",
        percent: 0,
        message: queuedMessage,
      });
    } catch (error) {
      if (error?.statusCode === 409) {
        const payload = error?.errors || {};
        const jobId = String(payload.jobId || "");
        if (jobId) {
          applyExportProgress(payload);
          startExportPoll(jobId);
        }
        openExportModal(payload);
        return;
      }
      console.error("Failed to export:", error);
      alert(
        error?.message ||
          t("ibReport_alert_exportFail", "Failed to export. Please try again."),
      );
    }
  };

  const startOrResumeExport = async (buildParams) => {
    discardDownload = false;
    try {
      const response = await getActiveExport();
      const active = response?.data ?? response;
      if (active?.active) {
        const status = String(active.status || "");
        if (["queued", "running", "cancelling"].includes(status)) {
          applyExportProgress(active);
          if (active.jobId) {
            startExportPoll(active.jobId);
          }
          openExportModal(active);
          return;
        }
        if (status === "done" && active.jobId) {
          try {
            await downloadExportFile(active.jobId);
            resetExportUi();
          } catch (error) {
            console.error("Failed to download export:", error);
            alert(
              error?.message ||
                t(
                  "ibReport_alert_exportFail",
                  "Failed to export. Please try again.",
                ),
            );
          }
          return;
        }
      }
      await enqueueNewExport(buildParams);
    } catch (error) {
      console.error("Failed to check export status:", error);
      alert(
        error?.message ||
          t("ibReport_alert_exportFail", "Failed to export. Please try again."),
      );
    }
  };

  const resumeActiveExportIfAny = async () => {
    try {
      const response = await getActiveExport();
      const active = response?.data ?? response;
      if (!active?.active || !active.jobId) return;
      const status = String(active.status || "");
      if (!["queued", "running", "cancelling"].includes(status)) return;
      applyExportProgress(active);
      exportBannerVisible.value = true;
      startExportPoll(active.jobId);
    } catch (error) {
      console.error("Failed to resume export status:", error);
    }
  };

  const dispose = () => {
    invalidateExportPolls();
  };

  onUnmounted(() => {
    dispose();
  });

  return {
    exportPolling,
    exportJobId,
    exportStatusText,
    exportBannerVisible,
    exportCancelling,
    exportModal,
    lastExportProgress,
    startOrResumeExport,
    resumeActiveExportIfAny,
    cancelActiveExport,
    onExportModalContinue,
    onExportModalCancel,
    stopExportPoll,
    dispose,
  };
}
