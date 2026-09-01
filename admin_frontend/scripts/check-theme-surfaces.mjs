import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const appRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const sourceRoot = path.join(appRoot, 'src')
const themeStylesheets = [
  path.join(sourceRoot, 'assets', 'styles', 'main.css'),
  path.join(sourceRoot, 'assets', 'styles', 'workspace.css')
]
const workspaceStylesheet = themeStylesheets[1]
const mainStylesheet = themeStylesheets[0]
const rawSurfaceColor = /\bbackground(?:-color)?\s*:\s*(white|#[0-9a-f]{3,6})\b/gi
const rawTextColor = /(?<![-\w])color\s*:\s*(#[0-9a-f]{3,6})\b/gi
const legacyTextColors = new Set([
  '1e293b', '1f2937', '0f172a', '111827', '1a1a1a', '334155', '374151', '475569', '4b5563', '64748b', '6b7280', '94a3b8', '999',
  'c53030', '9b2c2c', 'b91c1c', '991b1b', 'dc2626', 'ef4444', 'f56565',
  '276749', '22543d', '166534', '155724', '0f766e', '047857', '234e52', '285e61', '22673a', '134e4a',
  'c05621', 'b45309', '975a16', '744210', '7b341e', 'b7791f', '9c4221', 'dd6b20', 'ed8936', 'f59e0b', 'f6ad55',
  '2b6cb0', '3182ce', '0284c7', '004085', '0066cc', '0d6efd', '1d4ed8', '2563eb', '3b82f6', '4299e1',
  '805ad5', '5b21b6', '6b46c1', '7c3aed', '8b5cf6', '6366f1', '3730a3'
])

function listVueFiles(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const entryPath = path.join(directory, entry.name)
    if (entry.isDirectory()) return listVueFiles(entryPath)
    return entry.name.endsWith('.vue') ? [entryPath] : []
  })
}

function lineNumber(source, index) {
  return source.slice(0, index).split('\n').length
}

function isLightColor(value) {
  if (value.toLowerCase() === 'white') return true

  const hex = value.slice(1)
  const normalized = hex.length === 3
    ? [...hex].map((channel) => channel.repeat(2)).join('')
    : hex
  const channels = normalized.match(/.{2}/g).map((channel) => Number.parseInt(channel, 16) / 255)
  const linear = channels.map((channel) => (
    channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4
  ))
  const luminance = (0.2126 * linear[0]) + (0.7152 * linear[1]) + (0.0722 * linear[2])

  return luminance >= 0.6
}

const violations = []

