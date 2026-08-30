<template>
  <div
    v-if="modelValue"
    class="reset-password-modal show"
    @click.self="handleClose"
  >
    <div class="reset-password-modal-content">
      <div class="reset-password-modal-header">
        <h3><i class="fas fa-key"></i> Reset Password</h3>
        <button
          type="button"
          class="reset-password-modal-close"
          @click="handleClose"
        >
          &times;
        </button>
      </div>
      <div class="reset-password-modal-body">
        <div class="client-info-bar">
          <span
            >{{ entityLabel }}: <strong>{{ clientName }}</strong></span
          >
        </div>

        <!-- Password Strength Requirements -->
        <div v-if="loadingStrength" class="loading-strength">
          <i class="fas fa-spinner fa-spin"></i> Loading password
          requirements...
        </div>
        <div v-else-if="strengthRules" class="strength-requirements-box">
          <div class="strength-requirements-title">
            <i class="fas fa-shield-alt"></i> Password Requirements
          </div>
          <ul class="strength-requirements-list">
            <li :class="{ met: newPassword.length >= strengthRules.minLength }">
              <i
                :class="
                  newPassword.length >= strengthRules.minLength
                    ? 'fas fa-check'
                    : 'fas fa-times'
                "
              ></i>
              Minimum {{ strengthRules.minLength }} characters
            </li>
            <li
              v-if="strengthRules.requireLetters"
              :class="{ met: hasLetters }"
            >
              <i :class="hasLetters ? 'fas fa-check' : 'fas fa-times'"></i>
              Must contain letters
            </li>
            <li
              v-if="strengthRules.requireNumbers"
              :class="{ met: hasNumbers }"
            >
              <i :class="hasNumbers ? 'fas fa-check' : 'fas fa-times'"></i>
              Must contain numbers
            </li>
            <li
              v-if="strengthRules.requireSpecial"
              :class="{ met: hasSpecial }"
            >
              <i :class="hasSpecial ? 'fas fa-check' : 'fas fa-times'"></i>
              Must contain special characters
            </li>
            <li
              v-if="strengthRules.requireUppercase"
              :class="{ met: hasUppercase }"
            >
              <i :class="hasUppercase ? 'fas fa-check' : 'fas fa-times'"></i>
              Must contain uppercase letters
            </li>
          </ul>
        </div>

        <form @submit.prevent="handleSubmit">
          <div class="form-field">
            <label>New Password <span class="required">*</span></label>
            <div class="password-input-wrapper">
              <input
                v-model="newPassword"
                :type="showNewPassword ? 'text' : 'password'"
                placeholder="Enter new password"
                required
              />
              <button
                type="button"
                class="toggle-password"
                @click="showNewPassword = !showNewPassword"
              >
                <i
                  :class="showNewPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"
                ></i>
              </button>
            </div>
            <div class="password-strength">
              <div
                class="password-strength-bar"
                :class="passwordStrengthClass"
              ></div>
            </div>
          </div>

          <div class="form-field">
            <label>Confirm Password <span class="required">*</span></label>
            <div class="password-input-wrapper">
              <input
                v-model="confirmPassword"
                :type="showConfirmPassword ? 'text' : 'password'"
                placeholder="Re-enter new password"
                required
              />
              <button
                type="button"
                class="toggle-password"
                @click="showConfirmPassword = !showConfirmPassword"
              >
                <i
                  :class="
                    showConfirmPassword ? 'fas fa-eye-slash' : 'fas fa-eye'
                  "
                ></i>
              </button>
            </div>
            <span
              v-if="confirmPassword && newPassword !== confirmPassword"
              class="field-error"
            >
              Passwords do not match.
            </span>
          </div>

          <div class="reset-password-modal-footer">
            <button type="button" class="btn-cancel" @click="handleClose">
              Cancel
            </button>
            <button
              type="submit"
              class="btn-submit"
              :disabled="!canSubmit || submitting"
            >
              <i v-if="submitting" class="fas fa-spinner fa-spin"></i>
              <i v-else class="fas fa-key"></i>
              {{ submitting ? "Resetting..." : "Reset Password" }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch } from "vue";
import { clientService } from "@/services/clientListService";
import loginSettingsService from "@/services/loginSettingsService";

