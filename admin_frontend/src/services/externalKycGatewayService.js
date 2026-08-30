import api from "./api";
import { getSubModuleKey } from "@/config/operationLogPages";

const KYC_SETTINGS_LOG_SUB_MODULE = getSubModuleKey("page_kyc_settings");

const withLogSubModule = (data = {}) => ({
  logSubModuleKey: KYC_SETTINGS_LOG_SUB_MODULE,
  ...data,
});

const deleteLogParams = () =>
  KYC_SETTINGS_LOG_SUB_MODULE
    ? { logSubModuleKey: KYC_SETTINGS_LOG_SUB_MODULE }
    : {};

/**
 * 第三方 KYC 网关配置服务
 */
export const externalKycGatewayService = {
  /** 列表（默认排除软删除、隐藏 secret 字段） */
  async list() {
    return await api.get("/external-kyc-gateways");
  },

  /** 修改配置（含 secret） */
  async update(id, payload) {
    return await api.put(
      `/external-kyc-gateways/${id}`,
      withLogSubModule(payload),
    );
  },

  /** 启用/停用 */
  async setEnabled(id, isEnabled) {
    return await api.put(
      `/external-kyc-gateways/${id}/enabled`,
      withLogSubModule({ isEnabled }),
    );
  },

  /** 软删除 */
  async softDelete(id) {
    return await api.delete(`/external-kyc-gateways/${id}`, {
      params: deleteLogParams(),
    });
  },

  /** 拉取第三方 level 同步进 externalKycTemplates */
  async sync(id) {
    return await api.post(
      `/external-kyc-gateways/${id}/sync`,
      withLogSubModule({}),
    );
  },

  /** 获取已同步的 level / template 列表 */
  async listTemplates(id) {
    return await api.get(`/external-kyc-gateways/${id}/templates`);
  },

  /**
   * 按 externalTemplateId 直接拿绑定的 level + gateway 简略信息。
   * KYC Template detail 页开启第三方时用这个，避免拉全量 gateway。
   */
  async getTemplateWithGateway(externalTemplateId) {
    return await api.get(
      `/external-kyc-gateways/template/${externalTemplateId}`,
    );
  },
};
