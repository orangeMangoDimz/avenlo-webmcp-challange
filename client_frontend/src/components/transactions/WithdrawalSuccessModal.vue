<template>
  <div class="modal-overlay" @click.self="emit('close')">
    <div class="success-modal">
      <div class="success-icon">
        <i class="fas fa-check"></i>
      </div>
      <h3>{{ resolvedTitle }}</h3>
      <p>{{ resolvedMessage }}</p>
      <button type="button" class="close-btn" @click="emit('close')">
        {{ t("transClose", "Close") }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { useLanguageStore } from "@/stores/language";
import { computed } from "vue";

const languageStore = useLanguageStore();
const t = (key, fallback = "") => languageStore.t(key, fallback);

const props = defineProps({
  title: {
    type: String,
    default: "",
  },
  message: {
    type: String,
    default: "",
  },
});

const resolvedTitle = computed(
  () =>
    props.title ||
    t("transVerificationSubmittedTitle", "Verification submitted"),
);
const resolvedMessage = computed(
  () =>
    props.message ||
    t(
      "transWithdrawalVerificationSubmitted",
      "Your withdrawal address verification has been submitted. Please wait for review.",
    ),
);

const emit = defineEmits(["close"]);
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.48);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  z-index: 1000;
}

.success-modal {
  width: min(100%, 460px);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 20px;
  box-shadow: 0 24px 64px rgba(15, 23, 42, 0.22);
  padding: 32px 28px;
  text-align: center;
}

.success-icon {
  width: 68px;
  height: 68px;
  margin: 0 auto 18px;
  border-radius: 999px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(
    135deg,
    var(--color-success-solid),
    var(--color-success-solid)
  );
  color: #fff;
  font-size: 28px;
}

h3 {
  margin: 0 0 10px;
  color: var(--color-brand);
  font-size: 24px;
}

p {
  margin: 0;
  color: var(--color-muted);
  line-height: 1.6;
}

.close-btn {
  margin-top: 24px;
  border: none;
  border-radius: var(--radius-md);
  padding: 12px 20px;
  background: linear-gradient(
    135deg,
    var(--color-brand-solid),
    var(--color-purple-solid)
  );
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}
</style>
