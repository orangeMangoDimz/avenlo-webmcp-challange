<template>
  <div class="user-menu" :class="{ active: isActive }" ref="userMenuRef">
    <button
      type="button"
      class="user-menu-trigger"
      :aria-expanded="isActive"
      @click="toggleMenu"
    >
      <div class="user-avatar">{{ userInitials }}</div>
      <div class="user-info">
        <div class="user-name">
          {{ user.name
          }}<span v-if="user.id" class="user-id">#{{ user.id }}</span>
        </div>
        <div class="user-role">{{ t("userRoleClient", "Client Account") }}</div>
      </div>
      <i class="fas fa-chevron-down user-menu-arrow"></i>
    </button>

    <div v-if="isActive" class="user-dropdown">
      <div class="user-dropdown-header">
        <div class="user-name">{{ user.name }}</div>
        <div class="user-email">{{ user.email }}</div>
        <div v-if="user.id" class="user-uid">
          {{ t("uid", "UID") }}: {{ user.id }}
        </div>
      </div>

      <button
        class="user-dropdown-item"
        type="button"
        @click="openProfileModal"
      >
        <i class="fas fa-user"></i>
        <span>{{ t("myProfile", "My Profile") }}</span>
      </button>

      <button
        class="user-dropdown-item"
        type="button"
        @click="openPasswordModal"
      >
        <i class="fas fa-key"></i>
        <span>{{ t("changePassword", "Change Password") }}</span>
      </button>

      <button class="user-dropdown-item" type="button" @click="openTicketModal">
        <i class="fas fa-ticket-alt"></i>
        <span>{{ t("submitTicket", "Submit Ticket") }}</span>
      </button>

      <div class="user-dropdown-divider"></div>

      <button
        type="button"
        class="user-dropdown-item danger"
        @click="handleLogout"
      >
        <i class="fas fa-sign-out-alt"></i>
        <span>{{ t("logout", "Logout") }}</span>
      </button>
    </div>
  </div>

  <!-- Profile Modal -->
  <Teleport to="body">
    <div
      class="dropdown-modal"
      :class="{ active: showProfileModal }"
      @click="closeProfileModal"
    >
      <div class="dropdown-modal-content" @click.stop>
        <div class="dropdown-modal-header">
          <h2>
            <i class="fas fa-user"></i> {{ t("myProfile", "My Profile") }}
          </h2>
          <button
            type="button"
            class="dropdown-modal-close"
            :aria-label="t('close', 'Close')"
            @click="closeProfileModal"
          >
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="dropdown-modal-body">
          <form @submit.prevent="submitProfile">
            <div class="form-grid">
              <div class="form-group">
                <label for="firstName">{{
                  t("firstName", "First Name")
                }}</label>
                <input
                  id="firstName"
                  v-model="profileForm.firstName"
                  type="text"
                  :placeholder="t('enterFirstName', 'Enter first name')"
                  :class="{ 'field-error': profileErrors.firstName }"
                />
                <p v-if="profileErrors.firstName" class="error-text">
                  {{ profileErrors.firstName }}
                </p>
              </div>
              <div class="form-group">
                <label for="lastName">{{ t("lastName", "Last Name") }}</label>
                <input
                  id="lastName"
                  v-model="profileForm.lastName"
                  type="text"
                  :placeholder="t('enterLastName', 'Enter last name')"
                  :class="{ 'field-error': profileErrors.lastName }"
                />
                <p v-if="profileErrors.lastName" class="error-text">
                  {{ profileErrors.lastName }}
                </p>
              </div>
            </div>

            <div class="form-group">
              <label for="email">{{ t("email", "Email") }}</label>
              <input id="email" :value="user.email" type="email" disabled />
            </div>

            <div class="form-group">
              <label for="phone">{{ t("phone", "Phone") }}</label>
              <div class="phone-input-row">
                <input
                  id="phone"
                  v-model="profileForm.phone"
                  @input="handlePhoneInput"
                  type="tel"
                  :placeholder="t('enterPhoneNumber', 'Enter phone number')"
                  :class="[
                    'phone-input',
                    { 'field-error': profileErrors.phone },
                  ]"
                />
              </div>
              <p v-if="profileErrors.phone" class="error-text">
                {{ profileErrors.phone }}
              </p>
            </div>
            <div class="form-group">
              <label for="country">{{ t("country", "Country") }}</label>
              <div ref="countrySelectRef" class="custom-select">
                <button
                  id="country"
                  type="button"
                  :class="[
                    'custom-select-trigger',
                    {
                      'field-error': profileErrors.country,
                      placeholder: !profileForm.country,
                      open: showCountryDropdown,
                    },
                  ]"
                  @click="toggleCountryDropdown"
                >
                  <span>{{
                    selectedCountryLabel ||
                    t("selectYourCountry", "Select your country")
                  }}</span>
                  <i class="fas fa-chevron-down"></i>
                </button>
                <div v-if="showCountryDropdown" class="custom-select-menu">
                  <div class="custom-select-search">
                    <i class="fas fa-search"></i>
                    <input
                      v-model="countrySearch"
                      type="text"
                      :placeholder="t('searchCountry', 'Search country')"
                    />
                  </div>
                  <div class="custom-select-options">
                    <button
                      v-for="country in filteredCountryOptions"
                      :key="country.code"
                      type="button"
                      :class="[
                        'custom-select-option',
                        { selected: profileForm.country === country.code },
                      ]"
                      @click="selectProfileCountry(country)"
                    >
                      {{ country.name }}
                    </button>
                    <div
                      v-if="filteredCountryOptions.length === 0"
                      class="custom-select-empty"
                    >
                      {{ t("noCountriesFound", "No countries found") }}
                    </div>
                  </div>
                </div>
              </div>
              <p v-if="profileErrors.country" class="error-text">
                {{ profileErrors.country }}
              </p>
            </div>

            <div
              v-if="profileMessage"
              :class="['form-message', { success: profileSuccess }]"
            >
              {{ profileMessage }}
            </div>

            <div class="dropdown-modal-footer">
              <button
                type="button"
                class="btn btn-secondary"
                @click="closeProfileModal"
              >
                {{ t("cancel", "Cancel") }}
              </button>
              <button
                type="submit"
                class="btn btn-primary"
                :disabled="profileSaving"
              >
                <i v-if="profileSaving" class="fas fa-spinner fa-spin"></i>
                <span>{{
                  profileSaving
                    ? t("saving", "Saving...")
                    : t("saveChanges", "Save Changes")
                }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Submit Ticket Modal -->
  <Teleport to="body">
    <div
      class="dropdown-modal"
      :class="{ active: showTicketModal }"
      @click="closeTicketModal"
    >
      <div class="dropdown-modal-content" @click.stop>
        <div class="dropdown-modal-header">
          <h2>
            <i class="fas fa-ticket-alt"></i>
            {{ t("submitTicket", "Submit Ticket") }}
          </h2>
          <button
            type="button"
            class="dropdown-modal-close"
            :aria-label="t('close', 'Close')"
            @click="closeTicketModal"
          >
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="dropdown-modal-body">
          <form @submit.prevent="submitTicket">
            <div class="form-group">
              <label for="ticketTitle"
                >{{ t("title", "Title") }}
                <span style="color: #e53e3e">*</span></label
              >
              <input
                id="ticketTitle"
                v-model="ticketForm.title"
                type="text"
                :placeholder="t('enterTicketTitle', 'Enter ticket title')"
                maxlength="255"
                required
              />
              <p v-if="ticketErrors.title" class="error-text">
                {{ ticketErrors.title }}
              </p>
            </div>

            <div class="form-group">
              <label for="ticketContent"
                >{{ t("content", "Content") }}
                <span style="color: #e53e3e">*</span></label
              >
              <textarea
                id="ticketContent"
                v-model="ticketForm.content"
                :placeholder="
                  t('describeYourIssue', 'Describe your issue or question...')
                "
                rows="6"
                required
              ></textarea>
              <p v-if="ticketErrors.content" class="error-text">
                {{ ticketErrors.content }}
              </p>
            </div>

            <div
              v-if="ticketMessage"
              :class="['form-message', { success: ticketSuccess }]"
            >
              {{ ticketMessage }}
            </div>

            <div class="dropdown-modal-footer">
              <button
                type="button"
                class="btn btn-secondary"
                @click="closeTicketModal"
              >
                {{ t("cancel", "Cancel") }}
              </button>
              <button
                type="submit"
                class="btn btn-primary"
                :disabled="ticketSaving"
              >
                <i v-if="ticketSaving" class="fas fa-spinner fa-spin"></i>
                <span>{{
                  ticketSaving
                    ? t("submitting", "Submitting...")
                    : t("submitTicket", "Submit Ticket")
                }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Change Password Modal -->
  <Teleport to="body">
    <div
      class="dropdown-modal"
      :class="{ active: showPasswordModal }"
      @click="closePasswordModal"
    >
      <div class="dropdown-modal-content" @click.stop>
        <div class="dropdown-modal-header">
          <h2>
            <i class="fas fa-key"></i>
            {{ t("changePassword", "Change Password") }}
          </h2>
          <button
            type="button"
            class="dropdown-modal-close"
            :aria-label="t('close', 'Close')"
            @click="closePasswordModal"
          >
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="dropdown-modal-body">
          <form @submit.prevent="submitPassword">
            <div class="form-group">
              <label for="currentPassword">{{
                t("currentPassword", "Current Password")
              }}</label>
              <div class="password-input-wrapper">
                <input
                  id="currentPassword"
                  v-model="passwordForm.currentPassword"
                  :type="showCurrentPassword ? 'text' : 'password'"
                  :placeholder="
                    t('enterCurrentPassword', 'Enter current password')
                  "
                  autocomplete="current-password"
                  class="password-input"
                />
                <button
                  type="button"
                  class="password-toggle-btn"
                  :aria-label="
                    showCurrentPassword
                      ? t('hidePassword', 'Hide password')
                      : t('showPassword', 'Show password')
                  "
                  @click="toggleCurrentPassword"
                >
                  <i
                    :class="
                      showCurrentPassword ? 'fas fa-eye-slash' : 'fas fa-eye'
                    "
                  ></i>
                </button>
              </div>
              <p v-if="passwordErrors.currentPassword" class="error-text">
                {{ passwordErrors.currentPassword }}
              </p>
            </div>

            <div class="form-group">
              <label for="newPassword">{{
                t("newPassword", "New Password")
              }}</label>
              <div class="password-input-wrapper">
                <input
                  id="newPassword"
                  v-model="passwordForm.newPassword"
                  :type="showNewPassword ? 'text' : 'password'"
                  :placeholder="t('enterNewPassword', 'Enter new password')"
                  autocomplete="new-password"
                  class="password-input"
                />
                <button
                  type="button"
                  class="password-toggle-btn"
                  :aria-label="
                    showNewPassword
                      ? t('hidePassword', 'Hide password')
                      : t('showPassword', 'Show password')
                  "
                  @click="toggleNewPassword"
                >
                  <i
                    :class="showNewPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"
                  ></i>
                </button>
              </div>
              <div style="margin-top: 8px">
                <small style="color: var(--color-muted); font-size: 14px">
                  <i class="fas fa-info-circle"></i>
                  {{ passwordRequirementText }}
                </small>
              </div>
              <p v-if="passwordErrors.newPassword" class="error-text">
                {{ passwordErrors.newPassword }}
              </p>
            </div>
            <div class="form-group">
              <label for="confirmPassword">{{
                t("confirmNewPassword", "Confirm New Password")
              }}</label>
              <div class="password-input-wrapper">
                <input
                  id="confirmPassword"
                  v-model="passwordForm.confirmPassword"
                  :type="showConfirmPassword ? 'text' : 'password'"
                  :placeholder="
                    t('confirmNewPasswordPlaceholder', 'Confirm new password')
                  "
                  autocomplete="new-password"
                  class="password-input"
                />
                <button
                  type="button"
                  class="password-toggle-btn"
                  :aria-label="
                    showConfirmPassword
                      ? t('hidePassword', 'Hide password')
                      : t('showPassword', 'Show password')
                  "
                  @click="toggleConfirmPassword"
                >
                  <i
                    :class="
                      showConfirmPassword ? 'fas fa-eye-slash' : 'fas fa-eye'
                    "
                  ></i>
                </button>
              </div>
              <p v-if="passwordErrors.confirmPassword" class="error-text">
                {{ passwordErrors.confirmPassword }}
              </p>
            </div>

            <div
              v-if="passwordMessage"
              :class="['form-message', { success: passwordSuccess }]"
            >
              {{ passwordMessage }}
            </div>

            <div class="dropdown-modal-footer">
              <button
                type="button"
                class="btn btn-secondary"
                @click="closePasswordModal"
              >
                {{ t("cancel", "Cancel") }}
              </button>
              <button
                type="submit"
                class="btn btn-primary"
                :disabled="passwordSaving"
              >
                <i v-if="passwordSaving" class="fas fa-spinner fa-spin"></i>
                <span>{{
                  passwordSaving
                    ? t("updating", "Updating...")
                    : t("updatePassword", "Update Password")
                }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, reactive } from "vue";
import { useRouter } from "vue-router";
import { useClientAuthStore } from "@/stores/clientAuth";
import { useCountryStore } from "@/stores/countryStore";
import { useLanguageStore } from "@/stores/language";
import clientTicketService from "@/services/clientTicketService";
import loginSettingsService from "@/services/loginSettingsService";

const router = useRouter();
const clientAuthStore = useClientAuthStore();
const countryStore = useCountryStore();
const languageStore = useLanguageStore();
const t = (key, fallback = "") => languageStore.t(key, fallback);
const isActive = ref(false);
const userMenuRef = ref(null);
const showProfileModal = ref(false);
const showPasswordModal = ref(false);
const showTicketModal = ref(false);
const profileSaving = ref(false);
const passwordSaving = ref(false);
const ticketSaving = ref(false);
const profileSuccess = ref(false);
const passwordSuccess = ref(false);
const ticketSuccess = ref(false);
const profileMessage = ref("");
const passwordMessage = ref("");
const ticketMessage = ref("");
const profileErrors = reactive({});
const passwordErrors = reactive({});
const ticketErrors = reactive({});

const profileForm = reactive({
  firstName: "",
  lastName: "",
  phone: "",
  country: "",
});

const passwordForm = reactive({
  currentPassword: "",
  newPassword: "",
  confirmPassword: "",
});

const passwordStrengthSettings = ref({
  strengthLevel: "medium",
  minLength: 8,
  requireLetters: true,
  requireNumbers: true,
  requireUppercase: false,
  requireLowercase: false,
  requireSpecialChars: false,
  description: "Minimum 8 characters with letters and numbers",
});

// Password visibility toggles
const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

const ticketForm = reactive({
  title: "",
  content: "",
});

// Get user data from store
const user = computed(() => {
  if (clientAuthStore.user) {
    return {
      id: clientAuthStore.user.id ?? null,
      name:
        `${clientAuthStore.user.firstName || ""} ${clientAuthStore.user.lastName || ""}`.trim() ||
        "Client",
      email: clientAuthStore.user.email || "N/A",
      role: "Client Account",
    };
  }
  return {
    id: null,
    name: "Client",
    email: "N/A",
    role: "Client Account",
  };
});

const userInitials = computed(() => {
  return clientAuthStore.userInitials || "CL";
});

const countryOptions = computed(() => countryStore.countries);
const countrySelectRef = ref(null);
const showCountryDropdown = ref(false);
const countrySearch = ref("");
const selectedCountryLabel = computed(
  () =>
    countryOptions.value.find((country) => country.code === profileForm.country)
      ?.name || "",
);
const filteredCountryOptions = computed(() => {
  const keyword = countrySearch.value.trim().toLowerCase();
  if (!keyword) return countryOptions.value;
  return countryOptions.value.filter((country) =>
    String(country.name || "")
      .toLowerCase()
      .includes(keyword),
  );
});

const normalizePasswordStrengthSettings = (settings = {}) => ({
  strengthLevel: settings.strengthLevel || "medium",
  minLength: Number(settings.minLength || 8),
  requireLetters: Boolean(settings.requireLetters),
  requireNumbers: Boolean(settings.requireNumbers),
  requireUppercase: Boolean(settings.requireUppercase),
  requireLowercase: Boolean(settings.requireLowercase),
  requireSpecialChars: Boolean(settings.requireSpecialChars),
  description: settings.description || "",
});

const passwordRequirementText = computed(() => {
  const settings = passwordStrengthSettings.value;
  const requirements = [`Minimum ${settings.minLength} characters`];

  if (settings.requireLetters) requirements.push("letters");
  if (settings.requireNumbers) requirements.push("numbers");

  if (settings.requireUppercase && settings.requireLowercase) {
    requirements.push("uppercase and lowercase");
  } else if (settings.requireUppercase) {
    requirements.push("uppercase letters");
  } else if (settings.requireLowercase) {
    requirements.push("lowercase letters");
  }

  if (settings.requireSpecialChars) {
    requirements.push("special characters");
  }

  if (requirements.length <= 1) return requirements[0];
  if (requirements.length === 2) return requirements.join(" and ");
  const last = requirements.pop();
  return `${requirements.join(", ")}, and ${last}`;
});

const validatePasswordAgainstSettings = (password, settings) => {
  const normalizedPassword = String(password || "");
  const errors = [];

  if (normalizedPassword.length < settings.minLength) {
    errors.push(`Password must be at least ${settings.minLength} characters`);
  }

  if (settings.requireLetters && !/[A-Za-z]/.test(normalizedPassword)) {
    errors.push("Password must include letters");
  }

  if (settings.requireNumbers && !/\d/.test(normalizedPassword)) {
    errors.push("Password must include numbers");
  }

  if (settings.requireUppercase && !/[A-Z]/.test(normalizedPassword)) {
    errors.push("Password must include uppercase letters");
  }

  if (settings.requireLowercase && !/[a-z]/.test(normalizedPassword)) {
    errors.push("Password must include lowercase letters");
  }

  if (
    settings.requireSpecialChars &&
    !/[^A-Za-z0-9]/.test(normalizedPassword)
  ) {
    errors.push("Password must include special characters");
  }

  return {
    valid: errors.length === 0,
    errors,
  };
};

const toggleMenu = () => {
  isActive.value = !isActive.value;
};

const toggleCountryDropdown = () => {
  const nextOpen = !showCountryDropdown.value;
  showCountryDropdown.value = nextOpen;
  if (!nextOpen) {
    countrySearch.value = "";
  }
};

const closeCountryDropdown = () => {
  showCountryDropdown.value = false;
  countrySearch.value = "";
};

const selectProfileCountry = (country) => {
  profileForm.country = country.code;
  profileErrors.country = "";
  closeCountryDropdown();
};

const closeMenu = () => {
  isActive.value = false;
};

const resetProfileState = () => {
  profileErrors.firstName = "";
  profileErrors.lastName = "";
  profileErrors.phone = "";
  profileErrors.country = "";
  profileMessage.value = "";
  profileSuccess.value = false;
  profileSaving.value = false;
};

const resetPasswordState = () => {
  passwordErrors.currentPassword = "";
  passwordErrors.newPassword = "";
  passwordErrors.confirmPassword = "";
  passwordMessage.value = "";
  passwordSuccess.value = false;
  passwordSaving.value = false;
};

const loadPasswordStrengthSettings = async () => {
  try {
    const response = await loginSettingsService.getPasswordStrength();
    if (response.success && response.data) {
      passwordStrengthSettings.value = normalizePasswordStrengthSettings(
        response.data,
      );
    }
  } catch (error) {
    console.error("Failed to load password strength settings:", error);
  }
};

const handlePhoneInput = (event) => {
  // 只允许数字，过滤掉所有非数字字符
  const value = event.target.value.replace(/\D/g, "");
  profileForm.phone = value;
};

const openProfileModal = async () => {
  closeMenu();
  closeCountryDropdown();
  const currentUser = clientAuthStore.user || {};
  profileForm.firstName = currentUser.firstName || "";
  profileForm.lastName = currentUser.lastName || "";
  profileForm.phone = currentUser.phone || "";
  profileForm.country = currentUser.country || "";
  resetProfileState();
  showProfileModal.value = true;
};

const closeProfileModal = () => {
  closeCountryDropdown();
  showProfileModal.value = false;
};

const validateProfile = () => {
  resetProfileState();
  let valid = true;

  profileForm.firstName = (profileForm.firstName || "").trim();
  profileForm.lastName = (profileForm.lastName || "").trim();
  profileForm.phone = (profileForm.phone || "").trim();
  profileForm.country = (profileForm.country || "").trim();

  if (!profileForm.firstName) {
    profileErrors.firstName = t(
      "pleaseEnterFirstName",
      "Please enter your first name",
    );
    valid = false;
  } else if (profileForm.firstName.length > 100) {
    profileErrors.firstName = t(
      "firstNameTooLong",
      "First name must not exceed 100 characters",
    );
    valid = false;
  }

  if (!profileForm.lastName) {
    profileErrors.lastName = t(
      "pleaseEnterLastName",
      "Please enter your last name",
    );
    valid = false;
  } else if (profileForm.lastName.length > 100) {
    profileErrors.lastName = t(
      "lastNameTooLong",
      "Last name must not exceed 100 characters",
    );
    valid = false;
  }

  if (!profileForm.phone) {
    profileErrors.phone = t(
      "pleaseEnterPhone",
      "Please enter your phone number",
    );
    valid = false;
  } else if (profileForm.phone.length > 30) {
    profileErrors.phone = t(
      "phoneTooLong",
      "Phone must not exceed 30 characters",
    );
    valid = false;
  }

  if (!profileForm.country) {
    profileErrors.country = t(
      "pleaseSelectYourCountry",
      "Please select your country",
    );
    valid = false;
  } else if (profileForm.country.length > 100) {
    profileErrors.country = t(
      "countryTooLong",
      "Country must not exceed 100 characters",
    );
    valid = false;
  }

  return valid;
};

const submitProfile = async () => {
  if (!validateProfile()) {
    return;
  }

  profileSaving.value = true;
  profileMessage.value = "";

  const payload = {
    firstName: profileForm.firstName,
    lastName: profileForm.lastName,
    phone: profileForm.phone,
    country: profileForm.country,
  };

  const { success, error } = await clientAuthStore.updateProfile(payload);
  profileSaving.value = false;

  if (success) {
    profileSuccess.value = true;
    profileMessage.value = "Profile updated successfully.";
  } else {
    profileSuccess.value = false;
    profileMessage.value = error || "Failed to update profile.";
  }
};

const openPasswordModal = () => {
  closeMenu();
  passwordForm.currentPassword = "";
  passwordForm.newPassword = "";
  passwordForm.confirmPassword = "";
  // Reset password visibility states
  showCurrentPassword.value = false;
  showNewPassword.value = false;
  showConfirmPassword.value = false;
  resetPasswordState();
  showPasswordModal.value = true;
  loadPasswordStrengthSettings();
};

const toggleCurrentPassword = () => {
  showCurrentPassword.value = !showCurrentPassword.value;
};

const toggleNewPassword = () => {
  showNewPassword.value = !showNewPassword.value;
};

const toggleConfirmPassword = () => {
  showConfirmPassword.value = !showConfirmPassword.value;
};

const closePasswordModal = () => {
  showPasswordModal.value = false;
};

const resetTicketState = () => {
  ticketErrors.title = "";
  ticketErrors.content = "";
  ticketMessage.value = "";
  ticketSuccess.value = false;
  ticketSaving.value = false;
};

const openTicketModal = () => {
  closeMenu();
  ticketForm.title = "";
  ticketForm.content = "";
  resetTicketState();
  showTicketModal.value = true;
};

const closeTicketModal = () => {
  showTicketModal.value = false;
};

const validateTicket = () => {
  resetTicketState();
  let valid = true;

  ticketForm.title = (ticketForm.title || "").trim();
  ticketForm.content = (ticketForm.content || "").trim();

  if (!ticketForm.title) {
    ticketErrors.title = "Title is required";
    valid = false;
  } else if (ticketForm.title.length > 255) {
    ticketErrors.title = "Title must not exceed 255 characters";
    valid = false;
  }

  if (!ticketForm.content) {
    ticketErrors.content = "Content is required";
    valid = false;
  }

  return valid;
};

const submitTicket = async () => {
  if (!validateTicket()) {
    return;
  }

  ticketSaving.value = true;
  ticketMessage.value = "";

  try {
    const response = await clientTicketService.submitTicket({
      title: ticketForm.title,
      content: ticketForm.content,
    });

    if (response.success) {
      ticketSuccess.value = true;
      ticketMessage.value =
        "Ticket submitted successfully. We will get back to you soon.";
      ticketForm.title = "";
      ticketForm.content = "";
      // 3秒后自动关闭
      setTimeout(() => {
        closeTicketModal();
      }, 1000);
    } else {
      ticketSuccess.value = false;
      ticketMessage.value =
        response.message || "Failed to submit ticket. Please try again.";
    }
  } catch (err) {
    ticketSuccess.value = false;
    ticketMessage.value =
      err.response?.data?.message ||
      "Failed to submit ticket. Please try again.";
  } finally {
    ticketSaving.value = false;
  }
};

const validatePassword = () => {
  resetPasswordState();
  let valid = true;

  passwordForm.currentPassword = (passwordForm.currentPassword || "").trim();
  passwordForm.newPassword = (passwordForm.newPassword || "").trim();
  passwordForm.confirmPassword = (passwordForm.confirmPassword || "").trim();

  if (!passwordForm.currentPassword) {
    passwordErrors.currentPassword = t(
      "currentPasswordRequired",
      "Current password is required",
    );
    valid = false;
  }

  if (!passwordForm.newPassword) {
    passwordErrors.newPassword = t(
      "newPasswordRequired",
      "New password is required",
    );
    valid = false;
  } else {
    const passwordValidation = validatePasswordAgainstSettings(
      passwordForm.newPassword,
      passwordStrengthSettings.value,
    );

    if (!passwordValidation.valid) {
      passwordErrors.newPassword = passwordValidation.errors[0];
      valid = false;
    }
  }

  if (passwordForm.confirmPassword !== passwordForm.newPassword) {
    passwordErrors.confirmPassword = t(
      "passwordsDoNotMatch",
      "Passwords do not match",
    );
    valid = false;
  }

  return valid;
};

const submitPassword = async () => {
  if (!validatePassword()) {
    return;
  }

  passwordSaving.value = true;
  passwordMessage.value = "";

  const payload = {
    currentPassword: passwordForm.currentPassword,
    newPassword: passwordForm.newPassword,
    confirmPassword: passwordForm.confirmPassword,
  };

  const { success, error } = await clientAuthStore.changePassword(payload);
  passwordSaving.value = false;

  if (success) {
    passwordSuccess.value = true;
    passwordMessage.value = t(
      "passwordUpdatedSuccessfully",
      "Password updated successfully.",
    );
    passwordForm.currentPassword = "";
    passwordForm.newPassword = "";
    passwordForm.confirmPassword = "";
  } else {
    passwordSuccess.value = false;
    passwordMessage.value =
      error || t("failedToChangePassword", "Failed to change password.");
  }
};

const handleLogout = async () => {
  if (confirm(t("confirmLogout", "Are you sure you want to logout?"))) {
    await clientAuthStore.logout();
    router.push("/client/login");
  }
};

const handleClickOutside = (event) => {
  if (userMenuRef.value && !userMenuRef.value.contains(event.target)) {
    isActive.value = false;
  }

  if (
    countrySelectRef.value &&
    !countrySelectRef.value.contains(event.target)
  ) {
    closeCountryDropdown();
  }
};

onMounted(() => {
  if (!countryStore.loaded) {
    countryStore.fetchCountries();
  }
  document.addEventListener("click", handleClickOutside, true);
});

onUnmounted(() => {
  document.removeEventListener("click", handleClickOutside, true);
});
</script>

<style scoped>
.user-menu {
  position: relative;
}

.user-menu-trigger {
  width: 100%;
  border: 1px solid var(--color-border);
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 12px;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition:
    background-color 0.3s ease,
    border-color 0.3s ease;
}

.user-menu-trigger:hover {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
}

.user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--color-brand-solid);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  font-size: 14px;
}

