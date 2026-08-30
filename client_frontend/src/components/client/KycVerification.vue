<template>
  <div
    class="kyc-verification-wrapper"
    :class="{ 'wrapper-third-party': isThirdPartyMode }"
  >
    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <i class="fas fa-spinner fa-spin"></i>
      <p>{{ t("loadingKycForm", "Loading KYC verification form...") }}</p>
    </div>

    <!-- Third-party KYC (Sumsub WebSDK，内部 iframe) -->
    <div v-else-if="isThirdPartyMode" class="third-party-kyc-container">
      <div v-if="externalLaunching" class="loading-state">
        <i class="fas fa-spinner fa-spin"></i>
        <p>{{ t("preparingVerification", "Preparing verification...") }}</p>
      </div>
      <div v-else-if="externalLaunchError" class="error-state">
        <i class="fas fa-exclamation-circle"></i>
        <p>{{ externalLaunchError }}</p>
      </div>
      <!-- Sumsub WebSDK 把 iframe 渲染到这个容器里 -->
      <div id="sumsub-websdk-container" class="sumsub-container"></div>
    </div>

    <!-- Resubmit Required Form -->
    <div
      v-else-if="isResubmitMode && resubmitRequest"
      class="kyc-form-container"
    >
      <div class="resubmit-header">
        <div class="resubmit-title-row">
          <div class="resubmit-icon">
            <i class="fas fa-file-upload"></i>
          </div>
          <h2>
            {{ t("additionalInfoRequired", "Additional Information Required") }}
          </h2>
        </div>
        <p class="resubmit-description">
          {{
            t(
              "additionalInfoDesc",
              "We need some additional information to complete your verification. Please provide the requested items below.",
            )
          }}
        </p>
        <div v-if="resubmitRequest.notes" class="resubmit-notes">
          <i class="fas fa-sticky-note"></i>
          <p>{{ resubmitRequest.notes }}</p>
        </div>
      </div>

      <div class="kyc-form-card">
        <form @submit.prevent="handleResubmitSubmit">
          <!-- Requested Items -->
          <div class="resubmit-items-section">
            <h3 class="section-title">
              <i class="fas fa-clipboard-list"></i>
              {{ t("requestedItems", "Requested Items") }}
            </h3>

            <div
              v-for="(item, index) in resubmitRequest.requestedItems"
              :key="index"
              class="resubmit-item"
            >
              <!-- Question Item -->
              <div
                v-if="item.type === 'question'"
                class="resubmit-question-item"
              >
                <div class="item-header">
                  <i class="fas fa-question-circle"></i>
                  <label class="item-label">
                    {{ item.name || item.title }}
                    <span
                      v-if="item.isRequired !== false"
                      class="required-indicator"
                      >*</span
                    >
                  </label>
                </div>

                <!-- Text Input -->
                <input
                  v-if="
                    item.questionType === 'text' ||
                    item.questionType === 'email' ||
                    item.questionType === 'tel'
                  "
                  :type="
                    item.questionType === 'email'
                      ? 'email'
                      : item.questionType === 'tel'
                        ? 'tel'
                        : 'text'
                  "
                  class="form-input"
                  v-model="resubmitAnswers[index].answerText"
                  :required="item.isRequired !== false"
                  :placeholder="item.helpText || 'Enter your answer'"
                />

                <!-- Number Input -->
                <input
                  v-else-if="item.questionType === 'number'"
                  type="number"
                  class="form-input"
                  v-model="resubmitAnswers[index].answerText"
                  :required="item.isRequired !== false"
                  :placeholder="item.helpText || 'Enter a number'"
                />

                <!-- Date Input -->
                <input
                  v-else-if="item.questionType === 'date'"
                  type="date"
                  class="form-input"
                  v-model="resubmitAnswers[index].answerText"
                  :required="item.isRequired !== false"
                />

                <!-- Single Choice -->
                <div
                  v-else-if="
                    item.questionType === 'single_choice' &&
                    item.options &&
                    item.options.length > 0
                  "
                  class="radio-group"
                >
                  <div
                    v-for="(option, optIndex) in item.options"
                    :key="optIndex"
                    class="radio-option"
                    :class="{
                      selected: resubmitAnswers[index].answerText === option,
                    }"
                    @click="selectResubmitRadio(index, option)"
                  >
                    <input
                      type="radio"
                      :name="`resubmit-${index}`"
                      :value="option"
                      v-model="resubmitAnswers[index].answerText"
                      :required="item.isRequired !== false"
                    />
                    <label>{{ option }}</label>
                  </div>
                </div>

                <!-- Multiple Choice -->
                <div
                  v-else-if="
                    item.questionType === 'multiple_choice' &&
                    item.options &&
                    item.options.length > 0
                  "
                  class="checkbox-group"
                >
                  <div
                    v-for="(option, optIndex) in item.options"
                    :key="optIndex"
                    class="checkbox-option"
                    :class="{
                      selected: isResubmitCheckboxSelected(index, option),
                    }"
                    @click="toggleResubmitCheckbox(index, option)"
                  >
                    <input
                      type="checkbox"
                      :value="option"
                      v-model="resubmitAnswers[index].answerValues"
                    />
                    <label>{{ option }}</label>
                  </div>
                </div>

                <!-- Yes/No -->
                <div
                  v-else-if="item.questionType === 'yes_no'"
                  class="radio-group"
                >
                  <div
                    class="radio-option"
                    :class="{
                      selected: resubmitAnswers[index].answerText === 'Yes',
                    }"
                    @click="selectResubmitRadio(index, 'Yes')"
                  >
                    <input
                      type="radio"
                      :name="`resubmit-${index}`"
                      value="Yes"
                      v-model="resubmitAnswers[index].answerText"
                      :required="item.isRequired !== false"
                    />
                    <label>{{ t("commonYes", "Yes") }}</label>
                  </div>
                  <div
                    class="radio-option"
                    :class="{
                      selected: resubmitAnswers[index].answerText === 'No',
                    }"
                    @click="selectResubmitRadio(index, 'No')"
                  >
                    <input
                      type="radio"
                      :name="`resubmit-${index}`"
                      value="No"
                      v-model="resubmitAnswers[index].answerText"
                      :required="item.isRequired !== false"
                    />
                    <label>{{ t("commonNo", "No") }}</label>
                  </div>
                </div>

                <!-- File Upload -->
                <div v-else-if="item.questionType === 'file_upload'">
                  <ResubmitFileUpload
                    :item-index="index"
                    :submission-id="submissionId"
                    :required="item.isRequired !== false"
                    :document-types="getDocumentTypesForItem(item)"
                    @files-changed="
                      (itemIndex, files) =>
                        handleResubmitFilesChanged(index, files)
                    "
                  />
                </div>

                <!-- Textarea -->
                <textarea
                  v-else-if="item.questionType === 'textarea'"
                  class="form-input"
                  v-model="resubmitAnswers[index].answerText"
                  :required="item.isRequired !== false"
                  :placeholder="item.helpText || 'Enter your answer'"
                  rows="4"
                ></textarea>

                <p
                  v-if="
                    item.helpText &&
                    item.questionType !== 'file_upload' &&
                    item.questionType !== 'textarea'
                  "
                  class="help-text"
                >
                  <i class="fas fa-info-circle"></i>
                  {{ item.helpText }}
                </p>
              </div>

              <!-- Document Item -->
              <div
                v-else-if="item.type === 'document'"
                class="resubmit-document-item"
              >
                <div class="item-header">
                  <i class="fas fa-file-alt"></i>
                  <label class="item-label">
                    {{ item.name || item.title }}
                    <span
                      v-if="item.isRequired !== false"
                      class="required-indicator"
                      >*</span
                    >
                  </label>
                </div>
                <ResubmitFileUpload
                  :item-index="index"
                  :submission-id="submissionId"
                  :required="item.isRequired !== false"
                  :document-types="getDocumentTypesForItem(item)"
                  @files-changed="
                    (itemIndex, files) =>
                      handleResubmitFilesChanged(index, files)
                  "
                />
                <p v-if="item.helpText" class="help-text">
                  <i class="fas fa-info-circle"></i>
                  {{ item.helpText }}
                </p>
              </div>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="form-navigation">
            <button
              type="button"
              class="btn btn-secondary"
              @click="handleClose"
            >
              <i class="fas fa-times"></i> {{ t("cancel", "Cancel") }}
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="submitting"
            >
              <i
                class="fas"
                :class="submitting ? 'fa-spinner fa-spin' : 'fa-paper-plane'"
              ></i>
              {{
                submitting
                  ? t("submitting", "Submitting...")
                  : t("submitAdditionalInfo", "Submit Additional Information")
              }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Main Form（问卷流程；第三方模板不进这个分支） -->
    <div
      v-else-if="template && !template.isThirdPartyEnabled"
      class="kyc-form-container"
    >
      <!-- Progress Section -->
      <div class="progress-section">
        <div class="progress-header">
          <div class="progress-title">
            {{ t("verificationProgress", "Verification Progress") }}
          </div>
          <div class="progress-steps">
            {{ t("step", "Step") }} {{ currentStep + 1 }} {{ t("of", "of") }}
            {{ categories.length }}
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
                question.options.length > 5
              "
              class="form-select"
              v-model="answers[question.id]"
              :required="question.isRequired"
            >
              <option value="">
                {{ t("selectAnOption", "Select an option") }}
              </option>
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
                <label>{{ t("commonYes", "Yes") }}</label>
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
                <label>{{ t("commonNo", "No") }}</label>
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

          <!-- Document Signature Section (显示在最后一步) -->
          <div
            v-if="
              isLastStep &&
              requireDocumentSignature &&
              templateDocuments.length > 0
            "
            class="document-signature-section"
          >
            <div class="document-checkbox-wrapper">
              <input
                type="checkbox"
                id="agree-all-documents"
                v-model="allDocumentsAgreed"
                @change="handleDocumentAgreementChange"
                required
              />
              <label for="agree-all-documents" class="document-agreement-label">
                {{ t("iHaveReadAndAgree", "I have read and agree to the") }}
                <span
                  v-for="(document, index) in templateDocuments"
                  :key="document.id"
                >
                  <a
                    href="#"
                    @click.prevent="openDocumentModal(document)"
                    class="document-link"
                  >
                    {{ document.documentTitle }}
                  </a>
                  <span v-if="index < templateDocuments.length - 2">{{
                    t("listSeparator", ", ")
                  }}</span>
                  <span v-else-if="index === templateDocuments.length - 2">{{
                    t("listLastConnector", " and ")
                  }}</span>
                </span>
              </label>
            </div>
            <div v-if="documentSaving" class="saving-indicator">
              <i class="fas fa-spinner fa-spin"></i>
              {{ t("saving", "Saving...") }}
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
              <i class="fas fa-arrow-left"></i> {{ t("previous", "Previous") }}
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
                  ? t("processing", "Processing...")
                  : isLastStep
                    ? t("submitApplication", "Submit Application")
                    : t("next", "Next")
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

    <!-- Reject Modal -->
    <div
      v-if="showRejectModal"
      class="modal-overlay"
      @click="handleRejectClose"
    >
      <div class="modal-content reject-modal" @click.stop>
        <div class="modal-header">
          <h3>Application Rejected</h3>
          <button class="close-btn" @click="handleRejectClose">&times;</button>
        </div>
        <div class="modal-body">
          <div class="reject-icon">
            <svg
              width="64"
              height="64"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <circle
                cx="12"
                cy="12"
                r="10"
                stroke="#ef4444"
                stroke-width="2"
              />
              <path d="m15 9-6 6" stroke="#ef4444" stroke-width="2" />
              <path d="m9 9 6 6" stroke="#ef4444" stroke-width="2" />
            </svg>
          </div>
          <p class="reject-message">{{ rejectMessage }}</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary" @click="handleRejectClose">
            Sure
          </button>
        </div>
      </div>
    </div>

    <!-- Document Viewer Modal -->
    <div
      v-if="showDocumentModal && currentDocument"
      class="modal-overlay"
      @click="closeDocumentModal"
    >
      <div class="modal-content legal-modal" @click.stop>
        <div class="modal-header">
          <h3>{{ currentDocument.documentTitle }}</h3>
          <button class="close-btn" @click="closeDocumentModal">&times;</button>
        </div>
        <div class="modal-body legal-body">
          <div
            class="legal-document-content"
            v-html="currentDocument.documentContent"
          ></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary" @click="closeDocumentModal">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from "vue";
import { useLanguageStore } from "@/stores/language";
import { clientKycService } from "@/services/clientKycService";
import { sendToFlutter } from "@/utils/flutterBridge";
import snsWebSdk from "@sumsub/websdk";
import FileUpload from "./FileUpload.vue";
import ResubmitFileUpload from "./ResubmitFileUpload.vue";
import SuccessModal from "./KycSuccessModal.vue";

const languageStore = useLanguageStore();
const t = (key, fallback = "") => languageStore.t(key, fallback);

const props = defineProps({
  initialSubmissionId: {
    type: [String, Number],
    default: null,
  },
});

const emit = defineEmits(["close", "submitted"]);

// 重新提交流程里 Cancel 按钮：等同于父级 Back，向上 emit close，由父组件决定如何关闭/返回
const handleClose = () => {
  emit("close");
};

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
const showRejectModal = ref(false);
const rejectMessage = ref("");
const stepHistory = ref([]); // 记录步骤跳转历史
const isFirstTimeResume = ref(false); // 标记是否是首次恢复incomplete状态
const isResubmitMode = ref(false); // 是否为重新提交模式
const resubmitRequest = ref(null); // 重新提交请求信息
const resubmitAnswers = ref([]); // 重新提交的答案
const resubmitFiles = ref({}); // 重新提交的文件
const templateDocuments = ref([]); // 模板文档列表
const documentSignatures = ref({}); // 文档签名状态（保留用于API）
const allDocumentsAgreed = ref(false); // 是否同意所有文档
const requireDocumentSignature = ref(false); // 是否需要文档签名
const showDocumentModal = ref(false); // 显示文档弹窗
const currentDocument = ref(null); // 当前查看的文档
const documentSaving = ref(false); // 文档签名保存中

// === 第三方 KYC (Sumsub WebSDK) ===
const isThirdPartyMode = ref(false);
const externalLaunching = ref(false);
const externalLaunchError = ref(null);
const sumsubSdkInstance = ref(null); // 持有 SDK 实例引用，组件卸载时清理
let pendingRedirectTimer = null; // 进 pending 后延迟跳 dashboard 的定时器

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

  // 初始化步骤历史记录
  stepHistory.value = [];
  isFirstTimeResume.value = false;
  isResubmitMode.value = false;
  isThirdPartyMode.value = false;

  try {
    if (props.initialSubmissionId) {
      await loadExistingSubmission(props.initialSubmissionId);
      maybeLaunchExternalKyc();
      return;
    }

    // 首先检查当前的KYC状态
    const statusResponse = await clientKycService.getClientStatus();

    if (statusResponse.success && statusResponse.data) {
      const status = statusResponse.data.submissionStatus;
      const submissionId = statusResponse.data.submissionId;
      // 后端给的 isThirdPartyEnabled 标记当前模板是不是第三方
      const isThirdPartyTemplate = !!statusResponse.data.isThirdPartyEnabled;

      // 第三方模板：不管 status 是 draft / incomplete / resubmit_required，
      // 都走"加载 submission → 启动 Sumsub SDK"这条路，不走问卷的 resubmit / incomplete 分支
      if (isThirdPartyTemplate && submissionId) {
        await loadExistingSubmission(submissionId);
        maybeLaunchExternalKyc();
        return;
      }

      // 以下是问卷模板的原有逻辑
      // 检查是否是resubmit_required状态
      if (status === "resubmit_required" && submissionId) {
        isResubmitMode.value = true;
        // loadResubmitRequest 会自己管理 loading 状态
        await loadResubmitRequest(submissionId);
        return;
      }

      // 检查是否有现有的incomplete submission
      if (status === "incomplete" && submissionId) {
        // 有现有的incomplete submission，加载它
        isFirstTimeResume.value = true;
        await loadExistingSubmission(submissionId);
        return;
      }
    }

    // 没有现有submission或状态不匹配，创建新的
    await createNewSubmission();
    maybeLaunchExternalKyc();
  } catch (err) {
    console.error("Failed to load template:", err);
    error.value =
      err.message || "Failed to load KYC verification form. Please try again.";
  } finally {
    loading.value = false;
  }
};

