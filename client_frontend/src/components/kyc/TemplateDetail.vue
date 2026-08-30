<template>
  <div class="detail-content">
    <div class="detail-sections">
      <!-- Template Info Section -->
      <div class="detail-section">
        <h3>
          <div class="section-header">
            <div class="section-title">
              <i class="fas fa-info-circle"></i> Template Information
            </div>
            <button
              class="btn-save"
              :class="{
                active: hasTemplateChanges,
                disabled: !hasTemplateChanges,
              }"
              @click="saveTemplateInfo"
            >
              <i class="fas fa-save"></i> Save
            </button>
          </div>
        </h3>
        <div class="detail-field">
          <span class="detail-label">Template Name</span>
          <div class="detail-value-wrapper">
            <span
              class="detail-value"
              :class="{ editable: editingField === 'name' }"
              :contenteditable="editingField === 'name'"
              @input="checkTemplateChanges"
              ref="templateNameRef"
              >{{ localTemplate.name }}</span
            >
            <button class="btn-edit" @click="enableEdit('name')">
              <i class="fas fa-edit"></i>
            </button>
          </div>
        </div>
        <div class="detail-field">
          <span class="detail-label">Description</span>
          <div class="detail-value-wrapper">
            <span
              class="detail-value"
              :class="{ editable: editingField === 'description' }"
              :contenteditable="editingField === 'description'"
              @input="checkTemplateChanges"
              ref="templateDescRef"
              >{{ localTemplate.description }}</span
            >
            <button class="btn-edit" @click="enableEdit('description')">
              <i class="fas fa-edit"></i>
            </button>
          </div>
        </div>
        <div class="detail-field">
          <span class="detail-label">Total Questions</span>
          <span class="detail-value"
            >{{ localTemplate.questionCount || 0 }} Questions</span
          >
        </div>
        <div class="detail-field">
          <span class="detail-label">Active Rules</span>
          <span class="detail-value"
            >{{ formatNumber(localTemplate.ruleCount || 0) }} Rules</span
          >
        </div>
        <div class="detail-field">
          <span class="detail-label">Status</span>
          <span class="status-badge" :class="localTemplate.status">{{
            formatStatus(localTemplate.status)
          }}</span>
        </div>
        <div class="detail-field">
          <span class="detail-label">Last Updated</span>
          <span class="detail-value">{{
            formatDate(localTemplate.updatedAt)
          }}</span>
        </div>
        <div
          class="detail-field"
          style="
            border-top: 1px solid var(--color-border);
            padding-top: 15px;
            margin-top: 10px;
          "
        >
          <span
            class="detail-label"
            style="color: var(--color-ink); font-size: 14px"
          >
            <i class="fas fa-plug"></i> Enable Third-party KYC System
          </span>
          <div
            class="toggle-switch"
            :class="{ active: thirdPartyKyc }"
            @click="toggleThirdPartyKyc"
          ></div>
        </div>
        <div class="detail-field">
          <span
            class="detail-label"
            style="color: var(--color-ink); font-size: 14px"
          >
            <i class="fas fa-user-check"></i> Auto-Approve KYC
          </span>
          <div
            class="toggle-switch"
            :class="{ active: autoApprove }"
            @click="toggleAutoApprove"
          ></div>
        </div>
        <div v-if="autoApprove" class="auto-approve-note">
          <i class="fas fa-exclamation-triangle"></i>
          <strong>Warning:</strong> Users registered with this template will be
          automatically approved without manual review.
        </div>
      </div>

      <!-- Applied Countries Section -->
      <div class="detail-section">
        <h3>
          <div class="section-header">
            <div class="section-title">
              <i class="fas fa-globe"></i> Applied Countries
            </div>
            <button class="btn-edit-section" @click="openCountriesModal">
              <i class="fas fa-edit"></i> Edit
            </button>
          </div>
        </h3>
        <div class="detail-field">
          <span class="detail-label">Country List</span>
        </div>
        <div class="countries-display">
          <span
            v-for="(country, idx) in localTemplate.countries"
            :key="idx"
            class="country-tag"
          >
            <i class="fas fa-flag"></i> {{ country.name || country }}
          </span>
        </div>
      </div>
    </div>

    <!-- KYC Questions Section -->
    <div v-if="!thirdPartyKyc" class="detail-section full-width">
      <h3>
        <div class="section-header">
          <div class="section-title">
            <i class="fas fa-list"></i> KYC Questions ({{ questions.length }})
          </div>
          <button class="btn btn-success" @click="openCategoryModal">
            <i class="fas fa-plus"></i> New Category
          </button>
        </div>
      </h3>
      <QuestionList
        :template-id="template.templateId"
        :questions="questions"
        :categories="categories"
        @refresh="loadTemplateData"
      />
    </div>

    <!-- Rules Configuration Section -->
    <div v-if="!thirdPartyKyc" class="detail-section full-width">
      <h3>
        <div class="section-header">
          <div class="section-title">
            <i class="fas fa-sitemap"></i> Conditional Logic Rules ({{
              formatNumber(rules.length)
            }})
          </div>
          <button class="btn btn-success" @click="openRuleModal">
            <i class="fas fa-plus"></i> Add Rule
          </button>
        </div>
      </h3>
      <RuleList
        :template-id="template.templateId"
        :rules="rules"
        @refresh="loadTemplateData"
      />
    </div>

    <!-- Document Requirements Section -->
    <div v-if="!thirdPartyKyc" class="detail-section full-width">
      <h3>
        <div class="section-header">
          <div class="section-title">
            <i class="fas fa-file-signature"></i> Document Signature
            Requirements
          </div>
        </div>
      </h3>
      <DocumentList
        :template-id="template.templateId"
        :documents="documents"
        @refresh="loadTemplateData"
      />
    </div>

    <!-- Countries Modal -->
    <CountriesModal
      v-if="showCountriesModal"
      :template-id="template.templateId"
      :selected-countries="localTemplate.countries"
      @close="showCountriesModal = false"
      @save="handleCountriesSave"
    />

    <!-- Category Modal -->
    <CategoryModal
      v-if="showCategoryModal"
      :template-id="template.templateId"
      @close="showCategoryModal = false"
      @save="handleCategorySave"
    />

    <!-- Rule Modal -->
    <RuleModal
      v-if="showRuleModal"
      :template-id="template.templateId"
      :questions="choiceQuestions"
      @close="showRuleModal = false"
      @save="handleRuleSave"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick } from "vue";
