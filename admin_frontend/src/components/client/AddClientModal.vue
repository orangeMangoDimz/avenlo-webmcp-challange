<template>
  <div
    v-if="modelValue"
    class="add-client-modal show"
    @click.self="handleClose"
  >
    <div class="add-client-modal-content">
      <div class="add-client-modal-header">
        <div class="add-client-modal-title">
          <i class="fas fa-user-plus"></i>
          <h3>{{ t("addClientModal_title", "Add New Client") }}</h3>
        </div>
        <button
          type="button"
          class="add-client-modal-close"
          :aria-label="t('common_close', 'Close')"
          @click="handleClose"
        >
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="add-client-modal-body">
        <form @submit.prevent="handleSubmit">
          <!-- Personal Information Section -->
          <div class="form-section">
            <div class="form-section-title">
              <i class="fas fa-user"></i>
              {{ t("addClientModal_sectionPersonal", "Personal Information") }}
            </div>
            <div class="form-grid">
              <div class="form-field">
                <label>
                  {{ t("addClientModal_firstName", "First Name") }}
                  <span class="required">*</span>
                </label>
                <input
                  v-model="form.firstName"
                  type="text"
                  :placeholder="
                    t('addClientModal_phFirstName', 'Enter first name')
                  "
                  required
                  @blur="handleBlur('firstName')"
                  @input="handleInput('firstName')"
                />
                <span v-if="shouldShowError('firstName')" class="field-error">
                  {{ fieldErrors.firstName }}
                </span>
              </div>
              <div class="form-field">
                <label>
                  {{ t("addClientModal_lastName", "Last Name") }}
                  <span class="required">*</span>
                </label>
                <input
                  v-model="form.lastName"
                  type="text"
                  :placeholder="
                    t('addClientModal_phLastName', 'Enter last name')
                  "
                  required
                  @blur="handleBlur('lastName')"
                  @input="handleInput('lastName')"
                />
                <span v-if="shouldShowError('lastName')" class="field-error">
                  {{ fieldErrors.lastName }}
                </span>
              </div>
              <div class="form-field">
                <label>
                  {{ t("addClientModal_dob", "Date of Birth") }}
                  <span class="required">*</span>
                </label>
                <input
                  v-model="form.dob"
                  type="date"
                  required
                  @blur="handleBlur('dob')"
                  @input="handleInput('dob')"
                />
                <span v-if="shouldShowError('dob')" class="field-error">
                  {{ fieldErrors.dob }}
                </span>
              </div>
            </div>
          </div>

          <!-- Contact Information Section -->
          <div class="form-section">
            <div class="form-section-title">
              <i class="fas fa-envelope"></i>
              {{ t("addClientModal_sectionContact", "Contact Information") }}
            </div>
            <div class="form-grid">
              <div class="form-field">
                <label>
                  {{ t("addClientModal_email", "Email") }}
                  <span class="required">*</span>
                </label>
                <input
                  v-model="form.email"
                  type="email"
                  :placeholder="
                    t('addClientModal_phEmail', 'client@example.com')
                  "
                  required
                  @blur="handleBlur('email')"
                  @input="handleInput('email')"
                />
                <span v-if="shouldShowError('email')" class="field-error">
                  {{ fieldErrors.email }}
                </span>
              </div>
              <div class="form-field">
                <label>{{ t("addClientModal_phone", "Phone Number") }}</label>
                <input
                  v-model="form.phone"
                  type="tel"
                  :placeholder="
                    t('addClientModal_phPhone', '+1 (555) 123-4567')
                  "
                />
              </div>
              <div class="form-field">
                <label>
                  {{ t("addClientModal_country", "Country") }}
                  <span class="required">*</span>
                </label>
                <select
                  v-model="form.country"
                  required
                  @blur="handleBlur('country')"
                  @change="handleInput('country')"
                >
                  <option value="">
                    {{
                      t("addClientModal_selectCountry", "-- Select Country --")
                    }}
                  </option>
                  <option
                    v-for="country in countryOptions"
                    :key="country.code"
                    :value="country.code"
                  >
                    {{ country.name }}
                  </option>
                </select>
                <span v-if="shouldShowError('country')" class="field-error">
                  {{ fieldErrors.country }}
                </span>
              </div>
              <div class="form-field">
                <label>{{
                  t("addClientModal_currency", "Deposit Currency")
                }}</label>
                <select v-model="form.currency">
                  <option
                    v-for="currency in currencyOptions"
                    :key="currency.code"
                    :value="currency.code"
                  >
                    {{ currency.label || currency.code }}
                  </option>
                </select>
              </div>
            </div>
          </div>

          <!-- Login Credentials Section -->
          <div class="form-section">
            <div class="form-section-title">
              <i class="fas fa-key"></i>
              {{ t("addClientModal_sectionLogin", "Login Credentials") }}
            </div>
            <div class="form-grid">
              <div class="form-field">
                <label>
                  {{ t("addClientModal_password", "Password") }}
                  <span class="required" v-if="!form.generatePassword">*</span>
                </label>
                <input
                  v-model="form.password"
                  type="password"
                  :placeholder="
                    t(
                      'addClientModal_phPassword',
                      'Enter password (min. 6 characters)',
                    )
                  "
                  :disabled="form.generatePassword"
                  :required="!form.generatePassword"
                  @blur="handleBlur('password')"
                  @input="handleInput('password')"
                />
                <div class="password-strength">
                  <div
                    class="password-strength-bar"
                    :class="passwordStrengthClass"
                  ></div>
                </div>
                <span
                  class="field-hint"
                  :class="{ 'field-hint-error': shouldShowError('password') }"
                >
                  {{
                    t(
                      "addClientModal_passwordHint",
                      "Password must be at least 6 characters",
                    )
                  }}
                </span>
              </div>
              <div class="form-field">
                <label>
                  {{ t("addClientModal_confirmPassword", "Confirm Password") }}
                  <span class="required" v-if="!form.generatePassword">*</span>
                </label>
                <input
                  v-model="form.confirmPassword"
                  type="password"
                  :placeholder="
                    t('addClientModal_phConfirmPassword', 'Re-enter password')
                  "
                  :disabled="form.generatePassword"
                  :required="!form.generatePassword"
                  @blur="handleBlur('confirmPassword')"
                  @input="handleInput('confirmPassword')"
                />
                <span
                  v-if="shouldShowError('confirmPassword')"
                  class="field-error"
                >
                  {{ fieldErrors.confirmPassword }}
                </span>
              </div>
              <div class="form-field full-width">
                <label class="checkbox-label">
                  <input v-model="form.generatePassword" type="checkbox" />
                  <span>{{
                    t(
                      "addClientModal_generatePassword",
                      "Auto-generate secure password and send via email",
                    )
                  }}</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Account Management Section -->
          <div class="form-section">
            <div class="form-section-title">
              <i class="fas fa-user-tie"></i>
              {{ t("addClientModal_sectionAccount", "Account Management") }}
            </div>
            <div class="form-grid">
              <div class="form-field">
                <label>{{
                  t(
                    "addClientModal_assignSales",
                    "Assign to Sales Representative",
                  )
                }}</label>
                <select v-model="form.manager">
                  <option value="">
                    {{
                      t(
                        "addClientModal_selectSales",
                        "-- Select Sales Representative --",
                      )
                    }}
                  </option>
                  <option
                    v-for="manager in managerOptions"
                    :key="manager.id"
                    :value="manager.id"
                  >
                    {{ formatManagerLabel(manager) }}
                  </option>
                </select>
              </div>
            </div>
          </div>

          <!-- KYC Configuration Section -->
          <div class="form-section">
            <div class="form-section-title">
              <i class="fas fa-id-card"></i>
              {{ t("addClientModal_sectionKyc", "KYC Configuration") }}
            </div>
            <!--            <div class="kyc-options">-->
            <!--              <label-->
            <!--                class="kyc-option"-->
            <!--                :class="{ selected: form.kycOption === 'auto-approve' }"-->
            <!--              >-->
            <!--                <input-->
            <!--                  v-model="form.kycOption"-->
            <!--                  type="radio"-->
            <!--                  value="auto-approve"-->
            <!--                >-->
            <!--                <div class="kyc-option-content">-->
            <!--                  <div class="kyc-option-title">-->
            <!--                    <i class="fas fa-check-circle"></i>-->
            <!--                    Auto-Approve KYC-->
            <!--                  </div>-->
            <!--                  <div class="kyc-option-description">-->
            <!--                    Skip manual review and approve automatically after the client submits KYC-->
            <!--                  </div>-->
            <!--                </div>-->
            <!--              </label>-->

            <!--              <label-->
            <!--                class="kyc-option"-->
            <!--                :class="{ selected: form.kycOption === 'require-kyc' }"-->
            <!--              >-->
            <!--                <input-->
            <!--                  v-model="form.kycOption"-->
            <!--                  type="radio"-->
            <!--                  value="require-kyc"-->
            <!--                >-->
            <!--                <div class="kyc-option-content">-->
            <!--                  <div class="kyc-option-title">-->
            <!--                    <i class="fas fa-clipboard-check"></i>-->
            <!--                    Require KYC Verification-->
            <!--                  </div>-->
            <!--                  <div class="kyc-option-description">-->
            <!--                    Submit KYC for manual review by the compliance team-->
            <!--                  </div>-->
            <!--                </div>-->
            <!--              </label>-->
            <!--            </div>-->
            <div class="form-grid">
              <div class="form-field">
                <label>
                  {{ t("addClientModal_kycTemplate", "Select KYC Template") }}
                  <span class="required">*</span>
                </label>
                <select
                  v-model="form.kycTemplate"
                  required
                  @blur="handleBlur('kycTemplate')"
                  @change="handleInput('kycTemplate')"
                >
                  <option value="">
                    {{
                      t(
                        "addClientModal_selectTemplate",
                        "-- Select Template --",
                      )
                    }}
                  </option>
                  <option
                    v-for="template in kycTemplates"
                    :key="template.id"
                    :value="template.id"
                  >
                    {{ template.name }}
                  </option>
                </select>
                <span v-if="shouldShowError('kycTemplate')" class="field-error">
                  {{ fieldErrors.kycTemplate }}
                </span>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="add-client-modal-footer">
        <button
          type="button"
          class="btn-modal-client btn-modal-client-cancel"
          @click="handleClose"
        >
          {{ t("addClientModal_cancel", "Cancel") }}
        </button>
        <button
          type="button"
          class="btn-modal-client btn-modal-client-submit"
          @click="handleSubmit"
          :disabled="creatingClient || loadingOptions"
        >
          <i v-if="creatingClient" class="fas fa-spinner fa-spin"></i>
          {{
            loadingOptions
              ? t("addClientModal_loading", "Loading...")
              : creatingClient
                ? t("addClientModal_creating", "Creating...")
                : t("addClientModal_submit", "Add Client")
          }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, computed, watch, ref } from "vue";
