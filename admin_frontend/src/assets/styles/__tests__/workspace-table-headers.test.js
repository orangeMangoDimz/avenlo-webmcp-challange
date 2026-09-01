import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

const workspaceStyles = readFileSync(
  resolve(process.cwd(), "src/assets/styles/workspace.css"),
  "utf8",
);

describe("legacy administration table headers", () => {
  it("resets sticky positioning on the affected route tables", () => {
    const resetRule = workspaceStyles.match(
      /#app\s+\.workspace-main\s+:is\((?:[^)]|\n)*\.roles-card(?:[^)]|\n)*\)\s+table\s+th\s*\{([^}]*)\}/s,
    );

    expect(resetRule).not.toBeNull();

    const rule = resetRule[0];
    for (const container of [
      ".roles-card",
      ".accounts-card",
      ".templates-table-container",
      ".ls-table-wrap",
    ]) {
      expect(rule).toContain(container);
    }

    expect(resetRule[1]).toMatch(/position:\s*static/);
    expect(resetRule[1]).toMatch(/top:\s*auto/);
    expect(resetRule[1]).toMatch(/z-index:\s*auto/);
  });
});
