<template>
  <div class="kyc-submission-detail">
    <!-- Pending/Submitted状态：无审核员，需先配置审核员 -->
    <template
      v-if="
        submission.submissionStatus === 'pending' ||
        submission.submissionStatus === 'submitted'
      "
    >
      <div class="status-message pending-message">
        <div class="status-message-content">
          <i class="fas fa-exclamation-triangle"></i>
          <p class="status-message-title">
            {{ t("kycDetail_assignFirstTitle") }}
          </p>
          <p class="status-message-description">
            {{ t("kycDetail_assignFirstDesc") }}
          </p>
        </div>
      </div>
    </template>
    <!-- Under Review 且非当前审核员：仅提示 -->
    <template
      v-else-if="
        submission.submissionStatus === 'under_review' && !isCurrentUserReviewer
      "
    >
      <div class="status-message under-review-message">
        <div class="status-message-content">
          <i class="fas fa-id-card"></i>
          <p class="status-message-title">
            {{ t("kycDetail_underReviewTitle") }}
          </p>
          <p class="status-message-description">
            {{
              tParams(
                "kycDetail_underReviewDesc",
                "This submission is currently being reviewed by {name}",
                { name: reviewerDisplayName },
              )
            }}
          </p>
        </div>
      </div>
    </template>

    <!-- Under Review（当前审核员）或 Approved：完整问答/文件展示；Approved 为只读 -->
    <template v-else-if="showKycDetailContent">
      <div v-if="isReadOnlyDetail" class="approved-detail-meta">
        <div class="approved-detail-meta-icon" aria-hidden="true">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="approved-detail-meta-text">
          <div class="approved-detail-meta-heading">
            {{ t("kycDetail_approvedTitle") }}
          </div>
          <div class="approved-detail-meta-sub">
            {{
              tParams(
                "kycDetail_approvedDesc",
                "This application was approved on {date} by {name}",
                {
                  date: formatReviewDate(submission.reviewedAt),
                  name: reviewerDisplayName,
                },
              )
            }}
          </div>
        </div>
      </div>

      <div class="detail-sections">
        <div
          v-for="category in categorizedAnswers"
          :key="category.id"
          class="detail-section"
        >
          <h3>
            <div class="section-header">
              <div class="section-title">
                <i :class="getCategoryIcon(category.categoryName)"></i>
                {{ category.categoryName }}
              </div>
              <button
                v-if="hasApprovePermission && !isReadOnlyDetail"
                class="btn-approve-section"
                :class="{ approved: approvedSections.includes(category.id) }"
                @click="approveSection(category.id)"
              >
                <i
                  class="fas"
                  :class="
                    approvedSections.includes(category.id)
                      ? 'fa-check-circle'
                      : 'fa-check'
                  "
                ></i>
                {{
                  approvedSections.includes(category.id)
                    ? t("kycDetail_btnApproved")
                    : t("kycDetail_btnApprove")
                }}
              </button>
            </div>
          </h3>

          <div
            v-for="answer in category.answers"
            :key="answer.questionId"
            class="detail-field"
          >
            <span class="detail-label">{{ answer.questionText }}</span>
            <span
              class="detail-value font-floor-content"
              v-if="
                answer.questionType === 'file_upload' &&
                answer.files &&
                answer.files.length > 0
              "
            >
              <a
                v-for="(file, fileIdx) in answer.files"
                :key="fileIdx"
                :href="file.downloadUrl || getFileUrl(file.filePath || file)"
                target="_blank"
                class="file-download-link"
              >
                {{
                  file.fileName ||
                  basename(file.filePath || file.downloadUrl || "")
                }}
                <span v-if="fileIdx < answer.files.length - 1">, </span>
              </a>
            </span>
            <span
              v-else
              class="detail-value font-floor-content"
              v-html="formatAnswerValue(answer)"
            ></span>
          </div>
        </div>
      </div>

      <!-- 补充问题和文件详情 -->
      <div
        v-if="
          resubmitRequest &&
          resubmitRequest.answers &&
          resubmitRequest.answers.length > 0
        "
        class="resubmit-answers-section"
      >
        <div class="resubmit-answers-header">
          <i class="fas fa-file-upload"></i>
          <h3>{{ t("kycDetail_resubmitHeading") }}</h3>
        </div>
        <div class="resubmit-answers-content">
          <div
            v-for="(answer, index) in resubmitRequest.answers"
            :key="index"
            class="resubmit-answer-item"
          >
            <div class="resubmit-answer-header">
              <span class="resubmit-answer-label">
                <i
                  :class="
                    answer.itemType === 'question'
                      ? 'fas fa-question-circle'
                      : 'fas fa-file-alt'
                  "
                ></i>
                {{ answer.questionText || answer.documentName }}
              </span>
              <span
                v-if="answer.itemType === 'question' && answer.questionType"
                class="resubmit-answer-type"
              >
                {{ getQuestionTypeLabel(answer.questionType) }}
              </span>
            </div>
            <div class="resubmit-answer-value">
              <template v-if="answer.itemType === 'question'">
                <span v-if="answer.questionType === 'file_upload'">
                  <div
                    v-if="
                      answer.uploadedFiles && answer.uploadedFiles.length > 0
                    "
                    class="resubmit-file-list"
                  >
                    <div
                      v-for="(file, fileIndex) in answer.uploadedFiles"
                      :key="fileIndex"
                      class="resubmit-file-item"
                    >
                      <i class="fas fa-file"></i>
                      <a
                        :href="getFileUrl(file)"
                        target="_blank"
                        class="resubmit-file-link"
                      >
                        {{ getFileName(file) }}
                      </a>
                    </div>
                  </div>
                  <span v-else class="resubmit-no-file">{{
                    t("kycDetail_resubmitNoFile")
                  }}</span>
                </span>
                <span v-else-if="answer.answerText || answer.answerValues">
                  {{ formatResubmitAnswer(answer) }}
                </span>
                <span v-else class="resubmit-no-answer">{{
                  t("kycDetail_resubmitNoAnswer")
                }}</span>
              </template>
              <template v-else-if="answer.itemType === 'document'">
                <div
                  v-if="answer.uploadedFiles && answer.uploadedFiles.length > 0"
                  class="resubmit-file-list"
                >
                  <div
                    v-for="(file, fileIndex) in answer.uploadedFiles"
                    :key="fileIndex"
                    class="resubmit-file-item"
                  >
                    <i class="fas fa-file"></i>
                    <a
                      :href="getFileUrl(file)"
                      target="_blank"
                      class="resubmit-file-link"
                    >
                      {{ getFileName(file) }}
                    </a>
                  </div>
                </div>
                <span v-else class="resubmit-no-file">{{
                  t("kycDetail_resubmitNoFile")
                }}</span>
              </template>
            </div>
          </div>
          <div v-if="resubmitRequest.additionalNotes" class="resubmit-notes">
            <div class="resubmit-notes-label">
              <i class="fas fa-sticky-note"></i>
              {{ t("kycDetail_resubmitNotesLabel") }}
            </div>
            <div class="resubmit-notes-content">
              {{ resubmitRequest.additionalNotes }}
            </div>
          </div>
        </div>
      </div>

      <div v-if="!isReadOnlyDetail" class="kyc-actions-section">
        <div class="kyc-actions-header">
          <i class="fas fa-tasks"></i>
          <h3>{{ t("kycDetail_finalDecision") }}</h3>
        </div>

        <!-- Section Approval Progress -->
        <div
          class="approval-progress"
          :class="{ complete: allSectionsApproved }"
          v-if="categorizedAnswers.length > 0"
        >
          <div class="approval-progress-header">
            <span class="approval-progress-label">
              <i class="fas fa-clipboard-check"></i>
              {{ t("kycDetail_sectionProgressLabel") }}
            </span>
            <span
              class="approval-progress-count"
              :class="{ complete: allSectionsApproved }"
            >
              {{
                tParams(
                  "kycDetail_sectionsProgress",
                  "{approved} / {total} sections approved",
                  {
                    approved: approvedSections.length,
                    total: categorizedAnswers.length,
                  },
                )
              }}
            </span>
          </div>
          <div class="approval-progress-bar">
            <div
              class="approval-progress-fill"
              :style="{
                width:
                  (approvedSections.length / categorizedAnswers.length) * 100 +
                  '%',
              }"
            ></div>
          </div>
        </div>

        <div class="kyc-actions-buttons">
          <button
            v-if="hasApprovePermission"
            class="btn-kyc-action btn-approve-all"
            @click="approveAll"
            :disabled="approving"
          >
            <i
              :class="
                approving ? 'fas fa-spinner fa-spin' : 'fas fa-check-double'
              "
            ></i>
            {{ t("kycDetail_btnApproveAll") }}
          </button>
          <button
            v-if="hasNeedMoreDocumentsPermission"
            class="btn-kyc-action btn-need-docs"
            @click="needMoreDocuments"
          >
            <i class="fas fa-file-upload"></i>
            {{ t("kycDetail_btnNeedMoreDocs") }}
          </button>
          <button
            v-if="hasRejectPermission"
            class="btn-kyc-action btn-reject"
            @click="rejectSubmission"
          >
            <i class="fas fa-times"></i> {{ t("kycDetail_btnReject") }}
          </button>
        </div>
      </div>

      <div
        v-if="!isReadOnlyDetail"
        class="need-docs-section"
        :class="{ show: showNeedDocsSection }"
      >
        <div class="need-docs-header">
          <h4>
            <i class="fas fa-file-upload"></i>
            {{ t("kycDetail_needDocs_title") }}
          </h4>
        </div>
        <div class="need-docs-content">
          <!-- Combined Items Section -->
          <div class="need-docs-items-section">
            <div class="need-docs-items-header">
              <h5>
                <i class="fas fa-clipboard-list"></i>
                {{ t("kycDetail_needDocs_itemsHeader") }}
              </h5>
              <div class="add-item-buttons">
                <button
                  class="btn-add-item-small btn-add-question"
                  @click="openAddItemModal('question')"
                >
                  <i class="fas fa-plus"></i>
                  {{ t("kycDetail_btnAddQuestion") }}
                </button>
                <button
                  class="btn-add-item-small btn-add-document"
                  @click="openAddItemModal('document')"
                >
                  <i class="fas fa-plus"></i>
                  {{ t("kycDetail_btnAddDocument") }}
                </button>
              </div>
            </div>
            <div class="items-list">
              <div
                v-if="requestedItems.length === 0"
                class="empty-selection-message"
              >
                <i class="fas fa-inbox"></i>
                {{ t("kycDetail_emptyItems") }}
              </div>
              <!-- Question Item -->
              <div
                v-for="(item, index) in requestedItems"
                :key="index"
                :class="[
                  item.type === 'question'
                    ? 'selectable-question-item'
                    : 'selectable-document-item',
                  { selected: item.selected },
                ]"
                @click.stop="toggleItemSelection(index)"
              >
                <label class="question-checkbox" @click.stop>
                  <input
                    type="checkbox"
                    class="need-docs-checkbox"
                    :checked="item.selected"
                    @change="item.selected = $event.target.checked"
                  />
                  <span class="question-checkbox-mark"></span>
                </label>
                <!-- Question -->
                <template v-if="item.type === 'question'">
                  <div class="selectable-question-content">
                    <div class="selectable-question-title">
                      {{ item.name || item.title }}
                    </div>
                    <span
                      v-if="item.questionType"
                      class="selectable-question-type"
                    >
                      {{ getQuestionTypeLabel(item.questionType) }}
                    </span>
                  </div>
                </template>
                <!-- Document -->
                <template v-else>
                  <div class="document-icon">
                    <i :class="getDocumentIcon(item.documentType)"></i>
                  </div>
                  <div class="selectable-document-content">
                    <div class="selectable-document-title">
                      {{ item.name || item.title }}
                    </div>
                  </div>
                </template>
                <div class="item-actions">
                  <button
                    class="btn-edit-item"
                    @click.stop="openEditItemModal(index)"
                    :title="t('kycDetail_title_edit')"
                  >
                    <i class="fas fa-edit"></i>
                  </button>
                  <button
                    class="btn-remove-item"
                    @click.stop="removeRequestedItem(index)"
                    :title="t('kycDetail_title_remove')"
                  >
                    <i class="fas fa-times"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Additional Notes -->
          <div class="need-docs-notes-field">
            <label for="need-docs-notes"
              ><i class="fas fa-sticky-note"></i>
              {{ t("kycDetail_needDocs_notesLabel") }}</label
            >
            <textarea
              id="need-docs-notes"
              v-model="needDocsNotes"
              :placeholder="t('kycDetail_needDocs_notesPlaceholder')"
            ></textarea>
          </div>
        </div>

        <!-- Summary -->
        <div class="need-docs-summary">
          <h5>
            <i class="fas fa-clipboard-list"></i>
            {{ t("kycDetail_summaryTitle") }}
          </h5>
          <div class="need-docs-summary-items">
            <span
              v-if="selectedItems.length === 0"
              class="need-docs-summary-empty"
              >{{ t("kycDetail_summaryEmpty") }}</span
            >
            <span
              v-for="(item, index) in selectedItems"
              :key="index"
              class="need-docs-summary-tag"
            >
              <i
                :class="
                  item.type === 'question'
                    ? 'fas fa-question-circle'
                    : 'fas fa-file-alt'
                "
              ></i>
              {{ item.name || item.title }}
            </span>
          </div>
        </div>

        <!-- Actions -->
        <div class="need-docs-actions">
          <button class="btn-cancel-docs" @click="cancelNeedDocs">
            <i class="fas fa-times"></i> {{ t("kycDetail_cancel") }}
          </button>
          <button class="btn-send-request" @click="sendDocumentRequest">
            <i class="fas fa-paper-plane"></i> {{ t("kycDetail_sendRequest") }}
          </button>
        </div>
      </div>
    </template>

    <!-- Rejected状态：显示拒绝信息 -->
    <div
      v-else-if="submission.submissionStatus === 'rejected'"
      class="status-message rejected-message"
    >
      <div class="status-message-content">
        <i class="fas fa-times-circle"></i>
        <p class="status-message-title">{{ t("kycDetail_rejectedTitle") }}</p>
        <p class="status-message-description">
          {{
            tParams(
              "kycDetail_rejectedDesc",
              "This application was rejected on {date} by {name}",
              {
                date: formatReviewDate(submission.reviewedAt),
                name: reviewerDisplayName,
              },
            )
          }}
          <span
            v-if="
              submission.rejectionReason ||
              (submissionDetails && submissionDetails.rejectionReason)
            "
            class="rejection-reason"
          >
            <br /><strong>{{ t("kycDetail_reasonLabel") }}</strong>
            {{
              submission.rejectionReason ||
              (submissionDetails && submissionDetails.rejectionReason) ||
              t("addrVerif_na")
            }}
          </span>
        </p>
      </div>
    </div>

    <!-- Resubmit Required状态：显示需要补充文档信息（参考Need Docs样式） -->
    <div
      v-else-if="submission.submissionStatus === 'resubmit_required'"
      class="status-message need-docs-message"
    >
      <div class="status-message-content">
        <i class="fas fa-file-upload"></i>
        <p class="status-message-title">
          {{ t("kycDetail_resubmitRequiredTitle") }}
        </p>
        <p class="status-message-description">
          {{ t("kycDetail_resubmitRequiredDesc") }}
        </p>
      </div>
    </div>

    <!-- Add Item Modal（Approved 只读时不挂载，避免误操作） -->
    <div
      v-if="!isReadOnlyDetail"
      class="add-item-modal"
      :class="{ show: showAddItemModal }"
      @click.self="closeAddItemModal"
    >
      <div class="add-item-modal-content">
        <div class="add-item-modal-header">
          <h3>
            <i
              :class="
                isEditMode
                  ? 'fas fa-edit'
                  : addItemType === 'question'
                    ? 'fas fa-plus-circle'
                    : 'fas fa-file-alt'
              "
            ></i>
            {{
              isEditMode
                ? addItemType === "question"
                  ? t("kycDetail_modal_editQuestion")
                  : t("kycDetail_modal_editDocument")
                : addItemType === "question"
                  ? t("kycDetail_modal_addQuestion")
                  : t("kycDetail_modal_addDocument")
            }}
          </h3>
          <button class="add-item-modal-close" @click="closeAddItemModal">
            ×
          </button>
        </div>
        <div class="add-item-modal-body">
          <form @submit.prevent="saveItemToList">
            <!-- Question Text -->
            <div class="add-item-field" v-if="addItemType === 'question'">
              <label for="itemQuestionText"
                >{{ t("kycDetail_form_questionText") }}
                <span style="color: var(--color-danger)">*</span></label
              >
              <textarea
                id="itemQuestionText"
                v-model="itemForm.questionText"
                :placeholder="t('kycDetail_form_questionTextPlaceholder')"
                rows="3"
                required
              ></textarea>
            </div>

            <!-- Document Name -->
            <div class="add-item-field" v-if="addItemType === 'document'">
              <label for="itemDocumentName"
                >{{ t("kycDetail_form_documentName") }}
                <span style="color: var(--color-danger)">*</span></label
              >
              <input
                type="text"
                id="itemDocumentName"
                v-model="itemForm.documentName"
                :placeholder="t('kycDetail_form_documentNamePlaceholder')"
                required
              />
            </div>

            <!-- Question Type -->
            <div class="add-item-field" v-if="addItemType === 'question'">
              <label for="itemQuestionType"
                >{{ t("kycDetail_form_questionType") }}
                <span style="color: var(--color-danger)">*</span></label
              >
              <select
                id="itemQuestionType"
                v-model="itemForm.questionType"
                @change="toggleAnswerOptions"
                required
              >
                <option value="">
                  {{ t("kycDetail_form_selectQuestionType") }}
                </option>
                <option
                  v-for="type in questionTypes"
                  :key="type.value"
                  :value="type.value"
                >
                  {{ type.label }}
                </option>
              </select>
            </div>

            <!-- Document Type -->
            <div class="add-item-field" v-if="addItemType === 'document'">
              <label for="itemDocumentType">{{
                t("kycDetail_form_documentType")
              }}</label>
              <select id="itemDocumentType" v-model="itemForm.documentType">
                <option value="ID_CARD">
                  {{ t("kycDetail_docType_ID_CARD") }}
                </option>
                <option value="PASSPORT">
                  {{ t("kycDetail_docType_PASSPORT") }}
                </option>
                <option value="DRIVERS_LICENSE">
                  {{ t("kycDetail_docType_DRIVERS_LICENSE") }}
                </option>
                <option value="PROOF_ADDRESS">
                  {{ t("kycDetail_docType_PROOF_ADDRESS") }}
                </option>
                <option value="BANK_STATEMENT">
                  {{ t("kycDetail_docType_BANK_STATEMENT") }}
                </option>
                <option value="UTILITY_BILL">
                  {{ t("kycDetail_docType_UTILITY_BILL") }}
                </option>
                <option value="INCOME_PROOF">
                  {{ t("kycDetail_docType_INCOME_PROOF") }}
                </option>
                <option value="TAX_DOCUMENT">
                  {{ t("kycDetail_docType_TAX_DOCUMENT") }}
                </option>
                <option value="EMPLOYMENT_LETTER">
                  {{ t("kycDetail_docType_EMPLOYMENT_LETTER") }}
                </option>
                <option value="OTHER">
                  {{ t("kycDetail_docType_OTHER") }}
                </option>
              </select>
            </div>

            <!-- Help Text -->
            <div class="add-item-field">
              <label for="itemHelpText">{{
                t("kycDetail_form_helpText")
              }}</label>
              <input
                type="text"
                id="itemHelpText"
                v-model="itemForm.helpText"
                :placeholder="t('kycDetail_form_helpTextPlaceholder')"
              />
            </div>

            <!-- Validation Rule -->
            <div class="add-item-field" v-if="addItemType === 'question'">
              <label for="itemValidation">{{
                t("kycDetail_form_validation")
              }}</label>
              <input
                type="text"
                id="itemValidation"
                v-model="itemForm.validation"
                :placeholder="t('kycDetail_form_validationPlaceholder')"
              />
            </div>

            <!-- Answer Options (for choice questions) -->
            <div
              class="add-item-field"
              v-if="addItemType === 'question' && showAnswerOptions"
              style="grid-column: 1 / -1"
            >
              <label>{{ t("kycDetail_form_answerOptions") }}</label>
              <div class="answer-options-container">
                <div
                  v-for="(option, optIndex) in itemForm.options"
                  :key="optIndex"
                  class="answer-option-row"
                >
                  <input
                    type="text"
                    v-model="itemForm.options[optIndex]"
                    :placeholder="optionPlaceholder(optIndex)"
                    class="answer-option-input"
                  />
                  <button
                    type="button"
                    class="btn-remove-option"
                    @click="removeOption(optIndex)"
                    :disabled="itemForm.options.length <= 1"
                  >
                    <i class="fas fa-times"></i>
                  </button>
                </div>
                <button type="button" class="btn-add-option" @click="addOption">
                  <i class="fas fa-plus"></i> {{ t("kycDetail_btnAddOption") }}
                </button>
              </div>
            </div>

            <!-- File Document Types (for file upload questions) -->
            <div
              class="add-item-field"
              v-if="
                addItemType === 'question' &&
                itemForm.questionType === 'file_upload'
              "
              style="grid-column: 1 / -1"
            >
              <label for="itemFileDocumentTypes">
                <i class="fas fa-file-alt"></i>
                {{ t("kycDetail_form_acceptedDocTypes") }}
                <span style="color: var(--color-danger)">*</span>
              </label>
              <select
                id="itemFileDocumentTypes"
                v-model="itemForm.fileDocumentTypes"
                multiple
                class="file-document-types-select"
                required
              >
                <option value="ID_CARD">
                  {{ t("kycDetail_docType_ID_CARD") }}
                </option>
                <option value="PASSPORT">
                  {{ t("kycDetail_docType_PASSPORT") }}
                </option>
                <option value="DRIVERS_LICENSE">
                  {{ t("kycDetail_docType_DRIVERS_LICENSE") }}
                </option>
                <option value="PROOF_ADDRESS">
                  {{ t("kycDetail_docType_PROOF_ADDRESS") }}
                </option>
                <option value="BANK_STATEMENT">
                  {{ t("kycDetail_docType_BANK_STATEMENT") }}
                </option>
                <option value="UTILITY_BILL">
                  {{ t("kycDetail_docType_UTILITY_BILL") }}
                </option>
                <option value="INCOME_PROOF">
                  {{ t("kycDetail_docType_INCOME_PROOF") }}
                </option>
                <option value="TAX_DOCUMENT">
                  {{ t("kycDetail_docType_TAX_DOCUMENT") }}
                </option>
                <option value="EMPLOYMENT_LETTER">
                  {{ t("kycDetail_docType_EMPLOYMENT_LETTER") }}
                </option>
                <option value="OTHER">
                  {{ t("kycDetail_docType_OTHER") }}
                </option>
              </select>
              <small
                style="
                  color: var(--color-muted);
                  font-size: 14px;
                  margin-top: 8px;
                  display: block;
                "
              >
                <i class="fas fa-info-circle"></i>
                {{ t("kycDetail_form_multiSelectHint") }}
              </small>
            </div>

            <!-- Required Checkbox -->
            <div class="add-item-field" style="grid-column: 1 / -1">
              <label
                style="
                  display: flex;
                  align-items: center;
                  gap: 8px;
                  cursor: pointer;
                "
              >
                <input
                  type="checkbox"
                  v-model="itemForm.isRequired"
                  style="
                    width: 18px;
                    height: 18px;
                    accent-color: var(--color-brand);
                    cursor: pointer;
                  "
                />
                <span>{{ t("kycDetail_form_requiredField") }}</span>
              </label>
            </div>
          </form>
        </div>
        <div class="add-item-modal-footer">
          <button class="btn-modal-cancel" @click="closeAddItemModal">
            {{ t("kycDetail_cancel") }}
          </button>
          <button class="btn-modal-save" @click="saveItemToList">
            <i class="fas fa-save"></i>
            {{
              isEditMode
                ? t("kycDetail_btnUpdateItem")
                : t("kycDetail_btnAddItem")
            }}
          </button>
        </div>
      </div>
    </div>

    <!-- 拒绝模态框 -->
    <div
      v-if="!isReadOnlyDetail"
      class="reject-modal"
      :class="{ show: showRejectModal }"
      @click.self="cancelReject"
    >
      <div class="reject-modal-content">
        <div class="reject-modal-header">
          <h3>
            <i class="fas fa-times-circle"></i>
            {{ t("kycDetail_rejectModal_title") }}
          </h3>
          <button class="reject-modal-close" @click="cancelReject">×</button>
        </div>
        <div class="reject-modal-body">
          <div class="reject-field">
            <label for="rejection-reason"
              >{{ t("kycDetail_rejectModal_reason") }}
              <span style="color: var(--color-danger)">*</span></label
            >
            <textarea
              id="rejection-reason"
              v-model="rejectionReason"
              :placeholder="t('kycDetail_rejectModal_placeholder')"
              rows="5"
              required
            ></textarea>
          </div>
          <div class="reject-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <span>{{ t("kycDetail_rejectModal_warning") }}</span>
          </div>
        </div>
        <div class="reject-modal-footer">
          <button class="btn-reject-cancel" @click="cancelReject">
            {{ t("kycDetail_cancel") }}
          </button>
          <button
            class="btn-reject-confirm"
            @click="confirmReject"
            :disabled="!rejectionReason.trim()"
          >
            <i class="fas fa-times"></i> {{ t("kycDetail_confirmRejection") }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, watch } from "vue";