.user-info {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.user-name {
  font-weight: 600;
  font-size: 14px;
  color: var(--color-ink);
}

.user-role {
  font-size: 14px;
  color: var(--color-muted);
}

.user-id {
  margin-left: 6px;
  font-size: 14px;
  font-weight: 500;
  color: var(--color-faint);
}

.user-menu-arrow {
  color: var(--color-faint);
  font-size: 14px;
  transition: transform 0.3s ease;
}

.user-menu.active .user-menu-arrow {
  transform: rotate(180deg);
}

.user-dropdown {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
  min-width: 220px;
  z-index: 1000;
  overflow: hidden;
  animation: slideDown 0.3s ease;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.user-dropdown-header {
  padding: 15px 20px;
  background: var(--color-surface-soft);
  border-bottom: 1px solid var(--color-border);
}

.user-dropdown-header .user-name {
  font-size: 15px;
  margin-bottom: 3px;
}

.user-dropdown-header .user-email {
  font-size: 14px;
  color: var(--color-muted);
}

.user-dropdown-header .user-uid {
  margin-top: 4px;
  font-size: 14px;
  color: var(--color-faint);
}

.user-dropdown-item {
  padding: 12px 20px;
  display: flex;
  align-items: center;
  gap: 12px;
  color: var(--color-text);
  text-decoration: none;
  transition:
    background-color 0.2s ease,
    color 0.2s ease;
  cursor: pointer;
  font-size: 14px;
  width: 100%;
  border: none;
  background: transparent;
  text-align: left;
}

.user-dropdown-item:hover {
  background: var(--color-surface-soft);
  color: var(--color-brand);
}

.user-dropdown-item i {
  width: 20px;
  text-align: center;
  color: var(--color-faint);
}

.user-dropdown-item:hover i {
  color: var(--color-brand);
}

.user-dropdown-divider {
  height: 1px;
  background: var(--color-border);
  margin: 5px 0;
}

.user-dropdown-item.danger {
  color: var(--color-danger);
}

.user-dropdown-item.danger:hover {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.user-dropdown-item.danger i {
  color: var(--color-danger-border);
}

.user-dropdown-item.danger:hover i {
  color: var(--color-danger);
}

.dropdown-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 2000;
  padding: 20px;
  display: none;
  align-items: center;
  justify-content: center;
}

.dropdown-modal.active {
  display: flex;
}

.dropdown-modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  width: 90%;
  max-width: 600px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  animation: slideUp 0.3s ease;
}

@keyframes modalIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.dropdown-modal-header {
  background: var(--color-surface-soft);
  padding: 25px 30px;
  border-bottom: 1px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.dropdown-modal-header h2 {
  font-size: 22px;
  color: var(--color-ink);
}

.dropdown-modal-header h2 i {
  margin-right: 10px;
  color: var(--color-brand);
}

.modal-title {
  margin: 0;
  font-size: 20px;
  color: var(--color-ink);
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
}

.dropdown-modal-close {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-sm);
  background: var(--color-surface-soft);
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition:
    background-color 0.2s ease,
    color 0.2s ease;
  font-size: 18px;
  color: var(--color-text);
}

.dropdown-modal-close:hover {
  background: var(--color-border);
  color: var(--color-ink);
}

.dropdown-modal-body {
  padding: 24px 28px 0;
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  margin-bottom: 25px;
  position: relative;
}

.form-group label {
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 6px;
  color: var(--color-text);
}

.form-group input,
.form-group select,
.form-group textarea {
  padding: 12px 14px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border-strong);
  background: var(--color-surface);
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
  font-size: 14px;
  color: var(--color-ink);
  font-family: inherit;
  resize: vertical;
}