export default {
  name: "ResetPasswordModal",
  props: {
    modelValue: {
      type: Boolean,
      default: false,
    },
    clientId: {
      type: [Number, String],
      required: true,
    },
    clientName: {
      type: String,
      default: "",
    },
    entityLabel: {
      type: String,
      default: "Client",
    },
    resetFn: {
      type: Function,
      default: null,
    },
  },
  emits: ["update:modelValue", "success"],
  setup(props, { emit }) {
    const newPassword = ref("");
    const confirmPassword = ref("");
    const showNewPassword = ref(false);
    const showConfirmPassword = ref(false);
    const submitting = ref(false);
    const loadingStrength = ref(false);
    const strengthRules = ref(null);

    const hasLetters = computed(() => /[a-zA-Z]/.test(newPassword.value));
    const hasNumbers = computed(() => /\d/.test(newPassword.value));
    const hasSpecial = computed(() => /[^A-Za-z0-9]/.test(newPassword.value));
    const hasUppercase = computed(() => /[A-Z]/.test(newPassword.value));

    const passwordStrengthClass = computed(() => {
      const pwd = newPassword.value;
      if (!pwd) return "";
      let score = 0;
      if (pwd.length >= 6) score += 1;
      if (pwd.length >= 12) score += 1;
      if (/[A-Z]/.test(pwd) && /[a-z]/.test(pwd)) score += 1;
      if (/\d/.test(pwd)) score += 1;
      if (/[^A-Za-z0-9]/.test(pwd)) score += 1;
      if (score >= 4) return "strong";
      if (score >= 2) return "medium";
      return "weak";
    });

    const meetsRequirements = computed(() => {
      if (!strengthRules.value) return true;
      const rules = strengthRules.value;
      if (newPassword.value.length < rules.minLength) return false;
      if (rules.requireLetters && !hasLetters.value) return false;
      if (rules.requireNumbers && !hasNumbers.value) return false;
      if (rules.requireSpecial && !hasSpecial.value) return false;
      if (rules.requireUppercase && !hasUppercase.value) return false;
      return true;
    });

    const canSubmit = computed(() => {
      return (
        newPassword.value &&
        confirmPassword.value &&
        newPassword.value === confirmPassword.value &&
        meetsRequirements.value
      );
    });

    const loadPasswordStrength = async () => {
      loadingStrength.value = true;
      try {
        const res = await loginSettingsService.getPasswordStrength();
        if (res.data) {
          strengthRules.value = res.data;
        }
      } catch (e) {
        console.error("Failed to load password strength settings:", e);
      } finally {
        loadingStrength.value = false;
      }
    };

    const handleClose = () => {
      if (submitting.value) return;
      newPassword.value = "";
      confirmPassword.value = "";
      showNewPassword.value = false;
      showConfirmPassword.value = false;
      emit("update:modelValue", false);
    };

    const handleSubmit = async () => {
      if (!canSubmit.value || submitting.value) return;
      submitting.value = true;
      try {
        const resetFunc = props.resetFn || clientService.resetPassword;
        await resetFunc(props.clientId, {
          newPassword: newPassword.value,
        });
        emit("success");
        handleClose();
        alert("Password reset successfully!");
      } catch (error) {
        const message =
          error?.response?.data?.message || error?.message || "Unknown error";
        alert("Failed to reset password: " + message);
      } finally {
        submitting.value = false;
      }
    };

    watch(
      () => props.modelValue,
      (val) => {
        if (val) {
          loadPasswordStrength();
        }
      },
    );

    return {
      newPassword,
      confirmPassword,
      showNewPassword,
      showConfirmPassword,
      submitting,
      loadingStrength,
      strengthRules,
      hasLetters,
      hasNumbers,
      hasSpecial,
      hasUppercase,
      passwordStrengthClass,
      meetsRequirements,
      canSubmit,
      handleClose,
      handleSubmit,
    };
  },
};
</script>

<style scoped>
.reset-password-modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 1050;
  align-items: center;
  justify-content: center;
}

.reset-password-modal.show {
  display: flex;
}

.reset-password-modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  width: 480px;
  max-width: 90vw;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.reset-password-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid var(--color-border);
}

.reset-password-modal-header h3 {
  margin: 0;
  font-size: 18px;
  color: var(--color-ink-strong);
}

.reset-password-modal-header h3 i {
  color: var(--color-brand);
  margin-right: 8px;
}

.reset-password-modal-close {
  background: none;
  border: none;
  font-size: 24px;
  color: var(--color-faint);
  cursor: pointer;
  padding: 0;
  line-height: 1;
}

.reset-password-modal-close:hover {
  color: var(--color-danger);
}

.reset-password-modal-body {
  padding: 24px;
}

.client-info-bar {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 10px 14px;
  margin-bottom: 20px;
  font-size: 14px;
  color: var(--color-text);
}

.loading-strength {
  text-align: center;
  padding: 16px;
  color: var(--color-muted);
  font-size: 14px;
}

.strength-requirements-box {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 14px;
  margin-bottom: 20px;
}

.strength-requirements-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: 10px;
}

.strength-requirements-title i {
  color: var(--color-brand);
  margin-right: 6px;
}

.strength-requirements-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.strength-requirements-list li {
  font-size: 13px;
  color: var(--color-faint);
  padding: 3px 0;
  transition: color 0.2s;
}

.strength-requirements-list li.met {
  color: var(--color-success);
}

.strength-requirements-list li i {
  width: 16px;
  margin-right: 6px;
  font-size: 11px;
}

.form-field {
  margin-bottom: 18px;
}

.form-field label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: 6px;
}

.required {
  color: var(--color-danger);
}

.password-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.password-input-wrapper input {
  width: 100%;
  padding: 10px 40px 10px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: border-color 0.2s;
}

.password-input-wrapper input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.toggle-password {
  position: absolute;
  right: 10px;
  background: none;
  border: none;
  color: var(--color-faint);
  cursor: pointer;
  padding: 4px;
}

.toggle-password:hover {
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

.field-error {
  display: block;
  color: var(--color-danger);
  font-size: 12px;
  margin-top: 4px;
}

.reset-password-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 24px;
  padding-top: 16px;
  border-top: 1px solid var(--color-border);
}

.btn-cancel {
  padding: 10px 20px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-cancel:hover {
  background: var(--color-surface-soft);
  border-color: var(--color-border-strong);
}

.btn-submit {
  padding: 10px 20px;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-brand-solid);
  color: white;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-submit:hover:not(:disabled) {
  opacity: 0.9;
  transform: translateY(-1px);
}

.btn-submit:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-submit i {
  margin-right: 6px;
}
</style>
