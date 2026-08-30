<template>
  <div class="kyc-verification-wrapper">
    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <i class="fas fa-spinner fa-spin"></i>
      <p>Loading KYC verification form...</p>
    </div>

    <!-- Main Form -->
    <div v-else-if="template" class="kyc-form-container">
      <!-- Progress Section -->
      <div class="progress-section">
        <div class="progress-header">
          <div class="progress-title">Verification Progress</div>
          <div class="progress-steps">
            Step {{ currentStep + 1 }} of {{ categories.length }}
          </div>
        </div>
        <div class="progress-bar-container">
          <div
            class="progress-bar-fill"
            :style="{ width: progressPercentage + '%' }"
          ></div>
        </div>
      </div>

      <!-- Form Card -->
      <div class="kyc-form-card">
        <form @submit.prevent="handleNext">
          <!-- Category Header -->
          <div class="category-header">
            <div class="category-icon">
              <i
                class="fas"
                :class="currentCategory.icon || 'fa-clipboard'"
              ></i>
            </div>
            <div class="category-info">
              <h2>{{ currentCategory.categoryName }}</h2>
              <p>
                {{
                  currentCategory.description ||
                  "Please answer the following questions"
                }}
              </p>
            </div>
          </div>

          <!-- Questions -->
          <div
            v-for="question in currentQuestions"
            :key="question.id"
            class="form-group"
          >
            <label class="form-label">
              {{ question.questionText }}
              <span v-if="question.isRequired" class="required-indicator"
                >*</span
              >
            </label>

            <!-- Text Input -->
            <input
              v-if="question.questionType === 'text'"
              type="text"
              class="form-input"
              v-model="answers[question.id]"
              :required="question.isRequired"
            />

            <!-- Number Input -->
            <input
              v-else-if="question.questionType === 'number'"
              type="number"
              class="form-input"
              v-model="answers[question.id]"
              :required="question.isRequired"
            />

            <!-- Date Input -->
            <input
              v-else-if="question.questionType === 'date'"
              type="date"
              class="form-input"
              v-model="answers[question.id]"
              :required="question.isRequired"
            />

            <!-- Email Input -->
            <input
              v-else-if="question.questionType === 'email'"
              type="email"
              class="form-input"
              v-model="answers[question.id]"
              :required="question.isRequired"
            />

            <!-- Textarea -->
            <textarea
              v-else-if="question.questionType === 'textarea'"
              class="form-textarea"
              v-model="answers[question.id]"
              :required="question.isRequired"
              rows="4"
            ></textarea>

            <!-- Select Dropdown -->
            <select
              v-else-if="
                question.questionType === 'single_choice' &&
                question.options.length <= 5
              "
              class="form-select"
              v-model="answers[question.id]"
              :required="question.isRequired"
            >
              <option value="">Select an option</option>
              <option
                v-for="option in question.options"
                :key="option.id"
                :value="option.optionValue"
              >
                {{ option.optionValue }}
              </option>
            </select>

            <!-- Radio Group -->
            <div
              v-else-if="question.questionType === 'single_choice'"
              class="radio-group"
            >
              <div
                v-for="option in question.options"
                :key="option.id"
                class="radio-option"
                :class="{
                  selected: answers[question.id] === option.optionValue,
                }"
                @click="selectRadio(question.id, option.optionValue)"
              >
                <input
                  type="radio"
                  :name="`question_${question.id}`"
                  :value="option.optionValue"
                  v-model="answers[question.id]"
                  :required="question.isRequired"
                />
                <label>{{ option.optionValue }}</label>
              </div>
            </div>

            <!-- Yes/No Radio -->
            <div
              v-else-if="question.questionType === 'yes_no'"
              class="radio-group"
            >
              <div
                class="radio-option"
                :class="{ selected: answers[question.id] === 'Yes' }"
                @click="selectRadio(question.id, 'Yes')"
              >
                <input
                  type="radio"
                  :name="`question_${question.id}`"
                  value="Yes"
                  v-model="answers[question.id]"
                  :required="question.isRequired"
                />
                <label>Yes</label>
              </div>
              <div
                class="radio-option"
                :class="{ selected: answers[question.id] === 'No' }"
                @click="selectRadio(question.id, 'No')"
              >
                <input
                  type="radio"
                  :name="`question_${question.id}`"
                  value="No"
                  v-model="answers[question.id]"
                  :required="question.isRequired"
                />
                <label>No</label>
              </div>
            </div>

            <!-- Checkbox Group -->
            <div
              v-else-if="question.questionType === 'multiple_choice'"
              class="checkbox-group"
            >
              <div
                v-for="option in question.options"
                :key="option.id"
                class="checkbox-option"
                :class="{
                  selected: isCheckboxSelected(question.id, option.optionValue),
                }"
                @click="toggleCheckbox(question.id, option.optionValue)"
              >
                <input
                  type="checkbox"
                  :value="option.optionValue"
                  v-model="answers[question.id]"
                />
                <label>{{ option.optionValue }}</label>
              </div>
            </div>

            <!-- File Upload -->
            <div v-else-if="question.questionType === 'file_upload'">
              <FileUpload
                :question-id="question.id"
                :submission-id="submissionId"
                :required="question.isRequired"
                :document-types="question.documentTypes"
                @files-changed="handleFilesChanged"
              />
            </div>

            <!-- Help Text -->
            <div v-if="question.helpText" class="form-help">
              <i class="fas fa-info-circle"></i>
              {{ question.helpText }}
            </div>
          </div>

          <!-- Navigation Buttons -->
          <div class="form-navigation">
            <button
              v-if="currentStep > 0"
              type="button"
              class="btn btn-secondary"
              @click="handlePrevious"
            >
              <i class="fas fa-arrow-left"></i> Previous
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="submitting"
            >
              <i
                class="fas"
                :class="
                  submitting
                    ? 'fa-spinner fa-spin'
                    : isLastStep
                      ? 'fa-check'
                      : 'fa-arrow-right'
                "
              ></i>
              {{
                submitting
                  ? "Processing..."
                  : isLastStep
                    ? "Submit Application"
                    : "Next"
              }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <i class="fas fa-exclamation-triangle"></i>
      <p>{{ error }}</p>
      <button class="btn btn-primary" @click="loadTemplate">
        <i class="fas fa-redo"></i> Try Again
      </button>
    </div>

    <!-- Success Modal -->
    <SuccessModal v-if="showSuccessModal" @close="handleSuccessClose" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { clientKycService } from "@/services/clientKycService";
import FileUpload from "./FileUpload.vue";
import SuccessModal from "./KycSuccessModal.vue";

const emit = defineEmits(["close", "submitted"]);

// State
const loading = ref(false);
const submitting = ref(false);
const error = ref(null);
const template = ref(null);
const categories = ref([]);
const allQuestions = ref([]);
const currentStep = ref(0);
const answers = ref({});
const submissionId = ref(null);
const uploadedFiles = ref({});
const showSuccessModal = ref(false);

// Computed
const currentCategory = computed(() => {
  return categories.value[currentStep.value] || {};
});

const currentQuestions = computed(() => {
  return allQuestions.value.filter(
    (q) => q.categoryId === currentCategory.value.id,
  );
});

const progressPercentage = computed(() => {
  return ((currentStep.value + 1) / categories.value.length) * 100;
});

const isLastStep = computed(() => {
  return currentStep.value === categories.value.length - 1;
});

// Methods
const loadTemplate = async () => {
  loading.value = true;
  error.value = null;

  try {
    // 获取适用的模板
    const templateResponse = await clientKycService.getAvailableTemplate();

    if (!templateResponse.success || !templateResponse.data) {
      throw new Error("No KYC template available for your account");
    }

    const templateId = templateResponse.data.id;

    // 获取模板详情（包含所有问题）
    const detailsResponse =
      await clientKycService.getTemplateDetails(templateId);

    if (!detailsResponse.success) {
      throw new Error("Failed to load KYC template details");
    }

    template.value = detailsResponse.data;
    categories.value = detailsResponse.data.categories || [];
    allQuestions.value = detailsResponse.data.questions || [];

    // 初始化答案对象
    initializeAnswers();

    // 创建提交记录
    await createSubmission(templateId);
  } catch (err) {
    console.error("Failed to load template:", err);
    error.value =
      err.message || "Failed to load KYC verification form. Please try again.";
  } finally {
    loading.value = false;
  }
};

const initializeAnswers = () => {
  allQuestions.value.forEach((question) => {
    if (question.questionType === "multiple_choice") {
      answers.value[question.id] = [];
    } else {
      answers.value[question.id] = "";
    }
  });
};

const createSubmission = async (templateId) => {
  try {
    const response = await clientKycService.createSubmission(templateId);
    if (response.success && response.data) {
      submissionId.value = response.data.id;
    }
  } catch (err) {
    console.error("Failed to create submission:", err);
  }
};

const selectRadio = (questionId, value) => {
  answers.value[questionId] = value;
};

const isCheckboxSelected = (questionId, value) => {
  return (
    Array.isArray(answers.value[questionId]) &&
    answers.value[questionId].includes(value)
  );
};

const toggleCheckbox = (questionId, value) => {
  if (!Array.isArray(answers.value[questionId])) {
    answers.value[questionId] = [];
  }

  const index = answers.value[questionId].indexOf(value);
  if (index > -1) {
    answers.value[questionId].splice(index, 1);
  } else {
    answers.value[questionId].push(value);
  }
};

const handleFilesChanged = (questionId, files) => {
  uploadedFiles.value[questionId] = files;
};

const validateCurrentStep = () => {
  // Validate all questions in current step
  for (const question of currentQuestions.value) {
    if (question.isRequired) {
      const answer = answers.value[question.id];

      // Check if answer is empty
      if (answer === "" || answer === null || answer === undefined) {
        alert(`Please answer: ${question.questionText}`);
        return false;
      }

      // Check if multiple choice has at least one selection
      if (
        question.questionType === "multiple_choice" &&
        Array.isArray(answer) &&
        answer.length === 0
      ) {
        alert(
          `Please select at least one option for: ${question.questionText}`,
        );
        return false;
      }

      // Check if file upload has files
      if (question.questionType === "file_upload") {
        const files = uploadedFiles.value[question.id];
        if (!files || files.length === 0) {
          alert(
            `Please upload at least one document for: ${question.questionText}`,
          );
          return false;
        }
      }
    }
  }

  return true;
};

const saveCurrentStepAnswers = async () => {
  if (!submissionId.value) return;

  try {
    // Prepare answers for current step
    const stepAnswers = {};
    currentQuestions.value.forEach((question) => {
      stepAnswers[question.id] = {
        questionId: question.id,
        answer: answers.value[question.id],
        questionType: question.questionType,
      };
    });

    await clientKycService.saveAnswers(submissionId.value, stepAnswers);
  } catch (err) {
    console.error("Failed to save answers:", err);
    // Continue anyway - answers are stored locally
  }
};

const handleNext = async () => {
  if (!validateCurrentStep()) {
    return;
  }

  // Save current step answers
  await saveCurrentStepAnswers();

  if (isLastStep.value) {
    // Submit the application
    await submitApplication();
  } else {
    // Go to next step
    currentStep.value++;
    window.scrollTo({ top: 0, behavior: "smooth" });
  }
};

const handlePrevious = () => {
  if (currentStep.value > 0) {
    currentStep.value--;
    window.scrollTo({ top: 0, behavior: "smooth" });
  }
};

const submitApplication = async () => {
  submitting.value = true;

  try {
    // Final save of all answers
    await saveCurrentStepAnswers();

    // Submit the application
    const response = await clientKycService.submitApplication(
      submissionId.value,
    );

    if (response.success) {
      showSuccessModal.value = true;
    } else {
      alert(`Failed to submit application: ${response.message}`);
    }
  } catch (err) {
    console.error("Failed to submit application:", err);
    alert("Failed to submit your KYC application. Please try again.");
  } finally {
    submitting.value = false;
  }
};

const handleSuccessClose = () => {
  showSuccessModal.value = false;
  emit("submitted");
  emit("close");
};

// Lifecycle
onMounted(() => {
  loadTemplate();
});
</script>

<style scoped>
.kyc-verification-wrapper {
  max-width: 900px;
  margin: 0 auto;
  padding: 40px 20px;
}

.loading-state,
.error-state {
  text-align: center;
  padding: 60px 20px;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.loading-state i,
.error-state i {
  font-size: 48px;
  color: var(--color-brand);
  margin-bottom: 15px;
  display: block;
}

.error-state i {
  color: var(--color-danger);
}

.loading-state p,
.error-state p {
  font-size: 16px;
  color: var(--color-muted);
  margin-bottom: 20px;
}

/* Progress Bar */
.progress-section {
  margin-bottom: 30px;
}

.progress-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.progress-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-ink);
}

