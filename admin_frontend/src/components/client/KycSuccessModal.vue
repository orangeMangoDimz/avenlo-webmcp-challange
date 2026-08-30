<template>
  <div class="modal-overlay show">
    <div class="success-modal">
      <div class="success-icon">
        <i class="fas fa-check"></i>
      </div>
      <h2>Application Submitted!</h2>
      <p>
        Thank you for completing your KYC verification. Your application has
        been successfully submitted and is now under review.
      </p>
      <p>
        We will notify you via email once the review is complete. This typically
        takes 1-2 business days.
      </p>
      <div class="countdown-text">
        Redirecting to dashboard in
        <span class="countdown-number">{{ countdown }}</span> seconds...
      </div>
      <button class="btn btn-primary" @click="handleClose">
        <i class="fas fa-home"></i> Go to Dashboard Now
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";

const emit = defineEmits(["close"]);
const router = useRouter();

const countdown = ref(5);
let timer = null;

const handleClose = () => {
  if (timer) clearInterval(timer);
  emit("close");
  router.push("/client/dashboard");
};

onMounted(() => {
  timer = setInterval(() => {
    countdown.value--;
    if (countdown.value <= 0) {
      handleClose();
    }
  }, 1000);
});

onUnmounted(() => {
  if (timer) clearInterval(timer);
});
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

.success-modal {
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  padding: 40px;
  max-width: 500px;
  width: 90%;
  text-align: center;
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

.success-icon {
  width: 80px;
  height: 80px;
  background: linear-gradient(
    135deg,
    var(--color-success) 0%,
    var(--color-success) 100%
  );
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 25px;
  animation: scaleIn 0.5s ease;
}

@keyframes scaleIn {
  0% {
    transform: scale(0);
  }
  50% {
    transform: scale(1.1);
  }
  100% {
    transform: scale(1);
  }
}

.success-icon i {
  font-size: 40px;
  color: white;
}

.success-modal h2 {
  font-size: 28px;
  color: var(--color-ink);
  margin-bottom: 15px;
}

.success-modal p {
  font-size: 16px;
  color: var(--color-muted);
  line-height: 1.6;
  margin-bottom: 15px;
}

.countdown-text {
  font-size: 14px;
  color: var(--color-faint);
  margin: 20px 0;
}

.countdown-number {
  font-weight: 700;
  color: var(--color-brand);
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

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 4px 15px rgba(var(--color-brand-rgb), 0.4);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(var(--color-brand-rgb), 0.5);
}
</style>
