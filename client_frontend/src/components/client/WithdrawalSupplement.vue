<template>
  <div class="withdrawal-supplement-wrapper">
    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <i class="fas fa-spinner fa-spin"></i>
      <p>{{ t("wdLoadDocRequest", "Loading document request...") }}</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <i class="fas fa-exclamation-circle"></i>
      <p>{{ error }}</p>
      <button class="btn btn-primary" @click="loadDocumentRequest">
        <i class="fas fa-refresh"></i> {{ t("wdRetry", "Retry") }}
      </button>
    </div>

    <!-- Success State -->
    <div v-else-if="submitted" class="success-state">
      <div class="success-icon">
        <i class="fas fa-check-circle"></i>
      </div>
      <h2>
        {{ t("wdDocsSubmittedSuccess", "Documents Submitted Successfully") }}
      </h2>
      <p>
        {{
          t(
            "wdDocsSubmittedMsg",
            "Your additional information has been submitted. We will review it and get back to you soon.",
          )
        }}
      </p>
      <button class="btn btn-primary" @click="handleClose">
        <i class="fas fa-check"></i> {{ t("close", "Close") }}
      </button>
    </div>

    <!-- Form -->
    <div v-else-if="documentRequest" class="supplement-form-container">
      <div class="resubmit-header">
        <div class="resubmit-title-row">
          <div class="resubmit-icon">
            <i class="fas fa-file-upload"></i>
          </div>
          <h2>
            {{
              t(
                "wdAdditionalDocsRequired",
                "Additional Documents Required for Withdrawal",
              )
            }}
          </h2>
        </div>
        <p class="resubmit-description">
          {{
            t(
              "wdAdditionalInfoDesc",
              "We need some additional information to process your withdrawal request. Please provide the requested items below.",
            )
          }}
        </p>
        <div v-if="documentRequest.adminInstructions" class="resubmit-notes">
          <i class="fas fa-sticky-note"></i>
          <p>{{ documentRequest.adminInstructions }}</p>
        </div>
      </div>

      <div class="kyc-form-card">
        <form @submit.prevent="handleSubmit">
          <!-- Requested Items -->
          <div class="resubmit-items-section">
            <h3 class="section-title">
              <i class="fas fa-clipboard-list"></i>
              {{ t("wdRequestedItems", "Requested Items") }}
            </h3>

            <div
              v-for="item in documentRequest.items"
              :key="item.id"
              class="resubmit-item"
            >
              <!-- Question Item -->
              <div
                v-if="item.itemType === 'question'"
                class="resubmit-question-item"
              >
                <div class="item-header">
                  <i class="fas fa-question-circle"></i>
                  <label class="item-label">
                    {{ item.questionText }}
                    <span v-if="item.isRequired" class="required-indicator"
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
                  v-model="answers[item.id].answerText"
                  :required="item.isRequired"
                  :placeholder="
                    item.questionHelpText ||
                    t('wdEnterAnswer', 'Enter your answer')
                  "
                />

                <!-- Number Input -->
                <input
                  v-else-if="item.questionType === 'number'"
                  type="number"
                  class="form-input"
                  v-model="answers[item.id].answerText"
                  :required="item.isRequired"
                  :placeholder="
                    item.questionHelpText ||
                    t('wdEnterNumber', 'Enter a number')
                  "
                />

                <!-- Date Input -->
                <input
                  v-else-if="item.questionType === 'date'"
                  type="date"
                  class="form-input"
                  v-model="answers[item.id].answerText"
                  :required="item.isRequired"
                />

                <!-- Single Choice -->
                <div
                  v-else-if="
                    item.questionType === 'single_choice' &&
                    item.questionOptions &&
                    item.questionOptions.length > 0
                  "
                  class="radio-group"
                >
                  <div
                    v-for="(option, optIndex) in item.questionOptions"
                    :key="optIndex"
                    class="radio-option"
                    :class="{
                      selected: answers[item.id].answerText === option,
                    }"
                    @click="selectRadio(item.id, option)"
                  >
                    <input
                      type="radio"
                      :name="`question-${item.id}`"
                      :value="option"
                      v-model="answers[item.id].answerText"
                      :required="item.isRequired"
                    />
                    <label>{{ option }}</label>
                  </div>
                </div>

                <!-- Multiple Choice -->
                <div
                  v-else-if="
                    item.questionType === 'multiple_choice' &&
                    item.questionOptions &&
                    item.questionOptions.length > 0
                  "
                  class="checkbox-group"
                >
                  <div
                    v-for="(option, optIndex) in item.questionOptions"
                    :key="optIndex"
                    class="checkbox-option"
                    :class="{ selected: isCheckboxSelected(item.id, option) }"
                    @click="toggleCheckbox(item.id, option)"
                  >
                    <input
                      type="checkbox"
                      :value="option"
                      v-model="answers[item.id].answerValues"
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
                    :class="{ selected: answers[item.id].answerText === 'Yes' }"
                    @click="selectRadio(item.id, 'Yes')"
                  >
                    <input
                      type="radio"
                      :name="`question-${item.id}`"
                      value="Yes"
                      v-model="answers[item.id].answerText"
                      :required="item.isRequired"
                    />
                    <label>{{ t("wdYes", "Yes") }}</label>
                  </div>
                  <div
                    class="radio-option"
                    :class="{ selected: answers[item.id].answerText === 'No' }"
                    @click="selectRadio(item.id, 'No')"
                  >
                    <input
                      type="radio"
                      :name="`question-${item.id}`"
                      value="No"
                      v-model="answers[item.id].answerText"
                      :required="item.isRequired"
                    />
                    <label>{{ t("wdNo", "No") }}</label>
                  </div>
                </div>

                <!-- File Upload -->
                <div v-else-if="item.questionType === 'file_upload'">
                  <WithdrawalFileUpload
                    :item-id="item.id"
                    :withdrawal-id="props.withdrawalId"
                    :required="item.isRequired"
                    :accepted-file-types="item.acceptedFileTypes || []"
                    :document-types="getDocumentTypesForItem(item)"
                    :existing-files="getExistingFiles(item)"
                    @files-changed="
                      (files) => handleFilesChanged(item.id, files)
                    "
                  />
                </div>

                <!-- Textarea -->
                <textarea
                  v-else-if="item.questionType === 'textarea'"
                  class="form-input"
                  v-model="answers[item.id].answerText"
                  :required="item.isRequired"
                  :placeholder="
                    item.questionHelpText ||
                    t('wdEnterAnswer', 'Enter your answer')
                  "
                  rows="4"
                ></textarea>

                <p
                  v-if="
                    item.questionHelpText &&
                    item.questionType !== 'file_upload' &&
                    item.questionType !== 'textarea'
                  "
                  class="help-text"
                >
                  <i class="fas fa-info-circle"></i>
                  {{ item.questionHelpText }}
                </p>
              </div>

              <!-- Document Item -->
              <div
                v-else-if="item.itemType === 'document'"
                class="resubmit-document-item"
              >
                <div class="item-header">
                  <i class="fas fa-file-alt"></i>
                  <label class="item-label">
                    {{ item.documentName }}
                    <span v-if="item.isRequired" class="required-indicator"
                      >*</span
                    >
                  </label>
                </div>
                <WithdrawalFileUpload
                  :item-id="item.id"
                  :withdrawal-id="props.withdrawalId"
                  :required="item.isRequired"
                  :accepted-file-types="item.acceptedFileTypes || []"
                  :document-types="getDocumentTypesForItem(item)"
                  :existing-files="getExistingFiles(item)"
                  @files-changed="(files) => handleFilesChanged(item.id, files)"
                />
                <p
                  v-if="item.documentDescription || item.questionHelpText"
                  class="help-text"
                >
                  <i class="fas fa-info-circle"></i>
                  {{ item.documentDescription || item.questionHelpText }}
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
              <i class="fas fa-times"></i> {{ t("wdCancel", "Cancel") }}
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
                  ? t("wdSubmitting", "Submitting...")
                  : t("wdSubmitAdditionalInfo", "Submit Additional Information")
              }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useLanguageStore } from "@/stores/language";
