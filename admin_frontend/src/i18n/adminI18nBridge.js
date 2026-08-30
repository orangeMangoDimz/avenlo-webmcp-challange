/**
 * Avoids circular imports between api.js and stores/language.js.
 * Set once after pinia + language store are ready.
 */
let translate = (key, fallback = "") => fallback;

export function setAdminTranslator(fn) {
  translate = typeof fn === "function" ? fn : (k, fb) => fb;
}

/** Resolve API business error message: err_<code> in language packs, else fallback */
export function translateApiErrorMessage(errorCode, fallbackMessage) {
  if (errorCode === undefined || errorCode === null || errorCode === "") {
    return fallbackMessage;
  }
  const key = `err_${String(errorCode)}`;
  const out = translate(key, fallbackMessage);
  return out === key ? fallbackMessage : out;
}