import { clientService } from "@/services/clientListService";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";
import { getSubModuleKey } from "@/config/operationLogPages";

const { t, tParams } = useAdminI18n();

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  countryOptions: {
    type: Array,
    default: () => [],
  },
  currencyOptions: {
    type: Array,
    default: () => [],
  },
  managerOptions: {
    type: Array,
    default: () => [],
  },
  kycTemplates: {
    type: Array,
    default: () => [],
  },
  loadingOptions: {
    type: Boolean,
    default: false,
  },
  /** 操作日志子模块：leads | clients_list */
  logSubModuleKey: {
    type: String,
    default: () => getSubModuleKey("page_leads"),
  },
});

const emit = defineEmits(["update:modelValue", "success", "cancel"]);

const defaultForm = {
  firstName: "",
  lastName: "",
  dob: "",
  email: "",
  phone: "",
  country: "",
  currency: "USD",
  password: "",
  confirmPassword: "",
  generatePassword: false,
  manager: "",
  kycTemplate: "",
};

const form = reactive({ ...defaultForm });
const creatingClient = ref(false);
const fieldErrors = reactive({
  firstName: "",
  lastName: "",
  dob: "",
  email: "",
  country: "",
  password: "",
  confirmPassword: "",
  kycTemplate: "",
});
const touchedFields = reactive({
  firstName: false,
  lastName: false,
  dob: false,
  email: false,
  country: false,
  password: false,
  confirmPassword: false,
  kycTemplate: false,
});

