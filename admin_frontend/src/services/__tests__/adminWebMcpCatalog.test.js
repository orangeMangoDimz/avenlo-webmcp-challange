import { describe, expect, it } from "vitest";
import { getWebMcpToolBaseRoles } from "../adminWebMcpCatalog";

describe("WebMCP base role access", () => {
  it("shows every base role that satisfies any required permission", () => {
    expect(
      getWebMcpToolBaseRoles({
        permissionKeys: [
          "page_clientslist_readonly",
          "page_clientsdetail_profile",
        ],
      }),
    ).toEqual(["Administrator", "Manager", "Sales Manager", "Sales", "Ops"]);
  });

  it("requires every listed permission when a tool uses all matching", () => {
    expect(
      getWebMcpToolBaseRoles({
        permissionKeys: [
          "page_ibstatement_readonly",
          "page_ibstatement_export",
        ],
        permissionMatch: "all",
      }),
    ).toEqual(["Administrator"]);
  });

  it("keeps Administrator visible for permissions no other base role has", () => {
    expect(
      getWebMcpToolBaseRoles({
        permissionKeys: ["page_rolemanagement_edit"],
      }),
    ).toEqual(["Administrator"]);
  });
});
