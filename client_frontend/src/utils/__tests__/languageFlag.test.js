import { describe, expect, it } from "vitest";
import { languageFlagCode } from "../languageFlag";

describe("languageFlagCode", () => {
  it("maps English to the US flag", () => {
    expect(languageFlagCode("en")).toBe("us");
  });

  it("maps Chinese locale variants to the China flag", () => {
    expect(languageFlagCode("zh-CN")).toBe("cn");
  });

  it("falls back to the US flag for missing or unsupported codes", () => {
    expect(languageFlagCode()).toBe("us");
    expect(languageFlagCode("xx-unknown")).toBe("us");
  });
});
