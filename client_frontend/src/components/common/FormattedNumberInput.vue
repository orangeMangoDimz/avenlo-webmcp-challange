<template>
  <input
    ref="inputRef"
    type="text"
    :class="inputClass"
    :placeholder="placeholder"
    :disabled="disabled"
    :required="required"
    :value="displayValue"
    @input="handleInput"
    @focus="handleFocus"
    @blur="handleBlur"
  />
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { formatNumber } from "@/utils/helpers";

const props = defineProps({
  modelValue: {
    type: [Number, String],
    default: null,
  },
  decimals: {
    type: Number,
    default: 0,
  },
  placeholder: {
    type: String,
    default: "",
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  required: {
    type: Boolean,
    default: false,
  },
  max: {
    type: Number,
    default: null,
  },
  inputClass: {
    type: String,
    default: "",
  },
});

const emit = defineEmits(["update:modelValue"]);

const inputRef = ref(null);
const isFocused = ref(false);
const rawValue = ref("");

// 计算显示值：如果正在输入则显示原始值，否则显示格式化后的值
const displayValue = computed(() => {
  if (isFocused.value) {
    return rawValue.value;
  }
  if (
    props.modelValue === null ||
    props.modelValue === undefined ||
    props.modelValue === ""
  ) {
    return "";
  }
  return formatNumber(Number(props.modelValue), props.decimals);
});

// 监听外部值变化
watch(
  () => props.modelValue,
  (newVal) => {
    if (!isFocused.value) {
      if (newVal === null || newVal === undefined || newVal === "") {
        rawValue.value = "";
      } else {
        rawValue.value = String(newVal);
      }
    }
  },
  { immediate: true },
);

// 处理输入
const handleInput = (event) => {
  const input = event.target.value;

  // 移除所有非数字字符（除了小数点）
  let cleaned = input.replace(/[^\d.]/g, "");

  // 确保只有一个小数点
  const parts = cleaned.split(".");
  if (parts.length > 2) {
    cleaned = parts[0] + "." + parts.slice(1).join("");
  }

  // 限制小数位数
  if (parts.length === 2 && parts[1].length > props.decimals) {
    cleaned = parts[0] + "." + parts[1].substring(0, props.decimals);
  }

  rawValue.value = cleaned;

  // 发送数值给父组件
  const parsedValue =
    cleaned === ""
      ? null
      : props.decimals > 0
        ? parseFloat(cleaned)
        : parseInt(cleaned);
  if (parsedValue === null || isNaN(parsedValue)) {
    emit("update:modelValue", null);
    return;
  }

  const hasMax =
    props.max !== null && props.max !== undefined && props.max !== "";
  const maxValue = hasMax ? Number(props.max) : null;
  const normalizedValue = Number.isFinite(maxValue)
    ? Math.min(parsedValue, maxValue)
    : parsedValue;

  if (normalizedValue !== parsedValue) {
    rawValue.value = String(normalizedValue);
  }

  emit("update:modelValue", normalizedValue);
};

// 获得焦点时显示原始值
const handleFocus = () => {
  isFocused.value = true;
  if (
    props.modelValue !== null &&
    props.modelValue !== undefined &&
    props.modelValue !== ""
  ) {
    rawValue.value = String(props.modelValue);
  } else {
    rawValue.value = "";
  }
  // 选中所有文本以便快速替换
  setTimeout(() => {
    if (inputRef.value) {
      inputRef.value.select();
    }
  }, 0);
};

// 失去焦点时格式化显示
const handleBlur = () => {
  isFocused.value = false;
  // 确保rawValue是最新的数值
  if (
    props.modelValue !== null &&
    props.modelValue !== undefined &&
    props.modelValue !== ""
  ) {
    rawValue.value = String(props.modelValue);
  } else {
    rawValue.value = "";
  }
};
</script>

<style scoped>
input {
  width: 100%;
  box-sizing: border-box;
}
</style>