.progress-steps {
  font-size: 14px;
  color: var(--color-muted);
}

.progress-bar-container {
  background: var(--color-border);
  height: 12px;
  border-radius: 20px;
  overflow: hidden;
  position: relative;
}

.progress-bar-fill {
  height: 100%;
  background: var(--color-brand-solid);
  border-radius: 20px;
  transition: width 0.5s ease;
  position: relative;
}

.progress-bar-fill::after {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(255, 255, 255, 0.3),
    transparent
  );
  animation: shimmer 2s infinite;
}

@keyframes shimmer {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}

/* Form Card */
.kyc-form-card {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  padding: 40px;
}

.category-header {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.category-icon {
  width: 50px;
  height: 50px;
  background: var(--color-brand-solid);
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 24px;
}

.category-info h2 {
  font-size: 24px;
  color: var(--color-ink);
  margin-bottom: 5px;
}

.category-info p {
  font-size: 14px;
  color: var(--color-muted);
}

/* Form Fields */
.form-group {
  margin-bottom: 30px;
}

.form-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 10px;
}

.required-indicator {
  color: var(--color-danger);
  font-size: 16px;
}

.form-help {
  font-size: 14px;
  color: var(--color-muted);
  margin-top: 6px;
  display: flex;
  align-items: start;
  gap: 6px;
}

