import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const appRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const sidebarSource = fs.readFileSync(path.join(appRoot, 'src', 'components', 'layout', 'Sidebar.vue'), 'utf8')
const layoutSource = fs.readFileSync(path.join(appRoot, 'src', 'layouts', 'MainLayout.vue'), 'utf8')
const brandAssetPath = path.join(appRoot, 'src', 'assets', 'brand', 'avenlo-logo.png')

const requiredSidebarContracts = [
  ['Client operations', /nav_section_clients["']\s*,\s*["']Client operations["']/],
  ['Identity & compliance', /nav_section_compliance["']\s*,\s*["']Identity & compliance["']/],
  ['Money movement', /nav_section_money["']\s*,\s*["']Money movement["']/],
  ['Partner network', /nav_section_partners["']\s*,\s*["']Partner network["']/],
  ['Sales operations', /nav_section_sales["']\s*,\s*["']Sales operations["']/],
  ['Analytics', /nav_section_analytics["']\s*,\s*["']Analytics["']/],
  ['Administration', /nav_section_administration["']\s*,\s*["']Administration["']/],
  ['Clients', /nav_clients["']\s*,\s*["']Clients["']/],
  ['Withdrawals', /nav_withdrawals["']\s*,\s*["']Withdrawals["']/],
  ['Partner network link', /nav_partner_network["']\s*,\s*["']Partner network["']/],
  ['Sales team', /nav_sales_team["']\s*,\s*["']Sales team["']/],
  ['User accounts', /nav_user_accounts["']\s*,\s*["']User accounts["']/],
  ['Operations overview', /nav_operations_overview["']\s*,\s*["']Operations overview["']/],
  ['Tool catalog', /nav_webmcp_tools["']\s*,\s*["']Tool catalog["']/]
]

const requiredLayoutContracts = [
  'workspace-brand-logo',
  'avenloLogo',
  'alt="Avenlo"',
  'workspace-navigate-button',
  'aria-label="Open navigation"',
  'fa-bars'
]

const removedSidebarContracts = [
  ['duplicate sidebar brand', /sidebar-brand(?:-mark|-copy)?/],
  ['pin control', /sidebar-pin-btn|data-testid=["']sidebar-pin["']|toggle-pin|thumbtack/],
  ['nested WebMCP links', /webmcp-parent-item|menu-sub-items|menu-sub-item/],
  ['WebMCP overview inside Developer tools', /showDeveloperSettings[\s\S]*?to="\/webmcp\/overview"/]
]
const removedLayoutContracts = [
  ['visible menu label', /<span>Menu<\/span>/],
  ['legacy text logo', /workspace-brand-monogram|workspace-brand-copy/]
]

const missing = [
  ...requiredSidebarContracts
    .filter(([, contract]) => !contract.test(sidebarSource))
    .map(([name]) => `Sidebar contract missing: ${name}`),
  ...requiredLayoutContracts
    .filter((contract) => !layoutSource.includes(contract))
    .map((contract) => `Topbar brand contract missing: ${contract}`),
  ...(fs.existsSync(brandAssetPath)
    ? []
    : ['Topbar brand asset missing: src/assets/brand/avenlo-logo.png']),
  ...removedSidebarContracts
    .filter(([, contract]) => contract.test(sidebarSource) || contract.test(layoutSource))
    .map(([name]) => `Removed sidebar contract still present: ${name}`),
  ...removedLayoutContracts
    .filter(([, contract]) => contract.test(layoutSource))
    .map(([name]) => `Removed layout contract still present: ${name}`)
]

const navigationLabelIndex = sidebarSource.indexOf('sidebar-navigation-label')
const operationsLinkIndex = sidebarSource.indexOf('to="/webmcp/overview"')
const clientSectionIndex = sidebarSource.indexOf('<!-- Client operations -->')
if (operationsLinkIndex < 0 || clientSectionIndex < 0 || operationsLinkIndex > clientSectionIndex) {
  missing.push('Operations overview is not the first navigation item')
}
if (navigationLabelIndex < 0 || operationsLinkIndex < navigationLabelIndex) {
  missing.push('Operations overview is not positioned after the navigation label')
}

if (missing.length) {
  console.error('Sidebar navigation contract failed:')
  console.error(missing.join('\n'))
  process.exit(1)
}

console.log('PASS sidebar navigation audit: branded hierarchy and labels are present')
