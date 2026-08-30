import assert from "node:assert/strict";
import { mkdtempSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import path from "node:path";
import test from "node:test";

import { auditDirectory } from "./check-font-size.mjs";

function withFixture(source, callback) {
  const fixtureRoot = mkdtempSync(path.join(tmpdir(), "font-size-audit-"));
  writeFileSync(path.join(fixtureRoot, "Fixture.vue"), source);

  try {
    return callback(fixtureRoot);
  } finally {
    rmSync(fixtureRoot, { recursive: true, force: true });
  }
}

test("accepts readable text at or above 14px and preserves larger hierarchy", () => {
  withFixture(
    `<template><p class="body-copy">Body</p></template>
     <style>
       .body-copy { font-size: 14px; }
       h1 { font-size: 28px; }
     </style>`,
    (fixtureRoot) => {
      assert.deepEqual(auditDirectory(fixtureRoot), []);
    },
  );
});

test("reports unapproved readable font sizes below 14px, including inline styles", () => {
  withFixture(
    `<template><p style="font-size: 12px">Body</p></template>
     <style>.helper-copy { font-size: 13px; }</style>`,
    (fixtureRoot) => {
      const violations = auditDirectory(fixtureRoot);

      assert.equal(violations.length, 2);
      assert.match(violations[0], /font-size 12px/);
      assert.match(violations[1], /font-size 13px/);
    },
  );
});

test("allows only explicitly annotated visual font-size exceptions", () => {
  withFixture(
    `<template><i class="icon-only">x</i></template>
     <style>
       .icon-only {
         /* @font-floor-exempt: visual-only glyph */
         font-size: 10px;
       }
     </style>`,
    (fixtureRoot) => {
      assert.deepEqual(auditDirectory(fixtureRoot), []);
    },
  );
});

test("does not carry a visual exception into a later declaration", () => {
  withFixture(
    `<style>
      .icon-only {
        /* @font-floor-exempt: visual-only glyph */
        font-size: 10px;
      }
      .body-copy { font-size: 12px; }
    </style>`,
    (fixtureRoot) => {
      const violations = auditDirectory(fixtureRoot);

      assert.equal(violations.length, 1);
      assert.match(violations[0], /font-size 12px/);
    },
  );
});
