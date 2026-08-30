import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const SOURCE_EXTENSIONS = new Set([".css", ".html", ".js", ".jsx", ".mjs", ".ts", ".tsx", ".vue"]);
const FONT_SIZE_PATTERN = /(?:\bfont-size\b|--[\w-]*font-size[\w-]*)\s*:\s*([0-9]+(?:\.[0-9]+)?)px\b/gi;
const CLAMP_FONT_SIZE_PATTERN = /(?:\bfont-size\b|--[\w-]*font-size[\w-]*)\s*:\s*clamp\(\s*([0-9]+(?:\.[0-9]+)?)px\b/gi;
const FONT_FLOOR = 14;

function listSourceFiles(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const entryPath = path.join(directory, entry.name);

    if (entry.isDirectory()) return listSourceFiles(entryPath);
    if (!SOURCE_EXTENSIONS.has(path.extname(entry.name))) return [];

    return [entryPath];
  });
}

function preserveLineNumbersWithoutComments(source) {
  return source.replace(/\/\*[\s\S]*?\*\//g, (comment) => comment.replace(/[^\n]/g, " "))
    .replace(/<!--[\s\S]*?-->/g, (comment) => comment.replace(/[^\n]/g, " "));
}

function lineNumber(source, index) {
  return source.slice(0, index).split("\n").length;
}

function isVisualException(source, index) {
  const beforeDeclaration = source.slice(0, index);
  const declarationScopeStart = Math.max(
    beforeDeclaration.lastIndexOf("{"),
    beforeDeclaration.lastIndexOf(";"),
  );

  return source.slice(declarationScopeStart, index).includes("@font-floor-exempt");
}

function collectViolations(source, filePath) {
  const sourceWithoutComments = preserveLineNumbersWithoutComments(source);
  const violations = [];

  for (const pattern of [FONT_SIZE_PATTERN, CLAMP_FONT_SIZE_PATTERN]) {
    for (const match of sourceWithoutComments.matchAll(pattern)) {
      const size = Number.parseFloat(match[1]);
      if (size >= FONT_FLOOR || isVisualException(source, match.index)) continue;

      violations.push({
        filePath,
        line: lineNumber(source, match.index),
        size,
        text: `${path.relative(process.cwd(), filePath)}:${lineNumber(source, match.index)}: font-size ${size}px is below ${FONT_FLOOR}px`,
      });
    }
  }

  return violations;
}

export function auditDirectory(directory) {
  return listSourceFiles(directory)
    .flatMap((filePath) => collectViolations(fs.readFileSync(filePath, "utf8"), filePath))
    .sort((left, right) => left.filePath.localeCompare(right.filePath) || left.line - right.line || left.size - right.size)
    .map((violation) => violation.text);
}

function run() {
  const sourceRoot = path.resolve(process.argv[2] || path.join(process.cwd(), "src"));
  const violations = auditDirectory(sourceRoot);

  if (violations.length) {
    console.error(`Font-size audit failed: ${violations.length} unapproved value(s) below ${FONT_FLOOR}px`);
    console.error(violations.join("\n"));
    process.exitCode = 1;
    return;
  }

  console.log(`PASS font-size audit: all readable source values are at least ${FONT_FLOOR}px`);
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  run();
}
