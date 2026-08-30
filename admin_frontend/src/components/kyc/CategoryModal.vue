<template>
  <div class="modal-overlay show" @click="$emit('close')">
    <div class="modal" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          <i :class="isEditMode ? 'fas fa-edit' : 'fas fa-plus-circle'"></i>
          {{
            isEditMode
              ? t("kycTplCatModal_title_edit")
              : t("kycTplCatModal_title_create")
          }}
        </h2>
        <button class="modal-close" @click="$emit('close')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <form @submit.prevent="handleSubmit">
          <div class="form-group">
            <label class="form-label">{{
              t("kycTplCatModal_label_name")
            }}</label>
            <input
              type="text"
              class="form-input"
              v-model="formData.name"
              :placeholder="t('kycTplCatModal_placeholder_name')"
              required
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{
              t("kycTplCatModal_label_description")
            }}</label>
            <textarea
              class="form-textarea"
              v-model="formData.description"
              :placeholder="t('kycTplCatModal_placeholder_description')"
            ></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">{{
              t("kycTplCatModal_label_order")
            }}</label>
            <input
              type="number"
              class="form-input"
              v-model="formData.order"
              placeholder="1"
              min="1"
            />
          </div>

          <div class="form-group">
            <div class="form-checkbox">
              <input
                type="checkbox"
                id="categoryActive"
                v-model="formData.isActive"
              />
              <label for="categoryActive">{{
                t("kycTplCatModal_label_active")
              }}</label>
            </div>
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" @click="$emit('close')">
          {{ t("kycTplModal_btn_cancel") }}
        </button>
        <button type="button" class="btn btn-primary" @click="handleSubmit">
          <i class="fas fa-save"></i>
          {{
            isEditMode
              ? t("kycTplCatModal_btn_update")
              : t("kycTplCatModal_btn_create")
          }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { kycCategoryService } from "@/services/kycTemplateService";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams } = useAdminI18n();

const props = defineProps({
  templateId: {
    type: Number,
    required: true,
  },
  category: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(["close", "save"]);

const isEditMode = computed(() => !!props.category);

const formData = ref({
  name: "",
  description: "",
  order: 1,
  isActive: true,
});

// 监听category prop变化，初始化表单数据
watch(
  () => props.category,
  (newCategory) => {
    if (newCategory) {
      formData.value = {
        name: newCategory.categoryName || "",
        description: newCategory.description || "",
        order: newCategory.displayOrder || 1,
        isActive:
          newCategory.isActive !== undefined ? newCategory.isActive : true,
      };
    } else {
      // 重置表单
      formData.value = {
        name: "",
        description: "",
        order: 1,
        isActive: true,
      };
    }
  },
  { immediate: true },
);

const handleSubmit = async () => {
  if (!formData.value.name) {
    alert(t("kycTplCatModal_alert_name"));
    return;
  }

  const descStr = formData.value.description || "—";
  try {
    let response;
    if (isEditMode.value) {
      // 编辑模式：更新分类
      const payload = {
        categoryName: formData.value.name,
        description: formData.value.description || null,
        displayOrder: formData.value.order || 1,
        isActive: formData.value.isActive ? 1 : 0,
      };
      response = await kycCategoryService.updateCategory(
        props.category.id,
        payload,
      );
      if (response.success) {
        alert(
          tParams("kycTplCatModal_alert_updateOk", "", {
            name: formData.value.name,
            desc: descStr,
          }),
        );
        emit("save");
      } else {
        alert(
          tParams("kycTplCatModal_alert_updateFailed", "", {
            msg: response.message || t("common_unknownError"),
          }),
        );
      }
    } else {
      // 创建模式：创建新分类
      const payload = {
        ...formData.value,
        templateId: props.templateId,
      };
      response = await kycCategoryService.createCategory(payload);
      if (response.success) {
        alert(
          tParams("kycTplCatModal_alert_createOk", "", {
            name: formData.value.name,
            desc: descStr,
          }),
        );
        emit("save");
      } else {
        alert(
          tParams("kycTplCatModal_alert_createFailed", "", {
            msg: response.message || t("common_unknownError"),
          }),
        );
      }
    }
  } catch (error) {
    console.error(
      `Failed to ${isEditMode.value ? "update" : "create"} category:`,
      error,
    );
    alert(
      isEditMode.value
        ? t("kycTplCatModal_alert_updateErr")
        : t("kycTplCatModal_alert_createErr"),
    );
  }
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-overlay.show {
  display: flex;
}

.modal {
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

.modal-header {
  padding: 25px 30px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-surface-soft);
}

.modal-title {
  font-size: 20px;
  font-weight: 600;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.modal-title i {
  color: var(--color-brand);
}

.modal-close {
  background: var(--color-border);
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  font-size: 18px;
  color: var(--color-text);
}

.modal-close:hover {
  background: var(--color-brand-solid);
  color: white;
}

.modal-body {
  padding: 30px;
}

.form-group {
  margin-bottom: 25px;
}

.form-label {
  display: block;
  margin-bottom: 10px;
  color: var(--color-ink);
  font-weight: 600;
  font-size: 14px;
}

.form-input,
.form-textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
  background: var(--color-surface);
}

.form-input:focus,
.form-textarea:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.form-textarea {
  resize: vertical;
  min-height: 80px;
}

.form-checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
}

.form-checkbox input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: var(--color-brand);
  cursor: pointer;
}

.form-checkbox label {
  cursor: pointer;
  color: var(--color-text);
  font-size: 14px;
}

.modal-footer {
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  background: var(--color-surface-soft);
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

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.btn-secondary {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-secondary:hover {
  background: var(--color-border-strong);
}
</style>