.form-group textarea {
  min-height: 120px;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  border-color: var(--color-brand);
  box-shadow: none;
}

.custom-select {
  position: relative;
}

.custom-select-trigger {
  width: 100%;
  min-height: 46px;
  padding: 12px 14px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border-strong);
  background: var(--color-surface);
  color: var(--color-ink);
  font-size: 14px;
  font-family: inherit;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  cursor: pointer;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
}

.custom-select-trigger.placeholder {
  color: var(--color-faint);
}

.custom-select-trigger.open,
.custom-select-trigger:focus {
  border-color: var(--color-brand);
  box-shadow: none;
}

.custom-select-trigger.field-error {
  border-color: var(--color-danger);
  box-shadow: none;
}

.custom-select-trigger i {
  font-size: 14px;
  color: var(--color-muted);
  flex-shrink: 0;
}

.custom-select-menu {
  position: absolute;
  left: 0;
  right: 0;
  bottom: calc(100% + 8px);
  z-index: 30;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  box-shadow: 0 18px 35px rgba(15, 23, 42, 0.12);
  overflow: hidden;
}

.custom-select-search {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-bottom: 1px solid var(--color-border);
  background: var(--color-surface-soft);
}

.custom-select-search i {
  color: var(--color-faint);
}

.custom-select-search input {
  border: 0;
  padding: 0;
  box-shadow: none;
  background: transparent;
}

