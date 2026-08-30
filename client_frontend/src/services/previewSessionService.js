/**
 * 预览会话 API（View as client）
 * 客户端用 preview token 换取会话信息，关闭页时回收 token
 */

import api from "./api";

/**
 * GET /api/client/preview-session?token=xxx
 * 无需 Authorization，仅凭 token 校验
 */
export function getPreviewSession(token) {
  return api.get("/client/preview-session", { params: { token } });
}

/**
 * POST /api/client/preview-revoke
 * Body: { token }
 */
export function revokePreviewToken(token) {
  return api.post("/client/preview-revoke", { token });
}

/** 关闭页时用 fetch keepalive 回收 token（不依赖 axios） */
export function revokePreviewTokenBeacon(token) {
  if (!token) return;
  const baseURL = import.meta.env.VITE_API_BASE_URL || "";
  let url;
  if (baseURL.includes("?path=")) {
    const [base, prefix] = baseURL.split("?path=");
    url = `${base}?path=${(prefix || "api").replace(/\/$/, "")}/client/preview-revoke`;
  } else {
    url = `${baseURL.replace(/\/$/, "")}/client/preview-revoke`;
  }
  try {
    fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ token }),
      keepalive: true,
    }).catch(() => {});
  } catch (_) {}
}
