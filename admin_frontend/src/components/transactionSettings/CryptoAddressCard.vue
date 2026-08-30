<template>
  <div class="crypto-address-card">
    <div class="crypto-address-header">
      <div class="crypto-icon-section">
        <div :class="['crypto-icon', cryptoClass]">
          <i :class="icon"></i>
        </div>
        <div class="crypto-info">
          <div class="crypto-name">{{ cryptoData.methodName }}</div>
          <div class="crypto-network">
            Network: {{ cryptoData.networkName || cryptoData.networkType }}
          </div>
        </div>
      </div>
      <div class="crypto-actions">
        <span
          :class="[
            'crypto-status-badge',
            cryptoData.isActive ? 'active' : 'inactive',
          ]"
        >
          {{ cryptoData.isActive ? "Active" : "Inactive" }}
        </span>
        <div
          v-if="canEdit"
          :class="['toggle-switch', { active: isActive }]"
          @click="toggleActive"
        ></div>
      </div>
    </div>

    <div class="crypto-address-body">
      <div class="form-group">
        <label>
          Deposit Address
          <span class="label-description"
            >Enter your {{ cryptoData.shortCode }} wallet address</span
          >
        </label>
        <div class="address-input-group">
          <input
            type="text"
            v-model="formData.walletAddress"
            :placeholder="`Enter ${cryptoData.shortCode} address`"
            :disabled="!canEdit"
            @input="handleChange"
          />
          <button class="copy-btn" @click="copyAddress">
            <i :class="copied ? 'fas fa-check' : 'fas fa-copy'"></i>
            {{ copied ? "Copied!" : "Copy" }}
          </button>
        </div>
      </div>

      <div
        v-if="cryptoData.methodType === 'crypto' && showNetworkSelect"
        class="form-group"
      >
        <label>Network</label>
        <select
          v-model="formData.networkType"
          :disabled="!canEdit"
          @change="handleChange"
        >
          <option value="mainnet">Mainnet</option>
          <option value="erc20">ERC-20 (Ethereum)</option>
          <option value="trc20">TRC-20 (Tron)</option>
          <option value="bep20">BEP-20 (BSC)</option>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Minimum Deposit</label>
          <input
            type="number"
            v-model.number="formData.minimumDeposit"
            step="0.001"
            placeholder="0.001"
            :disabled="!canEdit"
            @input="handleChange"
          />
        </div>
        <div class="form-group">
          <label>Confirmation Blocks</label>
          <input
            type="number"
            v-model.number="formData.confirmationBlocks"
            placeholder="3"
            :disabled="!canEdit"
            @input="handleChange"
          />
        </div>
      </div>

      <div class="qr-code-section">
        <div class="qr-code-placeholder">
          <i class="fas fa-qrcode"></i>
        </div>
        <div class="qr-code-info">
          <h4>QR Code</h4>
          <p>
            Generate a QR code for this address to make it easier for clients to
            scan and deposit
          </p>
          <button class="btn-generate-qr" @click="generateQRCode">
            <i class="fas fa-qrcode"></i> Generate QR Code
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from "vue";

