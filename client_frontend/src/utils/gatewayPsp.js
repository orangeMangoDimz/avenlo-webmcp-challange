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

const OTHER_CURRENCY_GROUP = "Other";

const COUNTRY_NAMES_BY_CODE = {
  HK: "Hong Kong",
  ID: "Indonesia",
  JP: "Japan",
  KH: "Cambodia",
  KR: "South Korea",
  MY: "Malaysia",
  PH: "Philippines",
  SG: "Singapore",
  TH: "Thailand",
  TW: "Taiwan",
  US: "United States",
  VN: "Vietnam",
  CN: "China",
};

const COUNTRY_NAMES_BY_CURRENCY = {
  CNY: "China",
  HKD: "Hong Kong",
  IDR: "Indonesia",
  JPY: "Japan",
  KHR: "Cambodia",
  KRW: "South Korea",
  MYR: "Malaysia",
  PHP: "Philippines",
  SGD: "Singapore",
  THB: "Thailand",
  TWD: "Taiwan",
  USD: "United States",
  VND: "Vietnam",
};

const COUNTRY_NAMES_IN_TEXT = [
  "South Korea",
  "Hong Kong",
  "United States",
  "Cambodia",
  "Indonesia",
  "Malaysia",
  "Philippines",
  "Singapore",
  "Vietnam",
  "Thailand",
  "Taiwan",
  "China",
  "Japan",
  "Korea",
];

const normalizeCurrencyCodes = (items) => {
  if (!Array.isArray(items)) return [];

  return items
    .map((item) => {
      if (typeof item === "object" && item !== null) {
        return (
          item.shortCode ||
          item.code ||
          item.symbol ||
          item.currency ||
          item.name ||
          item.methodName ||
          ""
        );
      }
      return String(item || "").trim();
    })
    .map((item) =>
      String(item || "")
        .trim()
        .toUpperCase(),
    )
    .filter(Boolean);
};

export const getGatewayCurrencyCodes = (gateway) => {
  const codes = [
    ...new Set([
      ...normalizeCurrencyCodes(gateway?.supportedFiatCurrencies),
      ...normalizeCurrencyCodes(gateway?.supportedCryptoCurrencies),
    ]),
  ];
  if (codes.length) return codes;

  const name = String(gateway?.gatewayName || "").trim();
  const match = name.match(/\b([A-Z]{3})\b/);
  if (match) return [match[1]];

  return [];
};

const extractCountryFromText = (value) => {
  const text = String(value || "");
  for (const name of COUNTRY_NAMES_IN_TEXT) {
    const pattern = new RegExp(`\\b${name.replace(/\s+/g, "\\s+")}\\b`, "i");
    if (pattern.test(text)) {
      return name === "Korea" ? "South Korea" : name;
    }
  }
  return "";
};

export const getGatewayCountryName = (gateway, currencyCode = "") => {
  const fromName = extractCountryFromText(gateway?.gatewayName);
  if (fromName) return fromName;

  const config = parseConfigData(gateway?.configData);
  const fromRegion = extractCountryFromText(config.region || config.country);
  if (fromRegion) return fromRegion;

  const iso = String(gateway?.country || "")
    .trim()
    .toUpperCase();
  if (COUNTRY_NAMES_BY_CODE[iso]) return COUNTRY_NAMES_BY_CODE[iso];

  const code = String(currencyCode || "")
    .trim()
    .toUpperCase();
  if (COUNTRY_NAMES_BY_CURRENCY[code]) return COUNTRY_NAMES_BY_CURRENCY[code];

  return "";
};

export const formatCurrencyGroupName = (currencyCode, countryName) => {
  const code = String(currencyCode || "")
    .trim()
    .toUpperCase();
  const country = String(countryName || "").trim();
  if (!code) return OTHER_CURRENCY_GROUP;
  if (!country) return code;
  return `${code} - ${country}`;
};

export const groupGatewaysByCurrency = (gateways) => {
  const groups = new Map();

  for (const gateway of Array.isArray(gateways) ? gateways : []) {
    const codes = getGatewayCurrencyCodes(gateway);
    const labels = !codes.length
      ? [OTHER_CURRENCY_GROUP]
      : Number(gateway?.isMultiCurrency) === 1
        ? [codes[0]]
        : codes;

    for (const code of labels) {
      const name =
        code === OTHER_CURRENCY_GROUP
          ? OTHER_CURRENCY_GROUP
          : formatCurrencyGroupName(code, getGatewayCountryName(gateway, code));
      if (!groups.has(name)) {
        groups.set(name, []);
      }
      groups.get(name).push(gateway);
    }
  }

  return [...groups.entries()]
    .sort(([left], [right]) => {
      if (left === OTHER_CURRENCY_GROUP) return 1;
      if (right === OTHER_CURRENCY_GROUP) return -1;
      return compareByName(left, right);
    })
    .map(([name, items]) => ({
      name,
      gateways: [...items].sort((left, right) =>
        compareByName(left?.gatewayName, right?.gatewayName),
      ),
    }));
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