import withdrawalService from "@/services/withdrawalService";
import WithdrawalFileUpload from "./WithdrawalFileUpload.vue";

const languageStore = useLanguageStore();
const t = (key, fallback) => languageStore.t(key, fallback);

const props = defineProps({
  withdrawalId: {
    type: [String, Number],
    required: true,
  },
});

const emit = defineEmits(["submitted", "close"]);

const router = useRouter();

const loading = ref(true);
const error = ref(null);
const submitted = ref(false);
const submitting = ref(false);
const documentRequest = ref(null);
const answers = ref({});

// 加载文档请求
const loadDocumentRequest = async () => {
  // 验证withdrawalId
  if (!props.withdrawalId) {
    error.value = "Withdrawal ID is missing";
    loading.value = false;
    return;
  }

  // 检查是否有缓存的数据（从弹窗检查中获取）
  const cacheKey = `withdrawal_document_request_${props.withdrawalId}`;
  const cachedData = sessionStorage.getItem(cacheKey);

  // 如果缓存存在且有效（5分钟内），使用缓存数据，避免重复加载
  if (cachedData) {
    try {
      const cached = JSON.parse(cachedData);
      const cacheAge = Date.now() - cached.timestamp;
      const CACHE_VALIDITY = 5 * 60 * 1000; // 5分钟

      if (cacheAge < CACHE_VALIDITY && cached.data) {
        // 使用缓存数据
        documentRequest.value = cached.data;
        loading.value = false;

        // 初始化答案对象
        documentRequest.value.items.forEach((item) => {
          const existingResponse = item.clientResponse;
          let existingAnswer = {};

          if (existingResponse) {
            if (typeof existingResponse === "string") {
              try {
                existingAnswer = JSON.parse(existingResponse);
              } catch (e) {
                existingAnswer = { answerText: existingResponse };
              }
            } else if (typeof existingResponse === "object") {
              existingAnswer = existingResponse;
            }
          }

          answers.value[item.id] = {
            answerText: existingAnswer.answerText || "",
            answerValues: existingAnswer.answerValues || [],
            uploadedFiles:
              existingAnswer.uploadedFiles || existingAnswer.files || [],
          };
        });

        // 清除缓存（已使用）
        sessionStorage.removeItem(cacheKey);
        return;
      }
    } catch (e) {
      console.warn("Failed to parse cached data:", e);
      // 如果缓存解析失败，继续加载
    }
  }

  // 没有缓存或缓存过期，加载数据
  loading.value = true;
  error.value = null;

  try {
    const response = await withdrawalService.getDocumentRequest(
      props.withdrawalId,
    );

    if (!response.success || !response.data) {
      throw new Error(response.message || "Failed to load document request");
    }

    documentRequest.value = response.data;

    // 清除缓存（已加载新数据）
    sessionStorage.removeItem(cacheKey);

    // 初始化答案对象
    documentRequest.value.items.forEach((item) => {
      const existingResponse = item.clientResponse;
      let existingAnswer = {};

      if (existingResponse) {
        if (typeof existingResponse === "string") {
          try {
            existingAnswer = JSON.parse(existingResponse);
          } catch (e) {
            existingAnswer = { answerText: existingResponse };
          }
        } else if (typeof existingResponse === "object") {
          existingAnswer = existingResponse;
        }
      }

      answers.value[item.id] = {
        answerText: existingAnswer.answerText || "",
        answerValues: existingAnswer.answerValues || [],
        uploadedFiles:
          existingAnswer.uploadedFiles || existingAnswer.files || [],
      };
    });
  } catch (err) {
    console.error("Failed to load document request:", err);
    error.value =
      err.message || "Failed to load document request. Please try again.";
  } finally {
    loading.value = false;
  }
};