const passwordStrengthClass = computed(() => {
  if (form.generatePassword) {
    return "";
  }

  const password = form.password || "";
  if (!password) return "";

  let score = 0;
  if (password.length >= 6) score += 1;
  if (password.length >= 12) score += 1;
  if (/[A-Z]/.test(password) && /[a-z]/.test(password)) score += 1;
  if (/\d/.test(password)) score += 1;
  if (/[^A-Za-z0-9]/.test(password)) score += 1;

  if (score >= 4) return "strong";
  if (score >= 2) return "medium";
  return "weak";
});

const resetForm = () => {
  Object.assign(form, { ...defaultForm });
  Object.keys(fieldErrors).forEach((key) => {
    fieldErrors[key] = "";
  });
  Object.keys(touchedFields).forEach((key) => {
    touchedFields[key] = false;
  });
  applyDefaultSelections();
};

const applyDefaultSelections = () => {
  if (!form.country && props.countryOptions.length > 0) {
    form.country = props.countryOptions[0].code;
  }

  if (!form.currency && props.currencyOptions.length > 0) {
    form.currency = props.currencyOptions[0].code;
  }

  if (!form.kycTemplate && props.kycTemplates.length > 0) {
    form.kycTemplate = props.kycTemplates[0].id;
  }
};

watch(
  () => props.modelValue,
  (value) => {
    if (value) {
      resetForm();
    }
  },
);

