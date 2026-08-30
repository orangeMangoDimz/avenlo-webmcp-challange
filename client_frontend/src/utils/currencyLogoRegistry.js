const currencyLogoModules = import.meta.glob("../assets/currency-icons/*.svg", {
  eager: true,
  import: "default",
});

const currencyLogoRegistry = Object.fromEntries(
  Object.entries(currencyLogoModules)
    .map(([path, url]) => {
      const match = path.match(/\/([^/]+)\.svg$/);
      const code = match?.[1]?.toLowerCase();
      return [code, url];
    })
    .filter(([code]) => Boolean(code)),
);

export const getCurrencyLogo = (code) => {
  const normalizedCode = String(code || "")
    .trim()
    .toLowerCase();
  return normalizedCode ? currencyLogoRegistry[normalizedCode] || "" : "";
};

const fiatFlagMap = {
  usd: "us",
  eur: "eu",
  gbp: "gb",
  aud: "au",
  cad: "ca",
  nzd: "nz",
  jpy: "jp",
  cny: "cn",
  hkd: "hk",
  sgd: "sg",
  krw: "kr",
  thb: "th",
  myr: "my",
  idr: "id",
  inr: "in",
  vnd: "vn",
  chf: "ch",
  sek: "se",
  nok: "no",
  dkk: "dk",
  zar: "za",
  aed: "ae",
  sar: "sa",
};

export const getFiatFlagCode = (code) => {
  const normalizedCode = String(code || "")
    .trim()
    .toLowerCase();
  return normalizedCode ? fiatFlagMap[normalizedCode] || "" : "";
};

export default currencyLogoRegistry;
