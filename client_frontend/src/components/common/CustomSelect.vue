<template>
  <div ref="rootRef" class="custom-select" :class="{ disabled }">
    <button
      type="button"
      class="custom-select__trigger"
      :class="{ open: isOpen, placeholder: !selectedLabel, disabled, error }"
      :disabled="disabled"
      @click="toggleOpen"
    >
      <span class="custom-select__trigger-text">{{
        selectedLabel || placeholder
      }}</span>
      <i class="fas fa-chevron-down custom-select__trigger-icon"></i>
    </button>

    <div
      v-if="isOpen"
      class="custom-select__menu"
      :class="`custom-select__menu--${menuDirection}`"
    >
      <div v-if="searchable" class="custom-select__search">
        <i class="fas fa-search"></i>
        <input
          v-model="searchQuery"
          type="text"
          :placeholder="searchPlaceholder"
          @click.stop
        />
      </div>

      <div class="custom-select__options" :style="{ maxHeight: maxMenuHeight }">
        <template v-if="normalizedGroups.length > 0">
          <template
            v-for="group in filteredGroups"
            :key="group.label || 'group'"
          >
            <div v-if="group.label" class="custom-select__group">
              {{ group.label }}
            </div>
            <button
              v-for="option in group.options"
              :key="String(option.value)"
              type="button"
              class="custom-select__option"
              :class="{ selected: isSelected(option.value) }"
              :disabled="option.disabled"
              @click="selectOption(option)"
            >
              {{ option.label }}
            </button>
          </template>
        </template>

        <template v-else>
          <button
            v-for="option in filteredOptions"
            :key="String(option.value)"
            type="button"
            class="custom-select__option"
            :class="{ selected: isSelected(option.value) }"
            :disabled="option.disabled"
            @click="selectOption(option)"
          >
            {{ option.label }}
          </button>
        </template>

        <div v-if="hasNoResults" class="custom-select__empty">
          {{ emptyText }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";

const props = defineProps({
  modelValue: {
    type: [String, Number, Boolean, null],
    default: "",
  },
  options: {
    type: Array,
    default: () => [],
  },
  groups: {
    type: Array,
    default: () => [],
  },
  placeholder: {
    type: String,
    default: "Select an option",
  },
  searchable: {
    type: Boolean,
    default: false,
  },
  searchPlaceholder: {
    type: String,
    default: "Search...",
  },
  emptyText: {
    type: String,
    default: "No options found",
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  error: {
    type: Boolean,
    default: false,
  },
  menuDirection: {
    type: String,
    default: "down",
  },
  maxMenuHeight: {
    type: String,
    default: "260px",
  },
});

const emit = defineEmits(["update:modelValue", "change"]);

const rootRef = ref(null);
const isOpen = ref(false);
const searchQuery = ref("");

const normalizeOption = (option) => {
  if (option && typeof option === "object") {
    return {
      label: option.label ?? option.name ?? String(option.value ?? ""),
      value: option.value ?? option.code ?? option.id ?? option.label ?? "",
      disabled: Boolean(option.disabled),
    };
  }

  return {
    label: String(option ?? ""),
    value: option,
    disabled: false,
  };
};

const normalizedOptions = computed(() => props.options.map(normalizeOption));
const normalizedGroups = computed(() =>
  props.groups.map((group) => ({
    label: group.label || "",
    options: Array.isArray(group.options)
      ? group.options.map(normalizeOption)
      : [],
  })),
);

const selectedLabel = computed(() => {
  const value = props.modelValue;
  const flatMatch = normalizedOptions.value.find(
    (option) => option.value === value,
  );
  if (flatMatch) return flatMatch.label;

  for (const group of normalizedGroups.value) {
    const match = group.options.find((option) => option.value === value);
    if (match) return match.label;
  }

  return "";
});

const matchesQuery = (option) =>
  option.label.toLowerCase().includes(searchQuery.value.trim().toLowerCase());

const filteredOptions = computed(() => {
  if (!searchQuery.value.trim()) return normalizedOptions.value;
  return normalizedOptions.value.filter(matchesQuery);
});

const filteredGroups = computed(() =>
  normalizedGroups.value
    .map((group) => ({
      ...group,
      options: searchQuery.value.trim()
        ? group.options.filter(matchesQuery)
        : group.options,
    }))
    .filter((group) => group.options.length > 0),
);

const hasNoResults = computed(() => {
  if (normalizedGroups.value.length > 0)
    return filteredGroups.value.length === 0;
  return filteredOptions.value.length === 0;
});

const isSelected = (value) => props.modelValue === value;

const closeMenu = () => {
  isOpen.value = false;
  searchQuery.value = "";
};

const toggleOpen = () => {
  if (props.disabled) return;
  isOpen.value = !isOpen.value;
  if (!isOpen.value) {
    searchQuery.value = "";
  }
};

const selectOption = (option) => {
  if (option.disabled) return;
  emit("update:modelValue", option.value);
  emit("change", option.value, option);
  closeMenu();
};

const handleClickOutside = (event) => {
  if (!rootRef.value?.contains(event.target)) {
    closeMenu();
  }
};

watch(
  () => props.disabled,
  (disabled) => {
    if (disabled) closeMenu();
  },
);

onMounted(() => {
  document.addEventListener("click", handleClickOutside, true);
});

onBeforeUnmount(() => {
  document.removeEventListener("click", handleClickOutside, true);
});
</script>

<style scoped>
.custom-select {
  position: relative;
}

.custom-select.disabled {
  opacity: 0.7;
}

.custom-select__trigger {
  width: 100%;
  min-height: 46px;
  padding: 12px 14px;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-md);
  background: #fff;
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
  text-align: left;
}

.custom-select__trigger.placeholder {
  color: #94a3b8;
}

.custom-select__trigger.open,
.custom-select__trigger:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.16);
}

