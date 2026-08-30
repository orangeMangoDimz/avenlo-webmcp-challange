export const WEBMCP_ENABLED_STORAGE_KEY = "adminWebMcpEnabled";
export const WEBMCP_ENABLED_CHANGE_EVENT = "admin-webmcp-enabled-change";

const getStorage = () => {
  if (typeof window === "undefined") return null;
  try {
    return window.localStorage;
  } catch {
    return null;
  }
};

export const isWebMcpEnabled = () =>
  getStorage()?.getItem(WEBMCP_ENABLED_STORAGE_KEY) !== "false";

export const setWebMcpEnabled = (enabled) => {
  const value = Boolean(enabled);
  getStorage()?.setItem(WEBMCP_ENABLED_STORAGE_KEY, String(value));

  if (typeof window !== "undefined") {
    window.dispatchEvent(
      new CustomEvent(WEBMCP_ENABLED_CHANGE_EVENT, { detail: value }),
    );
  }

  return value;
};

export const subscribeWebMcpEnabled = (listener) => {
  if (typeof window === "undefined") return () => {};

  const handleChange = (event) => {
    if (
      event.type === "storage" &&
      event.key !== null &&
      event.key !== WEBMCP_ENABLED_STORAGE_KEY
    ) {
      return;
    }

    const enabled =
      typeof event.detail === "boolean" ? event.detail : isWebMcpEnabled();
    listener(enabled);
  };

  window.addEventListener(WEBMCP_ENABLED_CHANGE_EVENT, handleChange);
  window.addEventListener("storage", handleChange);

  return () => {
    window.removeEventListener(WEBMCP_ENABLED_CHANGE_EVENT, handleChange);
    window.removeEventListener("storage", handleChange);
  };
};