import { kycSubmissionService } from "@/services/kycListService";
import { useAuthStore } from "@/stores/auth";
import { useAdminI18n } from "@/composables/useAdminI18n";

export default {
  name: "KYCSubmissionDetail",
  props: {
    submission: {
      type: Object,
      required: true,
    },
    hasApprovePermission: {
      type: Boolean,
      default: false,
    },
    hasRejectPermission: {
      type: Boolean,
      default: false,
    },
    hasNeedMoreDocumentsPermission: {
      type: Boolean,
      default: false,
    },
    hasAssignReviewerPermission: {
      type: Boolean,
      default: false,
    },
    // 审批进行中：父组件发起 approve 请求期间置 true，禁用审批按钮防止重复提交
    approving: {
      type: Boolean,
      default: false,
    },
  },
  emits: ["approve", "reject", "needDocs", "assign"],
  setup(props, { emit }) {
    const { t, tParams, languageStore } = useAdminI18n();
    const dateLocale = () =>
      languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
    // 响应式数据
    const submissionDetails = ref(null);
    const loading = ref(false);
    const authStore = useAuthStore();
    const showNeedDocsSection = ref(false);
    const resubmitRequest = ref(null);
    const selectedDocumentTypes = ref([]);
    const needDocsNotes = ref("");
    const availableDocumentTypes = ref([]);
    const requestedItems = ref([]); // 请求的项目列表（问题和文档）
    const showAddItemModal = ref(false);
    const addItemType = ref("question"); // 'question' or 'document'
    const showAnswerOptions = ref(false);
    const isEditMode = ref(false);
    const editingItemIndex = ref(-1);
    const approvedSections = ref([]); // 已批准的section ID列表
    const itemForm = ref({
      questionText: "",
      documentName: "",
      questionType: "",
      documentType: "ID_CARD",
      helpText: "",
      validation: "",
      options: [""],
      fileDocumentTypes: [],
      isRequired: true,
    });

    // Question Types（与地址验证详情共用键）
    const questionTypes = computed(() => [
      { value: "text", label: t("txnSettings_qType_text") },
      { value: "number", label: t("addrVerif_qType_number") },
      { value: "email", label: t("txnSettings_qType_email") },
      { value: "tel", label: t("addrVerif_qType_tel_short") },
      { value: "date", label: t("txnSettings_qType_date") },
      { value: "single_choice", label: t("txnSettings_qType_single_choice") },
      { value: "multiple_choice", label: t("addrVerif_qType_multiple_choice") },
      { value: "yes_no", label: t("addrVerif_qType_yes_no") },
      { value: "file_upload", label: t("addrVerif_qType_file_upload") },
      { value: "textarea", label: t("addrVerif_qType_textarea") },
    ]);

    const reviewerDisplayName = computed(() => {
      return (
        props.submission.reviewerName ||
        submissionDetails.value?.reviewerName ||
        t("addrVerif_reviewerAnonymous")
      );
    });

    const optionPlaceholder = (index) =>
      tParams("kycDetail_optionN", "Option {n}", { n: index + 1 });

    // 计算属性 - 检查当前用户是否是审核员
    const isCurrentUserReviewer = computed(() => {
      if (!authStore.user || !props.submission.reviewerId) {
        return false;
      }
      return authStore.user.id === props.submission.reviewerId;
    });

    /** Approved：只读展示完整问卷与文件，样式对齐「Under Review 且当前用户为审核员」 */
    const isReadOnlyDetail = computed(
      () => props.submission.submissionStatus === "approved",
    );

    const showKycDetailContent = computed(() => {
      if (props.submission.submissionStatus === "approved") return true;
      return (
        props.submission.submissionStatus === "under_review" &&
        isCurrentUserReviewer.value
      );
    });

    // 计算属性 - 选中的项目
    const selectedItems = computed(() => {
      return requestedItems.value.filter((item) => item.selected);
    });

    // 计算属性 - 按分类组织的答案
    const categorizedAnswers = computed(() => {
      if (!submissionDetails.value || !submissionDetails.value.answers) {
        return [];
      }

      // 如果已经是按分类组织的格式，直接转换
      if (Array.isArray(submissionDetails.value.answers)) {
        return submissionDetails.value.answers.map((category, index) => {
          // 获取categoryId（如果有的话）
          const categoryId = category.categoryId || index;

          // 转换questions为answers格式，并添加questionType
          const answers = (category.questions || []).map((question) => {
            // 从原始答案数据中获取questionType（如果后端没有返回）
            // 这里假设后端返回的question对象中已经有questionType
            // 如果没有，我们需要从submissionDetails的原始数据中查找
            let questionType = question.questionType;

            // 如果questionType不存在，尝试从answer中推断
            if (!questionType && question.answer) {
              // 根据answer的类型推断questionType
              if (Array.isArray(question.answer)) {
                questionType = "multiple_choice";
              } else if (typeof question.answer === "number") {
                questionType = "number";
              } else if (question.answer.match(/^\d{4}-\d{2}-\d{2}/)) {
                questionType = "date";
              } else {
                questionType = "text";
              }
            }

            return {
              questionId: question.questionId,
              questionText: question.questionText,
              questionType: questionType || "text",
              answerValue: question.answer || question.answerValue || "-",
              files: question.files || null,
            };
          });

          return {
            id: categoryId,
            categoryName: category.categoryName || t("kycDetail_categoryOther"),
            answers: answers,
          };
        });
      }

      // 如果后端返回的是扁平化的答案数组，按分类分组
      const categories = {};
      submissionDetails.value.answers.forEach((answer) => {
        const categoryName =
          answer.categoryName || t("kycDetail_categoryOther");
        if (!categories[categoryName]) {
          categories[categoryName] = {
            id: answer.categoryId || 0,
            categoryName: categoryName,
            answers: [],
          };
        }
        categories[categoryName].answers.push({
          questionId: answer.questionId,
          questionText: answer.questionText,
          questionType: answer.questionType || "text",
          answerValue: getAnswerValue(answer),
        });
      });

      return Object.values(categories);
    });

    // 获取答案值的辅助函数
    const getAnswerValue = (answer) => {
      // 如果answer已经是格式化后的字符串，直接返回
      if (answer.answer !== undefined) {
        return answer.answer;
      }

      // 否则根据questionType从原始字段获取
      const questionType = answer.questionType || "text";
      switch (questionType) {
        case "number":
          return answer.answerNumber !== undefined
            ? answer.answerNumber
            : answer.answer || "-";
        case "date":
          return answer.answerDate || answer.answer || "-";
        case "multiple_choice":
          if (answer.answerValues) {
            const values =
              typeof answer.answerValues === "string"
                ? JSON.parse(answer.answerValues)
                : answer.answerValues;
            return Array.isArray(values) ? values.join(", ") : values;
          }
          return answer.answer || "-";
        case "file_upload":
          if (answer.uploadedFiles) {
            const files =
              typeof answer.uploadedFiles === "string"
                ? JSON.parse(answer.uploadedFiles)
                : answer.uploadedFiles;
            if (Array.isArray(files) && files.length > 0) {
              return files
                .map((f) =>
                  typeof f === "string" ? basename(f) : f.fileName || f,
                )
                .join(", ");
            }
          }
          return answer.answer || t("kycDetail_noFileUploaded");
        default:
          return answer.answerText || answer.answer || "-";
      }
    };

    // 获取文件名（辅助函数）
    const basename = (path) => {
      if (!path) return "";
      const parts = path.split("/");
      return parts[parts.length - 1];
    };

    // 格式化补充答案
    const formatResubmitAnswer = (answer) => {
      if (answer.answerText) {
        return answer.answerText;
      }
      if (answer.answerValues) {
        try {
          const values =
            typeof answer.answerValues === "string"
              ? JSON.parse(answer.answerValues)
              : answer.answerValues;
          if (Array.isArray(values)) {
            return values.join(", ");
          }
          if (values !== null && values !== undefined) {
            return String(values);
          }
        } catch (e) {
          console.error("Failed to parse answerValues:", e);
        }
      }
      return "-";
    };

    // 获取文件 URL
    const getFileUrl = (file) => {
      // 优先使用后端返回的 downloadUrl
      if (typeof file === "object" && file.downloadUrl) {
        return file.downloadUrl;
      }
      // 如果是对象，可能有 url 或 path 属性
      if (typeof file === "object") {
        return file.url || file.path || file.filePath || file;
      }
      // 如果是字符串，可能是文件路径
      if (typeof file === "string") {
        // 如果是相对路径，添加基础 URL
        if (file.startsWith("uploads/")) {
          return `/api/${file}`;
        }
        return file;
      }
      return file;
    };

    // 获取文件名
    const getFileName = (file) => {
      // 优先使用 fileName 属性
      if (typeof file === "object" && file.fileName) {
        return file.fileName;
      }
      // 如果是对象，尝试其他属性
      if (typeof file === "object") {
        return (
          file.name ||
          basename(
            file.path || file.filePath || file.downloadUrl || file.url || "",
          )
        );
      }
      // 如果是字符串，直接获取文件名
      if (typeof file === "string") {
        return basename(file);
      }
      return file;
    };

    // 加载提交详情
    const loadSubmissionDetails = async () => {
      // pending状态且没有审核员时，不加载详情
      if (
        (props.submission.submissionStatus === "pending" ||
          props.submission.submissionStatus === "submitted") &&
        !props.submission.reviewerId
      ) {
        return;
      }

      // under_review状态且不是当前用户审核时，不加载详情
      if (
        props.submission.submissionStatus === "under_review" &&
        !isCurrentUserReviewer.value
      ) {
        return;
      }

      // rejected状态需要加载详情以获取rejectionReason
      if (props.submission.submissionStatus === "rejected") {
        // 即使rejected状态也加载详情，以便获取rejectionReason
        // 但不需要显示详情内容，只显示状态消息
      }

      try {
        loading.value = true;
        const response = await kycSubmissionService.getDetail(
          props.submission.submissionId,
        );
        submissionDetails.value = response.data;

        // under_review 且为当前审核员，或 approved：加载补充问答/文件（若有）
        if (
          (props.submission.submissionStatus === "under_review" &&
            isCurrentUserReviewer.value) ||
          props.submission.submissionStatus === "approved"
        ) {
          try {
            const resubmitResponse =
              await kycSubmissionService.getResubmitAnswers(
                props.submission.submissionId,
              );
            if (resubmitResponse.data) {
              resubmitRequest.value = resubmitResponse.data;
            }
          } catch (error) {
            // 如果没有 resubmit request，忽略错误
            console.log("No resubmit request found:", error);
            resubmitRequest.value = null;
          }
        }
      } catch (error) {
        console.error("Failed to load submission details:", error);
      } finally {
        loading.value = false;
      }
    };

    // 获取分类图标
    const getCategoryIcon = (categoryName) => {
      const iconMap = {
        "Personal Information": "fas fa-user-circle",
        "Financial Information": "fas fa-dollar-sign",
        "Investment Experience": "fas fa-chart-line",
        "Risk Assessment": "fas fa-exclamation-triangle",
        Compliance: "fas fa-shield-alt",
        "Identity Verification": "fas fa-id-card",
        "Address Verification": "fas fa-home",
        "Employment Information": "fas fa-briefcase",
      };
      return iconMap[categoryName] || "fas fa-folder";
    };

    // 格式化审核日期
    const formatReviewDate = (dateString) => {
      if (!dateString) return t("addrVerif_na");
      return new Date(dateString).toLocaleDateString(dateLocale(), {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      });
    };

    // 格式化日期
    const formatDate = (dateString) => {
      if (!dateString) return "-";
      return new Date(dateString).toLocaleDateString(dateLocale(), {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      });
    };

    // 格式化答案值
    const formatAnswerValue = (answer) => {
      const answerValue = answer.answerValue;

      if (
        answerValue === null ||
        answerValue === undefined ||
        answerValue === ""
      ) {
        return "-";
      }

      // 根据问题类型格式化答案
      switch (answer.questionType) {
        case "date":
          try {
            return new Date(answerValue).toLocaleDateString(dateLocale());
          } catch (e) {
            return answerValue;
          }
        case "file_upload":
          // 如果是 files 数组（包含 downloadUrl），显示可点击的链接
          if (
            answer.files &&
            Array.isArray(answer.files) &&
            answer.files.length > 0
          ) {
            // 返回 HTML 字符串（Vue 需要使用 v-html 渲染）
            return answer.files
              .map((f) => {
                const fileName =
                  f.fileName || basename(f.filePath || f.downloadUrl || "");
                const downloadUrl =
                  f.downloadUrl || getFileUrl(f.filePath || f);
                return `<a href="${downloadUrl}" target="_blank" class="file-download-link">${fileName}</a>`;
              })
              .join(", ");
          }
          // 如果是数组，显示文件名列表
          if (Array.isArray(answerValue)) {
            return answerValue
              .map((f) =>
                typeof f === "string" ? basename(f) : f.fileName || f,
              )
              .join(", ");
          }
          // 如果是字符串，可能是文件路径
          if (typeof answerValue === "string") {
            return basename(answerValue);
          }
          return answerValue;
        case "multiple_choice":
          // 如果是数组，用逗号连接
          if (Array.isArray(answerValue)) {
            return answerValue.join(", ");
          }
          return answerValue;
        case "single_choice":
          return answerValue;
        default:
          return answerValue;
      }
    };

    // 获取活动图标
    const getActivityIcon = (activityType) => {
      const iconMap = {
        created: "fas fa-plus-circle",
        submitted: "fas fa-paper-plane",
        approved: "fas fa-check-circle",
        rejected: "fas fa-times-circle",
        assigned: "fas fa-user-check",
        need_docs: "fas fa-file-upload",
      };
      return iconMap[activityType] || "fas fa-circle";
    };

    // 审批单个分类
    const approveSection = (categoryId) => {
      const index = approvedSections.value.indexOf(categoryId);
      if (index > -1) {
        // 如果已批准，取消批准
        approvedSections.value.splice(index, 1);
      } else {
        // 如果未批准，添加到已批准列表
        approvedSections.value.push(categoryId);
      }
    };

    // 计算属性 - 是否所有section都已批准
    const allSectionsApproved = computed(() => {
      if (categorizedAnswers.value.length === 0) return false;
      return categorizedAnswers.value.every((category) =>
        approvedSections.value.includes(category.id),
      );
    });

    // 审批全部
    const approveAll = () => {
      if (props.approving) return; // 进行中，避免重复提交
      // 自动批准所有未批准的section
      categorizedAnswers.value.forEach((category) => {
        if (!approvedSections.value.includes(category.id)) {
          approvedSections.value.push(category.id);
        }
      });

      // 执行最终批准
      emit("approve", props.submission.submissionId);
    };

    // 拒绝提交相关
    const showRejectModal = ref(false);
    const rejectionReason = ref("");

    const rejectSubmission = () => {
      showRejectModal.value = true;
    };

    const cancelReject = () => {
      showRejectModal.value = false;
      rejectionReason.value = "";
    };

    const confirmReject = () => {
      if (!rejectionReason.value.trim()) {
        alert(t("kycDetail_alert_rejectReason"));
        return;
      }
      emit(
        "reject",
        props.submission.submissionId,
        rejectionReason.value.trim(),
      );
      cancelReject();
    };

    // 需要更多文档
    const needMoreDocuments = () => {
      showNeedDocsSection.value = true;
    };

    // 切换文档类型选择
    const toggleDocumentType = (docTypeId) => {
      const index = selectedDocumentTypes.value.indexOf(docTypeId);
      if (index > -1) {
        selectedDocumentTypes.value.splice(index, 1);
      } else {
        selectedDocumentTypes.value.push(docTypeId);
      }
    };

    // 打开添加项目模态框
    const openAddItemModal = (type) => {
      isEditMode.value = false;
      editingItemIndex.value = -1;
      addItemType.value = type;
      // 重置表单
      itemForm.value = {
        questionText: "",
        documentName: "",
        questionType: "",
        documentType: "id-card",
        helpText: "",
        validation: "",
        options: [""],
        fileDocumentTypes: [],
        isRequired: true,
      };
      showAnswerOptions.value = false;
      showAddItemModal.value = true;
    };

    // 打开编辑项目模态框
    const openEditItemModal = (index) => {
      const item = requestedItems.value[index];
      if (!item) return;

      isEditMode.value = true;
      editingItemIndex.value = index;
      addItemType.value = item.type;

      // 填充表单数据
      if (item.type === "question") {
        itemForm.value = {
          questionText: item.name || item.title || "",
          documentName: "",
          questionType: item.questionType || "",
          documentType: "id-card",
          helpText: item.helpText || "",
          validation: item.validation || "",
          options:
            item.options && item.options.length > 0 ? [...item.options] : [""],
          fileDocumentTypes: item.fileDocumentTypes || [],
          isRequired: item.isRequired !== undefined ? item.isRequired : true,
        };
        // 根据问题类型显示答案选项
        showAnswerOptions.value =
          item.questionType === "single_choice" ||
          item.questionType === "multiple_choice";
      } else {
        itemForm.value = {
          questionText: "",
          documentName: item.name || item.title || "",
          questionType: "",
          documentType: item.documentType || "id-card",
          helpText: "",
          validation: "",
          options: [""],
          fileDocumentTypes: [],
          isRequired: item.isRequired !== undefined ? item.isRequired : true,
        };
        showAnswerOptions.value = false;
      }

      showAddItemModal.value = true;
    };

    // 关闭添加项目模态框
    const closeAddItemModal = () => {
      showAddItemModal.value = false;
      isEditMode.value = false;
      editingItemIndex.value = -1;
      // 延迟重置表单，让动画完成
      setTimeout(() => {
        itemForm.value = {
          questionText: "",
          documentName: "",
          questionType: "",
          documentType: "id-card",
          helpText: "",
          validation: "",
          options: [""],
          fileDocumentTypes: [],
          isRequired: true,
        };
        showAnswerOptions.value = false;
      }, 300);
    };

    // 切换答案选项显示
    const toggleAnswerOptions = () => {
      const questionType = itemForm.value.questionType;
      showAnswerOptions.value =
        questionType === "single_choice" || questionType === "multiple_choice";
      if (showAnswerOptions.value && itemForm.value.options.length === 0) {
        itemForm.value.options = [""];
      }
    };

    // 添加选项
    const addOption = () => {
      itemForm.value.options.push("");
    };

    // 移除选项
    const removeOption = (index) => {
      if (itemForm.value.options.length > 1) {
        itemForm.value.options.splice(index, 1);
      }
    };

    // 获取文档图标
    const getDocumentIcon = (documentType) => {
      const iconMap = {
        "id-card": "fas fa-id-card",
        passport: "fas fa-passport",
        home: "fas fa-home",
        university: "fas fa-university",
        "file-invoice": "fas fa-file-invoice",
        "money-check-alt": "fas fa-money-check-alt",
        camera: "fas fa-camera",
        briefcase: "fas fa-briefcase",
        "file-invoice-dollar": "fas fa-file-invoice-dollar",
        "file-alt": "fas fa-file-alt",
      };
      return iconMap[documentType] || "fas fa-file-alt";
    };

    // 获取问题类型标签
    const getQuestionTypeLabel = (value) => {
      const type = questionTypes.value.find((qt) => qt.value === value);
      return type ? type.label : value;
    };

    // 切换项目选择状态
    const toggleItemSelection = (index) => {
      requestedItems.value[index].selected =
        !requestedItems.value[index].selected;
    };

    // 保存项目到列表
    const saveItemToList = () => {
      if (addItemType.value === "question") {
        if (!itemForm.value.questionText.trim()) {
          alert(t("kycDetail_alert_questionText"));
          return;
        }
        if (!itemForm.value.questionType) {
          alert(t("kycDetail_alert_questionType"));
          return;
        }

        // 检查选项
        if (
          itemForm.value.questionType === "single_choice" ||
          itemForm.value.questionType === "multiple_choice"
        ) {
          const validOptions = itemForm.value.options.filter((opt) =>
            opt.trim(),
          );
          if (validOptions.length === 0) {
            alert(t("kycDetail_alert_choiceOptions"));
            return;
          }
        }

        // 检查文件上传类型
        if (itemForm.value.questionType === "file_upload") {
          if (itemForm.value.fileDocumentTypes.length === 0) {
            alert(t("kycDetail_alert_fileDocTypes"));
            return;
          }
        }

        const questionData = {
          type: "question",
          name: itemForm.value.questionText.trim(),
          title: itemForm.value.questionText.trim(),
          questionType: itemForm.value.questionType,
          helpText: itemForm.value.helpText,
          validation: itemForm.value.validation,
          options:
            itemForm.value.questionType === "single_choice" ||
            itemForm.value.questionType === "multiple_choice"
              ? itemForm.value.options.filter((opt) => opt.trim())
              : [],
          fileDocumentTypes:
            itemForm.value.questionType === "file_upload"
              ? itemForm.value.fileDocumentTypes
              : [],
          isRequired: itemForm.value.isRequired,
          selected: isEditMode.value
            ? requestedItems.value[editingItemIndex.value]?.selected
            : true,
        };

        if (isEditMode.value && editingItemIndex.value >= 0) {
          // 编辑模式：更新现有项目
          requestedItems.value[editingItemIndex.value] = questionData;
        } else {
          // 新增模式：添加到列表
          requestedItems.value.push(questionData);
        }
      } else {
        if (!itemForm.value.documentName.trim()) {
          alert(t("kycDetail_alert_documentName"));
          return;
        }

        const documentData = {
          type: "document",
          name: itemForm.value.documentName.trim(),
          title: itemForm.value.documentName.trim(),
          documentType: itemForm.value.documentType,
          isRequired: itemForm.value.isRequired,
          selected: isEditMode.value
            ? requestedItems.value[editingItemIndex.value]?.selected
            : true,
        };

        if (isEditMode.value && editingItemIndex.value >= 0) {
          // 编辑模式：更新现有项目
          requestedItems.value[editingItemIndex.value] = documentData;
        } else {
          // 新增模式：添加到列表
          requestedItems.value.push(documentData);
        }
      }

      closeAddItemModal();
    };

    // 移除请求的项目
    const removeRequestedItem = (index) => {
      requestedItems.value.splice(index, 1);
    };

    // 取消需要更多文档
    const cancelNeedDocs = () => {
      showNeedDocsSection.value = false;
      selectedDocumentTypes.value = [];
      needDocsNotes.value = "";
      requestedItems.value = [];
    };

    // 发送文档请求
    const sendDocumentRequest = () => {
      const selectedItemsList = requestedItems.value.filter(
        (item) => item.selected,
      );
      if (selectedItemsList.length === 0 && !needDocsNotes.value.trim()) {
        alert(t("kycDetail_alert_needSelectionOrNotes"));
        return;
      }
      emit("needDocs", props.submission.submissionId, {
        items: selectedItemsList,
        documentTypes: selectedDocumentTypes.value,
        notes: needDocsNotes.value,
      });
      cancelNeedDocs();
    };

    // 监听submission变化，以便在状态改变后刷新
    watch(
      () => props.submission.submissionStatus,
      (newStatus, oldStatus) => {
        if (newStatus === "approved" && oldStatus !== "approved") {
          loadSubmissionDetails();
        }
        // 如果状态变为rejected，重新加载详情以获取rejectionReason
        if (newStatus === "rejected" && oldStatus !== "rejected") {
          loadSubmissionDetails();
        }
        // 如果状态变为resubmit_required，关闭Need Docs部分并刷新详情
        if (
          newStatus === "resubmit_required" &&
          oldStatus !== "resubmit_required"
        ) {
          showNeedDocsSection.value = false;
          // 重新加载详情以更新显示内容
          loadSubmissionDetails();
        }
        // 如果状态从其他状态变为resubmit_required之外的状态，也刷新详情
        if (
          oldStatus === "resubmit_required" &&
          newStatus !== "resubmit_required"
        ) {
          loadSubmissionDetails();
        }
      },
    );

    // 生命周期
    onMounted(() => {
      // 如果是pending状态且没有审核员，显示弹窗提示
      if (
        (props.submission.submissionStatus === "pending" ||
          props.submission.submissionStatus === "submitted") &&
        !props.submission.reviewerId
      ) {
        alert(t("kycDetail_alert_assignFirst"));
      }
      loadSubmissionDetails();
    });

    return {
      t,
      tParams,
      reviewerDisplayName,
      optionPlaceholder,
      // 数据
      submissionDetails,
      loading,
      authStore,
      showNeedDocsSection,
      selectedDocumentTypes,
      needDocsNotes,
      availableDocumentTypes,
      requestedItems,
      showRejectModal,
      rejectionReason,
      resubmitRequest,
      approvedSections,

      // 计算属性
      categorizedAnswers,
      selectedItems,
      isCurrentUserReviewer,
      isReadOnlyDetail,
      showKycDetailContent,
      allSectionsApproved,

      // 方法
      getCategoryIcon,
      formatAnswerValue,
      formatReviewDate,
      formatDate,
      getAnswerValue,
      basename,
      approveSection,
      approveAll,
      rejectSubmission,
      cancelReject,
      confirmReject,
      needMoreDocuments,
      toggleDocumentType,
      cancelNeedDocs,
      sendDocumentRequest,
      openAddItemModal,
      openEditItemModal,
      closeAddItemModal,
      removeRequestedItem,
      showAddItemModal,
      addItemType,
      itemForm,
      showAnswerOptions,
      isEditMode,
      questionTypes,
      toggleAnswerOptions,
      addOption,
      removeOption,
      saveItemToList,
      toggleItemSelection,
      getDocumentIcon,
      getQuestionTypeLabel,
      formatResubmitAnswer,
      getFileUrl,
      getFileName,
    };
  },
};
</script>

