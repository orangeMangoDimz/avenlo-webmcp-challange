import clientApi from "./clientApi";

/**
 * 客户端IB邀请服务
 */
const ibInvitationApi = {
  /**
   * 验证加密的邀请码
   * @param {string} encryptedCode - 加密的邀请码
   */
  async verifyInvitation(encryptedCode) {
    return await clientApi.post("/index.php?path=api/ib-invitations/verify", {
      encryptedCode: encryptedCode,
    });
  },

  /**
   * 获取当前用户的IB状态
   */
  async getMyStatus() {
    return await clientApi.get("/index.php?path=api/ib-invitations/my-status");
  },
};

export default ibInvitationApi;
