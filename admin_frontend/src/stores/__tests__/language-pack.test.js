import fs from "node:fs";
import { describe, expect, it } from "vitest";
import { normalizeTranslationPack } from "../language";

const loadPack = (filename) =>
  JSON.parse(
    fs.readFileSync(
      new URL(`../../../public/${filename}`, import.meta.url),
      "utf8",
    ),
  );

describe("admin language packs", () => {
  it.each(["language-pack-template.json", "language-pack-zh-template.json"])(
    "exposes legacy client-list labels at the direct translation level (%s)",
    (filename) => {
      const pack = loadPack(filename);
      const translations = normalizeTranslationPack(pack);

      expect(translations.leads_col_client).toBeTruthy();
      expect(translations.leads_col_phone).toBeTruthy();
      expect(translations.leads_col_country).toBeTruthy();
      expect(translations.leads_col_manager).toBeTruthy();
      expect(translations.leads_col_tags).toBeTruthy();
      expect(translations.leads_col_kycStatus).toBeTruthy();
      expect(translations.leads_col_action).toBeTruthy();
    },
  );
});