// 获取现有文件
const getExistingFiles = (item) => {
  const answer = answers.value[item.id];
  if (answer && answer.uploadedFiles && Array.isArray(answer.uploadedFiles)) {
    return answer.uploadedFiles;
  }
  // 兼容旧的数据结构
  if (answer && answer.files && Array.isArray(answer.files)) {
    return answer.files;
  }
  return [];
};

// 处理文件变化
const handleFilesChanged = (itemId, files) => {
  if (!answers.value[itemId]) {
    answers.value[itemId] = {
      answerText: "",
      answerValues: [],
      uploadedFiles: [],
    };
  }
  answers.value[itemId].uploadedFiles = files;
};

// 选择单选
const selectRadio = (itemId, value) => {
  if (!answers.value[itemId]) {
    answers.value[itemId] = {
      answerText: "",
      answerValues: [],
      uploadedFiles: [],
    };
  }
  answers.value[itemId].answerText = value;
};

// 切换复选框
const toggleCheckbox = (itemId, value) => {
  if (!answers.value[itemId]) {
    answers.value[itemId] = {
      answerText: "",
      answerValues: [],
      uploadedFiles: [],
    };
  }
  if (!Array.isArray(answers.value[itemId].answerValues)) {
    answers.value[itemId].answerValues = [];
  }
  const values = answers.value[itemId].answerValues;
  const index = values.indexOf(value);
  if (index > -1) {
    values.splice(index, 1);
  } else {
    values.push(value);
  }
};