for (const filePath of [...listVueFiles(sourceRoot), ...themeStylesheets]) {
  const source = fs.readFileSync(filePath, 'utf8').replace(/\/\*[\s\S]*?\*\//g, '')
  for (const match of source.matchAll(rawSurfaceColor)) {
    if (isLightColor(match[1])) {
      violations.push(`${path.relative(appRoot, filePath)}:${lineNumber(source, match.index)} (${match[1]})`)
    }
  }

  for (const match of source.matchAll(rawTextColor)) {
    if (legacyTextColors.has(match[1].slice(1).toLowerCase())) {
      violations.push(`${path.relative(appRoot, filePath)}:${lineNumber(source, match.index)} (${match[1]})`)
    }
  }
}

const workspaceSource = fs.readFileSync(workspaceStylesheet, 'utf8')
const mainSource = fs.readFileSync(mainStylesheet, 'utf8')
const stickySurfaceContracts = [
  ['Clients sticky action header', /#app\s+\.workspace-main\s+\.clients-table\s*>\s*thead\s*>\s*tr\s*>\s*\.clients-table__th--sticky\s*\{[^}]*background:\s*var\(--color-surface-soft\)/s],
  ['Clients sticky action cell', /#app\s+\.workspace-main\s+\.clients-table\s*>\s*tbody\s*>\s*tr\s*>\s*\.clients-table__cell--sticky\s*\{[^}]*background:\s*var\(--color-surface\)/s],
  ['Leads sticky action header', /#app\s+\.workspace-main\s+\.leads-table\s*>\s*thead\s*>\s*tr\s*>\s*\.leads-table__th--sticky\s*\{[^}]*background:\s*var\(--color-surface-soft\)/s],
  ['Leads sticky action cell', /#app\s+\.workspace-main\s+\.leads-table\s*>\s*tbody\s*>\s*tr\s*>\s*\.leads-table__cell--sticky\s*\{[^}]*background:\s*var\(--color-surface\)/s],
  ['IB rules sticky action header', /#app\s+\.workspace-main\s+\.ib-rules-table\s*>\s*thead\s*>\s*tr\s*>\s*\.ib-rules-table__th--sticky\s*\{[^}]*background:\s*var\(--color-surface-soft\)/s],
  ['IB rules sticky action cell', /#app\s+\.workspace-main\s+\.ib-rules-table\s*>\s*tbody\s*>\s*tr\s*>\s*\.ib-rules-table__cell--sticky\s*\{[^}]*background:\s*var\(--color-surface\)/s]
]
const detailSurfaceContracts = [
  ['Client detail shell', /#app\s+\.workspace-main\s+\.client-detail-page\s+\.client-detail-container\s*\{[^}]*background:\s*var\(--color-surface\)/s],
  ['Client detail navigation', /#app\s+\.workspace-main\s+\.client-detail-page\s+\.tab-nav\s*\{[^}]*background:\s*var\(--color-surface\)/s],
  ['Client detail cards', /#app\s+\.workspace-main\s+\.client-detail-page\s+\.data-card\s*\{[^}]*background:\s*var\(--color-surface\)/s]
]
const headerControlContracts = [
  ['Topbar theme toggle foreground', /#app\s+\.workspace-header-actions\s+:is\([^)]*\.theme-toggle[^)]*\)\s*\{[^}]*color:\s*var\(--color-ink\)/s]
]
const lightThemeContracts = [
  ['Light theme topbar surface', /:root:not\(\[data-theme="dark"\]\)\s+#app\s+\.workspace-shell\s+\.workspace-topbar\s*\{[^}]*background:\s*var\(--color-surface\)/s],
  ['Light theme topbar foreground', /:root:not\(\[data-theme="dark"\]\)\s+#app\s+\.workspace-shell\s+\.workspace-topbar\s*\{[^}]*color:\s*var\(--color-ink\)/s],
  ['Light theme sidebar surface', /:root:not\(\[data-theme="dark"\]\)\s+#app\s+\.workspace-shell\s+>\s+\.sidebar\s*\{[^}]*background:\s*var\(--color-surface\)/s],
  ['Light theme sidebar foreground', /:root:not\(\[data-theme="dark"\]\)\s+#app\s+\.workspace-shell\s+>\s+\.sidebar\s*\{[^}]*color:\s*var\(--color-ink\)/s],
  ['Light theme active sidebar foreground', /:root:not\(\[data-theme="dark"\]\)\s+#app\s+\.workspace-shell\s+>\s+\.sidebar\s+:is\([^)]*\.menu-item\.active[^)]*\)\s*\{[^}]*color:\s*#fff/s]
]
const datePickerContracts = [
  ['Element Plus date picker inner input reset', /#app\s+:where\(\.el-date-editor\)\s+\.el-input__inner\s*\{[^}]*min-height:\s*0;[^}]*background:\s*transparent;[^}]*border:\s*0;[^}]*box-shadow:\s*none;/s]
]
const tablePositionContracts = [
  ['IB grid headers stay within their scroll region', /#app\s+\.workspace-main\s+\.ir-list-table\s*>\s*thead\s*>\s*tr\s*>\s*th\s*\{[^}]*position:\s*static;[^}]*top:\s*auto;/s],
  ['IB sticky action header keeps horizontal pinning only', /#app\s+\.workspace-main\s+\.ir-list-table\s*>\s*thead\s*>\s*tr\s*>\s*\.ir-list-table__th--sticky\s*\{[^}]*position:\s*sticky;[^}]*top:\s*auto;[^}]*right:\s*0;[^}]*z-index:\s*4;/s],
  ['Activity audit header stays within its table', /#app\s+\.workspace-main\s+\.operation-log-report-page\s+\.olr-table\s*>\s*thead\s*>\s*tr\s*>\s*th\s*\{[^}]*position:\s*static;[^}]*top:\s*auto;[^}]*z-index:\s*auto;/s],
  ['Funding trend rows stay within their table', /#app\s+\.workspace-main\s+\.funding-chart\s+table\s+th\s*\{[^}]*position:\s*static;[^}]*top:\s*auto;[^}]*z-index:\s*auto;/s],
  ['Payment gateway headers stay within their table', /#app\s+\.workspace-main\s+\.gateway-table\s+table\s+th\s*\{[^}]*position:\s*static;[^}]*top:\s*auto;[^}]*z-index:\s*auto;/s],
  ['Exchange rate headers stay within their table', /#app\s+\.workspace-main\s+\.rates-table\s+table\s+th\s*\{[^}]*position:\s*static;[^}]*top:\s*auto;[^}]*z-index:\s*auto;/s],
  ['Daily sales headers stay within their table', /#app\s+\.workspace-main\s+\.dr-table\s+th\s*\{[^}]*position:\s*static;[^}]*top:\s*auto;[^}]*z-index:\s*auto;/s]
]
const darkThemeSource = fs.readFileSync(themeStylesheets[0], 'utf8').match(/:root\[data-theme=(?:'dark'|"dark")\]\s*\{([\s\S]*?)\n\}/)?.[1] || ''
const darkPaletteContracts = {
  '--color-brand': '#6688d8',
  '--color-brand-solid': '#315ca8',
  '--color-success': '#36a76c',
  '--color-success-solid': '#176b44',
  '--color-warning': '#bf8f3f',
  '--color-warning-solid': '#72531d',
  '--color-danger': '#cb686e',
  '--color-danger-solid': '#833943',
  '--color-info': '#628bd4',
  '--color-info-solid': '#2c558c',
  '--color-purple': '#b878ac',
  '--color-purple-solid': '#613d6e'
}

for (const [token, value] of Object.entries(darkPaletteContracts)) {
  if (!new RegExp(`${token}:\\s*${value};`).test(darkThemeSource)) {
    violations.push(`Dark palette token ${token} must be ${value}`)
  }
}

const nonSolidSemanticBackgrounds = []
for (const filePath of [...listVueFiles(sourceRoot), ...themeStylesheets]) {
  const source = fs.readFileSync(filePath, 'utf8').replace(/\/\*[\s\S]*?\*\//g, '')
  const matches = source.match(/\bbackground(?:-color)?\s*:\s*var\(--color-(brand|success|warning|danger|info)\)(?=\s*;)/g) || []
  if (matches.length) nonSolidSemanticBackgrounds.push(`${path.relative(appRoot, filePath)} (${matches.length})`)
}
if (nonSolidSemanticBackgrounds.length) {
  violations.push(`Filled semantic UI must use solid tokens: ${nonSolidSemanticBackgrounds.join(', ')}`)
}

for (const [name, contract] of [...stickySurfaceContracts, ...detailSurfaceContracts, ...headerControlContracts, ...lightThemeContracts, ...tablePositionContracts]) {
  if (!contract.test(workspaceSource)) violations.push(`${name} is missing its tokenized workspace fallback`)
}
for (const [name, contract] of datePickerContracts) {
  if (!contract.test(mainSource)) violations.push(`${name} is missing its tokenized main stylesheet reset`)
}

if (violations.length) {
  console.error('Raw legacy UI colors bypass the theme tokens:')
  console.error(violations.join('\n'))
  process.exit(1)
}

console.log('PASS theme audit: no raw light surfaces or legacy text colors found')