import { kycTemplateService } from "@/services/kycTemplateService";
import QuestionList from "./QuestionList.vue";
import RuleList from "./RuleList.vue";
import DocumentList from "./DocumentList.vue";
import CountriesModal from "./CountriesModal.vue";
import CategoryModal from "./CategoryModal.vue";
import RuleModal from "./RuleModal.vue";
import { formatNumber } from "@/utils/helpers";

const props = defineProps({
  template: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(["refresh", "close"]);

// Refs
const templateNameRef = ref(null);
const templateDescRef = ref(null);

// State
const localTemplate = reactive({ ...props.template });
const originalTemplate = { ...props.template };
const editingField = ref(null);
const hasTemplateChanges = ref(false);
const thirdPartyKyc = ref(props.template.thirdPartyKyc || false);
const autoApprove = ref(props.template.autoApprove || false);

const questions = ref([]);
const categories = ref([]);
const rules = ref([]);
const documents = ref([]);
const choiceQuestions = ref([]);

const showCountriesModal = ref(false);
const showCategoryModal = ref(false);
const showRuleModal = ref(false);

// Methods
const loadTemplateData = async () => {
  try {
    const [questionsRes, categoriesRes, rulesRes] = await Promise.all([
      kycTemplateService.getTemplateQuestions(props.template.templateId),
      kycTemplateService.getTemplateCategories(props.template.templateId),
      kycTemplateService.getTemplateRules(props.template.templateId),
    ]);

    if (questionsRes.success) questions.value = questionsRes.data;
    if (categoriesRes.success) categories.value = categoriesRes.data;
    if (rulesRes.success) rules.value = rulesRes.data;

    // Load choice questions for rule configuration
    const choiceRes = await kycTemplateService.getChoiceQuestions(
      props.template.templateId,
    );
    if (choiceRes.success) choiceQuestions.value = choiceRes.data;
  } catch (error) {
    console.error("Failed to load template data:", error);
  }
};

const enableEdit = (field) => {
  editingField.value = field;
  nextTick(() => {
    if (field === "name" && templateNameRef.value) {
      templateNameRef.value.focus();
      selectAllText(templateNameRef.value);
    } else if (field === "description" && templateDescRef.value) {
      templateDescRef.value.focus();
      selectAllText(templateDescRef.value);
    }
  });
};

const selectAllText = (element) => {
  const range = document.createRange();
  range.selectNodeContents(element);
  const selection = window.getSelection();
  selection.removeAllRanges();
  selection.addRange(range);
};

const checkTemplateChanges = () => {
  const currentName = templateNameRef.value?.textContent || "";
  const currentDesc = templateDescRef.value?.textContent || "";

  hasTemplateChanges.value =
    currentName !== originalTemplate.name ||
    currentDesc !== originalTemplate.description;
};

const saveTemplateInfo = async () => {
  if (!hasTemplateChanges.value) return;

  const updatedData = {
    name: templateNameRef.value?.textContent || localTemplate.name,
    description:
      templateDescRef.value?.textContent || localTemplate.description,
  };

  try {
    const response = await kycTemplateService.updateTemplate(
      props.template.templateId,
      updatedData,
    );
    if (response.success) {
      localTemplate.name = updatedData.name;
      localTemplate.description = updatedData.description;
      originalTemplate.name = updatedData.name;
      originalTemplate.description = updatedData.description;
      hasTemplateChanges.value = false;
      editingField.value = null;
      alert("✓ Template information saved successfully!");
      emit("refresh");
    } else {
      alert(`Failed to save template: ${response.message}`);
    }
  } catch (error) {
    console.error("Failed to save template:", error);
    alert("Failed to save template. Please try again.");
  }
};

const toggleThirdPartyKyc = async () => {
  const newValue = !thirdPartyKyc.value;

  if (newValue) {
    if (
      confirm(
        "⚠️ Enable Third-party KYC System?\n\nEnabling this will use an external KYC verification system and hide the custom questions, rules, and document requirements.\n\nAre you sure you want to continue?",
      )
    ) {
      thirdPartyKyc.value = newValue;
      await updateTemplateSettings();
      alert("✓ Third-party KYC system enabled!");
    }
  } else {
    if (
      confirm(
        "Disable Third-party KYC System?\n\nThis will restore the custom KYC questions, rules, and document requirements.\n\nDo you want to continue?",
      )
    ) {
      thirdPartyKyc.value = newValue;
      await updateTemplateSettings();
      alert("✓ Third-party KYC system disabled!");
    }
  }
};

const toggleAutoApprove = async () => {
  const newValue = !autoApprove.value;

  if (newValue) {
    if (
      confirm(
        "⚠️ Enable Auto-Approve KYC?\n\nWhen enabled, users registering with this template will be automatically approved without manual review.\n\nThis may pose security risks. Are you sure you want to continue?",
      )
    ) {
      autoApprove.value = newValue;
      await updateTemplateSettings();
      alert("✓ Auto-Approve KYC enabled!");
    }
  } else {
    if (
      confirm(
        "Disable Auto-Approve KYC?\n\nUsers will require manual review after registration.\n\nDo you want to continue?",
      )
    ) {
      autoApprove.value = newValue;
      await updateTemplateSettings();
      alert("✓ Auto-Approve KYC disabled!");
    }
  }
};

const updateTemplateSettings = async () => {
  try {
    const response = await kycTemplateService.updateTemplate(
      props.template.templateId,
      {
        thirdPartyKyc: thirdPartyKyc.value,
        autoApprove: autoApprove.value,
      },
    );

    if (!response.success) {
      alert(`Failed to update settings: ${response.message}`);
    }
  } catch (error) {
    console.error("Failed to update settings:", error);
    alert("Failed to update settings. Please try again.");
  }
};

const openCountriesModal = () => {
  showCountriesModal.value = true;
};

const handleCountriesSave = async (countries) => {
  localTemplate.countries = countries;
  showCountriesModal.value = false;
  emit("refresh");
};

const openCategoryModal = () => {
  showCategoryModal.value = true;
};

const handleCategorySave = async () => {
  showCategoryModal.value = false;
  await loadTemplateData();
  emit("refresh");
};

const openRuleModal = () => {
  showRuleModal.value = true;
};

const handleRuleSave = async () => {
  showRuleModal.value = false;
  await loadTemplateData();
  emit("refresh");
};

const formatStatus = (status) => {
  const statusMap = {
    active: "Active",
    inactive: "Inactive",
    draft: "Draft",
  };
  return statusMap[status] || status;
};

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
};

// Lifecycle
onMounted(() => {
  loadTemplateData();
});
</script>

<style scoped>
.detail-content {
  padding: 30px;
  background: var(--color-surface-soft);
}

.detail-sections {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 20px;
}

.detail-section {
  background: var(--color-surface);
  border-radius: var(--radius-md);
  padding: 25px;
  border: 1px solid var(--color-border);
}

.detail-section h3 {
  font-size: 16px;
  color: var(--color-ink);
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 1px solid var(--color-border);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 10px;
}

.section-title i {
  color: var(--color-brand);
}

.detail-section.full-width {
  grid-column: 1 / -1;
}

.detail-field {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid var(--color-border);
}

.detail-field:last-child {
  border-bottom: none;
}

.detail-label {
  font-weight: 600;
  color: var(--color-muted);
  font-size: 14px;
}

.detail-value {
  color: var(--color-ink);
  font-size: 14px;
  font-weight: 500;
  text-align: right;
}

.detail-value-wrapper {
  display: flex;
  align-items: center;
  gap: 10px;
}

.detail-value.editable {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  cursor: text;
  padding: 4px 8px;
  border-radius: 4px;
  min-width: 150px;
}

.detail-value.editable:focus {
  border-color: var(--color-brand);
  box-shadow: none;
}

.detail-value[contenteditable="true"] {
  border-color: var(--color-brand);
  background: var(--color-surface-soft);
}

.btn-edit {
  background: none;
  border: none;
  color: var(--color-faint);
  cursor: pointer;
  font-size: 14px;
  padding: 4px;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-edit:hover {
  color: var(--color-brand);
  transform: scale(1.1);
}

.btn-save {
  padding: 6px 16px;
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

.btn-save.disabled {
  background: var(--color-border);
  color: var(--color-faint);
  cursor: not-allowed;
}

.btn-save.active {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.btn-save.active:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.btn-edit-section {
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
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.btn-edit-section:hover {
  background: var(--color-brand-solid);
  color: white;
  transform: translateY(-1px);
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-badge.active {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-badge.inactive {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.status-badge.draft {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.toggle-switch {
  position: relative;
  width: 50px;
  height: 26px;
  background: var(--color-border-strong);
  border-radius: 13px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.toggle-switch.active {
  background: var(--color-success-solid);
}

.toggle-switch::after {
  content: "";
  position: absolute;
  top: 3px;
  left: 3px;
  width: 20px;
  height: 20px;
  background: var(--color-surface);
  border-radius: 50%;
  transition: all 0.3s ease;
}

.toggle-switch.active::after {
  left: 27px;
}

.auto-approve-note {
  margin-top: 10px;
  padding: 12px;
  background: var(--color-warning-soft);
  border: 1px solid var(--color-warning-border);
  border-radius: var(--radius-sm);
  font-size: 14px;
  color: var(--color-warning);
}

.auto-approve-note i {
  margin-right: 6px;
}

.countries-display {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}

.country-tag {
  background: var(--color-brand-soft);
  color: var(--color-brand);
  padding: 4px 10px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.btn {
  padding: 12px 20px;
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

.btn-success {
  background: var(--color-success-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(72, 187, 120, 0.3);
}

.btn-success:hover {
  background: var(--color-success-solid);
  transform: translateY(-2px);
}

@media (max-width: 768px) {
  .detail-sections {
    grid-template-columns: 1fr;
  }
}
</style>