.custom-select-search input:focus {
  box-shadow: none;
}

.custom-select-options {
  max-height: 220px;
  overflow-y: auto;
}

.custom-select-option {
  width: 100%;
  border: 0;
  background: var(--color-surface);
  padding: 11px 14px;
  text-align: left;
  font-size: 14px;
  color: var(--color-ink);
  cursor: pointer;
  transition:
    background 0.2s ease,
    color 0.2s ease;
}

.custom-select-option:hover,
.custom-select-option.selected {
  background: var(--color-brand-soft);
  color: var(--color-brand-strong);
}

.custom-select-empty {
  padding: 14px;
  font-size: 14px;
  color: var(--color-muted);
}

.form-group input:disabled,
.form-group select:disabled {
  background: var(--color-surface-soft);
  color: var(--color-muted);
}

.form-group input.field-error,
.form-group select.field-error,
.form-group textarea.field-error,
.phone-input.field-error {
  border-color: var(--color-danger);
  box-shadow: none;
}

.form-group input.field-error:focus,
.form-group select.field-error:focus,
.form-group textarea.field-error:focus,
.phone-input.field-error:focus {
  border-color: var(--color-danger);
  box-shadow: none;
}

.error-text {
  margin-top: 6px;
  font-size: 14px;
  color: var(--color-danger);
}