<style scoped>
.kyc-submission-detail {
  padding: 0;
}

/* Status Message Styles */
.status-message {
  text-align: center;
  padding: 40px;
  border-radius: var(--radius-md);
  margin-bottom: 20px;
}

.status-message-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 15px;
}

.status-message-content i {
  font-size: 48px;
  margin-bottom: 15px;
}

.status-message-title {
  font-size: 16px;
  font-weight: 600;
  margin: 0;
}

.status-message-description {
  font-size: 14px;
  margin: 0;
}

.pending-message {
  color: var(--color-warning);
  background: var(--color-warning-soft);
}

.under-review-message {
  color: var(--color-muted);
}

.approved-message {
  color: var(--color-success);
  background: var(--color-success-soft);
}

/* Approved 详情顶部：与下方 .detail-section 卡片风格一致，不使用 .status-message-content */
.approved-detail-meta {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 18px 22px;
  margin-bottom: 20px;
  border-left: 4px solid var(--color-success);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
}

.approved-detail-meta-icon {
  color: var(--color-success);
  font-size: 22px;
  line-height: 1.25;
  flex-shrink: 0;
  padding-top: 2px;
}

.approved-detail-meta-text {
  min-width: 0;
  flex: 1;
}

.approved-detail-meta-heading {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-ink);
  margin: 0 0 6px;
  line-height: 1.35;
}

