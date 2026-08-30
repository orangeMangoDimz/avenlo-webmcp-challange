import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { setActivePinia, createPinia } from "pinia";
import { useClientAuthStore } from "@/stores/clientAuth";

// api.js's request interceptor is what we're testing. Its response
// interceptor separately imports the app router only to redirect on 401
// (out of scope here). Router/index.js itself imports
// @/services/previewSessionService, which imports @/services/api — i.e.
// api.js -> router -> previewSessionService -> api.js is a real import
// cycle in this codebase. Mocking @/router avoids ever evaluating that
// cycle (and the admin auth store / lazy view imports router pulls in),
// keeping this file scoped to the request interceptor's write-block logic.
vi.mock("@/router", () => ({
  default: {
    push: vi.fn(),
    currentRoute: { value: { path: "/" } },
  },
}));

// Imported unmocked — its own request interceptor is exactly what this file
// tests. @/stores/clientAuth is intentionally NOT mocked either: real Pinia
// is used so the store instance the interceptor reads via useClientAuthStore()
// is the same instance this file controls via $patch (see
// .claude/docs/client-frontend-agent/pinia-store-test-pattern.md, "Real Store
// (recommended for store unit tests)").
import api from "@/services/api";

// Mirrors the private PREVIEW_BLOCK_MESSAGE constant in src/services/api.js
// (not exported, so duplicated here intentionally — if the real message
// changes, these assertions should fail and flag the change for review).
const PREVIEW_BLOCK_MESSAGE = "Preview mode - operation not allowed";

/**
 * The request interceptor registered by src/services/api.js via
 * `api.interceptors.request.use(fulfilled, rejected)`. Axios's
 * InterceptorManager (verified against the installed axios@1.12.2 source at
 * node_modules/axios/lib/core/InterceptorManager.js) stores registered
 * interceptors as `{ fulfilled, rejected, synchronous, runWhen }` objects in
 * an internal `handlers` array. api.js registers exactly one request
 * interceptor, so index 0 is stable. Invoking it directly lets us test the
 * write-block branch logic without dispatching a real HTTP request (there is
 * no backend in the test environment, and the real request would otherwise
 * hang/timeout instead of failing fast).
 */
const requestInterceptor = api.interceptors.request.handlers[0].fulfilled;

/**
 * Builds a minimal Axios request config. `headers` must be a plain object,
 * not undefined: outside of preview mode the interceptor unconditionally
 * does `config.headers.Authorization = ...` once a token exists in
 * localStorage, which is not wrapped in try/catch and would throw on
 * `config.headers === undefined`.
 */
function makeConfig(method, url, overrides = {}) {
  return {
    method,
    url,
    headers: {},
    ...overrides,
  };
}

