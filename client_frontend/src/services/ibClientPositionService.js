import api from "./api";

export const getPositions = (params = {}) => {
  return api.get("/client/ib-client-position", { params });
};

export default {
  getPositions,
};