.form-message {
  margin-top: 8px;
  margin-bottom: 4px;
  padding: 10px 12px;
  border-radius: var(--radius-md);
  font-size: 14px;
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.form-message.success {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.dropdown-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 20px 28px;
  margin: 0 -28px;
  border-top: 1px solid var(--color-border);
  background: var(--color-surface-soft);
  flex-shrink: 0;
}

.btn {
  border: none;
  border-radius: var(--radius-md);
  padding: 12px 20px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition:
    background-color 0.2s ease,
    color 0.2s ease,
    box-shadow 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 10px 20px rgba(var(--color-brand-rgb), 0.25);
}

.btn-primary:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.btn-primary:hover:not(:disabled) {
  box-shadow: 0 12px 24px rgba(var(--color-brand-rgb), 0.35);
}

.btn-secondary {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.btn-secondary:hover {
  background: var(--color-border);
}

@media (max-width: 768px) {
  .user-info {
    display: none;
  }

  .user-menu-arrow {
    display: none;
  }

  .user-menu-trigger {
    padding: 4px;
    gap: 0;
  }

  .user-avatar {
    width: 34px;
    height: 34px;
    font-size: 14px;
  }
}

.phone-input-row {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}

.phone-input {
  flex: 1;
  padding: 12px 14px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border-strong);
  background: var(--color-surface);
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
  font-size: 14px;
  color: var(--color-ink);
  font-family: inherit;
}

.phone-input:focus {
  border-color: var(--color-brand);
  box-shadow: none;
}

/* Password input with toggle button */
.password-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.password-input {
  width: 100%;
  padding: 12px 45px 12px 14px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border-strong);
  background: var(--color-surface);
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
  font-size: 14px;
  color: var(--color-ink);
  font-family: inherit;
}

.password-input:focus {
  border-color: var(--color-brand);
  box-shadow: none;
}

.password-toggle-btn {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px 8px;
  color: var(--color-muted);
  font-size: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition:
    background-color 0.2s ease,
    color 0.2s ease;
  border-radius: 4px;
  width: 32px;
  height: 32px;
}

.password-toggle-btn:hover {
  color: var(--color-brand);
  background: var(--color-brand-soft);
}

.password-toggle-btn:focus {
  color: var(--color-brand);
  background: var(--color-brand-soft);
}

.password-toggle-btn i {
  pointer-events: none;
}

@media (max-width: 768px) {
  .dropdown-modal-content {
    max-width: 100%;
  }

  .dropdown-modal-body {
    padding: 20px 20px 0;
  }

  .dropdown-modal-footer {
    padding: 20px;
    margin: 0 -20px;
  }

  .phone-input-row {
    flex-direction: column;
  }

  .phone-input {
    width: 100%;
  }
}
</style>