watch(
  () => form.generatePassword,
  (value) => {
    if (value) {
      form.password = "";
      form.confirmPassword = "";
      fieldErrors.password = "";
      fieldErrors.confirmPassword = "";
    }
  },
);

watch(
  () => [props.countryOptions, props.currencyOptions, props.kycTemplates],
  () => {
    applyDefaultSelections();
  },
  { deep: true },
);

const handleClose = () => {
  emit("update:modelValue", false);
  emit("cancel");
};

const formatManagerLabel = (manager) => {
  const name =
    manager.name ||
    manager.email ||
    t("addClientModal_managerUnknown", "Unknown");
  if (manager.email && manager.email !== name) {
    return `${name} (${manager.email})`;
  }
  return name;
};

const buildPayload = () => {
  const payload = {
    firstName: form.firstName,
    lastName: form.lastName,
    email: form.email,
    phone: form.phone || undefined,
    country: form.country,
    depositCurrency: form.currency,
    kycTemplateId: form.kycTemplate ? Number(form.kycTemplate) : undefined,
    generatePassword: form.generatePassword,
  };

  if (form.dob) {
    payload.dateOfBirth = form.dob;
  }

  if (props.logSubModuleKey) {
    payload.logSubModuleKey = props.logSubModuleKey;
  }

  if (!form.generatePassword) {
    payload.password = form.password;
  }

  if (form.manager) {
    const managerId = Number(form.manager);
    if (!Number.isNaN(managerId)) {
      payload.assignedTo = managerId;
      payload.accountManagerId = managerId;
    }
  }

  return payload;
};

