import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const appRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const sourceRoot = path.join(appRoot, "src");
const themeStylesheets = [
  path.join(sourceRoot, "assets", "styles", "main.css"),
  path.join(sourceRoot, "assets", "styles", "workspace.css"),
];
const rawSurfaceColor = /\bbackground(?:-color)?\s*:\s*(white|#[0-9a-f]{3,8})\b/gi;
const rawGradientColor =
  /\bbackground(?:-image)?\s*:[^;{}]*gradient\([^;{}]*#[0-9a-f]{3,8}[^;{}]*;/gi;
const rawTextColor = /(?<![-\w])color\s*:\s*(#[0-9a-f]{3,8})\b/gi;
const thickBorder = /\bborder(?:-(?:top|right|bottom|left))?\s*:\s*([2-9]|\d{2,})px\b/gi;
const haloShadow = /\bbox-shadow\s*:[^;{}]*\b0\s+0\s+0\s+([2-9]|\d{2,})px\b/gi;
const suppressedOutline = /\boutline\s*:\s*none\b/gi;
const legacyTextColors = new Set([
  "0f172a",
  "111827",
  "1e293b",
  "1f2937",
  "334155",
  "374151",
  "475569",
  "4b5563",
  "64748b",
  "6b7280",
  "94a3b8",
  "999",
  "c53030",
  "9b2c2c",
  "b91c1c",
  "991b1b",
  "dc2626",
  "ef4444",
  "f56565",
  "276749",
  "22543d",
  "166534",
  "155724",
  "0f766e",
  "047857",
  "c05621",
  "b45309",
  "975a16",
  "744210",
  "b7791f",
  "dd6b20",
  "ed8936",
  "f59e0b",
  "2b6cb0",
  "3182ce",
  "0284c7",
  "004085",
  "0066cc",
  "0d6efd",
  "1d4ed8",
  "2563eb",
  "3b82f6",
  "4299e1",
  "805ad5",
  "5b21b6",
  "6b46c1",
  "7c3aed",
  "8b5cf6",
  "6366f1",
  "3730a3",
]);

function listSourceFiles(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const entryPath = path.join(directory, entry.name);
    if (entry.isDirectory()) return listSourceFiles(entryPath);
    return /\.(?:vue|css)$/.test(entry.name) ? [entryPath] : [];
  });
}

function lineNumber(source, index) {
  return source.slice(0, index).split("\n").length;
}

function styleSource(filePath) {
  const source = fs.readFileSync(filePath, "utf8");
  if (!filePath.endsWith(".vue")) return source;

  const styleBlocks = [...source.matchAll(/<style\b[^>]*>([\s\S]*?)<\/style>/gi)]
    .map((match) => "\n".repeat(source.slice(0, match.index).split("\n").length - 1) + match[1])
    .join("\n");
  const inlineStyles = [...source.matchAll(/(?<![:@\w-])style\s*=\s*["']([^"']*)["']/gi)]
    .map((match) => "\n".repeat(source.slice(0, match.index).split("\n").length - 1) + match[1])
    .join("\n");

  return `${styleBlocks}\n${inlineStyles}`;
}

function reportMatch(violations, label, filePath, source, match) {
  violations.push(
    `${label}: ${path.relative(appRoot, filePath)}:${lineNumber(source, match.index)}`,
  );
}

const violations = [];
const sourceFiles = listSourceFiles(sourceRoot);

for (const filePath of sourceFiles) {
  const source = styleSource(filePath).replace(/\/\*[\s\S]*?\*\//g, "");
  const isTokenFile = filePath === themeStylesheets[0];

  if (!isTokenFile) {
    for (const match of source.matchAll(rawSurfaceColor)) {
      reportMatch(violations, `Raw surface ${match[1]}`, filePath, source, match);
    }
    for (const match of source.matchAll(rawGradientColor)) {
      reportMatch(violations, "Raw gradient color", filePath, source, match);
    }
    for (const match of source.matchAll(rawTextColor)) {
      if (legacyTextColors.has(match[1].slice(1).toLowerCase())) {
        reportMatch(violations, `Legacy text color ${match[1]}`, filePath, source, match);
      }
    }
    for (const match of source.matchAll(thickBorder)) {
      reportMatch(violations, `Decorative ${match[1]}px border`, filePath, source, match);
    }
    for (const match of source.matchAll(haloShadow)) {
      reportMatch(violations, `Persistent ${match[1]}px halo`, filePath, source, match);
    }
    for (const match of source.matchAll(suppressedOutline)) {
      reportMatch(violations, "Suppressed focus outline", filePath, source, match);
    }
  }
}

const darkThemeSource = fs
  .readFileSync(themeStylesheets[0], "utf8")
  .match(/:root\[data-theme=(?:'dark'|"dark")\]\s*\{([\s\S]*?)\n\}/)?.[1] || "";
const darkPaletteContracts = {
  "--color-brand": "#6688d8",
  "--color-brand-solid": "#315ca8",
  "--color-success": "#36a76c",
  "--color-success-solid": "#176b44",
  "--color-warning": "#bf8f3f",
  "--color-warning-solid": "#72531d",
  "--color-danger": "#cb686e",
  "--color-danger-solid": "#833943",
  "--color-info": "#628bd4",
  "--color-info-solid": "#2c558c",
  "--color-purple": "#b878ac",
  "--color-purple-solid": "#613d6e",
};

for (const [token, value] of Object.entries(darkPaletteContracts)) {
  if (!new RegExp(`${token}:\\s*${value};`).test(darkThemeSource)) {
    violations.push(`Dark palette token ${token} must be ${value}`);
  }
}

for (const filePath of sourceFiles) {
  const source = styleSource(filePath).replace(/\/\*[\s\S]*?\*\//g, "");
  const matches =
    source.match(
      /\bbackground(?:-color)?\s*:\s*var\(--color-(brand|success|warning|danger|info|purple)\)(?=\s*;)/g,
    ) || [];
  if (matches.length) {
    violations.push(
      `Filled semantic UI must use solid tokens: ${path.relative(appRoot, filePath)} (${matches.length})`,
    );
  }
}

if (violations.length) {
  console.error("Client theme contract violations:");
  console.error(violations.join("\n"));
  process.exit(1);
}

console.log("PASS client theme audit: tokenized surfaces and restrained outlines");
