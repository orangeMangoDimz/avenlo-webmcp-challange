import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const appRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const sidebarSource = fs.readFileSync(path.join(appRoot, 'src', 'components', 'layout', 'Sidebar.vue'), 'utf8')
const layoutSource = fs.readFileSync(path.join(appRoot, 'src', 'layouts', 'MainLayout.vue'), 'utf8')

const requiredSidebarContracts = [
  'sidebar-brand-mark',
  "nav_section_clients', 'Client operations'",
  "nav_section_compliance', 'Identity & compliance'",
  "nav_section_money', 'Money movement'",
  "nav_section_partners', 'Partner network'",
  "nav_section_sales', 'Sales operations'",
  "nav_section_analytics', 'Analytics'",
  "nav_section_administration', 'Administration'",
  "nav_clients', 'Clients'",
  "nav_withdrawals', 'Withdrawals'",
  "nav_partner_network', 'Partner network'",
  "nav_sales_team', 'Sales team'",
  "nav_user_accounts', 'User accounts'"
]

const requiredLayoutContracts = [
  'workspace-brand-monogram',
  'Avenlo',
  'Control center',
  '>Menu<'
]

const missing = [
  ...requiredSidebarContracts
    .filter((contract) => !sidebarSource.includes(contract))
    .map((contract) => `Sidebar contract missing: ${contract}`),
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
