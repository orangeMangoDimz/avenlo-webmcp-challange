import { defineStore } from "pinia";
import { ref } from "vue";
import countryService from "@/services/countryService";

export const useCountryStore = defineStore("countryStore", () => {
  const countries = ref([]);
  const loading = ref(false);
  const loaded = ref(false);

  const fetchCountries = async (includeAll = true) => {
    if (loaded.value || loading.value) {
      return;
    }

    loading.value = true;
    try {
      const response = includeAll
        ? await countryService.getCountriesWithAll()
        : await countryService.getCountriesWithoutAll();
      const payload = response.data ?? response ?? [];
      if (Array.isArray(payload)) {
        countries.value = payload;
        loaded.value = true;
      } else if (Array.isArray(payload.data)) {
        countries.value = payload.data;
        loaded.value = true;
      }
    } catch (error) {
      console.error("Failed to load countries:", error);
    } finally {
      loading.value = false;
    }
  };

  return {
    countries,
    loading,
    loaded,
    fetchCountries,
  };
});
