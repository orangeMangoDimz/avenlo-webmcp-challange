import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const appRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const views = ['WebMcpOverview.vue', 'WebMcpTools.vue']
const developerViews = ['DeveloperSettings.vue', 'WebMcpTools.vue']
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
    if (!source.includes('webmcp_accessible_roles_column')) {
      violations.push('WebMcpTools does not expose an Accessible roles column')
    }
    if (!source.includes('class="webmcp-tool-role-badge"') || !source.includes('colspan="6"')) {
      violations.push('WebMcpTools does not render role badges or span expanded rows across all columns')
    }
    if (!source.includes('min-width: 1320px') ||
        !source.includes('th:nth-child(5) {\n  width: 16%;') ||
        !source.includes('th:nth-child(6) {\n  width: 14%;')) {
      violations.push('WebMcpTools does not reserve sufficient width for Status and Detail alongside role badges')
    }
    if (source.includes('class="webmcp-section-list"') || source.includes('class="webmcp-tool-summary"')) {
      violations.push('WebMcpTools still renders the card-based accordion catalog')
    }
    if (!source.includes('class="webmcp-tool-section-cell"') || source.includes('class="webmcp-section-row"')) {
      violations.push('WebMcpTools does not render each tool as a direct table row with a section column')
    }
  }

  if (view === 'WebMcpOverview.vue') {
    for (const dashboardSurface of [
      'class="operations-filter-bar"',
      'class="operations-metric-grid"',
      'class="operations-attention-panel"',
      'class="operations-support-grid"',
      'aria-live="polite"',
    ]) {
      if (!source.includes(dashboardSurface)) {
        violations.push(`WebMcpOverview is missing ${dashboardSurface}`)
      }
    }
    if (!source.includes('getOperationsOverview')) {
      violations.push('WebMcpOverview does not request the permission-aware operations aggregate')
    }
    if (!source.includes('setWebMcpEnabled')) {
      violations.push('WebMcpOverview no longer exposes the compact browser runtime control')
    }
  }

}

if (!developerTabsSource.includes('to: "/developer-settings"') ||
    !developerTabsSource.includes('to: "/webmcp/tools"')) {
  violations.push('Developer tools tab strip is missing a retained tool section')
}
if (developerTabsSource.includes('to: "/webmcp/overview"')) {
  violations.push('Developer tools tab strip still exposes the WebMCP overview tab')
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

if (
  !/#app\s+\.workspace-main\s+\.webmcp-tool-table\s*\{[^}]*min-width:\s*1320px;/s.test(
    workspaceSource,
  )
) {
  violations.push('WebMCP workspace table rule does not preserve the catalog width needed for Status and Detail')
}

if (violations.length) {
  console.error('WebMCP layout audit failed:')
  console.error(violations.join('\n'))
  process.exit(1)
}

console.log('PASS WebMCP layout audit: page content has no redundant tab strip')
