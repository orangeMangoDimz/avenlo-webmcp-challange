import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const appRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const views = ['WebMcpOverview.vue', 'WebMcpTools.vue']
const workspaceSource = fs.readFileSync(
  path.join(appRoot, 'src', 'assets', 'styles', 'workspace.css'),
  'utf8',
)
const violations = []

for (const view of views) {
  const source = fs.readFileSync(path.join(appRoot, 'src', 'views', view), 'utf8')
  if (source.includes('webmcp-tabs')) {
    violations.push(`${view} still renders the redundant WebMCP page tabs`)
  }

}

if (
  !/#app\s+\.workspace-main\s+\.webmcp-arguments-table\s+th\s*\{[^}]*position:\s*static;[^}]*top:\s*auto;/s.test(
    workspaceSource,
  )
) {
  violations.push('WebMCP argument table does not override the global sticky header rule')
}

if (violations.length) {
  console.error('WebMCP layout audit failed:')
  console.error(violations.join('\n'))
  process.exit(1)
}

console.log('PASS WebMCP layout audit: page content has no redundant tab strip')