.approved-detail-meta-sub {
  font-size: 14px;
  color: var(--color-muted);
  line-height: 1.55;
  margin: 0;
}

.rejected-message {
  color: var(--color-danger);
  background: var(--color-danger-soft);
}

.rejected-message .status-message-content i {
  color: var(--color-danger);
}

.need-docs-message {
  background: var(--color-warning-soft);
  border-color: var(--color-warning-border);
}

.need-docs-message .status-message-content {
  text-align: center;
  padding: 40px;
}

.need-docs-message .status-message-content i {
  font-size: 48px;
  margin-bottom: 15px;
  color: var(--color-warning);
}

.need-docs-message .status-message-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-warning);
  margin-bottom: 8px;
}

.need-docs-message .status-message-description {
  font-size: 14px;
  color: var(--color-warning);
}

.rejection-reason {
  display: block;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid rgba(197, 48, 48, 0.2);
}

.rejection-reason strong {
  color: var(--color-danger);
}

.detail-sections {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
  gap: 20px;
  margin-bottom: 20px;
}

.detail-section {
  background: var(--color-surface);
  border-radius: var(--radius-md);
  padding: 25px;
  border: 2px solid var(--color-border);
  transition: all 0.3s ease;
}

.detail-section:hover {
  border-color: var(--color-border-strong);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--color-border);
}

