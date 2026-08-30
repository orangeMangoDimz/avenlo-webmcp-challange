import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

const source = readFileSync(
  resolve(process.cwd(), "src/layouts/ClientLayout.vue"),
  "utf8",
);

describe("ClientLayout language switcher contract", () => {
  it("keeps the admin-compatible flag, label, and arrow structure", () => {
    expect(source).toMatch(/class="language-trigger-flag"/);
    expect(source).toMatch(/class="language-text"/);
    expect(source).toMatch(/class="language-arrow"/);
  });

  it("keeps the trigger on the dedicated language control class", () => {
    expect(source).toMatch(
      /class="workspace-action language-switcher-trigger"/,
    );
  });
});