const props = defineProps({
  cryptoData: {
    type: Object,
    required: true,
  },
  showNetworkSelect: {
    type: Boolean,
    default: false,
  },
  canEdit: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(["update", "change"]);

// 表单数据
const formData = ref({
  walletAddress: props.cryptoData.walletAddress || "",
  networkType: props.cryptoData.networkType || "mainnet",
  minimumDeposit: props.cryptoData.minimumDeposit || 0,
  minimumDepositUsd: props.cryptoData.minimumDepositUsd || 0,
  confirmationBlocks: props.cryptoData.confirmationBlocks || 3,
  isActive: props.cryptoData.isActive || true,
});

const isActive = ref(props.cryptoData.isActive || true);
const copied = ref(false);

// 计算图标类名
const cryptoClass = computed(() => {
  const key =
    props.cryptoData.methodKey || props.cryptoData.shortCode?.toLowerCase();
  if (key === "bitcoin" || key === "btc") return "btc";
  if (key === "ethereum" || key === "eth") return "eth";
  if (key === "usdt") return "usdt";
  if (key === "usdc") return "usdc";
  return "btc";
});

// 计算图标
const icon = computed(() => {
  const key =
    props.cryptoData.methodKey || props.cryptoData.shortCode?.toLowerCase();
  if (key === "bitcoin" || key === "btc") return "fab fa-bitcoin";
  if (key === "ethereum" || key === "eth") return "fab fa-ethereum";
  return "fas fa-coins";
});

// 切换激活状态
const toggleActive = () => {
  if (!props.canEdit) return;
  isActive.value = !isActive.value;
  formData.value.isActive = isActive.value;
  handleChange();
};

// 复制地址
const copyAddress = async () => {
  try {
    await navigator.clipboard.writeText(formData.value.walletAddress);
    copied.value = true;
    setTimeout(() => {
      copied.value = false;
    }, 2000);
  } catch (err) {
    console.error("Failed to copy:", err);
  }
};

// 生成二维码
const generateQRCode = () => {
  if (!formData.value.walletAddress) {
    alert("Please enter a wallet address first");
    return;
  }
  // TODO: 实现二维码生成功能
  alert(
    `Generating QR code for ${props.cryptoData.shortCode} address...\n\nThis feature will integrate with a QR code generation library.`,
  );
};

// 处理变更
const handleChange = () => {
  emit("change", {
    paymentMethodId: props.cryptoData.id || props.cryptoData.paymentMethodId,
    data: { ...formData.value },
  });
};

// 监听prop变化
watch(
  () => props.cryptoData,
  (newVal) => {
    formData.value = {
      walletAddress: newVal.walletAddress || "",
      networkType: newVal.networkType || "mainnet",
      minimumDeposit: newVal.minimumDeposit || 0,
      minimumDepositUsd: newVal.minimumDepositUsd || 0,
      confirmationBlocks: newVal.confirmationBlocks || 3,
      isActive: newVal.isActive || true,
    };
    isActive.value = newVal.isActive || true;
  },
  { deep: true },
);
</script>

<style scoped>
.crypto-address-card {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 0;
  margin-bottom: 20px;
  overflow: hidden;
  transition: all 0.3s ease;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.crypto-address-card:hover {
  border-color: var(--color-border-strong);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}

.crypto-address-header {
  background: var(--color-surface-soft);
  padding: 20px 25px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 15px;
}

.crypto-icon-section {
  display: flex;
  align-items: center;
  gap: 15px;
  flex: 1;
}

.crypto-icon {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 20px;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
  font-weight: bold;
}

.crypto-icon.btc {
  background: linear-gradient(135deg, #f7931a 0%, #ff9800 100%);
}

.crypto-icon.eth {
  background: linear-gradient(135deg, #627eea 0%, #3c58c7 100%);
}

.crypto-icon.usdt {
  background: linear-gradient(135deg, #26a17b 0%, #169e76 100%);
}

.crypto-icon.usdc {
  background: linear-gradient(135deg, #2775ca 0%, #1c5ca8 100%);
}

.crypto-info {
  flex: 1;
}

.crypto-name {
  font-size: 16px;
  font-weight: 700;
  color: var(--color-ink);
  margin-bottom: 3px;
}

.crypto-network {
  font-size: 14px;
  color: var(--color-muted);
}

.crypto-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.crypto-status-badge {
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
}

.crypto-status-badge.active {
  background: var(--color-success-soft);
  color: var(--color-success);
  border: 1px solid #6ee7b7;
}

.crypto-status-badge.inactive {
  background: var(--color-danger-soft);
  color: var(--color-danger);
  border: 1px solid var(--color-danger-border);
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

.crypto-address-body {
  padding: 25px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 10px;
  color: var(--color-ink);
  font-weight: 600;
  font-size: 14px;
}

.label-description {
  display: block;
  color: var(--color-muted);
  font-weight: 400;
  font-size: 14px;
  margin-top: 5px;
}

.form-group input,
.form-group select {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
  background: var(--color-surface);
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.address-input-group {
  position: relative;
}

.address-input-group input {
  padding-right: 100px;
  font-family: "Courier New", monospace;
  font-size: 14px;
}

.copy-btn {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  background: var(--color-brand-solid);
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.copy-btn:hover {
  transform: translateY(-50%) scale(1.05);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.form-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}

.qr-code-section {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 20px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  margin-top: 20px;
}

.qr-code-placeholder {
  width: 120px;
  height: 120px;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-faint);
  font-size: 48px;
}

.qr-code-info {
  flex: 1;
}

.qr-code-info h4 {
  font-size: 14px;
  color: var(--color-ink);
  margin-bottom: 8px;
}

.qr-code-info p {
  font-size: 14px;
  color: var(--color-muted);
  margin-bottom: 12px;
}

.btn-generate-qr {
  background: var(--color-surface);
  border: 2px solid var(--color-brand);
  color: var(--color-brand);
  padding: 8px 16px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.btn-generate-qr:hover {
  background: var(--color-brand-solid);
  color: white;
}

@media (max-width: 768px) {
  .form-row {
    grid-template-columns: 1fr;
  }

  .qr-code-section {
    flex-direction: column;
  }
}
</style>
