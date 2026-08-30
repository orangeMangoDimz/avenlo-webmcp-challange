import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

const source = readFileSync(
  resolve(process.cwd(), "src/views/TransactionHistory.vue"),
  "utf8",
);
const styles = source.match(/<style[^>]*>([\s\S]*?)<\/style>/i)?.[1] || "";

describe("TransactionHistory theme surfaces", () => {
  it("keeps the page container on the shared surface token", () => {
    expect(styles).toMatch(
      /\.history-container\s*\{[^}]*background:\s*var\(--color-surface\)/s,
    );
    expect(styles).not.toMatch(
      /\.history-container\s*\{[^}]*background:\s*rgba\(255\s*,\s*255\s*,\s*255/s,
    );
  });

  it("keeps transaction cards on the shared surface token", () => {
    expect(styles).toMatch(
      /\.history-item\s*\{[^}]*background:\s*var\(--color-surface\)/s,
    );
    expect(styles).not.toMatch(
      /\.history-item\s*\{[^}]*background:\s*linear-gradient\(/s,
    );
  });
});