// 模板加载完之后判断要不要切到第三方流程
// 注意：不在这里立即设 isThirdPartyMode=true，要等 launchExternalKyc 拿到 canLaunch 再决定。
// 这样当 status 是 pending / approved / rejected 时，UI 走原有内置流程，不被第三方容器抢占。
const maybeLaunchExternalKyc = () => {
  if (template.value && template.value.isThirdPartyEnabled) {
    launchExternalKyc();
  }
};

const launchExternalKyc = async () => {
  externalLaunching.value = true;
  externalLaunchError.value = null;
  try {
    const res = await clientKycService.getExternalLaunch();
    if (!res.success || !res.data) {
      throw new Error(res.message || "Failed to get launch info");
    }

    // 当前 status 不能启动 SDK（pending / approved / rejected）→ 啥都不做，
    // 让 KycVerification 走它原本的 status-based 渲染（KycStatusCard 等已经处理过这些状态）
    if (res.data.canLaunch === false) {
      return;
    }

    if (!res.data.accessToken) {
      throw new Error("No access token returned");
    }

    // 到这里说明能启动，正式切到第三方容器 UI
    isThirdPartyMode.value = true;

    // 等 Vue 把 #sumsub-websdk-container 这个 div 渲染到 DOM 里，再调 SDK.launch()
    // 否则 SDK 会报 "Provide a valid selector for the iframe container"
    await nextTick();

    const { accessToken } = res.data;

    // Token 接近过期时 SDK 会调这个回调拿新的，回调必须返回 Promise<string>
    // 实现就是再请求一次后端的 external-launch（后端每次都现取，不缓存）
    const refreshAccessToken = async () => {
      const r = await clientKycService.getExternalLaunch();
      return r?.data?.accessToken || "";
    };

    const lang = languageStore.currentLanguage === "zh" ? "zh" : "en";

    sumsubSdkInstance.value = snsWebSdk
      .init(accessToken, refreshAccessToken)
      .withConf({ lang, theme: "light" })
      // adaptIframeHeight=false：让 iframe 撑满容器（容器自己是 viewport 高度），
      // 而不是跟着 Sumsub 内容自动缩高
      .withOptions({ addViewportTag: false, adaptIframeHeight: false })
      .on("idCheck.onApplicantStatusChanged", (payload) => {
        // 用户阶段状态变化（pending / onHold / approved / rejected 等）
        // 真正的状态同步靠后端 webhook，这里只打日志方便排查
        console.log("Sumsub onApplicantStatusChanged", payload);

        // 进入 pending（用户提交完、Sumsub 接手）→ 5 秒后回 dashboard
        // 用 timer 引用做防抖，避免后续多次 status change 重复排程
        if (
          payload &&
          payload.reviewStatus === "pending" &&
          !pendingRedirectTimer
        ) {
          // Sumsub 走到 pending 即视为本次 KYC 已经提交完成，通知 Flutter 宿主刷新
          sendToFlutter("kycSubmitted", true);
          pendingRedirectTimer = setTimeout(() => {
            pendingRedirectTimer = null;
            emit("submitted");
          }, 5000);
        }
      })
      .on("idCheck.onApplicantSubmitted", () => {
        // 用户在 Sumsub 那边点了提交按钮 —— 这个事件立即触发，
        // 但我们要等 onApplicantStatusChanged 里 reviewStatus 变成 'pending' 才排 5s 跳转，
        // 这里只打个日志就行，不要 emit('submitted')，否则会立刻跳走
        console.log("Sumsub onApplicantSubmitted");
      })
      .on("idCheck.onError", (error) => {
        console.error("Sumsub onError", error);
      })
      .build();

    sumsubSdkInstance.value.launch("#sumsub-websdk-container");
  } catch (err) {
    console.error("Failed to launch external KYC:", err);
    externalLaunchError.value = err?.message || "Failed to start verification";
  } finally {
    externalLaunching.value = false;
  }
};