.section-title {
  display: flex;
  align-items: center;
  gap: 10px;
}

.section-title h3 {
  font-size: 16px;
  color: var(--color-ink);
  margin: 0;
}

.section-title i {
  color: var(--color-brand);
}

.btn-approve-section {
  padding: 6px 12px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--color-success-solid);
  color: white;
}

.btn-approve-section:hover {
  background: var(--color-success-solid);
  transform: translateY(-1px);
}

.btn-approve-section.approved {
  background: linear-gradient(135deg, var(--color-success) 0%, #276749 100%);
  box-shadow: 0 2px 8px rgba(34, 84, 61, 0.3);
}

.btn-approve-section.approved:hover {
  background: linear-gradient(135deg, #276749 0%, var(--color-success) 100%);
  transform: translateY(-1px);
}

.detail-field {
  display: flex;
  justify-content: space-between;
  align-items: start;
  padding: 12px 0;
  border-bottom: 1px solid #f0f0f0;
}

.detail-field:last-child {
  border-bottom: none;
}

.detail-label {
  font-weight: 600;
  color: var(--color-muted);
  font-size: 14px;
  min-width: 180px;
}

.detail-value {
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 500;
  text-align: right;
  flex: 1;
}

/* Resubmit Answers Section Styles */
.resubmit-answers-section {
  background: var(--color-surface);
  border-radius: var(--radius-md);
  padding: 25px;
  border: 2px solid var(--color-warning-border);
  margin-bottom: 20px;
}

.resubmit-answers-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--color-warning-border);
}