.form-help i {
  margin-top: 2px;
  color: var(--color-brand);
}

.form-input,
.form-select,
.form-textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
  font-family: inherit;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.form-textarea {
  resize: vertical;
  min-height: 100px;
}

/* Radio and Checkbox Groups */
.radio-group,
.checkbox-group {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.radio-option,
.checkbox-option {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all 0.3s ease;
}

.radio-option:hover,
.checkbox-option:hover {
  border-color: var(--color-brand);
  background: var(--color-surface-soft);
}

.radio-option.selected,
.checkbox-option.selected {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
}

.radio-option input[type="radio"],
.checkbox-option input[type="checkbox"] {
  width: 20px;
  height: 20px;
  accent-color: var(--color-brand);
  cursor: pointer;
}

.radio-option label,
.checkbox-option label {
  flex: 1;
  cursor: pointer;
  font-size: 14px;
  color: var(--color-ink);
}

/* Navigation Buttons */
.form-navigation {
  display: flex;
  justify-content: space-between;
  gap: 15px;
  margin-top: 40px;
}

.btn {
  padding: 14px 32px;
  border-radius: var(--radius-md);
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 10px;
}

.btn-secondary {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-secondary:hover {
  background: var(--color-border-strong);
}

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 4px 15px rgba(var(--color-brand-rgb), 0.4);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(var(--color-brand-rgb), 0.5);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none !important;
}

@media (max-width: 768px) {
  .kyc-verification-wrapper {
    padding: 20px 15px;
  }

  .kyc-form-card {
    padding: 25px 20px;
  }

  .form-navigation {
    flex-direction: column;
  }

  .btn {
    width: 100%;
    justify-content: center;
  }
}
</style>
