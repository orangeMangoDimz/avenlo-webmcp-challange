import { adminWebMcpApi } from "@/services/adminWebMcpApi";

const MAX_CLIENT_ID = 2147483647;
const LOOKUP_KEYS = ["email", "id", "code"];
const noop = () => {};

const clientLookupInputSchema = {
  type: "object",
  properties: {
    email: {
      type: "string",
      maxLength: 254,
      description: "Exact client email address.",
    },
    id: {
      type: "integer",
      minimum: 1,
      maximum: MAX_CLIENT_ID,
      description: "Exact numeric client ID.",
    },
    code: {
      type: "string",
      maxLength: 64,
      description: "Exact approved IB code for a client who is an IB.",
    },
  },
  oneOf: [
    { required: ["email"] },
    { required: ["id"] },
    { required: ["code"] },
  ],
  additionalProperties: false,
};

const hasClientReadPermission = (authStore) =>
  authStore?.hasPermission?.("page_clientslist_readonly") ||
  authStore?.hasPermission?.("page_clientsdetail_profile");

const requirePermission = (authStore) => {
  if (!authStore?.isAuthenticated || !hasClientReadPermission(authStore)) {
    throw new Error("You are not authorized to use this tool.");
  }
};

const resolveClient = async ({ authStore, webMcpApi }, input) => {
  requirePermission(authStore);
  const lookup = normalizeLookupInput(input);

  try {
    return await webMcpApi.getClient(lookup);
  } catch (error) {
    if (
      error?.statusCode === 404 ||
      error?.response?.data?.statusCode === 404
    ) {
      throw new Error("Client not found.");
    }
    throw new Error("Unable to get client.");
  }
};

export const normalizeLookupInput = (input = {}) => {
  if (!input || typeof input !== "object" || Array.isArray(input)) {
    throw new Error("Input must be an object.");
  }

  const providedKeys = LOOKUP_KEYS.filter((key) =>
    Object.prototype.hasOwnProperty.call(input, key),
  );
  if (providedKeys.length !== 1) {
    throw new Error("Exactly one of email, id, or code is required.");
  }

  const [key] = providedKeys;
  if (key === "email") {
    if (typeof input.email !== "string") {
      throw new Error("email must be a string.");
    }

    const email = input.email.trim();
    if (!email || email.length > 254 || !/^\S+@\S+\.\S+$/.test(email)) {
      throw new Error(
        "email must be a valid email address of 254 characters or fewer.",
      );
    }
    return { email };
  }

  if (key === "code") {
    if (typeof input.code !== "string") {
      throw new Error("code must be a string.");
    }

    const code = input.code.trim();
    if (!code || code.length > 64) {
      throw new Error("code must be between 1 and 64 characters.");
    }
    return { code };
  }

  const id =
    typeof input.id === "string" && /^\d+$/.test(input.id.trim())
      ? Number(input.id.trim())
      : Number(input.id);
  if (!Number.isSafeInteger(id) || id < 1 || id > MAX_CLIENT_ID) {
    throw new Error(`id must be an integer between 1 and ${MAX_CLIENT_ID}.`);
  }
  return { id };
};

const clientName = (client) =>
  [client.firstName, client.lastName].filter(Boolean).join(" ").trim() ||
  client.name ||
  client.email ||
  `Client ${client.id}`;

export const projectClient = (client = {}) => {
  const ibCode =
    typeof client.ibCode === "string" && client.ibCode.trim() !== ""
      ? client.ibCode.trim()
      : null;

  return {
    id: Number(client.id),
    name: clientName(client),
    email: client.email || null,
    country: client.country || null,
    status: client.status || null,
    kycStatus: client.kycStatus || client.latestKycStatus || null,
    manager:
      client.manager || client.managerName || client.managerEmail || null,
    registeredAt: client.createdAt || null,
    lastLoginAt: client.lastLoginAt || null,
    isIb: Boolean(ibCode),
    ibCode,
  };
};

export const createGetClientTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
}) => ({
  name: "get_client",
  title: "Get client",
  description:
    "Retrieve one client visible to the signed-in administrator by email, client ID, or exact approved IB code.",
  inputSchema: clientLookupInputSchema,
  annotations: {
    readOnlyHint: true,
    untrustedContentHint: true,
  },
  execute: async (input = {}) => {
    const client = await resolveClient({ authStore, webMcpApi }, input);
    return { client: projectClient(client) };
  },
});

export const createNavigateToClientTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
  router,
}) => ({
  name: "navigate_to_client",
  title: "Navigate to client",
  description:
    "Open one client visible to the signed-in administrator in the admin dashboard by email, client ID, or exact approved IB code.",
  inputSchema: clientLookupInputSchema,
  annotations: {
    readOnlyHint: true,
    untrustedContentHint: true,
  },
  execute: async (input = {}) => {
    const client = await resolveClient({ authStore, webMcpApi }, input);
    const clientId = Number(client.id);

    if (!router?.push) {
      throw new Error("Unable to navigate to client.");
    }

    try {
      await router.push({
        name: "client-detail",
        query: { id: clientId },
      });
    } catch {
      throw new Error("Unable to navigate to client.");
    }

    return {
      success: true,
      clientId,
      route: `/client-detail?id=${clientId}`,
    };
  },
});

export const registerAdminClientWebMcpTools = ({
  authStore,
  webMcpApi = adminWebMcpApi,
  router,
}) => {
  const modelContext =
    typeof document === "undefined" ? null : document.modelContext;
  if (
    !modelContext?.registerTool ||
    !authStore?.isAuthenticated ||
    !hasClientReadPermission(authStore)
  ) {
    return noop;
  }

  const controller = new AbortController();
  const tools = [
    createGetClientTool({ authStore, webMcpApi }),
    createNavigateToClientTool({ authStore, webMcpApi, router }),
  ];

  try {
    tools.forEach((tool) => {
      Promise.resolve(
        modelContext.registerTool(tool, { signal: controller.signal }),
      ).catch((error) => {
        controller.abort();
        if (error?.name !== "AbortError") {
          console.warn(
            "Admin client WebMCP tool could not be registered.",
            error,
          );
        }
      });
    });
  } catch (error) {
    controller.abort();
    console.warn("Admin client WebMCP tool could not be registered.", error);
  }

  return () => controller.abort();
};