describe("api.js request interceptor — preview mode write block", () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    sessionStorage.clear();
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe("scenario 1 — allowlisted commission-report export endpoint is exempt", () => {
    it("lets a POST to /client/commission-report/export through while in preview mode", async () => {
      // Arrange
      const store = useClientAuthStore();
      store.$patch({ isPreviewMode: true });
      const dispatchEventSpy = vi.spyOn(window, "dispatchEvent");
      const config = makeConfig("post", "/client/commission-report/export");

      // Act
      const result = await requestInterceptor(config);

      // Assert — interceptor resolved (did not reject), so the request proceeds
      expect(result).toBe(config);
      expect(window.alert).not.toHaveBeenCalled();
      expect(dispatchEventSpy).not.toHaveBeenCalled();
    });

    it("also lets the allowlisted endpoint through when the URL has no leading slash", async () => {
      // Arrange — PREVIEW_ALLOWED_WRITE_ENDPOINTS lists both slash variants;
      // this exercises the second array entry specifically.
      const store = useClientAuthStore();
      store.$patch({ isPreviewMode: true });
      const config = makeConfig("post", "client/commission-report/export");

      // Act
      const result = await requestInterceptor(config);

      // Assert
      expect(result).toBe(config);
      expect(window.alert).not.toHaveBeenCalled();
    });
  });

  describe("scenario 1b — allowlisted commission-report export-cancel endpoint is exempt", () => {
    it("lets a POST to /client/commission-report/export-cancel through while in preview mode", async () => {
      // Arrange
      const store = useClientAuthStore();
      store.$patch({ isPreviewMode: true });
      const dispatchEventSpy = vi.spyOn(window, "dispatchEvent");
      const config = makeConfig(
        "post",
        "/client/commission-report/export-cancel",
      );

      // Act
      const result = await requestInterceptor(config);

      // Assert
      expect(result).toBe(config);
      expect(window.alert).not.toHaveBeenCalled();
      expect(dispatchEventSpy).not.toHaveBeenCalled();
    });

    it("also lets export-cancel through when the URL has no leading slash", async () => {
      // Arrange — exercises the no-leading-slash allowlist entry
      const store = useClientAuthStore();
      store.$patch({ isPreviewMode: true });
      const config = makeConfig(
        "post",
        "client/commission-report/export-cancel",
      );

      // Act
      const result = await requestInterceptor(config);

      // Assert
      expect(result).toBe(config);
      expect(window.alert).not.toHaveBeenCalled();
    });
  });

  describe("scenario 1c — allowlisted deposit-withdraw-report export endpoint is exempt", () => {
    it("lets a POST to /client/deposit-withdraw-report/export through while in preview mode", async () => {
      const store = useClientAuthStore();
      store.$patch({ isPreviewMode: true });
      const dispatchEventSpy = vi.spyOn(window, "dispatchEvent");
      const config = makeConfig(
        "post",
        "/client/deposit-withdraw-report/export",
      );

      const result = await requestInterceptor(config);

      expect(result).toBe(config);
      expect(window.alert).not.toHaveBeenCalled();
      expect(dispatchEventSpy).not.toHaveBeenCalled();
    });

    it("also lets the allowlisted endpoint through when the URL has no leading slash", async () => {
      const store = useClientAuthStore();
      store.$patch({ isPreviewMode: true });
      const config = makeConfig(
        "post",
        "client/deposit-withdraw-report/export",
      );

      const result = await requestInterceptor(config);

      expect(result).toBe(config);
      expect(window.alert).not.toHaveBeenCalled();
    });
  });

  describe("scenario 1d — allowlisted deposit-withdraw-report export-cancel endpoint is exempt", () => {
    it("lets a POST to /client/deposit-withdraw-report/export-cancel through while in preview mode", async () => {
      const store = useClientAuthStore();
      store.$patch({ isPreviewMode: true });
      const dispatchEventSpy = vi.spyOn(window, "dispatchEvent");
      const config = makeConfig(
        "post",
        "/client/deposit-withdraw-report/export-cancel",
      );

      const result = await requestInterceptor(config);

      expect(result).toBe(config);
      expect(window.alert).not.toHaveBeenCalled();
      expect(dispatchEventSpy).not.toHaveBeenCalled();
    });

    it("also lets export-cancel through when the URL has no leading slash", async () => {
      const store = useClientAuthStore();
      store.$patch({ isPreviewMode: true });
      const config = makeConfig(
        "post",
        "client/deposit-withdraw-report/export-cancel",
      );

      const result = await requestInterceptor(config);

      expect(result).toBe(config);
      expect(window.alert).not.toHaveBeenCalled();
    });
  });

  describe("scenario 2 — regression guard: the allowlist does not silently grow", () => {
    it("still blocks a POST to a different write endpoint while in preview mode", async () => {
      // Arrange
      const store = useClientAuthStore();
      store.$patch({ isPreviewMode: true });
      const dispatchEventSpy = vi.spyOn(window, "dispatchEvent");
      const config = makeConfig("post", "/client/some-other-write-endpoint");

      // Act / Assert — request is rejected, not sent
      await expect(requestInterceptor(config)).rejects.toMatchObject({
        message: PREVIEW_BLOCK_MESSAGE,
        isPreviewBlock: true,
      });
      expect(window.alert).toHaveBeenCalledWith(PREVIEW_BLOCK_MESSAGE);
      expect(dispatchEventSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          type: "preview-mode-blocked",
          detail: { message: PREVIEW_BLOCK_MESSAGE },
        }),
      );
    });

    it.each(["put", "patch", "delete"])(
      "still blocks a %s request to a non-allowlisted endpoint while in preview mode",
      async (method) => {
        // Arrange
        const store = useClientAuthStore();
        store.$patch({ isPreviewMode: true });
        const config = makeConfig(method, "/client/some-other-write-endpoint");

        // Act / Assert
        await expect(requestInterceptor(config)).rejects.toMatchObject({
          message: PREVIEW_BLOCK_MESSAGE,
          isPreviewBlock: true,
        });
      },
    );

    it("blocks via the sessionStorage fallback when no Pinia store is active", async () => {
      // Arrange — simulate useClientAuthStore() throwing (e.g. no active
      // Pinia yet), forcing the interceptor's sessionStorage fallback branch
      setActivePinia(undefined);
      sessionStorage.setItem("previewToken", "preview-abc123");
      const config = makeConfig("post", "/client/some-other-write-endpoint");

      // Act / Assert
      await expect(requestInterceptor(config)).rejects.toMatchObject({
        isPreviewBlock: true,
      });
    });
  });

  describe("scenario 3 — GET requests are never blocked (unaffected by this change)", () => {
    it("never blocks a GET request regardless of preview mode", async () => {
      // Arrange
      const store = useClientAuthStore();
      store.$patch({ isPreviewMode: true });
      const config = makeConfig("get", "/client/some-other-endpoint");

      // Act
      const result = await requestInterceptor(config);

      // Assert
      expect(result).toBe(config);
      expect(window.alert).not.toHaveBeenCalled();
    });

    it("treats a request with no method as GET and never blocks it", async () => {
      // Arrange
      const store = useClientAuthStore();
      store.$patch({ isPreviewMode: true });
      const config = makeConfig(undefined, "/client/some-other-endpoint");

      // Act
      const result = await requestInterceptor(config);

      // Assert
      expect(result).toBe(config);
    });
  });

  describe("scenario 4 — writes are unblocked entirely outside of preview mode", () => {
    it("does not block a POST to a non-allowlisted endpoint when isPreviewMode is false", async () => {
      // Arrange — isPreviewMode defaults to false; sessionStorage has no previewToken
      const config = makeConfig("post", "/client/some-other-write-endpoint");

      // Act
      const result = await requestInterceptor(config);

      // Assert
      expect(result).toBe(config);
      expect(window.alert).not.toHaveBeenCalled();
    });
  });
});