// 组件卸载（路由切走 / 关闭页面）时清理 SDK + 定时器
onBeforeUnmount(() => {
  if (pendingRedirectTimer) {
    clearTimeout(pendingRedirectTimer);
    pendingRedirectTimer = null;
  }
  if (
    sumsubSdkInstance.value &&
    typeof sumsubSdkInstance.value.destroy === "function"
  ) {
    try {
      sumsubSdkInstance.value.destroy();
    } catch (e) {
      // 静默 —— destroy 在某些版本可能不存在
    }
  }
  sumsubSdkInstance.value = null;
});

// 加载重新提交请求
const loadResubmitRequest = async (submissionIdParam) => {
  // 注意：loading 状态已经在 loadTemplate 中设置为 true，这里不需要重复设置
  try {
    const response =
      await clientKycService.getResubmitRequest(submissionIdParam);

    if (!response.success || !response.data) {
      throw new Error("Failed to load resubmit request");
    }

    const data = response.data;

    // 设置 submissionId ref
    submissionId.value = submissionIdParam;

    // 处理 notes 字段（后端返回的是 additionalNotes）
    const notes = data.notes || data.additionalNotes || null;

    // 处理 requestedItems：如果后端返回的是items数组，需要转换
    let requestedItems = [];
    if (data.items && data.items.length > 0 && !data.requestedItems) {
      // 从 kycResubmitAnswers 表获取的 items
      requestedItems = data.items.map((item) => ({
        id: item.id || item.itemId,
        type: item.itemType || item.type,
        name: item.questionText || item.documentName || item.itemText || "",
        title: item.questionText || item.documentName || item.itemText || "",
        questionType: item.questionType || null,
        options: item.options
          ? typeof item.options === "string"
            ? JSON.parse(item.options)
            : item.options
          : [],
        fileDocumentTypes: item.fileDocumentTypes
          ? typeof item.fileDocumentTypes === "string"
            ? JSON.parse(item.fileDocumentTypes)
            : item.fileDocumentTypes
          : [],
        helpText: item.helpText || null,
        isRequired:
          item.isRequired !== 0 &&
          item.isRequired !== false &&
          item.isRequired !== null,
      }));
    } else if (data.requestedItems && Array.isArray(data.requestedItems)) {
      // 直接使用 requestedItems
      requestedItems = data.requestedItems;
    }

    // 验证 requestedItems
    if (!requestedItems || requestedItems.length === 0) {
      console.warn("No requested items found in resubmit request:", data);
      throw new Error("No requested items found. Please contact support.");
    }

    // 设置 resubmitRequest
    resubmitRequest.value = {
      ...data,
      notes: notes,
      requestedItems: requestedItems,
    };

    // 初始化答案数组
    resubmitAnswers.value = requestedItems.map((item, index) => {
      const answer = {
        itemId: item.id || index,
        type: item.type,
        answerText: "",
        answerValues: [],
        uploadedFiles: [],
      };

      // 如果是多选类型，初始化数组
      if (item.questionType === "multiple_choice") {
        answer.answerValues = [];
      }

      return answer;
    });

    console.log("Resubmit request loaded successfully:", {
      submissionId: submissionIdParam,
      requestedItemsCount: requestedItems.length,
      answersCount: resubmitAnswers.value.length,
      hasNotes: !!notes,
      requestedItems: requestedItems,
    });
  } catch (err) {
    console.error("Failed to load resubmit request:", err);
    error.value =
      err.message || "Failed to load resubmit request. Please try again.";
  } finally {
    loading.value = false;
  }
};

