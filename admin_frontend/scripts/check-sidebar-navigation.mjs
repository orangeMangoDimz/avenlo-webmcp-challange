import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const appRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const sidebarSource = fs.readFileSync(path.join(appRoot, 'src', 'components', 'layout', 'Sidebar.vue'), 'utf8')
const layoutSource = fs.readFileSync(path.join(appRoot, 'src', 'layouts', 'MainLayout.vue'), 'utf8')

const requiredSidebarContracts = [
  ['sidebar-brand-mark', /sidebar-brand-mark/],
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
  ['User accounts', /nav_user_accounts["']\s*,\s*["']User accounts["']/]
]

const requiredLayoutContracts = [
  'workspace-brand-monogram',
  'Avenlo',
  'Control center',
  '>Menu<'
]

const missing = [
  ...requiredSidebarContracts
    .filter(([, contract]) => !contract.test(sidebarSource))
    .map(([name]) => `Sidebar contract missing: ${name}`),
  ...requiredLayoutContracts
    .filter((contract) => !layoutSource.includes(contract))
    .map((contract) => `Topbar brand contract missing: ${contract}`)
]

if (missing.length) {
  console.error('Sidebar navigation contract failed:')
  console.error(missing.join('\n'))
  process.exit(1)
}

console.log('PASS sidebar navigation audit: branded hierarchy and labels are present')