const validateField = (field) => {
  let message = "";
  const getString = (val) => (typeof val === "string" ? val.trim() : "");
  const isEmpty = (val) => val === "" || val === null || val === undefined;

  if (field === "firstName" && !getString(form.firstName)) {
    message = t("addClientModal_val_firstName", "First name is required.");
  }

  if (field === "lastName" && !getString(form.lastName)) {
    message = t("addClientModal_val_lastName", "Last name is required.");
  }

  if (field === "dob" && !getString(form.dob)) {
    message = t("addClientModal_val_dob", "Date of birth is required.");
  }

  if (field === "email") {
    const emailValue = getString(form.email);
    if (!emailValue) {
      message = t("addClientModal_val_emailRequired", "Email is required.");
    } else if (!/^\S+@\S+\.\S+$/.test(emailValue)) {
      message = t(
        "addClientModal_val_emailInvalid",
        "Please enter a valid email address.",
      );
    }
  }

  if (field === "country" && !getString(form.country)) {
    message = t("addClientModal_val_country", "Please select a country.");
  }

  if (field === "password") {
    if (!form.generatePassword) {
      const passwordValue = String(form.password || "");
      if (!passwordValue) {
        message = t("addClientModal_val_password", "Password is required.");
      } else if (passwordValue.length < 6) {
        message = t(
          "addClientModal_val_passwordMin",
          "Password must be at least 6 characters.",
        );
      }
    }
  }

  if (field === "confirmPassword") {
    if (!form.generatePassword) {
      const confirmValue = String(form.confirmPassword || "");
      if (!confirmValue) {
        message = t(
          "addClientModal_val_confirmRequired",
          "Please confirm the password.",
        );
      } else if (form.password !== form.confirmPassword) {
        message = t(
          "addClientModal_val_passwordMismatch",
          "Passwords do not match.",
        );
      }
    }
  }

  if (field === "kycTemplate" && isEmpty(form.kycTemplate)) {
    message = t(
      "addClientModal_val_kycTemplate",
      "Please select a KYC template.",
    );
  }

  fieldErrors[field] = message;
  return !message;
};

const shouldShowError = (field) => touchedFields[field] && fieldErrors[field];

const handleBlur = (field) => {
  touchedFields[field] = true;
  validateField(field);
};

const handleInput = (field) => {
  if (touchedFields[field]) {
    validateField(field);
  }
};

const validateForm = () => {
  const fieldsToValidate = [
    "firstName",
    "lastName",
    "dob",
    "email",
    "country",
    "kycTemplate",
  ];

  if (!form.generatePassword) {
    fieldsToValidate.push("password", "confirmPassword");
  }

  let isValid = true;
  fieldsToValidate.forEach((field) => {
    touchedFields[field] = true;
    if (!validateField(field)) {
      isValid = false;
    }
  });

  return isValid;
};

const handleSubmit = async () => {
  if (creatingClient.value || props.loadingOptions) return;
  if (!validateForm()) return;

  const payload = buildPayload();

  creatingClient.value = true;
  try {
    const response = await clientService.createClient(payload);
    const responseData = response?.data ?? response ?? {};
    const generatedPassword = responseData.generatedPassword;

    const successMessages = [
      t("addClientModal_success", "Client created successfully!"),
    ];
    if (generatedPassword) {
      successMessages.push(
        tParams("addClientModal_successPassword", "Temporary password: {pwd}", {
          pwd: generatedPassword,
        }),
      );
    }
    alert(successMessages.join("\n"));

    emit("success", responseData);
    emit("update:modelValue", false);
  } catch (error) {
    console.error("Failed to create client:", error);
    const data = error?.response?.data ?? error;
    const rawMsg =
      data?.message ||
      error?.message ||
      t("common_unknownError", "Unknown error");
    const mainMsg = translateApiErrorMessage(data?.errorCode, rawMsg);
    const backendErrors = data?.errors ?? error?.response?.data?.errors;
    if (backendErrors && typeof backendErrors === "object") {
      const combined = Object.values(backendErrors)
        .flat()
        .map((line) =>
          translateApiErrorMessage(
            data?.errorCode,
            typeof line === "string" ? line : String(line),
          ),
        )
        .join("\n");
      alert(
        `${t("addClientModal_createFailedFields", "Failed to create client:")}\n${combined}`,
      );
    } else {
      alert(
        tParams(
          "addClientModal_createFailed",
          "Failed to create client: {msg}",
          { msg: mainMsg },
        ),
      );
    }
  } finally {
    creatingClient.value = false;
  }
};
</script>

<style scoped>
.add-client-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.6);
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  animation: addClientModalFadeIn 0.3s ease;
}

@keyframes addClientModalFadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.add-client-modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  padding: 0;
  max-width: 800px;
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  animation: modalSlideIn 0.3s ease;
}

