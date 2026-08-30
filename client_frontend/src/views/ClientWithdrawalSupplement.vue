<template>
  <div class="withdrawal-supplement-page">
    <WithdrawalSupplement
      :withdrawal-id="withdrawalId"
      @submitted="handleSubmitted"
      @close="handleClose"
    />
  </div>
</template>

<script setup>
import { computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import WithdrawalSupplement from "@/components/client/WithdrawalSupplement.vue";

const route = useRoute();
const router = useRouter();

const withdrawalId = computed(() => {
  const id = route.params.id;
  if (!id) {
    console.error("Withdrawal ID is missing from route params");
    // 如果ID缺失，重定向到dashboard
    router.push("/client/dashboard");
    return null;
  }
  return id;
});

const handleSubmitted = () => {
  router.push("/client/dashboard");
};

const handleClose = () => {
  router.push("/client/dashboard");
};
</script>

<style scoped>
.withdrawal-supplement-page {
  min-height: 100vh;
  background: var(--color-canvas);
}
</style>
