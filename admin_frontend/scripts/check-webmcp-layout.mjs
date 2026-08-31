import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const appRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const views = ['WebMcpOverview.vue', 'WebMcpTools.vue']
const developerViews = ['DeveloperSettings.vue', ...views]
const developerTabsSource = fs.readFileSync(
  path.join(appRoot, 'src', 'components', 'layout', 'DeveloperToolsTabs.vue'),
  'utf8',
)
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

  if (view === 'WebMcpTools.vue') {
    if (!source.includes('class="webmcp-tool-table"')) {
      violations.push('WebMcpTools does not render the shared table-based catalog')
    }
    if (!source.includes('class="webmcp-table-container"')) {
      violations.push('WebMcpTools does not render the IB-report-style table surface')
    }
    if (!source.includes('class="webmcp-table-header"')) {
      violations.push('WebMcpTools does not render the table title bar')
    }
    if (!source.includes('class="webmcp-table-scroll"')) {
      violations.push('WebMcpTools does not render the table scroll wrapper')
    }
    if (source.includes('class="webmcp-section-list"') || source.includes('class="webmcp-tool-summary"')) {
      violations.push('WebMcpTools still renders the card-based accordion catalog')
    }
    if (!source.includes('class="webmcp-tool-section-cell"') || source.includes('class="webmcp-section-row"')) {
      violations.push('WebMcpTools does not render each tool as a direct table row with a section column')
    }
  }

}

if (!developerTabsSource.includes('to: "/developer-settings"') ||
    !developerTabsSource.includes('to: "/webmcp/overview"') ||
    !developerTabsSource.includes('to: "/webmcp/tools"')) {
  violations.push('Developer tools tab strip does not expose all tool sections')
}

for (const view of developerViews) {
  const source = fs.readFileSync(path.join(appRoot, 'src', 'views', view), 'utf8')
  if (!source.includes('<DeveloperToolsTabs />')) {
    violations.push(`${view} does not render the shared developer tools tab strip`)
  }
}

if (
  !/#app\s+\.workspace-main\s+\.webmcp-arguments-table\s+th\s*\{[^}]*position:\s*static;[^}]*top:\s*auto;/s.test(
    workspaceSource,
  )
) {
  violations.push('WebMCP argument table does not override the global sticky header rule')
}

if (!workspaceSource.includes('.webmcp-tool-table > thead > tr > th')) {
  violations.push('WebMCP catalog table does not use the report-style table header rule')
}

if (violations.length) {
  console.error('WebMCP layout audit failed:')
  console.error(violations.join('\n'))
  process.exit(1)
}

console.log('PASS WebMCP layout audit: page content has no redundant tab strip')
