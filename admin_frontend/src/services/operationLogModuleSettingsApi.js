/**
 * 后台操作日志模块开关（日志设置页）
 */
import api from "./api";
import { getSubModuleKey } from "@/config/operationLogPages";

/** 每次请求时解析，避免模块顶层缓存空值（HMR / 加载顺序） */
const logSettingsLogSubModuleKey = () => getSubModuleKey("page_log_settings");

const withLogSettingsLog = (payload = {}) => ({
  ...payload,
  logSubModuleKey: logSettingsLogSubModuleKey(),
});

/**
 * 分页列表
 * @param {{ page?: number, per_page?: number|string }} params
 */
export function fetchOperationLogModuleSettings(params = {}) {
  return api.get("/operation-log/module-settings", { params });
}

/** 启动单条 */
export function startOperationLogModule(id) {
  return api.post(
    "/operation-log/module-settings/start",
    withLogSettingsLog({ id }),
  );
}

/** 停止单条 */
export function stopOperationLogModule(id) {
  return api.post(
    "/operation-log/module-settings/stop",
    withLogSettingsLog({ id }),
  );
}

/** 批量启动 */
export function bulkStartOperationLogModules(ids) {
  return api.post(
    "/operation-log/module-settings/bulk-start",
    withLogSettingsLog({ ids }),
  );
}

/** 批量停止 */
export function bulkStopOperationLogModules(ids) {
  return api.post(
    "/operation-log/module-settings/bulk-stop",
    withLogSettingsLog({ ids }),
  );
}

/**
 * 按 modelKey 查询是否开启（供其它模块调用）
 */
export function checkOperationLogModule(modelKey) {
  return api.get("/operation-log/module-settings/check", {
    params: { modelKey },
  });
}

export default {
  fetchOperationLogModuleSettings,
  startOperationLogModule,
  stopOperationLogModule,
  bulkStartOperationLogModules,
  bulkStopOperationLogModules,
  checkOperationLogModule,
};