// 检查复选框是否选中
const isCheckboxSelected = (itemId, value) => {
  const answer = answers.value[itemId];
  return answer && answer.answerValues && answer.answerValues.includes(value);
};

// 获取文件类型显示信息（参考KycVerification）
// 兼容两种方式：
// 1. Add Question + file_upload: 使用 acceptedFileTypes (数组)
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

  // 情况1: Add Question + file_upload (使用 acceptedFileTypes)
  if (item.itemType === "question" && item.questionType === "file_upload") {
    let acceptedFileTypes = item.acceptedFileTypes || [];

    // 如果是字符串，尝试解析
    if (typeof acceptedFileTypes === "string") {
      try {
        acceptedFileTypes = JSON.parse(acceptedFileTypes);
      } catch (e) {
        console.error("Failed to parse acceptedFileTypes:", e);
        acceptedFileTypes = [];
      }
    }

    // 确保是数组，并标准化每个元素
    if (Array.isArray(acceptedFileTypes)) {
      documentTypes = acceptedFileTypes.map((dt) => normalizeDocumentType(dt));
    }
  }
  // 情况2: Add Document (使用 documentType)
  else if (item.itemType === "document" && item.documentType) {
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

// 验证表单
const validateForm = () => {
  if (!documentRequest.value || !documentRequest.value.items) {
    return false;
  }

  for (const item of documentRequest.value.items) {
    if (item.isRequired) {
      const answer = answers.value[item.id];

      if (!answer) {
        alert(
          `Please provide an answer for: ${item.questionText || item.documentName}`,
        );
        return false;
      }

      if (item.itemType === "question") {
        if (item.questionType === "file_upload") {
          if (!answer.uploadedFiles || answer.uploadedFiles.length === 0) {
            // 兼容旧的数据结构
            if (!answer.files || answer.files.length === 0) {
              alert(`Please upload a file for: ${item.questionText}`);
              return false;
            }
          }
        } else if (item.questionType === "multiple_choice") {
          if (!answer.answerValues || answer.answerValues.length === 0) {
            alert(
              `Please select at least one option for: ${item.questionText}`,
            );
            return false;
          }
        } else {
          if (!answer.answerText || answer.answerText.trim() === "") {
            alert(`Please provide an answer for: ${item.questionText}`);
            return false;
          }
        }
      } else if (item.itemType === "document") {
        if (!answer.uploadedFiles || answer.uploadedFiles.length === 0) {
          // 兼容旧的数据结构
          if (!answer.files || answer.files.length === 0) {
            alert(`Please upload a file for: ${item.documentName}`);
            return false;
          }
        }
      }
    }
  }

  return true;
};

// 提交表单
const handleSubmit = async () => {
  if (!validateForm()) {
    return;
  }

  submitting.value = true;

  try {
    // 准备答案数据
    const answersData = {};

    for (const item of documentRequest.value.items) {
      const answer = answers.value[item.id];
      if (answer) {
        if (item.itemType === "question") {
          if (item.questionType === "file_upload") {
            answersData[item.id] = {
              files: answer.uploadedFiles || answer.files || [],
            };
          } else if (item.questionType === "multiple_choice") {
            answersData[item.id] = {
              answerValues: answer.answerValues || [],
            };
          } else {
            answersData[item.id] = {
              answerText: answer.answerText || "",
            };
          }
        } else if (item.itemType === "document") {
          answersData[item.id] = {
            files: answer.uploadedFiles || answer.files || [],
          };
        }
      }
    }

    const response = await withdrawalService.submitDocuments(
      props.withdrawalId,
      answersData,
    );

    if (!response.success) {
      throw new Error(response.message || "Failed to submit documents");
    }

    submitted.value = true;
    emit("submitted", response.data);
  } catch (err) {
    console.error("Failed to submit documents:", err);
    alert(err.message || "Failed to submit documents. Please try again.");
  } finally {
    submitting.value = false;
  }
};

// 关闭
const handleClose = () => {
  emit("close");
  router.push("/client/dashboard");
};

onMounted(() => {
  loadDocumentRequest();
});
</script>

<style scoped>
.withdrawal-supplement-wrapper {
  min-height: 100vh;
  background: var(--color-canvas);
  padding: 20px;
}

.loading-state,
.error-state,
.success-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
  text-align: center;
}

