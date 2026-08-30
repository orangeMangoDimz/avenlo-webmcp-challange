import { defineStore } from "pinia";
import { ref } from "vue";
import loginSettingsService from "@/services/loginSettingsService";

export const useLoginSettingsStore = defineStore("loginSettings", () => {
  const branding = ref({
    logoType: "text",
    logoText: "BDX",
    logoImagePath: null,
    taglineEn: "",
    taglineZh: "",
    featuresContent: "",
  });

  const formFields = ref([]);
  const passwordStrength = ref({});
  const legalDocuments = ref([]);
  const languagePacks = ref([]);
  const ipLanguageDetection = ref({});
  const emailVerification = ref({});

  // Branding
  async function loadBranding() {
    try {
      const response = await loginSettingsService.getBranding();
      if (response.data) {
        branding.value = response.data;
      }
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message || "Failed to load branding",
      };
    }
  }

  async function updateBranding(data) {
    try {
      await loginSettingsService.updateBranding(data);
      branding.value = { ...branding.value, ...data };
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message || "Failed to update branding",
      };
    }
  }

  async function uploadLogo(file) {
    try {
      const formData = new FormData();
      formData.append("logo", file);
      const response = await loginSettingsService.uploadLogo(formData);
      branding.value.logoImagePath = response.data.logoPath;
      branding.value.logoType = "image";
      return { success: true, data: response.data };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message || "Failed to upload logo",
      };
    }
  }

  // Form Fields
  async function loadFormFields() {
    try {
      const response = await loginSettingsService.getFormFields();
      if (response.data) {
        formFields.value = response.data;
      }
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message || "Failed to load form fields",
      };
    }
  }

  async function createFormField(data) {
    try {
      const response = await loginSettingsService.createFormField(data);
      await loadFormFields();
      return { success: true, data: response.data };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message || "Failed to create form field",
      };
    }
  }

  async function updateFormField(id, data) {
    try {
      await loginSettingsService.updateFormField(id, data);
      await loadFormFields();
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message || "Failed to update form field",
      };
    }
  }

  async function deleteFormField(id) {
    try {
      await loginSettingsService.deleteFormField(id);
      formFields.value = formFields.value.filter((f) => f.id !== id);
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message || "Failed to delete form field",
      };
    }
  }

  // Password Strength
  async function loadPasswordStrength() {
    try {
      const response = await loginSettingsService.getPasswordStrength();
      if (response.data) {
        passwordStrength.value = response.data;
      }
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message || "Failed to load password strength",
      };
    }
  }

  async function updatePasswordStrength(data) {
    try {
      await loginSettingsService.updatePasswordStrength(data);
      passwordStrength.value = { ...passwordStrength.value, ...data };
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message || "Failed to update password strength",
      };
    }
  }

  async function applyPasswordLevel(level) {
    try {
      await loginSettingsService.applyPasswordLevel(level);
      await loadPasswordStrength();
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message || "Failed to apply password level",
      };
    }
  }

  // Legal Documents
  async function loadLegalDocuments(lang = "en") {
    try {
      const response = await loginSettingsService.getLegalDocuments(lang);
      if (response.data) {
        legalDocuments.value = response.data;
      }
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message || "Failed to load legal documents",
      };
    }
  }

  async function createLegalDocument(data) {
    try {
      const response = await loginSettingsService.createLegalDocument(data);
      await loadLegalDocuments();
      return { success: true, data: response.data };
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message || "Failed to create legal document",
      };
    }
  }

  async function updateLegalDocument(id, data) {
    try {
      await loginSettingsService.updateLegalDocument(id, data);
      await loadLegalDocuments();
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message || "Failed to update legal document",
      };
    }
  }

  async function deleteLegalDocument(id) {
    try {
      await loginSettingsService.deleteLegalDocument(id);
      legalDocuments.value = legalDocuments.value.filter((d) => d.id !== id);
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message || "Failed to delete legal document",
      };
    }
  }

  // Language Packs
  async function loadLanguagePacks() {
    try {
      const response = await loginSettingsService.getLanguagePacks();
      if (response.data) {
        languagePacks.value = response.data;
      }
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message || "Failed to load language packs",
      };
    }
  }

  async function uploadLanguagePack(data) {
    try {
      const response = await loginSettingsService.uploadLanguagePack(data);
      await loadLanguagePacks();
      return { success: true, data: response.data };
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message || "Failed to upload language pack",
      };
    }
  }

  async function setDefaultLanguage(languageCode) {
    try {
      await loginSettingsService.setDefaultLanguage(languageCode);
      languagePacks.value.forEach((lang) => {
        lang.isDefault = lang.languageCode === languageCode;
      });
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message || "Failed to set default language",
      };
    }
  }

  // IP Language Detection
  async function loadIpLanguageDetection() {
    try {
      const response = await loginSettingsService.getIpLanguageDetection();
      if (response.data) {
        ipLanguageDetection.value = response.data;
      }
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message ||
          "Failed to load IP language detection",
      };
    }
  }

  async function updateIpLanguageDetection(data) {
    try {
      await loginSettingsService.updateIpLanguageDetection(data);
      ipLanguageDetection.value = { ...ipLanguageDetection.value, ...data };
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message ||
          "Failed to update IP language detection",
      };
    }
  }

  // Email Verification
  async function loadEmailVerification() {
    try {
      const response = await loginSettingsService.getEmailVerification();
      if (response.data) {
        emailVerification.value = response.data;
      }
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message || "Failed to load email verification",
      };
    }
  }

  async function updateEmailVerification(data) {
    try {
      await loginSettingsService.updateEmailVerification(data);
      emailVerification.value = { ...emailVerification.value, ...data };
      return { success: true };
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message ||
          "Failed to update email verification",
      };
    }
  }

  // Load all settings
  async function loadAllSettings() {
    await Promise.all([
      loadBranding(),
      loadFormFields(),
      loadPasswordStrength(),
      loadLegalDocuments(),
      loadLanguagePacks(),
      loadIpLanguageDetection(),
      loadEmailVerification(),
    ]);
  }

  return {
    branding,
    formFields,
    passwordStrength,
    legalDocuments,
    languagePacks,
    ipLanguageDetection,
    emailVerification,
    loadBranding,
    updateBranding,
    uploadLogo,
    loadFormFields,
    createFormField,
    updateFormField,
    deleteFormField,
    loadPasswordStrength,
    updatePasswordStrength,
    applyPasswordLevel,
    loadLegalDocuments,
    createLegalDocument,
    updateLegalDocument,
    deleteLegalDocument,
    loadLanguagePacks,
    uploadLanguagePack,
    setDefaultLanguage,
    loadIpLanguageDetection,
    updateIpLanguageDetection,
    loadEmailVerification,
    updateEmailVerification,
    loadAllSettings,
  };
});