.resubmit-answers-header i {
  color: var(--color-warning);
  font-size: 20px;
}

.resubmit-answers-header h3 {
  font-size: 16px;
  color: var(--color-warning);
  margin: 0;
}

.resubmit-answers-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.resubmit-answer-item {
  background: var(--color-warning-soft);
  border: 1px solid var(--color-warning-border);
  border-radius: var(--radius-md);
  padding: 15px;
}

.resubmit-answer-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.resubmit-answer-label {
  font-weight: 600;
  color: var(--color-warning);
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.resubmit-answer-label i {
  color: var(--color-warning);
}

.resubmit-answer-type {
  display: inline-block;
  background: var(--color-brand-soft);
  color: var(--color-brand);
  padding: 2px 8px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
}

.resubmit-answer-value {
  color: var(--color-ink);
  font-size: 14px;
  padding-left: 26px;
}

.resubmit-file-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.resubmit-file-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
}

.resubmit-file-item i {
  color: var(--color-brand);
}

.resubmit-file-link {
  color: var(--color-brand);
  text-decoration: none;
  font-size: 14px;
  transition: color 0.2s ease;
}

.resubmit-file-link:hover {
  color: var(--color-brand-strong);
  text-decoration: underline;
}

/* File Download Link Styles */
.file-download-link {
  color: var(--color-brand);
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
  transition: color 0.2s ease;
  margin-right: 8px;
}

