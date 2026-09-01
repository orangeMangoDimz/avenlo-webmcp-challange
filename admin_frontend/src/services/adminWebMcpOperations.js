const DAY_MS = 24 * 60 * 60 * 1000;
const ACTIVITY_PREFIX = "adminWebMcpDashboardActivity";

const dateInOffset = (date, offsetMinutes) => {
  const shifted = new Date(date.getTime() + offsetMinutes * 60 * 1000);
  return [
    shifted.getUTCFullYear(),
    String(shifted.getUTCMonth() + 1).padStart(2, "0"),
    String(shifted.getUTCDate()).padStart(2, "0"),
  ].join("-");
};

export const defaultOperationsFilters = (
  now = new Date(),
  tzOffset = -new Date().getTimezoneOffset(),
) => ({
  startDate: dateInOffset(new Date(now.getTime() - 6 * DAY_MS), tzOffset),
  endDate: dateInOffset(now, tzOffset),
  tzOffset,
});

const section = (value, fallback = { status: "restricted" }) => {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return { ...fallback };
  }
  if (value.status === "restricted") return { status: "restricted" };
  if (value.status === "error") {
    return { status: "error", message: String(value.message || "Unable to load this section.") };
  }
  return { ...value };
};

export const normalizeOperationsOverview = (payload = {}) => ({
  generatedAt: String(payload.generatedAt || ""),
  period: payload.period && typeof payload.period === "object" ? { ...payload.period } : {},
  policy: payload.policy && typeof payload.policy === "object" ? { ...payload.policy } : {},
  scope: Object.fromEntries(
    Object.entries(payload.scope || {}).map(([key, value]) => [
      key,
      {
        access: ["all", "own", "self", "team"].includes(value?.access)
          ? value.access
          : "restricted",
        canExport: Boolean(value?.canExport),
      },
    ]),
  ),
  metrics: Object.fromEntries(
    Object.entries(payload.metrics || {}).map(([key, value]) => [key, section(value)]),
  ),
  attentionQueue: {
    items: Array.isArray(payload.attentionQueue?.items)
      ? payload.attentionQueue.items.map((item) => ({ ...item }))
      : [],
    total: Number(payload.attentionQueue?.total) || 0,
    truncated: Boolean(payload.attentionQueue?.truncated),
  },
  fundingTrend: section(payload.fundingTrend),
  sales: section(payload.sales),
  ib: section(payload.ib),
  recentActivity: section(payload.recentActivity, { status: "ready", items: [] }),
  sectionErrors: Array.isArray(payload.sectionErrors)
    ? payload.sectionErrors.map((error) => ({ ...error }))
    : [],
});

export const paginateItems = (items = [], requestedPage = 1, requestedPerPage = 10) => {
  const values = Array.isArray(items) ? items : [];
  const perPage = Math.max(1, Math.min(100, Number(requestedPerPage) || 10));
  const total = values.length;
  const totalPages = Math.max(1, Math.ceil(total / perPage));
  const page = Math.max(1, Math.min(totalPages, Number(requestedPage) || 1));
  const from = total === 0 ? 0 : (page - 1) * perPage + 1;
  const to = total === 0 ? 0 : Math.min(page * perPage, total);
  return { items: values.slice(from - 1, to), page, perPage, total, totalPages, from, to };
};

export const paginationPages = (totalPages, currentPage, maxVisible = 5) => {
  const total = Math.max(1, Number(totalPages) || 1);
  const current = Math.max(1, Math.min(total, Number(currentPage) || 1));
  if (total <= maxVisible) return Array.from({ length: total }, (_, index) => index + 1);
  if (current <= 3) return [1, 2, 3, 4, "…", total];
  if (current >= total - 2) return [1, "…", total - 3, total - 2, total - 1, total];
  return [1, "…", current - 1, current, current + 1, "…", total];
};

const positiveId = (value) => {
  const number = Number(value);
  return Number.isSafeInteger(number) && number > 0 ? String(number) : "";
};