const loadExistingSubmission = async (existingSubmissionId) => {
  // 获取现有submission的详情
  const submissionResponse =
    await clientKycService.getSubmissionDetails(existingSubmissionId);

  if (!submissionResponse.success) {
    throw new Error("Failed to load existing submission");
  }

  const submission = submissionResponse.data;
  submissionId.value = existingSubmissionId;

  // 获取模板详情
  const detailsResponse = await clientKycService.getTemplateDetails(
    submission.templateId,
  );

  if (!detailsResponse.success) {
    throw new Error("Failed to load KYC template details");
  }

  // 后端返回的数据结构是 { template: {...}, categories: [...], questions: [...] }
  template.value = detailsResponse.data.template || detailsResponse.data;
  categories.value = detailsResponse.data.categories || [];
  allQuestions.value = detailsResponse.data.questions || [];

  // 从 template 对象中获取文档和签名要求
  const templateData = detailsResponse.data.template || detailsResponse.data;
  templateDocuments.value = templateData.documents || [];
  requireDocumentSignature.value = !!templateData.requireDocumentSignature;

  // 初始化答案对象
  initializeAnswers();

  // 加载已保存的答案
  if (submission.answers) {
    loadSavedAnswers(submission.answers);
  }

  // 加载已签名的文档
  if (requireDocumentSignature.value) {
    await loadSignedDocuments();
  }

  // 设置当前步骤为第一个未完成的步骤（智能定位）
  await setCurrentStepFromProgress();
};

const createNewSubmission = async () => {
  // 获取适用的模板
  const templateResponse = await clientKycService.getAvailableTemplate();

  if (!templateResponse.success || !templateResponse.data) {
    throw new Error("No KYC template available for your account");
  }

  const templateId = templateResponse.data.id;

  // 获取模板详情（包含所有问题）
  const detailsResponse = await clientKycService.getTemplateDetails(templateId);

  if (!detailsResponse.success) {
    throw new Error("Failed to load KYC template details");
  }

  // 后端返回的数据结构是 { template: {...}, categories: [...], questions: [...] }
  template.value = detailsResponse.data.template || detailsResponse.data;
  categories.value = detailsResponse.data.categories || [];
  allQuestions.value = detailsResponse.data.questions || [];

  // 从 template 对象中获取文档和签名要求
  const templateData = detailsResponse.data.template || detailsResponse.data;
  templateDocuments.value = templateData.documents || [];
  requireDocumentSignature.value = !!templateData.requireDocumentSignature;

  // 初始化答案对象
  initializeAnswers();

  // 创建提交记录
  await createSubmission(templateId);
};

const loadSavedAnswers = (savedAnswers) => {
  // 将保存的答案加载到answers对象中
  Object.keys(savedAnswers).forEach((questionId) => {
    const answer = savedAnswers[questionId];

    if (answer && answer.answer !== undefined) {
      // 根据问题类型处理答案格式
      if (answer.questionType === "multiple_choice") {
        answers.value[questionId] = Array.isArray(answer.answer)
          ? answer.answer
          : [];
      } else if (answer.questionType === "date") {
        // 确保日期格式正确 (YYYY-MM-DD)
        const dateValue = answer.answer;
        if (dateValue) {
          // 如果是完整的日期时间格式，只取日期部分
          answers.value[questionId] = dateValue.split(" ")[0];
        } else {
          answers.value[questionId] = "";
        }
      } else {
        answers.value[questionId] = answer.answer;
      }
    }
  });
};

const setCurrentStepFromProgress = async () => {
  try {
    let targetStep = 0;

    // 只有在首次恢复时才跳转到未完成步骤
    if (isFirstTimeResume.value) {
      // 使用后端API获取下一个应该回答的问题
      if (submissionId.value) {
        const nextQuestionResponse = await clientKycService.getNextQuestion(
          submissionId.value,
        );
        if (nextQuestionResponse.success) {
          if (nextQuestionResponse.data.nextQuestion) {
            const nextQuestion = nextQuestionResponse.data.nextQuestion;
            // 直接使用后端返回的 stepIndex
            if (
              nextQuestion.stepIndex !== undefined &&
              nextQuestion.stepIndex >= 0 &&
              nextQuestion.stepIndex < categories.value.length
            ) {
              targetStep = nextQuestion.stepIndex;
            }
          } else {
            // 如果没有下一个问题，可能所有问题都已完成，跳转到最后一步
            if (categories.value.length > 0) {
              targetStep = categories.value.length - 1;
            }
          }
        } else {
          console.error("API call failed:", nextQuestionResponse);
          // API调用失败，使用本地逻辑
          const nextStep = await findNextIncompleteStepFromBackend();
          targetStep = nextStep !== -1 ? nextStep : 0;
        }
      } else {
        // 没有submissionId，使用本地逻辑
        const nextStep = await findNextIncompleteStepFromBackend();
        targetStep = nextStep !== -1 ? nextStep : 0;
      }

      // 重建跳转历史
      await rebuildStepHistory(targetStep);

      // 首次恢复完成，重置标记
      isFirstTimeResume.value = false;
    } else {
      // 非首次恢复，从第一步开始
      targetStep = 0;
    }

    // 设置当前步骤
    currentStep.value = targetStep;
  } catch (error) {
    console.error("Error getting next question:", error);
    // 出错时从第一步开始
    currentStep.value = 0;
  }
};

const findNextIncompleteStepFromBackend = async () => {
  try {
    if (!submissionId.value) {
      // 没有submissionId，说明是新的submission，从第一步开始
      return 0;
    }

    // 获取提交的进度信息
    const progressResponse = await clientKycService.getSubmissionProgress(
      submissionId.value,
    );
    if (progressResponse.success && progressResponse.data) {
      const progress = progressResponse.data;

      // 如果后端返回了下一个步骤信息
      if (progress.nextStepIndex !== undefined && progress.nextStepIndex >= 0) {
        // console.log(`后端智能定位：跳转到步骤 ${progress.nextStepIndex + 1}`)
        return progress.nextStepIndex;
      }

      // 如果后端返回了已完成的步骤列表，找到第一个未完成的
      if (progress.completedSteps && Array.isArray(progress.completedSteps)) {
        for (let i = 0; i < categories.value.length; i++) {
          if (!progress.completedSteps.includes(i)) {
            // console.log(`根据完成步骤列表定位：跳转到步骤 ${i + 1}`)
            return i;
          }
        }
      }
    }
  } catch (error) {
    console.error("Error getting submission progress:", error);
  }

  // 如果后端API失败，使用本地逻辑
  return findNextIncompleteStep(0);
};

const findNextIncompleteStep = (startIndex = 0) => {
  // 从指定索引开始，找到第一个有未回答必填问题的步骤
  for (
    let stepIndex = startIndex;
    stepIndex < categories.value.length;
    stepIndex++
  ) {
    const category = categories.value[stepIndex];
    const stepQuestions = allQuestions.value.filter(
      (q) => q.categoryId === category.id,
    );

    // 检查这个步骤是否有未回答的必填问题
    const hasUnansweredRequired = stepQuestions.some((question) => {
      if (!question.isRequired) return false;

      const answer = answers.value[question.id];
      if (answer === "" || answer === null || answer === undefined) return true;
      if (
        question.questionType === "multiple_choice" &&
        Array.isArray(answer) &&
        answer.length === 0
      )
        return true;

      return false;
    });

    if (hasUnansweredRequired) {
      return stepIndex;
    }
  }

  return -1; // 所有步骤都完成了
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
      submissionId.value = response.data.submissionId || response.data.id;
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
      // Check file upload questions separately
      if (question.questionType === "file_upload") {
        const files = uploadedFiles.value[question.id];
        if (!files || files.length === 0) {
          alert(
            `Please upload at least one document for: ${question.questionText}`,
          );
          return false;
        }
      } else {
        // Check answer for non-file-upload questions
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

    // Update submission status to 'incomplete' on first save
    // This will be handled by the backend when answers are saved
  } catch (err) {
    console.error("Failed to save answers:", err);
    // Continue anyway - answers are stored locally
  }
};

