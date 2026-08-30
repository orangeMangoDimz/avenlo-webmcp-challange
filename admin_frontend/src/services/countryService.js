import api from "./api";

export const countryService = {
  async getCountriesWithAll() {
    return await api.get("/countries/all");
  },
  async getCountriesWithoutAll() {
    return await api.get("/countries/without-all");
  },
};

export default countryService;