.custom-select__trigger.error {
  border-color: var(--color-danger);
  box-shadow: 0 0 0 3px rgba(229, 62, 62, 0.12);
}

.custom-select__trigger.disabled {
  cursor: not-allowed;
}

.custom-select__trigger-text {
  min-width: 0;
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.custom-select__trigger-icon {
  font-size: 13px;
  color: #64748b;
  flex-shrink: 0;
}

.custom-select__menu {
  position: absolute;
  left: 0;
  right: 0;
  z-index: 40;
  background: #fff;
  border: 1px solid #dbe3ef;
  border-radius: var(--radius-lg);
  box-shadow: 0 18px 35px rgba(15, 23, 42, 0.12);
  overflow: hidden;
}

.custom-select__menu--down {
  top: calc(100% + 8px);
}

.custom-select__menu--up {
  bottom: calc(100% + 8px);
}

.custom-select__search {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-bottom: 1px solid var(--color-border);
  background: var(--color-surface-soft);
}

.custom-select__search i {
  color: #94a3b8;
}

.custom-select__search input {
  width: 100%;
  border: 0;
  padding: 0;
  box-shadow: none;
  background: transparent;
  outline: none;
}

.custom-select__options {
  overflow-y: auto;
}

.custom-select__group {
  padding: 10px 14px 6px;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #94a3b8;
  background: var(--color-surface-soft);
}

.custom-select__option {
  width: 100%;
  border: 0;
  background: #fff;
  padding: 11px 14px;
  text-align: left;
  font-size: 13px;
  color: var(--color-ink);
  cursor: pointer;
  transition:
    background 0.2s ease,
    color 0.2s ease;
}

.custom-select__option:hover,
.custom-select__option.selected {
  background: var(--color-brand-soft);
  color: var(--color-brand-strong);
}

.custom-select__option:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.custom-select__empty {
  padding: 14px;
  font-size: 13px;
  color: var(--color-muted);
}
</style>
