/**
 * Flutter InAppWebView JS Bridge
 *
 * 当 client 前端被嵌在 Flutter App 的 webview 里运行时，
 * window.flutter_inappwebview 会被注入；普通浏览器环境下不存在。
 *
 * 协议：复用 App 端 `H5ToFlutter` handler（与现有 networkRequest / closeWebview /
 * showLoading 等方法同一套 RPC 协议），通过 `method: 'refreshState'` 通知
 * Flutter App 刷新对应模块。
 *
 * App 端实现位置：fp-app/lib/common/bridge/js_bridge.dart 中的 `_handleRefreshState`。
 *
 * 消息体结构：
 * ```json
 * {
 *   "type": "callNative",
 *   "method": "refreshState",
 *   "data": { "scope": "kyc" | "account" | "all" }
 * }
 * ```
 *
 * 这里不发 callbackId，做成 fire-and-forget；App 端按 scope 走对应刷新链路：
 *   - kyc     → UserStatusController.refreshUserInfo(force: true)
 *   - account → eventBus.fire(AccountRefreshEvent())
 *   - all     → 两者都触发
 */

const HANDLER_NAME = "H5ToFlutter";
const METHOD_REFRESH_STATE = "refreshState";

/**
 * 业务 action → App 端 refreshState scope 的映射
 *
 * 前端依然按业务事件名（kycSubmitted / kycResubmitSubmitted / ...）调用，
 * 内部统一翻译成 App 端关心的 scope。新增 action 时在这里加一条即可，
 * 调用方代码不用动。
 */
const ACTION_SCOPE_MAP = {
  kycSubmitted: "kyc",
  kycResubmitSubmitted: "kyc",
  openAccountSuccess: "account",
};

/**
 * 是否运行在 Flutter webview 中
 * @returns {boolean}
 */
export function hasFlutterBridge() {
  return (
    typeof window !== "undefined" &&
    !!window.flutter_inappwebview &&
    typeof window.flutter_inappwebview.callHandler === "function"
  );
}

/**
 * 向 Flutter 端发送刷新通知
 *
 * 不在 Flutter 环境时直接返回 null，不抛错也不打扰浏览器调用方；
 * 未知 action 也仅打 warn、不抛错。
 *
 * @param {string} action - 业务事件标识，例如 'kycSubmitted'
 * @param {boolean} [refresh=true] - 是否需要 Flutter 端刷新；false 时直接跳过
 * @returns {Promise<any|null>} fire-and-forget，正常情况 resolve 为 null
 */
export async function sendToFlutter(action, refresh = true) {
  if (!hasFlutterBridge()) return null;
  if (!refresh) return null;

  const scope = ACTION_SCOPE_MAP[action];
  if (!scope) {
    console.warn("[flutterBridge] unknown action, skip:", action);
    return null;
  }

  try {
    return await window.flutter_inappwebview.callHandler(HANDLER_NAME, {
      type: "callNative",
      method: METHOD_REFRESH_STATE,
      data: { scope },
    });
  } catch (e) {
    console.error("[flutterBridge] callHandler failed:", e);
    return null;
  }
}