.file-download-link:hover {
  color: var(--color-brand-strong);
  text-decoration: underline;
}

.file-download-link:last-child {
  margin-right: 0;
}

.resubmit-no-file,
.resubmit-no-answer {
  color: var(--color-faint);
  font-style: italic;
  font-size: 14px;
}

.resubmit-notes {
  margin-top: 15px;
  padding-top: 15px;
  border-top: 1px solid var(--color-warning-border);
}

.resubmit-notes-label {
  font-weight: 600;
  color: var(--color-warning);
  font-size: 14px;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.resubmit-notes-label i {
  color: var(--color-warning);
}

.resubmit-notes-content {
  color: var(--color-ink);
  font-size: 14px;
  line-height: 1.6;
  padding-left: 26px;
  white-space: pre-wrap;
}

.kyc-actions-section {
  background: var(--color-surface);
  border-radius: var(--radius-md);
  padding: 25px;
  border: 2px solid var(--color-border);
  margin-bottom: 20px;
}

.kyc-actions-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--color-border);
}

.kyc-actions-header h3 {
  font-size: 16px;
  color: var(--color-ink);
  margin: 0;
}

.kyc-actions-header i {
  color: var(--color-brand);
}

.approval-progress {
  background: var(--color-surface-soft);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 15px 20px;
  margin-bottom: 20px;
}

.approval-progress-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.approval-progress-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  display: flex;
  align-items: center;
  gap: 8px;
}

.approval-progress-label i {
  color: var(--color-brand);
}

.approval-progress-count {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-muted);
  padding: 4px 12px;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  border: 2px solid var(--color-border);
}

.approval-progress-count.complete {
  color: var(--color-success);
  background: var(--color-success-soft);
  border-color: var(--color-success);
}

.approval-progress-bar {
  width: 100%;
  height: 10px;
  background: var(--color-border);
  border-radius: 5px;
  overflow: hidden;
}

.approval-progress-fill {
  height: 100%;
  background: var(--color-brand-solid);
  transition:
    width 0.3s ease,
    background 0.3s ease;
  border-radius: 5px;
}

.approval-progress.complete .approval-progress-fill {
  background: linear-gradient(
    135deg,
    var(--color-success) 0%,
    var(--color-success) 100%
  );
}

.kyc-actions-buttons {
  display: flex;
  gap: 15px;
  align-items: center;
  flex-wrap: wrap;
}

.btn-kyc-action {
  padding: 12px 30px;
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

.btn-approve-all {
  background: linear-gradient(
    135deg,
    var(--color-success) 0%,
    var(--color-success) 100%
  );
  color: white;
  box-shadow: 0 2px 8px rgba(72, 187, 120, 0.3);
}

.btn-approve-all:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4);
}

.btn-need-docs {
  background: linear-gradient(
    135deg,
    var(--color-warning) 0%,
    var(--color-warning) 100%
  );
  color: white;
  box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
}

.btn-need-docs:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
}

.btn-reject {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-reject:hover:not(:disabled) {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.btn-kyc-action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none !important;
  box-shadow: none !important;
}

.need-docs-section {
  background: var(--color-surface);
  border-radius: var(--radius-md);
  padding: 25px;
  border: 2px solid var(--color-warning-border);
  margin-top: 20px;
  max-height: 0;
  overflow: hidden;
  opacity: 0;
  transition:
    max-height 0.3s ease,
    opacity 0.3s ease,
    padding 0.3s ease,
    margin 0.3s ease;
  padding-top: 0;
  padding-bottom: 0;
  margin-top: 0;
  margin-bottom: 0;
}

.need-docs-section.show {
  max-height: 5000px;
  opacity: 1;
  padding: 25px;
  margin-top: 20px;
  margin-bottom: 20px;
  animation: slideDown 0.3s ease;
}

.need-docs-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--color-warning-border);
}

.need-docs-header h4 {
  font-size: 16px;
  color: var(--color-warning);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}

.need-docs-header i {
  color: var(--color-warning);
}

.need-docs-items-section {
  background: var(--color-warning-soft);
  border: 2px solid var(--color-warning-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 20px;
}

.need-docs-items-header h5 {
  font-size: 14px;
  color: var(--color-warning);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.need-docs-items-header h5 i {
  color: var(--color-warning);
}

.need-docs-items-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 15px;
}

.add-item-buttons {
  display: flex;
  gap: 10px;
}

.btn-add-item-small {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-add-question {
  background: var(--color-brand-solid);
  color: white;
}

.btn-add-question:hover {
  background: var(--color-brand-strong);
  transform: translateY(-1px);
}

.btn-add-document {
  background: var(--color-success-solid);
  color: white;
}

.btn-add-document:hover {
  background: var(--color-success-solid);
  transform: translateY(-1px);
}

.items-list {
  max-height: 400px;
  overflow-y: auto;
  padding-right: 5px;
}

.items-list::-webkit-scrollbar {
  width: 6px;
}

.items-list::-webkit-scrollbar-track {
  background: var(--color-surface-soft);
  border-radius: 3px;
}

.items-list::-webkit-scrollbar-thumb {
  background: var(--color-border-strong);
  border-radius: 3px;
}

.items-list::-webkit-scrollbar-thumb:hover {
  background: var(--color-faint);
}

.empty-selection-message {
  text-align: center;
  padding: 30px 20px;
  color: var(--color-faint);
  font-size: 14px;
  font-style: italic;
}

.empty-selection-message i {
  font-size: 32px;
  display: block;
  margin-bottom: 10px;
  opacity: 0.5;
}

.document-types-list {
  max-height: 300px;
  overflow-y: auto;
}

.selectable-document-item {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 12px 15px;
  margin-bottom: 10px;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 10px;
  position: relative;
}

.selectable-document-item:last-child {
  margin-bottom: 0;
}

.selectable-document-item:hover {
  border-color: var(--color-warning);
  background: var(--color-warning-soft);
}

.selectable-document-item.selected {
  background: var(--color-warning-soft);
  border-color: var(--color-warning);
  box-shadow: 0 2px 8px rgba(245, 158, 11, 0.2);
}

.question-checkbox {
  position: relative;
  display: inline-block;
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

.question-checkbox input[type="checkbox"] {
  position: absolute;
  opacity: 0;
  cursor: pointer;
  width: 18px;
  height: 18px;
  margin: 0;
}

.question-checkbox-mark {
  position: absolute;
  top: 0;
  left: 0;
  height: 18px;
  width: 18px;
  background-color: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: 4px;
  transition: all 0.3s ease;
}

.question-checkbox:hover .question-checkbox-mark {
  border-color: var(--color-warning);
}

.question-checkbox input[type="checkbox"]:checked ~ .question-checkbox-mark {
  background: var(--color-warning-solid);
  border-color: var(--color-warning);
}

.question-checkbox-mark:after {
  content: "";
  position: absolute;
  display: none;
  left: 5px;
  top: 1px;
  width: 4px;
  height: 9px;
  border: solid white;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
}

.question-checkbox
  input[type="checkbox"]:checked
  ~ .question-checkbox-mark:after {
  display: block;
}

.document-icon {
  width: 32px;
  height: 32px;
  background: linear-gradient(
    135deg,
    var(--color-warning) 0%,
    var(--color-warning) 100%
  );
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 14px;
  flex-shrink: 0;
}

.document-icon.icon-question {
  background: linear-gradient(
    135deg,
    var(--color-brand) 0%,
    var(--color-brand-strong) 100%
  );
}

.item-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.btn-edit-item {
  padding: 6px 10px;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-brand-soft);
  color: var(--color-brand);
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  flex-shrink: 0;
}

.btn-edit-item:hover {
  background: var(--color-brand-soft);
  transform: scale(1.1);
}

.btn-remove-item {
  padding: 6px 10px;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-danger-soft);
  color: var(--color-danger);
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  flex-shrink: 0;
}

.btn-remove-item:hover {
  background: var(--color-danger-border);
  transform: scale(1.1);
}

.selectable-document-content {
  flex: 1;
}

.selectable-document-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}

.need-docs-notes-field {
  grid-column: 1 / -1;
  margin-top: 10px;
  margin-bottom: 20px;
}