.add-client-modal-header {
  flex-shrink: 0;
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-brand-solid);
  color: white;
  border-radius: 16px 16px 0 0;
}

.add-client-modal-title {
  display: flex;
  align-items: center;
  gap: 12px;
}

.add-client-modal-title i {
  font-size: 24px;
}

.add-client-modal-title h3 {
  font-size: 22px;
  font-weight: 600;
  margin: 0;
  color: white;
}

.add-client-modal-close {
  border: none;
  background: rgba(255, 255, 255, 0.2);
  width: 36px;
  height: 36px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  color: white;
  font-size: 18px;
  line-height: 1;
  padding: 0;
}

.add-client-modal-close:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: rotate(90deg);
}

.add-client-modal-body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 30px;
}

.add-client-modal-body::-webkit-scrollbar {
  width: 8px;
}

.add-client-modal-body::-webkit-scrollbar-track {
  background: var(--color-surface-soft);
  border-radius: 4px;
}

.add-client-modal-body::-webkit-scrollbar-thumb {
  background: rgba(var(--color-brand-rgb), 0.45);
  border-radius: 4px;
}

.form-section {
  margin-bottom: 30px;
}

.form-section:last-child {
  margin-bottom: 0;
}

.form-section-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  gap: 8px;
}

.form-section-title i {
  color: var(--color-brand);
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-field.full-width {
  grid-column: 1 / -1;
}

.form-field label {
  font-weight: 600;
  color: var(--color-text);
  font-size: 13px;
}

.form-field .required {
  color: var(--color-danger);
  margin-left: 2px;
}

.form-field input,
.form-field select {
  padding: 10px 14px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  outline: none;
  transition: all 0.3s ease;
  background: var(--color-surface);
}

.form-field input:focus,
.form-field select:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.field-hint {
  font-size: 12px;
  color: var(--color-muted);
  font-style: italic;
  margin-top: -4px;
}

.field-hint-error {
  color: var(--color-danger);
  font-weight: 500;
}

.field-error {
  color: var(--color-danger);
  font-size: 12px;
  margin-top: -4px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  font-weight: 500;
  color: var(--color-text);
  font-size: 13px;
  user-select: none;
}

.checkbox-label input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: var(--color-brand);
}

.checkbox-label:hover {
  color: var(--color-brand);
}

.password-strength {
  width: 100%;
  height: 4px;
  background: var(--color-border);
  border-radius: 2px;
  overflow: hidden;
  margin-top: 8px;
}

.password-strength-bar {
  height: 100%;
  width: 0%;
  transition: all 0.3s ease;
  border-radius: 2px;
}

.password-strength-bar.weak {
  width: 33%;
  background: var(--color-danger-solid);
}

.password-strength-bar.medium {
  width: 66%;
  background: var(--color-warning-solid);
}

.password-strength-bar.strong {
  width: 100%;
  background: var(--color-success-solid);
}

.kyc-options {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.kyc-option {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 15px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all 0.3s ease;
}

.kyc-option:hover {
  border-color: var(--color-brand);
  background: var(--color-surface-soft);
}

.kyc-option input[type="radio"] {
  margin-top: 3px;
  cursor: pointer;
  accent-color: var(--color-brand);
  width: 18px;
  height: 18px;
}

.kyc-option-content {
  flex: 1;
}

.kyc-option-title {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
  margin-bottom: 4px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.kyc-option-title i {
  color: var(--color-brand);
}

.kyc-option-description {
  font-size: 13px;
  color: var(--color-muted);
}

.kyc-option.selected {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
}

.kyc-template-select {
  margin-top: 15px;
}

.add-client-modal-footer {
  flex-shrink: 0;
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  background: var(--color-surface-soft);
  border-radius: 0 0 16px 16px;
}

.btn-modal-client {
  padding: 12px 24px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-modal-client-cancel {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-modal-client-cancel:hover {
  background: var(--color-border-strong);
}

.btn-modal-client-submit {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.btn-modal-client-submit:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.btn-modal-client-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  box-shadow: none;
}

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (max-width: 768px) {
  .add-client-modal-content {
    width: 95%;
    max-height: 95vh;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