const handleNext = async () => {
  if (!validateCurrentStep()) {
    return;
  }

  // 用户开始正常答题，重置首次恢复标记
  isFirstTimeResume.value = false;
  // Save current step answers
  await saveCurrentStepAnswers();

  // 评估规则，看是否会被拒绝或需要跳转
  // 现在规则评估只会基于当前步骤及之前步骤的答案
  const ruleResult = await evaluateRulesAndGetNextStep();

  // 如果被拒绝，直接返回，不进行跳转操作
  if (ruleResult === "rejected") {
    return;
  }

  if (isLastStep.value) {
    // Submit the application
    await submitApplication();
  } else if (
    ruleResult !== null &&
    typeof ruleResult === "number" &&
    ruleResult !== currentStep.value + 1
  ) {
    // 有规则跳转，跳转到指定步骤
    // 记录跳转历史：当前步骤 -> 目标步骤
    stepHistory.value.push({
      from: currentStep.value,
      to: ruleResult,
      type: "rule_jump",
    });
    currentStep.value = ruleResult;
    window.scrollTo({ top: 0, behavior: "smooth" });
  } else {
    // 正常进入下一步
    // 记录正常跳转历史
    stepHistory.value.push({
      from: currentStep.value,
      to: currentStep.value + 1,
      type: "normal",
    });

    currentStep.value++;
    window.scrollTo({ top: 0, behavior: "smooth" });
  }
};

const handlePrevious = () => {
  if (currentStep.value > 0) {
    // 用户开始正常答题，重置首次恢复标记
    isFirstTimeResume.value = false;
    // 查找最后一次跳转到当前步骤的历史记录
    const lastJumpToCurrentStep = findLastJumpToCurrentStep();

    if (lastJumpToCurrentStep && lastJumpToCurrentStep.type === "rule_jump") {
      // 如果找到规则跳转历史，返回到跳转前的步骤
      currentStep.value = lastJumpToCurrentStep.from;

      // 移除这次跳转记录，因为我们已经返回了
      removeHistoryAfterStep(lastJumpToCurrentStep.from);
    } else {
      // 没有规则跳转历史，正常返回上一步
      currentStep.value--;

      // 移除最后一条历史记录（如果是正常跳转记录）
      if (
        stepHistory.value.length > 0 &&
        stepHistory.value[stepHistory.value.length - 1].type === "normal"
      ) {
        stepHistory.value.pop();
      }
    }

    window.scrollTo({ top: 0, behavior: "smooth" });
  }
};

const loadSignedDocuments = async () => {
  try {
    if (!submissionId.value) return;

    const response = await clientKycService.getSubmissionDetails(
      submissionId.value,
    );
    if (response.success && response.data.signedDocuments) {
      // 如果所有文档都已签名，则勾选总复选框
      const signedCount = response.data.signedDocuments.length;
      if (signedCount > 0 && signedCount === templateDocuments.value.length) {
        allDocumentsAgreed.value = true;
      }
    }
  } catch (err) {
    console.error("Failed to load signed documents:", err);
  }
};

// 处理文档同意状态变化 - 立即保存到数据库
const handleDocumentAgreementChange = async () => {
  if (!submissionId.value) {
    console.error("No submission ID available");
    allDocumentsAgreed.value = false;
    return;
  }

  documentSaving.value = true;

  try {
    if (allDocumentsAgreed.value) {
      // 勾选 - 保存所有文档签名到数据库
      const documentIds = templateDocuments.value.map((doc) => doc.id);
      const response = await clientKycService.signDocuments(
        submissionId.value,
        documentIds,
      );

      if (!response.success) {
        // 保存失败，恢复状态
        allDocumentsAgreed.value = false;
        alert(
          `Failed to save document agreements: ${response.message || "Please try again"}`,
        );
      } else {
        console.log("✓ Document signatures saved to database");
      }
    } else {
      // 取消勾选 - 从数据库删除所有文档签名
      const response = await clientKycService.signDocuments(
        submissionId.value,
        [],
      );

      if (!response.success) {
        // 删除失败，恢复状态
        allDocumentsAgreed.value = true;
        alert(
          `Failed to remove document agreements: ${response.message || "Please try again"}`,
        );
      } else {
        console.log("✓ Document signatures removed from database");
      }
    }
  } catch (err) {
    console.error("Failed to save document signatures:", err);
    // 恢复状态
    allDocumentsAgreed.value = !allDocumentsAgreed.value;
    alert(
      "Network error. Failed to save document agreements. Please check your connection and try again.",
    );
  } finally {
    documentSaving.value = false;
  }
};

const openDocumentModal = (document) => {
  currentDocument.value = document;
  showDocumentModal.value = true;
};

const closeDocumentModal = () => {
  showDocumentModal.value = false;
  currentDocument.value = null;
};

const validateDocumentSignatures = () => {
  if (!requireDocumentSignature.value || templateDocuments.value.length === 0) {
    return true;
  }

  if (!allDocumentsAgreed.value) {
    alert("Please agree to all required documents before submitting.");
    return false;
  }

  return true;
};

