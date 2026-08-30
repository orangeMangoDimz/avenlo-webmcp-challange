const PSP_LABELS = {
  "5pay": "5Pay",
  coinsbuy: "Coinsbuy",
  crypto: "Crypto",
  cvpay: "CVPay",
  flashpay: "FlashPay",
  ibeepay: "IBeePay",
  payment_asia: "Payment Asia",
  spay: "5Pay",
  vexora: "Vexora",
  xlink: "X-Link",
};

const GATEWAY_KEY_FAMILY = {
  pa: "payment_asia",
};

const compareByName = (left, right) =>
  String(left || "").localeCompare(String(right || ""), undefined, {
    sensitivity: "base",
    numeric: true,
  });

const parseConfigData = (configData) => {
  if (!configData) return {};
  if (typeof configData === "object") return configData;
  if (typeof configData !== "string") return {};

  try {
    const parsed = JSON.parse(configData);
    return parsed && typeof parsed === "object" ? parsed : {};
  } catch {
    return {};
  }
};

const normalizePspKey = (value) =>
  String(value || "")
    .trim()
    .toLowerCase()
    .replace(/[\s-]+/g, "_");

export const getGatewayPspName = (gateway) => {
  const config = parseConfigData(gateway?.configData);
  const fromConfig = normalizePspKey(config.providerKey || config.provider);
  if (PSP_LABELS[fromConfig]) return PSP_LABELS[fromConfig];

  const gatewayKey = String(gateway?.gatewayKey || "").toLowerCase();
  const keyPrefix = gatewayKey.split("-")[0];
  const family = GATEWAY_KEY_FAMILY[keyPrefix] || keyPrefix;
  if (PSP_LABELS[family]) return PSP_LABELS[family];

  const name = String(gateway?.gatewayName || "").trim();
  const match = name.match(/^(.+?)\s+[A-Z]{3}(?:\s|\(|$)/);
  if (match) return match[1].trim();

  return name || gatewayKey || "Other";
};

export const groupGatewaysByPsp = (gateways) => {
  const groups = new Map();

  for (const gateway of Array.isArray(gateways) ? gateways : []) {
    const name = getGatewayPspName(gateway);
    if (!groups.has(name)) {
      groups.set(name, []);
    }
    groups.get(name).push(gateway);
  }

  return [...groups.entries()]
    .sort(([left], [right]) => compareByName(left, right))
    .map(([name, items]) => ({
      name,
      gateways: [...items].sort((left, right) =>
        compareByName(left?.gatewayName, right?.gatewayName),
      ),
    }));
};
