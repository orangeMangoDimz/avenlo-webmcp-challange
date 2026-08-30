<template>
  <div class="gateway-card">
    <div class="gateway-header">
      <div class="gateway-title-section">
        <div class="gateway-logo">
          <img
            v-if="gatewayData.gatewayKey === 'alchemy_pay'"
            src="https://www.alchemypay.org/img/logo-light.svg"
            alt="AlchemyPay"
            style="width: 100%; padding: 8px"
            @error="handleImageError"
          />
          <span v-else>{{ gatewayData.gatewayKey?.toUpperCase() }}</span>
        </div>
        <div class="gateway-title-info">
          <h3>{{ gatewayData.gatewayName }}</h3>
          <p>Fiat-to-Crypto Payment Gateway</p>
        </div>
      </div>
      <div class="gateway-actions">
        <div class="toggle-switch-wrapper">
          <div
            :class="[
              'toggle-switch',
              { active: formData.isEnabled, disabled: !canEdit },
            ]"
            @click="canEdit ? toggleEnabled() : null"
          ></div>
        </div>
      </div>
    </div>

    <div class="gateway-body">
      <div class="form-group">
        <label>
          Environment
          <span class="label-description"
            >Select production or sandbox environment</span
          >
        </label>
        <select
          v-model="formData.environment"
          :disabled="!canEdit"
          @change="handleChange"
        >
          <option value="sandbox">Sandbox (Testing)</option>
          <option value="production">Production (Live)</option>
        </select>
      </div>

      <div class="form-group">
        <label>
          App ID
          <span class="label-description"
            >Your {{ gatewayData.gatewayName }} application identifier</span
          >
        </label>
        <input
          type="text"
          v-model="formData.appId"
          :placeholder="`Enter ${gatewayData.gatewayName} App ID`"
          :disabled="!canEdit"
          @input="handleChange"
        />
      </div>

      <div class="form-group">
        <label>
          API Key
          <span class="label-description"
            >Your {{ gatewayData.gatewayName }} API key (keep this secret)</span
          >
        </label>
        <input
          type="password"
          v-model="formData.apiKey"
          placeholder="Enter API Key"
          :disabled="!canEdit"
          @input="handleChange"
        />
      </div>

      <div class="form-group">
        <label>
          Secret Key
          <span class="label-description"
            >Your {{ gatewayData.gatewayName }} secret key for signature
            verification</span
          >
        </label>
        <input
          type="password"
          v-model="formData.secretKey"
          placeholder="Enter Secret Key"
          :disabled="!canEdit"
          @input="handleChange"
        />
      </div>

      <div class="form-group">
        <label>
          Merchant Name
          <span class="label-description"
            >Your business name displayed during checkout</span
          >
        </label>
        <input
          type="text"
          v-model="formData.merchantName"
          placeholder="Enter merchant name"
          :disabled="!canEdit"
          @input="handleChange"
        />
      </div>

      <!-- Webhook URL / Return URL 暂时隐藏 -->
      <div v-if="false" class="form-row">
        <div class="form-group">
          <label>Webhook URL</label>
          <input
            type="text"
            v-model="formData.webhookUrl"
            placeholder="Enter webhook URL"
            :disabled="!canEdit"
            @input="handleChange"
          />
        </div>
        <div class="form-group">
          <label>Return URL</label>
          <input
            type="text"
            v-model="formData.returnUrl"
            placeholder="Enter return URL"
            :disabled="!canEdit"
            @input="handleChange"
          />
        </div>
      </div>

      <div class="form-group">
        <label>
          {{
            t("txnSettings_label_supportedFiat", "Supported Fiat Currencies")
          }}
          <span class="label-description">{{
            t(
              "txnSettings_hint_supportedFiat",
              "Select which fiat currencies to accept",
            )
          }}</span>
        </label>
        <select
          v-model="selectedFiatCurrencies"
          multiple
          :disabled="!canEdit"
          @change="handleCurrencyChange"
        >
          <option value="USD">USD - US Dollar</option>
          <option value="EUR">EUR - Euro</option>
          <option value="GBP">GBP - British Pound</option>
          <option value="JPY">JPY - Japanese Yen</option>
          <option value="CNY">CNY - Chinese Yuan</option>
          <option value="AUD">AUD - Australian Dollar</option>
          <option value="CAD">CAD - Canadian Dollar</option>
          <option value="SGD">SGD - Singapore Dollar</option>
          <option value="HKD">HKD - Hong Kong Dollar</option>
          <option value="KRW">KRW - South Korean Won</option>
        </select>
        <small class="help-text">
          <i class="fas fa-info-circle"></i>
          {{
            t(
              "txnSettings_hint_multiSelect",
              "Hold Ctrl (Cmd on Mac) to select multiple currencies",
            )
          }}
        </small>
      </div>

      <div class="form-group">
        <label>
          {{
            t(
              "txnSettings_label_supportedCrypto",
              "Supported Crypto Currencies",
            )
          }}
          <span class="label-description">{{
            t(
              "txnSettings_hint_supportedCrypto",
              "Select which cryptocurrencies clients can purchase",
            )
          }}</span>
        </label>
        <select
          v-model="selectedCryptoCurrencies"
          multiple
          :disabled="!canEdit"
          @change="handleCurrencyChange"
        >
          <option value="BTC">Bitcoin (BTC)</option>
          <option value="ETH">Ethereum (ETH)</option>
          <option value="USDT">Tether (USDT)</option>
          <option value="USDC">USD Coin (USDC)</option>
          <option value="BNB">Binance Coin (BNB)</option>
          <option value="MATIC">Polygon (MATIC)</option>
          <option value="SOL">Solana (SOL)</option>
        </select>
      </div>

      <div class="info-box">
        <p>
          <strong><i class="fas fa-shield-alt"></i> Security:</strong>
          All sensitive data (API keys, secret keys) are encrypted before
          storage. Never share these credentials with anyone.
        </p>
      </div>

      <!--      <div v-if="canEdit" class="gateway-test-actions">-->
      <!--        <button class="btn btn-secondary btn-small" @click="testConnection">-->
      <!--          <i class="fas fa-plug"></i> Test Connection-->
      <!--        </button>-->
      <!--        <button class="btn btn-secondary btn-small" @click="viewDocs">-->
      <!--          <i class="fas fa-book"></i> View Documentation-->
      <!--        </button>-->
      <!--      </div>-->
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();