const submitApplication = async () => {
  // 验证文档签名
  if (!validateDocumentSignatures()) {
    return;
  }

  submitting.value = true;

  try {
    // 文档签名已经在 checkbox change 时实时保存，这里不需要再次保存
    // 只需要验证是否已签名即可（上面的 validateDocumentSignatures 已经做了）

    // Final save of all answers
    // await saveCurrentStepAnswers()

    // Submit the application
    const response = await clientKycService.submitApplication(
      submissionId.value,
    );

    if (response.success) {
      showSuccessModal.value = true;
      sendToFlutter("kycSubmitted", true);
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

const showRejectModalWithMessage = (message) => {
  rejectMessage.value = message;
  showRejectModal.value = true;
};

const handleRejectClose = () => {
  showRejectModal.value = false;
};

// 处理重新提交的文件变化
const handleResubmitFilesChanged = (itemIndex, files) => {
  // itemIndex 是数字索引
  const index = typeof itemIndex === "number" ? itemIndex : parseInt(itemIndex);

  // 确保 files 是数组
  if (!Array.isArray(files)) {
    console.error("handleResubmitFilesChanged: files is not an array", files);
    return;
  }

  if (!resubmitAnswers.value[index]) {
    resubmitAnswers.value[index] = {
      itemId: index,
      type: "document",
      answerText: null,
      answerValues: null,
      uploadedFiles: [],
    };
  }

  // 安全地映射文件数组
  resubmitAnswers.value[index].uploadedFiles = files.map((f) => ({
    fileName: f.filename || f.name || f.fileName,
    filePath: f.url || f.path || f.filePath,
    fileSize: f.size || f.fileSize,
  }));

  // console.log('Resubmit files changed:', {
  //   index,
  //   filesCount: files.length,
  //   files: files,
  //   uploadedFiles: resubmitAnswers.value[index].uploadedFiles
  // })
};

// 处理重新提交的单选
const selectResubmitRadio = (index, value) => {
  if (!resubmitAnswers.value[index]) {
    resubmitAnswers.value[index] = {
      itemId: index,
      type: "question",
      answerText: "",
      answerValues: [],
      uploadedFiles: [],
    };
  }
  resubmitAnswers.value[index].answerText = value;
};

// 处理重新提交的复选框
const toggleResubmitCheckbox = (index, option) => {
  if (!resubmitAnswers.value[index]) {
    resubmitAnswers.value[index] = {
      itemId: index,
      type: "question",
      answerText: "",
      answerValues: [],
      uploadedFiles: [],
    };
  }
  if (!Array.isArray(resubmitAnswers.value[index].answerValues)) {
    resubmitAnswers.value[index].answerValues = [];
  }
  const idx = resubmitAnswers.value[index].answerValues.indexOf(option);
  if (idx > -1) {
    resubmitAnswers.value[index].answerValues.splice(idx, 1);
  } else {
    resubmitAnswers.value[index].answerValues.push(option);
  }
};

// 获取文件类型显示信息
// 兼容两种方式：
// 1. Add Question + file_upload: 使用 fileDocumentTypes (数组)
// 2. Add Document: 使用 documentType (单个值)
const getDocumentTypesForItem = (item) => {
  if (!item) return [];

  // 文档类型映射表（从后台选择的值到显示名称）
  // 支持两种格式：ID_CARD (大写下划线) 和 id-card (小写连字符)
  const documentTypeMap = {
    // 大写下划线格式（Add Document 选择的格式）
    ID_CARD: "Identity Card",
    PASSPORT: "Passport",
    DRIVERS_LICENSE: "Driver's License",
    PROOF_ADDRESS: "Proof of Address",
    BANK_STATEMENT: "Bank Statement",
    UTILITY_BILL: "Utility Bill",
    INCOME_PROOF: "Income Verification",
    TAX_DOCUMENT: "Tax Document",
    EMPLOYMENT_LETTER: "Employment Letter",
    BUSINESS_REGISTRATION: "Business Registration",
    FINANCIAL_STATEMENT: "Financial Statement",
    OTHER: "Other Document",
    // 小写连字符格式（默认值，兼容处理）
    "id-card": "Identity Card",
    passport: "Passport",
    "drivers-license": "Driver's License",
    "proof-address": "Proof of Address",
    "bank-statement": "Bank Statement",
    "utility-bill": "Utility Bill",
    "income-proof": "Income Verification",
    "tax-document": "Tax Document",
    "employment-letter": "Employment Letter",
    "business-registration": "Business Registration",
    "financial-statement": "Financial Statement",
    other: "Other Document",
  };

  // 将小写连字符格式转换为大写下划线格式
  const normalizeDocumentType = (docType) => {
    if (!docType || typeof docType !== "string") return docType;
    // 如果已经是大写下划线格式，直接返回
    if (docType.includes("_") && docType === docType.toUpperCase()) {
      return docType;
    }
    // 如果是小写连字符格式，转换为大写下划线格式
    if (docType.includes("-")) {
      return docType.toUpperCase().replace(/-/g, "_");
    }
    // 其他情况直接返回大写格式
    return docType.toUpperCase();
  };

  let documentTypes = [];

  // 情况1: Add Question + file_upload (使用 fileDocumentTypes)
  if (item.type === "question" && item.questionType === "file_upload") {
    let fileDocumentTypes = item.fileDocumentTypes || [];

    // 如果是字符串，尝试解析
    if (typeof fileDocumentTypes === "string") {
      try {
        fileDocumentTypes = JSON.parse(fileDocumentTypes);
      } catch (e) {
        console.error("Failed to parse fileDocumentTypes:", e);
        fileDocumentTypes = [];
      }
    }

    // 确保是数组，并标准化每个元素
    if (Array.isArray(fileDocumentTypes)) {
      documentTypes = fileDocumentTypes.map((dt) => normalizeDocumentType(dt));
    }
  }
  // 情况2: Add Document (使用 documentType)
  else if (item.type === "document" && item.documentType) {
    // documentType 是单个值，转换为数组
    const docType = item.documentType;
    if (docType && typeof docType === "string") {
      // 标准化格式并转换为数组
      documentTypes = [normalizeDocumentType(docType)];
    } else if (Array.isArray(docType)) {
      // 如果是数组，标准化每个元素
      documentTypes = docType.map((dt) => normalizeDocumentType(dt));
    }
  }

  // 转换为组件需要的格式
  if (documentTypes.length > 0) {
    return documentTypes.map((docType) => {
      // docType 已经在之前标准化过了
      return {
        documentType: docType,
        documentDisplayName: documentTypeMap[docType] || docType,
      };
    });
  }

  return [];
};

const isResubmitCheckboxSelected = (index, option) => {
  if (
    !resubmitAnswers.value[index] ||
    !Array.isArray(resubmitAnswers.value[index].answerValues)
  ) {
    return false;
  }
  return resubmitAnswers.value[index].answerValues.includes(option);
};

// 验证重新提交的答案
const validateResubmitAnswers = () => {
  if (!resubmitRequest.value || !resubmitRequest.value.requestedItems) {
    return false;
  }

  for (let i = 0; i < resubmitRequest.value.requestedItems.length; i++) {
    const item = resubmitRequest.value.requestedItems[i];
    const answer = resubmitAnswers.value[i];

    // 检查必填项
    if (item.isRequired !== false) {
      if (item.type === "question") {
        if (item.questionType === "file_upload") {
          // 文件上传类型，检查是否有上传文件
          if (
            !answer ||
            !answer.uploadedFiles ||
            answer.uploadedFiles.length === 0
          ) {
            alert(`Please upload file for: ${item.name || item.title}`);
            return false;
          }
        } else if (item.questionType === "multiple_choice") {
          // 多选类型，检查是否至少选择了一个选项
          if (
            !answer ||
            !answer.answerValues ||
            answer.answerValues.length === 0
          ) {
            alert(
              `Please select at least one option for: ${item.name || item.title}`,
            );
            return false;
          }
        } else {
          // 其他类型，检查是否有文本答案
          if (
            !answer ||
            !answer.answerText ||
            answer.answerText.trim() === ""
          ) {
            alert(`Please answer: ${item.name || item.title}`);
            return false;
          }
        }
      } else if (item.type === "document") {
        // 文档类型，检查是否有上传文件
        if (
          !answer ||
          !answer.uploadedFiles ||
          answer.uploadedFiles.length === 0
        ) {
          alert(`Please upload document for: ${item.name || item.title}`);
          return false;
        }
      }
    }
  }

  return true;
};

// 提交重新提交的答案
const handleResubmitSubmit = async () => {
  if (!validateResubmitAnswers()) {
    return;
  }

  submitting.value = true;

  try {
    // 准备答案数据，格式与后端期望一致
    const answersData = resubmitRequest.value.requestedItems.map(
      (item, index) => {
        const answer = resubmitAnswers.value[index] || {};

        // 获取请求项的ID（从items数组中获取，如果没有则使用index）
        const requestItem =
          resubmitRequest.value.items && resubmitRequest.value.items[index];
        const itemId = requestItem ? requestItem.id : item.id || index;

        const answerData = {
          itemId: itemId,
          type: item.type,
          questionText:
            item.type === "question" ? item.name || item.title : null,
          questionType: item.type === "question" ? item.questionType : null,
          documentName:
            item.type === "document" ? item.name || item.title : null,
        };

        // 根据类型添加答案
        if (item.type === "question") {
          if (item.questionType === "file_upload") {
            answerData.uploadedFiles = answer.uploadedFiles || [];
          } else if (item.questionType === "multiple_choice") {
            answerData.answerValues = answer.answerValues || [];
          } else {
            answerData.answerText = answer.answerText || "";
          }
        } else if (item.type === "document") {
          answerData.uploadedFiles = answer.uploadedFiles || [];
        }

        return answerData;
      },
    );

    // 调用API提交答案
    const response = await clientKycService.submitResubmitAnswers(
      submissionId.value,
      answersData,
    );

    if (response.success) {
      // 和 submitApplication 保持一致：只开 modal，不立刻 emit('submitted')。
      // 否则外层 ClientKycVerification 会立即切到 KycRequiredNotice 把本组件卸载，modal 也跟着消失。
      // 真正的 emit('submitted') 由 handleSuccessClose 在 modal 关闭/倒计时结束时统一触发。
      showSuccessModal.value = true;
      sendToFlutter("kycResubmitSubmitted", true);
    } else {
      throw new Error(
        response.message || "Failed to submit additional information",
      );
    }
  } catch (err) {
    console.error("Failed to submit resubmit answers:", err);
    alert(
      err.message ||
        "Failed to submit additional information. Please try again.",
    );
  } finally {
    submitting.value = false;
  }
};

// 重建跳转历史函数
const rebuildStepHistory = async (currentTargetStep) => {
  try {
    stepHistory.value = [];

    if (!submissionId.value || currentTargetStep === 0) {
      return;
    }

    // 分析已完成的步骤，找出跳转路径
    const completedSteps = [];

    // 检查每个步骤是否有已保存的答案
    for (let stepIndex = 0; stepIndex < categories.value.length; stepIndex++) {
      const currentCategory = categories.value[stepIndex];
      if (!currentCategory) continue;

      const stepQuestions = allQuestions.value.filter(
        (q) => q.categoryId === currentCategory.id,
      );

      const hasAnswers = stepQuestions.some((question) => {
        const answer = answers.value[question.id];
        return (
          answer !== "" &&
          answer !== null &&
          answer !== undefined &&
          !(Array.isArray(answer) && answer.length === 0)
        );
      });

      if (hasAnswers) {
        completedSteps.push(stepIndex);
      }
    }
    // 重建跳转路径 - 基于步骤连续性分析
    if (completedSteps.length > 0) {
      let currentPos = 0;

      // 从第一个完成的步骤开始分析
      while (currentPos < completedSteps.length) {
        const currentStepIndex = completedSteps[currentPos];

        // 查找下一个目标步骤
        let nextStepIndex;
        if (currentPos < completedSteps.length - 1) {
          nextStepIndex = completedSteps[currentPos + 1];
        } else {
          // 最后一个完成步骤，检查是否跳转到当前目标步骤
          if (currentTargetStep > currentStepIndex) {
            nextStepIndex = currentTargetStep;
          } else {
            break;
          }
        }

        // 判断跳转类型
        if (nextStepIndex === currentStepIndex + 1) {
          // 连续步骤，正常跳转
          stepHistory.value.push({
            from: currentStepIndex,
            to: nextStepIndex,
            type: "normal",
          });
        } else if (nextStepIndex > currentStepIndex + 1) {
          // 跳过了中间步骤，规则跳转
          stepHistory.value.push({
            from: currentStepIndex,
            to: nextStepIndex,
            type: "rule_jump",
          });
        }

        currentPos++;
      }
    }
  } catch (error) {
    console.error("Error rebuilding step history:", error);
    stepHistory.value = [];
  }
};

// 历史记录辅助函数
const findLastJumpToCurrentStep = () => {
  // 从后往前查找最后一次跳转到当前步骤的记录
  for (let i = stepHistory.value.length - 1; i >= 0; i--) {
    const history = stepHistory.value[i];
    if (history.to === currentStep.value) {
      return history;
    }
  }
  return null;
};

const removeHistoryAfterStep = (stepIndex) => {
  // 移除指定步骤之后的所有历史记录
  stepHistory.value = stepHistory.value.filter(
    (history) => history.from <= stepIndex,
  );
};

// Lifecycle
const evaluateRulesAndGetNextStep = async () => {
  try {
    if (!submissionId.value) {
      return null;
    }

    const ruleResponse = await clientKycService.evaluateRules(
      submissionId.value,
    );
    if (
      ruleResponse.success &&
      ruleResponse.data.actions &&
      ruleResponse.data.actions.length > 0
    ) {
      // 检查是否有拒绝规则被触发（优先处理拒绝规则）
      const rejectActions = ruleResponse.data.actions.filter(
        (action) => action.ruleType === "reject",
      );
      if (rejectActions.length > 0) {
        const rejectAction = rejectActions[0];
        showRejectModalWithMessage(
          rejectAction.rejectMessage || "您的申请不符合要求，无法继续进行。",
        );
        return "rejected"; // 返回特殊值表示被拒绝
      }

      // 检查是否有跳转规则被触发
      const jumpActions = ruleResponse.data.actions.filter(
        (action) => action.ruleType === "jump_to",
      );
      if (jumpActions.length > 0) {
        // 取最后一个跳转规则（优先级最高）
        const lastJumpAction = jumpActions[jumpActions.length - 1];
        const targetQuestionId = lastJumpAction.targetQuestionId;
        // 找到目标问题所在的步骤
        const targetQuestion = allQuestions.value.find(
          (q) => q.id == targetQuestionId,
        ); // 使用 == 而不是 === 以防类型不匹配
        if (targetQuestion) {
          const targetStepIndex = categories.value.findIndex(
            (cat) => cat.id == targetQuestion.categoryId,
          ); // 使用 == 而不是 ===
          if (targetStepIndex !== -1 && isFirstTimeResume.value) {
            // console.log(`Rule jump: from step ${currentStep.value + 1} to step ${targetStepIndex + 1}`)
            return parseInt(targetStepIndex); // 确保返回数字类型
          } else {
            console.error(
              "Target category not found for question:",
              targetQuestion,
            );
            console.error("Available categories:", categories.value);
          }
        } else {
          console.error("Target question not found:", targetQuestionId);
          console.error(
            "Available questions:",
            allQuestions.value.map((q) => q.id),
          );
        }
      }
    }
  } catch (error) {
    console.error("Error evaluating rules:", error);
    // 规则评估失败时，不阻止用户继续，但记录错误
  }

  return null; // 没有规则跳转，继续正常流程
};

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

/* 第三方模式：撑满 main-content 宽高，去掉宽度限制和上下大 padding */
.kyc-verification-wrapper.wrapper-third-party {
  max-width: none;
  padding: 0;
  /* 撑满父级 (.main-content) 在视口内的可用高度。
   * 实测要再多留一点，避免末尾 1-2px 边框引发滚动。
   * 100vh - sticky header (~80) - main-content 上下 padding (40+40) - 余量 ≈ 200 */
  height: calc(100vh - 200px);
  display: flex;
  flex-direction: column;
}

.loading-state,
.error-state {
  text-align: center;
  padding: 60px 20px;
  background: white;
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

/*
 * 第三方 KYC 容器：在正常文档流里渲染（不用 position:fixed，会盖住 sidebar）。
 * 通过 wrapper 上的 .wrapper-third-party 修饰类让它撑满父容器，再用 flex 让 card 吃掉所有可用高度。
 */
.third-party-kyc-container {
  background: white;
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  padding: 16px;
  flex: 1; /* 吃掉 wrapper 内剩余高度 */
  min-height: 540px;
  display: flex;
  flex-direction: column;
}

.sumsub-container {
  flex: 1;
  min-height: 0;
}

/* 让 Sumsub 自己 build 出来的 iframe 撑满容器 */
.sumsub-container :deep(iframe) {
  width: 100% !important;
  height: 100% !important;
  border: none;
  display: block;
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
  background: var(--color-brand);
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
  background: white;
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
  background: var(--color-brand);
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
  font-size: 13px;
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
  background: var(--color-brand);
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
    padding: 20px;
  }

  .kyc-form-card {
    padding: 15px;
  }

  .form-navigation {
    flex-direction: column;
  }

  .btn {
    width: 100%;
    justify-content: center;
  }

  /* Resubmit 头部：移动端缩小图标和字号，避免标题被挤成多行 */
  .resubmit-header {
    padding: 12px 14px;
    margin-bottom: 12px;
  }

  .resubmit-title-row {
    gap: 8px;
    margin-bottom: 6px;
  }

  .resubmit-icon {
    font-size: 20px !important;
  }

  .resubmit-header h2 {
    font-size: 14px !important;
    line-height: 1.3;
  }

  .resubmit-description {
    font-size: 12px !important;
    line-height: 1.5;
    margin-bottom: 0 !important;
  }

  .resubmit-notes {
    padding: 8px 10px;
    gap: 8px;
  }

  .resubmit-notes i {
    font-size: 14px;
  }

  .resubmit-notes p {
    font-size: 12px;
    line-height: 1.5;
  }

  .resubmit-items-section {
    margin-bottom: 12px;
  }

  .resubmit-items-section .section-title {
    font-size: 13px;
    margin-bottom: 10px;
    gap: 8px;
  }

  .resubmit-item {
    padding: 12px;
    margin-bottom: 12px;
  }

  .resubmit-item .item-header {
    gap: 8px;
    margin-bottom: 8px;
  }

  .resubmit-item .item-header i {
    font-size: 10px;
  }

  .resubmit-item .item-label {
    font-size: 13px;
  }

  .resubmit-item .help-text {
    font-size: 12px;
  }

  .form-input,
  .form-textarea,
  .form-select {
    font-size: 13px;
    padding: 10px 12px;
  }

  /* 主 KYC 流程：进度条、Category 头、表单字段移动端缩放 */
  .progress-section {
    margin-bottom: 16px;
  }

  .progress-header {
    margin-bottom: 8px;
  }

  .progress-title {
    font-size: 14px;
  }

  .progress-steps {
    font-size: 12px;
  }

  .progress-bar-container {
    height: 8px;
  }

  .category-header {
    gap: 10px;
    margin-bottom: 16px;
    padding-bottom: 12px;
  }

  .category-icon {
    width: 36px;
    height: 36px;
    font-size: 16px;
    border-radius: var(--radius-md);
  }

  .category-info h2 {
    font-size: 16px;
    margin-bottom: 2px;
  }

  .category-info p {
    font-size: 12px;
    line-height: 1.4;
  }

  .form-group {
    margin-bottom: 16px;
  }

  .form-label {
    font-size: 13px;
    margin-bottom: 6px;
  }

  .required-indicator {
    font-size: 14px;
  }

  .form-help {
    font-size: 12px;
    line-height: 1.5;
  }
}

/* Reject Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: var(--radius-xl);
  max-width: 500px;
  width: 90%;
  animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.reject-modal .modal-header {
  padding: 24px 24px 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.reject-modal .modal-header h3 {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
  color: var(--color-danger);
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  color: #9ca3af;
  cursor: pointer;
  padding: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: all 0.2s;
}

.close-btn:hover {
  background: #f3f4f6;
  color: #6b7280;
}

.reject-modal .modal-body {
  padding: 24px;
  text-align: center;
}

.reject-icon {
  margin-bottom: 20px;
}

.reject-message {
  font-size: 16px;
  color: #374151;
  margin-bottom: 12px;
  line-height: 1.5;
}

.reject-note {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 0;
}

.reject-modal .modal-footer {
  padding: 0 24px 24px;
  text-align: center;
}

.reject-modal .btn {
  padding: 12px 24px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.reject-modal .btn-primary {
  background: #3b82f6;
  color: white;
}

.reject-modal .btn-primary:hover {
  background: #2563eb;
}

/* Resubmit Required Styles */
.resubmit-header {
  padding: 20px 24px;
  background: var(--color-warning-soft);
  border-radius: var(--radius-lg);
  margin-bottom: 30px;
}

.resubmit-title-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}

.resubmit-icon {
  font-size: 32px;
  color: var(--color-warning);
  flex-shrink: 0;
}

.resubmit-header h2 {
  font-size: 20px;
  font-weight: 600;
  color: var(--color-warning);
  margin: 0;
  flex: 1;
}

.resubmit-description {
  font-size: 14px;
  color: var(--color-warning);
  margin-bottom: 12px;
  line-height: 1.5;
}

.resubmit-notes {
  margin-top: 12px;
  padding: 12px 16px;
  background: rgba(255, 255, 255, 0.7);
  border-radius: var(--radius-md);
  display: flex;
  align-items: start;
  gap: 12px;
}

.resubmit-notes i {
  color: var(--color-warning);
  font-size: 20px;
  margin-top: 2px;
  flex-shrink: 0;
}

.resubmit-notes p {
  color: var(--color-warning);
  font-size: 14px;
  margin: 0;
  line-height: 1.6;
}

.resubmit-items-section {
  margin-bottom: 30px;
}

.resubmit-items-section .section-title {
  font-size: 18px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.resubmit-items-section .section-title i {
  color: var(--color-brand);
}

.resubmit-item {
  margin-bottom: 30px;
  padding: 20px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
}

.resubmit-question-item,
.resubmit-document-item {
  width: 100%;
}

.resubmit-item .item-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.resubmit-item .item-header i {
  font-size: 20px;
  color: var(--color-brand);
}

.resubmit-item .item-label {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 4px;
}

.resubmit-item .required-indicator {
  color: var(--color-danger);
  font-weight: 600;
}

.resubmit-item .help-text {
  font-size: 13px;
  color: var(--color-muted);
  margin-top: 8px;
  display: flex;
  align-items: start;
  gap: 6px;
}

.resubmit-item .help-text i {
  color: var(--color-brand);
  margin-top: 2px;
  flex-shrink: 0;
}

/* Document Signature Section */
.document-signature-section {
  margin-top: 40px;
  padding: 20px 25px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  border: 2px solid var(--color-border);
  position: relative;
}

.saving-indicator {
  margin-top: 10px;
  font-size: 13px;
  color: var(--color-brand);
  display: flex;
  align-items: center;
  gap: 8px;
}

.saving-indicator i {
  font-size: 14px;
}

.document-checkbox-wrapper {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.document-checkbox-wrapper input[type="checkbox"] {
  width: 20px;
  height: 20px;
  cursor: pointer;
  margin-top: 3px;
  flex-shrink: 0;
  accent-color: var(--color-brand);
}

.document-agreement-label {
  color: var(--color-ink);
  font-size: 14px;
  line-height: 1.7;
  cursor: pointer;
  margin: 0;
  flex: 1;
}

.document-link {
  color: var(--color-brand);
  text-decoration: underline;
  font-weight: 600;
  transition: color 0.2s ease;
}

.document-link:hover {
  color: var(--color-brand-strong);
}

/* Legal Document Modal */
.legal-modal {
  max-width: 900px;
  width: 90%;
}

.legal-modal .modal-header {
  background: var(--color-brand);
  color: white;
  padding: 25px 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-radius: 16px 16px 0 0;
}

.legal-modal .modal-header h3 {
  margin: 0;
  font-size: 22px;
  font-weight: 600;
  color: white;
}

.legal-modal .close-btn {
  color: white;
  opacity: 0.9;
}

.legal-modal .close-btn:hover {
  opacity: 1;
  background: rgba(255, 255, 255, 0.2);
}

.legal-body {
  padding: 30px 40px;
  max-height: calc(85vh - 180px);
  overflow-y: auto;
}

.legal-modal .modal-footer {
  padding: 20px 40px;
  border-top: 2px solid var(--color-border);
  text-align: center;
}

.legal-document-content {
  line-height: 1.8;
  color: var(--color-ink);
  font-size: 15px;
}

.legal-document-content :deep(h1) {
  font-size: 24px;
  color: var(--color-ink);
  margin-bottom: 20px;
  margin-top: 30px;
}

.legal-document-content :deep(h2) {
  font-size: 20px;
  color: var(--color-ink);
  margin-bottom: 15px;
  margin-top: 25px;
}

.legal-document-content :deep(h3) {
  font-size: 18px;
  color: var(--color-text);
  margin-bottom: 12px;
  margin-top: 20px;
}

.legal-document-content :deep(p) {
  margin-bottom: 15px;
  text-align: justify;
}

.legal-document-content :deep(ul),
.legal-document-content :deep(ol) {
  margin-left: 25px;
  margin-bottom: 15px;
}

.legal-document-content :deep(li) {
  margin-bottom: 8px;
}

.legal-document-content :deep(strong) {
  color: var(--color-ink);
  font-weight: 600;
}

.legal-document-content :deep(a) {
  color: var(--color-brand);
  text-decoration: underline;
}

.legal-document-content :deep(a):hover {
  color: var(--color-brand-strong);
}
</style>
