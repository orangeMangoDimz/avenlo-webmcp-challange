import { adminWebMcpApi } from "@/services/adminWebMcpApi";
import { WEBMCP_TOOL_CATALOG } from "@/services/adminWebMcpCatalog";

const MAX_ID = 2147483647;
const MAX_WAITING_HOURS = 87600;
const KYC_PERMISSION = "page_kyclist_readonly";
const noop = () => {};

const submissionIdProperty = {
  type: "integer",
  minimum: 1,
  maximum: MAX_ID,
  description: "Exact numeric KYC submission ID.",
};

const kycSubmissionInputSchema = {
  type: "object",
  required: ["submissionId"],
  properties: { submissionId: submissionIdProperty },
  additionalProperties: false,
};

const kycSearchInputSchema = {
  type: "object",
  properties: {
    submissionId: submissionIdProperty,
    email: {
      type: "string",
      maxLength: 254,
      description: "Exact client email address.",
    },
    country: {
      type: "string",
      maxLength: 100,
      description: "Exact country code or country name.",
    },
    status: {
      type: "string",
      enum: [
        "draft",
        "incomplete",
        "pending",
        "under_review",
        "requires_documents",
        "approved",
        "rejected",
        "expired",
      ],
      description: "Canonical KYC submission status.",
    },
    assigned: {
      type: "boolean",
      description: "Use false to find submissions without a reviewer.",
    },
    reviewerId: {
      type: "integer",
      minimum: 1,
      maximum: MAX_ID,
      description: "Exact assigned reviewer ID.",
    },
    templateId: {
      type: "integer",
      minimum: 1,
      maximum: MAX_ID,
      description: "Exact KYC template ID.",
    },
    provider: {
      type: "string",
      maxLength: 100,
      description: "Exact verification provider name, or Local.",
    },
    minWaitingHours: {
      type: "integer",
      minimum: 0,
      maximum: MAX_WAITING_HOURS,
      description: "Minimum whole hours the submission has waited.",
    },
    page: { type: "integer", minimum: 1, maximum: 1000 },
    limit: { type: "integer", minimum: 1, maximum: 50 },
  },
  anyOf: [
    { required: ["submissionId"] },
    { required: ["email"] },
    { required: ["country"] },
    { required: ["status"] },
    { required: ["assigned"] },
    { required: ["reviewerId"] },
    { required: ["templateId"] },
    { required: ["provider"] },
    { required: ["minWaitingHours"] },
  ],
  additionalProperties: false,
};

const normalizeInteger = (value, name, minimum, maximum, defaultValue) => {
  if (value === undefined) return defaultValue;
  const number =
    typeof value === "string" && /^\d+$/.test(value.trim())
      ? Number(value.trim())
      : value;
  if (
    !Number.isSafeInteger(number) ||
    number < minimum ||
    number > maximum
  ) {
    throw new Error(
      `${name} must be an integer between ${minimum} and ${maximum}.`,
    );
  }
  return number;
};

const normalizeString = (value, name, maximum) => {
  if (typeof value !== "string") {
    throw new Error(`${name} must be a string.`);
  }
  const normalized = value.trim();
  if (!normalized || normalized.length > maximum) {
    throw new Error(`${name} must be between 1 and ${maximum} characters.`);
  }
  return normalized;
};

const canonicalStatus = (value) => {
  const status = String(value).trim().toLowerCase();
  if (status === "submitted") return "pending";
  if (["resubmit_required", "pending_documents"].includes(status)) {
    return "requires_documents";
  }
  return status;
};

export const normalizeKycSearchInput = (input = {}) => {
  if (!input || typeof input !== "object" || Array.isArray(input)) {
    throw new Error("Input must be an object.");
  }
  const filterKeys = [
    "submissionId",
    "email",
    "country",
    "status",
    "assigned",
    "reviewerId",
    "templateId",
    "provider",
    "minWaitingHours",
  ];
  const allowedKeys = [...filterKeys, "page", "limit"];
  const unsupportedKey = Object.keys(input).find(
    (key) => !allowedKeys.includes(key),
  );
  if (unsupportedKey) {
    throw new Error(`${unsupportedKey} is not supported for KYC search.`);
  }
  if (!filterKeys.some((key) => Object.hasOwn(input, key))) {
    throw new Error("At least one KYC search filter is required.");
  }

  const normalized = {};
  for (const key of ["submissionId", "reviewerId", "templateId"]) {
    if (Object.hasOwn(input, key)) {
      normalized[key] = normalizeInteger(input[key], key, 1, MAX_ID);
    }
  }
  if (Object.hasOwn(input, "email")) {
    normalized.email = normalizeString(input.email, "email", 254);
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalized.email)) {
      throw new Error("email must be a valid email address.");
    }
  }
  for (const [key, maximum] of [
    ["country", 100],
    ["provider", 100],
  ]) {
    if (Object.hasOwn(input, key)) {
      normalized[key] = normalizeString(input[key], key, maximum);
    }
  }
  if (Object.hasOwn(input, "status")) {
    const status = canonicalStatus(input.status);
    const statuses = [
      "draft",
      "incomplete",
      "pending",
      "under_review",
      "requires_documents",
      "approved",
      "rejected",
      "expired",
    ];
    if (!statuses.includes(status)) {
      throw new Error(`status must be one of: ${statuses.join(", ")}.`);
    }
    normalized.status = status;
  }
  if (Object.hasOwn(input, "assigned")) {
    if (typeof input.assigned !== "boolean") {
      throw new Error("assigned must be a boolean.");
    }
    normalized.assigned = input.assigned;
  }
  if (Object.hasOwn(input, "minWaitingHours")) {
    normalized.minWaitingHours = normalizeInteger(
      input.minWaitingHours,
      "minWaitingHours",
      0,
      MAX_WAITING_HOURS,
    );
  }
  normalized.page = normalizeInteger(input.page, "page", 1, 1000, 1);
  normalized.limit = normalizeInteger(input.limit, "limit", 1, 50, 25);
  return normalized;
};

