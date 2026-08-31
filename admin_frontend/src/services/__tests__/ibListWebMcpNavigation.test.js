import { describe, expect, it, vi } from "vitest";
import { applyIbListWebMcpNavigation } from "../ibListWebMcpNavigation";

describe("IB list WebMCP navigation", () => {
  it("loads the requested search and expands a resolved IB", async () => {
    const setSearch = vi.fn();
    const loadList = vi.fn(async () => {});
    const expand = vi.fn();

    const result = await applyIbListWebMcpNavigation({
      query: { search: "IB-2026-042", detailId: "42" },
      setSearch,
      loadList,
      hasRow: (id) => id === 42,
      expand,
    });

    expect(setSearch).toHaveBeenCalledWith("IB-2026-042");
    expect(loadList).toHaveBeenCalledOnce();
    expect(expand).toHaveBeenCalledWith(42);
    expect(result).toEqual({ search: "IB-2026-042", detailId: 42 });
  });

  it("loads normally and ignores malformed detail IDs", async () => {
    const setSearch = vi.fn();
    const loadList = vi.fn(async () => {});
    const expand = vi.fn();

    const result = await applyIbListWebMcpNavigation({
      query: { detailId: "bad" },
      setSearch,
      loadList,
      hasRow: () => true,
      expand,
    });

    expect(setSearch).not.toHaveBeenCalled();
    expect(loadList).toHaveBeenCalledOnce();
    expect(expand).not.toHaveBeenCalled();
    expect(result).toEqual({ search: null, detailId: null });
  });
});