export const buildOperationsRecordRoute = (item = {}) => {
  if (item.kind === "transaction") {
    const typeRoutes = {
      deposit: "deposits",
      withdrawal: "withdrawals",
      internal_transfer: "internal-transfers",
    };
    const name = typeRoutes[item.transactionType];
    const detailId = positiveId(item.recordId);
    const transactionId = String(item.transactionId || "").trim();
    if (!name || !detailId || !transactionId) return null;
    return {
      name,
      query: {
        source: "webmcp-overview",
        search: transactionId,
        detailId,
      },
    };
  }
  if (item.kind === "kyc") {
    const submissionId = positiveId(item.submissionId);
    return submissionId
      ? {
          name: "kyc-list",
          query: { source: "webmcp-overview", submissionId },
        }
      : null;
  }
  if (item.kind === "client") {
    const id = positiveId(item.clientId);
    return id ? { name: "client-detail", query: { id } } : null;
  }
  if (item.kind === "audit") {
    const operationLogId = positiveId(item.operationLogId);
    return operationLogId
      ? {
          name: "operation-log-report",
          query: { source: "webmcp", modelKey: "all", query: operationLogId },
        }
      : null;
  }
  return null;
};

const activityKey = (adminId) => `${ACTIVITY_PREFIX}:${positiveId(adminId) || "anonymous"}`;

const parseActivities = (storage, key) => {
  try {
    const value = JSON.parse(storage?.getItem?.(key) || "[]");
    return Array.isArray(value) ? value : [];
  } catch {
    return [];
  }
};

export const createWebMcpActivityStore = ({
  storage = typeof window !== "undefined" ? window.localStorage : null,
  adminId,
  now = () => Date.now(),
  limit = 20,
  retentionDays = 30,
} = {}) => {
  const key = activityKey(adminId);
  const clean = () => {
    const cutoff = now() - Math.max(1, retentionDays) * DAY_MS;
    return parseActivities(storage, key)
      .filter((item) => Number(item?.createdAtMs) >= cutoff)
      .slice(0, Math.max(1, limit));
  };
  const persist = (items) => {
    try {
      storage?.setItem?.(key, JSON.stringify(items));
    } catch {
      // Activity history is optional and must never block dashboard actions.
    }
  };
  return {
    list: () => clean(),
    record: (activity = {}) => {
      const createdAtMs = now();
      const item = {
        id: String(activity.id || `${createdAtMs}`),
        kind: String(activity.kind || "investigation"),
        label: String(activity.label || "Dashboard activity").slice(0, 160),
        route: activity.route && typeof activity.route === "object" ? activity.route : null,
        jobId: String(activity.jobId || "").slice(0, 80),
        exportKind: String(activity.exportKind || "").slice(0, 80),
        createdAt: new Date(createdAtMs).toISOString(),
        createdAtMs,
      };
      const items = [item, ...clean().filter((existing) => existing.id !== item.id)].slice(
        0,
        Math.max(1, limit),
      );
      persist(items);
      return item;
    },
  };
};

export const recordWebMcpActivity = (activity, adminId) => {
  try {
    const storage = typeof window !== "undefined" ? window.localStorage : null;
    if (!storage) return null;
    return createWebMcpActivityStore({ storage, adminId }).record(activity);
  } catch {
    return null;
  }
};

const csvCell = (value) => {
  let text = String(value ?? "").replaceAll(/\r?\n/g, " ");
  if (/^[=+\-@]/.test(text)) text = `'${text}`;
  return `"${text.replaceAll('"', '""')}"`;
};

export const buildEvidenceCsv = (items = []) => {
  const rows = [
    ["Severity", "Type", "Reason", "Related record", "Age hours"],
    ...items.map((item) => [
      item.severity,
      item.kind,
      item.reason,
      item.relatedLabel,
      item.ageHours,
    ]),
  ];
  return rows.map((row, index) => row.map(index === 0 ? String : csvCell).join(",")).join("\r\n");
};

export const downloadEvidenceCsv = (items, fileName = "webmcp_attention_evidence.csv") => {
  const blob = new Blob([buildEvidenceCsv(items)], { type: "text/csv;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = fileName.replace(/[^A-Za-z0-9._-]/g, "_");
  link.style.display = "none";
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 0);
};