export const normalizeKycSubmissionInput = (input = {}) => {
  if (!input || typeof input !== "object" || Array.isArray(input)) {
    throw new Error("Input must be an object.");
  }
  const unsupportedKey = Object.keys(input).find(
    (key) => key !== "submissionId",
  );
  if (unsupportedKey) {
    throw new Error(
      `${unsupportedKey} is not supported for a KYC submission lookup.`,
    );
  }
  if (!Object.hasOwn(input, "submissionId")) {
    throw new Error("submissionId is required.");
  }
  return {
    submissionId: normalizeInteger(
      input.submissionId,
      "submissionId",
      1,
      MAX_ID,
    ),
  };
};

const requirePermission = (authStore) => {
  if (
    !authStore?.isAuthenticated ||
    !authStore?.hasPermission?.(KYC_PERMISSION)
  ) {
    throw new Error("You are not authorized to use this tool.");
  }
};

const executeApi = async (operation, notFoundMessage, unavailableMessage) => {
  try {
    return await operation();
  } catch (error) {
    const statusCode = error?.statusCode ?? error?.response?.data?.statusCode;
    if (statusCode === 401 || statusCode === 403) {
      throw new Error("You are not authorized to use this tool.");
    }
    if (statusCode === 404) {
      throw new Error(notFoundMessage);
    }
    throw new Error(unavailableMessage);
  }
};

const catalogEntry = (name) =>
  WEBMCP_TOOL_CATALOG.find((tool) => tool.name === name);

const createSubmissionTool = ({
  name,
  apiMethod,
  authStore,
  webMcpApi,
  unavailableMessage,
}) => ({
  name,
  description: catalogEntry(name)?.description || name,
  inputSchema: kycSubmissionInputSchema,
  execute: async (input = {}) => {
    requirePermission(authStore);
    const normalized = normalizeKycSubmissionInput(input);
    return executeApi(
      () => webMcpApi[apiMethod](normalized),
      "KYC submission not found.",
      unavailableMessage,
    );
  },
});

export const createSearchKycTool = ({ authStore, webMcpApi }) => ({
  name: "search_kyc",
  description: catalogEntry("search_kyc")?.description || "Search KYC submissions.",
  inputSchema: kycSearchInputSchema,
  execute: async (input = {}) => {
    requirePermission(authStore);
    const normalized = normalizeKycSearchInput(input);
    return executeApi(
      () => webMcpApi.searchKyc(normalized),
      "No visible KYC submissions matched the search.",
      "Unable to search KYC submissions.",
    );
  },
});

export const createGetKycTool = ({ authStore, webMcpApi }) =>
  createSubmissionTool({
    name: "get_kyc",
    apiMethod: "getKyc",
    authStore,
    webMcpApi,
    unavailableMessage: "Unable to get the KYC submission.",
  });

export const createGetKycAnswersTool = ({ authStore, webMcpApi }) =>
  createSubmissionTool({
    name: "get_kyc_answers",
    apiMethod: "getKycAnswers",
    authStore,
    webMcpApi,
    unavailableMessage: "Unable to get KYC answers.",
  });

export const createGetKycDocumentsTool = ({ authStore, webMcpApi }) =>
  createSubmissionTool({
    name: "get_kyc_documents",
    apiMethod: "getKycDocuments",
    authStore,
    webMcpApi,
    unavailableMessage: "Unable to get KYC document metadata.",
  });

export const createGetKycProgressTool = ({ authStore, webMcpApi }) =>
  createSubmissionTool({
    name: "get_kyc_progress",
    apiMethod: "getKycProgress",
    authStore,
    webMcpApi,
    unavailableMessage: "Unable to get KYC progress.",
  });

export const createGetKycTimelineTool = ({ authStore, webMcpApi }) =>
  createSubmissionTool({
    name: "get_kyc_timeline",
    apiMethod: "getKycTimeline",
    authStore,
    webMcpApi,
    unavailableMessage: "Unable to get the KYC timeline.",
  });

export const registerAdminKycWebMcpTools = ({
  authStore,
  webMcpApi = adminWebMcpApi,
  modelContext = typeof document !== "undefined"
    ? document.modelContext
    : undefined,
} = {}) => {
  if (
    !modelContext?.registerTool ||
    !authStore?.isAuthenticated ||
    !authStore?.hasPermission?.(KYC_PERMISSION)
  ) {
    return noop;
  }

  const controller = new AbortController();
  const tools = [
    createSearchKycTool({ authStore, webMcpApi }),
    createGetKycTool({ authStore, webMcpApi }),
    createGetKycAnswersTool({ authStore, webMcpApi }),
    createGetKycDocumentsTool({ authStore, webMcpApi }),
    createGetKycProgressTool({ authStore, webMcpApi }),
    createGetKycTimelineTool({ authStore, webMcpApi }),
  ];

  try {
    tools.forEach((tool) => {
      Promise.resolve(
        modelContext.registerTool(tool, { signal: controller.signal }),
      ).catch((error) => {
        controller.abort();
        if (error?.name !== "AbortError") {
          console.warn("Admin KYC WebMCP tool could not be registered.", error);
        }
      });
    });
  } catch (error) {
    controller.abort();
    console.warn("Admin KYC WebMCP tool could not be registered.", error);
  }

  return () => controller.abort();
};
