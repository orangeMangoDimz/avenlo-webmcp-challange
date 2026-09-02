/** @vitest-environment jsdom */

import { afterEach, describe, expect, it, vi } from "vitest";
import { registerAdminWebMcpTools } from "../adminWebMcpRegistry";

describe("admin WebMCP registry", () => {
  afterEach(() => {
    delete document.modelContext;
    vi.restoreAllMocks();
  });

  it("registers all permitted domain tools and cleans up every registration", async () => {
    const registerTool = vi.fn(() => Promise.resolve());
    document.modelContext = { registerTool };
    const authStore = {
      isAuthenticated: true,
      hasPermission: vi.fn(() => true),
    };

    const cleanup = registerAdminWebMcpTools({
      authStore,
      router: { push: vi.fn(async () => {}) },
    });
    await Promise.resolve();

    expect(registerTool.mock.calls.map(([tool]) => tool.name)).toEqual([
      "get_dashboard_summary",
      "get_client",
      "navigate_to_client",
      "search_clients",
      "get_client_documents",
      "get_client_trading_accounts",
      "get_client_recent_transactions",
      "export_clients",
      "export_client_transactions",
      "search_transactions",
      "get_transaction",
      "export_transactions",
      "search_kyc",
      "get_kyc",
      "get_kyc_answers",
      "get_kyc_documents",
      "get_kyc_progress",
      "get_kyc_timeline",
      "get_ib_partner",
      "get_ib_network",
      "get_ib_network_stats",
      "get_ib_clients",
      "get_client_ib_upline",
      "navigate_to_ib",
      "search_sales",
      "get_sales_clients",
      "get_sales_partners",
      "get_sales_performance",
      "get_sales_daily_summary",
      "get_sales_leaderboard",
      "navigate_to_sales",
      "get_funding_summary",
      "search_funding_transactions",
      "get_daily_sales_performance",
      "search_ib_partners",
      "get_ib_statement",
      "list_custom_reports",
      "get_custom_report_results",
      "navigate_to_report",
      "export_funding_report",
      "export_ib_statement",
      "search_admin_users",
      "get_admin_user",
      "get_role_permissions",
      "find_roles_by_permission",
      "check_admin_user_permission",
      "search_operation_logs",
      "get_operation_log",
      "navigate_to_operation_logs",
      "export_operation_logs",
    ]);

    const signals = registerTool.mock.calls.map(
      ([, options]) => options.signal,
    );
    cleanup();
    expect(signals.every((signal) => signal.aborted)).toBe(true);
  });
});