.need-docs-notes-field label {
  display: block;
  font-weight: 600;
  color: var(--color-warning);
  font-size: 14px;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.need-docs-notes-field label i {
  color: var(--color-warning);
}

.need-docs-notes-field textarea {
  width: 100%;
  padding: 12px 15px;
  border: 2px solid var(--color-warning-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  outline: none;
  transition: all 0.3s ease;
  resize: vertical;
  font-family: inherit;
  background: var(--color-surface);
}

.need-docs-notes-field textarea:focus {
  border-color: var(--color-warning);
  box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
}

.need-docs-summary {
  background: var(--color-warning-soft);
  border: 2px solid var(--color-warning-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-top: 20px;
}

.need-docs-summary h5 {
  font-size: 14px;
  color: var(--color-warning);
  font-weight: 600;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.need-docs-summary h5 i {
  color: var(--color-warning);
}

.need-docs-summary-items {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.need-docs-summary-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: var(--color-warning-solid);
  color: white;
  border-radius: var(--radius-xl);
  font-size: 14px;
  font-weight: 500;
}

.need-docs-summary-empty {
  color: var(--color-warning);
  font-size: 14px;
  font-style: italic;
}

.need-docs-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 20px;
  padding-top: 20px;
  border-top: 2px solid var(--color-warning-border);
}

.btn-cancel-docs {
  padding: 10px 20px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--color-border);
  color: var(--color-text);
}

.btn-cancel-docs:hover {
  background: var(--color-border-strong);
}

.btn-send-request {
  padding: 10px 20px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: linear-gradient(
    135deg,
    var(--color-warning) 0%,
    var(--color-warning) 100%
  );
  color: white;
  box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
}

.btn-send-request:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
}

.activity-timeline {
  background: var(--color-surface);
  border-radius: var(--radius-md);
  padding: 25px;
  border: 2px solid var(--color-border);
}

.timeline-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--color-border);
}

.timeline-header h3 {
  font-size: 16px;
  color: var(--color-ink);
  margin: 0;
}

.timeline-header i {
  color: var(--color-brand);
}

.timeline-items {
  position: relative;
}

.timeline-items::before {
  content: "";
  position: absolute;
  left: 20px;
  top: 0;
  bottom: 0;
  width: 2px;
  background: var(--color-border);
}

.timeline-item {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 15px;
  margin-bottom: 20px;
  padding-left: 0;
}

.timeline-item:last-child {
  margin-bottom: 0;
}

.timeline-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 16px;
  flex-shrink: 0;
  position: relative;
  z-index: 1;
  background: var(--color-brand-solid);
}

.timeline-icon.created {
  background: var(--color-success-solid);
}

.timeline-icon.submitted {
  background: var(--color-brand-solid);
}

.timeline-icon.approved {
  background: var(--color-success-solid);
}

.timeline-icon.rejected {
  background: var(--color-danger-solid);
}

.timeline-icon.assigned {
  background: #ed8936;
}

.timeline-content {
  flex: 1;
  padding-top: 8px;
}

.timeline-title {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
  margin-bottom: 4px;
}

.timeline-meta {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 14px;
  color: var(--color-muted);
}

.timeline-user {
  font-weight: 500;
}

.timeline-date {
  color: var(--color-faint);
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

/* Reject Modal Styles */
.reject-modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 2000;
  align-items: center;
  justify-content: center;
}

.reject-modal.show {
  display: flex;
}

.reject-modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 0;
  max-width: 600px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
  animation: modalSlideIn 0.3s ease;
}

.reject-modal-header {
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-danger-soft);
}

.reject-modal-header h3 {
  font-size: 20px;
  color: var(--color-danger);
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.reject-modal-header h3 i {
  font-size: 22px;
}

.reject-modal-close {
  background: none;
  border: none;
  font-size: 28px;
  color: var(--color-faint);
  cursor: pointer;
  transition: all 0.2s ease;
  line-height: 1;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-sm);
}

.reject-modal-close:hover {
  color: var(--color-danger);
  background: var(--color-danger-soft);
}

.reject-modal-body {
  padding: 30px;
}

.reject-field {
  margin-bottom: 20px;
}

.reject-field label {
  display: block;
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
  margin-bottom: 8px;
}

.reject-field textarea {
  width: 100%;
  padding: 12px 15px;
  border: 2px solid var(--color-danger-soft);
  border-radius: var(--radius-md);
  font-size: 14px;
  outline: none;
  transition: all 0.3s ease;
  resize: vertical;
  font-family: inherit;
  background: var(--color-surface);
}

.reject-field textarea:focus {
  border-color: var(--color-danger);
  box-shadow: 0 0 0 3px rgba(197, 48, 48, 0.1);
}

.reject-warning {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 15px;
  background: var(--color-danger-soft);
  border: 2px solid var(--color-danger-soft);
  border-radius: var(--radius-md);
  color: var(--color-danger);
  font-size: 14px;
}

.reject-warning i {
  font-size: 16px;
  flex-shrink: 0;
}

.reject-modal-footer {
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  background: var(--color-surface-soft);
}

.btn-reject-cancel {
  padding: 10px 20px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--color-border);
  color: var(--color-text);
}

.btn-reject-cancel:hover {
  background: var(--color-border-strong);
}

.btn-reject-confirm {
  padding: 10px 20px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--color-danger-solid);
  color: white;
}

.btn-reject-confirm:hover:not(:disabled) {
  background: #b91c1c;
}

.btn-reject-confirm:disabled {
  background: var(--color-faint);
  cursor: not-allowed;
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

/* Add Item Modal Styles */
.add-item-modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 2000;
  align-items: center;
  justify-content: center;
}

.add-item-modal.show {
  display: flex;
}

.add-item-modal-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 0;
  max-width: 600px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
  animation: modalSlideIn 0.3s ease;
}

.add-item-modal-header {
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-surface-soft);
}

.add-item-modal-header h3 {
  font-size: 20px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.add-item-modal-header h3 i {
  color: var(--color-brand);
  font-size: 22px;
}

.add-item-modal-close {
  background: none;
  border: none;
  font-size: 28px;
  color: var(--color-faint);
  cursor: pointer;
  transition: all 0.2s ease;
  line-height: 1;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-sm);
}

.add-item-modal-close:hover {
  color: var(--color-brand);
  background: var(--color-brand-soft);
}

.add-item-modal-body {
  padding: 30px;
  /*display: grid;*/
  /*grid-template-columns: 1fr 1fr;*/
  /*gap: 20px;*/
}

.add-item-field {
  display: flex;
  flex-direction: column;
}

.add-item-field label {
  display: block;
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
  margin-bottom: 8px;
}

.add-item-field input,
.add-item-field textarea,
.add-item-field select {
  width: 100%;
  padding: 10px 14px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  outline: none;
  transition: all 0.3s ease;
  font-family: inherit;
  background: var(--color-surface);
}

.add-item-field input:focus,
.add-item-field textarea:focus,
.add-item-field select:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.add-item-field textarea {
  resize: vertical;
  min-height: 60px;
}

.file-document-types-select {
  min-height: 120px;
  padding: 10px 14px;
}

.answer-options-container {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 15px;
}

.answer-option-row {
  display: flex;
  gap: 10px;
  margin-bottom: 10px;
}

.answer-option-row:last-of-type {
  margin-bottom: 0;
}

.answer-option-input {
  flex: 1;
  padding: 8px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  font-size: 14px;
  outline: none;
  transition: all 0.3s ease;
}

.answer-option-input:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 2px rgba(var(--color-brand-rgb), 0.1);
}

.btn-remove-option {
  background: var(--color-danger-solid);
  color: white;
  border: none;
  border-radius: 4px;
  padding: 0px 16px;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-remove-option:hover:not(:disabled) {
  background: var(--color-danger-solid);
}

.btn-remove-option:disabled {
  background: var(--color-border-strong);
  cursor: not-allowed;
}

.btn-add-option {
  background: var(--color-brand-solid);
  color: white;
  border: none;
  border-radius: var(--radius-sm);
  padding: 8px 12px;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 5px;
}

.btn-add-option:hover {
  background: var(--color-brand-strong);
}

.add-item-modal-footer {
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  background: var(--color-surface-soft);
}

.btn-modal-cancel {
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
  background: var(--color-border);
  color: var(--color-text);
}

.btn-modal-cancel:hover {
  background: var(--color-border-strong);
}

.btn-modal-save {
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
  background: var(--color-brand-solid);
  color: white;
}

.btn-modal-save:hover {
  background: var(--color-brand-strong);
}

/* Question Item Styles */
.selectable-question-item {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 12px 15px;
  margin-bottom: 10px;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: start;
  gap: 10px;
}

.selectable-question-item:last-child {
  margin-bottom: 0;
}

.selectable-question-item:hover {
  border-color: var(--color-warning);
  background: var(--color-warning-soft);
}

.selectable-question-item.selected {
  background: var(--color-warning-soft);
  border-color: var(--color-warning);
  box-shadow: 0 2px 8px rgba(245, 158, 11, 0.2);
}

.selectable-question-content {
  flex: 1;
}

.selectable-question-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 4px;
}

.selectable-question-type {
  display: inline-block;
  background: var(--color-brand-soft);
  color: var(--color-brand);
  padding: 2px 8px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
}

@media (max-width: 768px) {
  .detail-sections {
    grid-template-columns: 1fr;
  }

  .kyc-actions-buttons {
    flex-direction: column;
    align-items: stretch;
  }

  .btn-kyc-action {
    justify-content: center;
  }

  .need-docs-actions {
    flex-direction: column;
  }

  .reject-modal-content {
    width: 95%;
  }

  .reject-modal-footer {
    flex-direction: column;
  }

  .btn-reject-cancel,
  .btn-reject-confirm {
    width: 100%;
    justify-content: center;
  }
}
</style>