.loading-state i,
.error-state i {
  font-size: 48px;
  color: var(--color-brand);
  margin-bottom: 20px;
}

.error-state i {
  color: var(--color-danger);
}

.success-state {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 60px 40px;
  max-width: 600px;
  margin: 40px auto;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.success-icon {
  font-size: 64px;
  color: var(--color-success);
  margin-bottom: 20px;
}

.success-state h2 {
  color: var(--color-ink);
  margin-bottom: 16px;
}

.success-state p {
  color: var(--color-muted);
  margin-bottom: 30px;
}

.supplement-form-container {
  max-width: 900px;
  margin: 0 auto;
  padding: 20px 0;
}

/* Resubmit Header Styles (参考KycVerification) */
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

.kyc-form-card {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  padding: 40px;
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

.form-input,
.form-textarea {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.2s;
}

.form-input:focus,
.form-textarea:focus {
  border-color: var(--color-brand);
  box-shadow: none;
}

.form-textarea {
  resize: vertical;
  min-height: 100px;
}

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
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all 0.2s;
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

.radio-option input,
.checkbox-option input {
  cursor: pointer;
}

.radio-option label,
.checkbox-option label {
  cursor: pointer;
  margin: 0;
  flex: 1;
}

.resubmit-item .help-text {
  font-size: 14px;
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

.form-navigation {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 32px;
  padding-top: 24px;
  border-top: 1px solid var(--color-border);
}

.btn {
  padding: 12px 24px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  background: var(--color-surface);
  color: var(--color-muted);
  border: 1px solid var(--color-border);
}

.btn-secondary:hover {
  background: var(--color-surface-soft);
  border-color: var(--color-border-strong);
}
</style>