const props = defineProps({
  gatewayData: {
    type: Object,
    required: true,
  },
  canEdit: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(["update", "change"]);

// 表单数据
const formData = ref({
  isEnabled: props.gatewayData.isEnabled || false,
  environment: props.gatewayData.environment || "production",
  appId: props.gatewayData.appId || "",
  apiKey: props.gatewayData.apiKey || "",
  secretKey: props.gatewayData.secretKey || "",
  merchantName: props.gatewayData.merchantName || "",
  webhookUrl: props.gatewayData.webhookUrl || "",
  returnUrl: props.gatewayData.returnUrl || "",
  supportedFiatCurrencies: props.gatewayData.supportedFiatCurrencies || [],
  supportedCryptoCurrencies: props.gatewayData.supportedCryptoCurrencies || [],
});

// 解析支持的货币
const selectedFiatCurrencies = ref([]);
const selectedCryptoCurrencies = ref([]);

// 初始化货币选择
const initializeCurrencies = () => {
  const fiat = props.gatewayData.supportedFiatCurrencies;
  const crypto = props.gatewayData.supportedCryptoCurrencies;

  if (typeof fiat === "string") {
    try {
      selectedFiatCurrencies.value = JSON.parse(fiat);
    } catch {
      selectedFiatCurrencies.value = fiat.split(",").map((c) => c.trim());
    }
  } else if (Array.isArray(fiat)) {
    selectedFiatCurrencies.value = fiat;
  }

  if (typeof crypto === "string") {
    try {
      selectedCryptoCurrencies.value = JSON.parse(crypto);
    } catch {
      selectedCryptoCurrencies.value = crypto.split(",").map((c) => c.trim());
    }
  } else if (Array.isArray(crypto)) {
    selectedCryptoCurrencies.value = crypto;
  }
};

initializeCurrencies();

// 切换启用状态
const toggleEnabled = () => {
  if (!props.canEdit) return;
  formData.value.isEnabled = !formData.value.isEnabled;
  handleChange();
};

// 处理货币变更
const handleCurrencyChange = () => {
  formData.value.supportedFiatCurrencies = selectedFiatCurrencies.value;
  formData.value.supportedCryptoCurrencies = selectedCryptoCurrencies.value;
  handleChange();
};

// 处理变更
const handleChange = () => {
  emit("change", {
    gatewayKey: props.gatewayData.gatewayKey,
    data: { ...formData.value },
  });
};

// 处理图片加载错误
const handleImageError = (e) => {
  e.target.style.display = "none";
  e.target.parentElement.textContent = "ACH";
};

// 测试连接
const testConnection = () => {
  if (!formData.value.appId || !formData.value.apiKey) {
    alert("⚠️ Please enter App ID and API Key before testing connection.");
    return;
  }
  // TODO: 实现真实的连接测试
  alert(
    "🔄 Testing connection...\n\nThis will verify your credentials and check API accessibility.\n\n(In production, this will make an actual API call)",
  );
};

// 查看文档
const viewDocs = () => {
  if (props.gatewayData.gatewayKey === "alchemy_pay") {
    window.open("https://docs.alchemypay.org/", "_blank");
  }
};

// 监听prop变化
watch(
  () => props.gatewayData,
  (newVal) => {
    formData.value = {
      isEnabled: newVal.isEnabled || false,
      environment: newVal.environment || "production",
      appId: newVal.appId || "",
      apiKey: newVal.apiKey || "",
      secretKey: newVal.secretKey || "",
      merchantName: newVal.merchantName || "",
      webhookUrl: newVal.webhookUrl || "",
      returnUrl: newVal.returnUrl || "",
      supportedFiatCurrencies: newVal.supportedFiatCurrencies || [],
      supportedCryptoCurrencies: newVal.supportedCryptoCurrencies || [],
    };
    initializeCurrencies();
  },
  { deep: true },
);
</script>

<style scoped>
.gateway-card {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 0;
  overflow: hidden;
  transition: all 0.3s ease;
}

.gateway-header {
  background: var(--color-surface-soft);
  padding: 20px 25px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 15px;
}

.gateway-title-section {
  display: flex;
  align-items: center;
  gap: 15px;
}

.gateway-logo {
  width: 56px;
  height: 56px;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
  color: var(--color-brand);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.gateway-title-info h3 {
  font-size: 18px;
  color: var(--color-ink);
  margin-bottom: 3px;
}

.gateway-title-info p {
  font-size: 13px;
  color: var(--color-muted);
}

.toggle-switch-wrapper {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 0;
  background: transparent;
  padding: 0;
}

.toggle-label-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
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

.toggle-switch.disabled {
  cursor: not-allowed;
  opacity: 0.7;
  background: var(--color-border) !important;
}

.toggle-switch.disabled.active {
  background: var(--color-success-soft) !important;
  opacity: 0.8;
}

.toggle-switch.disabled::after {
  background: var(--color-border-strong);
}

.toggle-switch.disabled.active::after {
  background: var(--color-surface);
}

.toggle-switch.disabled:hover {
  cursor: not-allowed;
}

.gateway-body {
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
  font-size: 13px;
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

.form-group select[multiple] {
  min-height: 120px;
}

.help-text {
  color: var(--color-muted);
  font-size: 12px;
  margin-top: 8px;
  display: block;
}

.form-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}

.info-box {
  background: var(--color-brand-soft);
  border-left: 4px solid var(--color-brand);
  padding: 15px 20px;
  border-radius: var(--radius-md);
  margin-top: 20px;
}

.info-box p {
  color: var(--color-text);
  font-size: 13px;
  line-height: 1.6;
  margin: 0;
}

.gateway-test-actions {
  display: flex;
  gap: 10px;
  margin-top: 20px;
}

.btn {
  padding: 8px 16px;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-secondary {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-secondary:hover {
  background: var(--color-border-strong);
}

@media (max-width: 768px) {
  .form-row {
    grid-template-columns: 1fr;
  }

  .gateway-test-actions {
    flex-direction: column;
  }
}
</style>
