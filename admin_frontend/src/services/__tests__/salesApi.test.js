import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("../api", () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));

import api from "../api";
import salesApi, { normalizeSalesDashboardId } from "../salesApi";

describe("sales API dashboard targets", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("accepts only positive dashboard sales IDs", () => {
    expect(normalizeSalesDashboardId("42")).toBe(42);
    expect(normalizeSalesDashboardId(7)).toBe(7);
    expect(normalizeSalesDashboardId("0")).toBeNull();
    expect(normalizeSalesDashboardId("Sarah")).toBeNull();
  });

  it("loads one authorized sales dashboard profile by ID", async () => {
    api.get.mockResolvedValue({ data: { id: 42, salesName: "Sarah Tan" } });

    const result = await salesApi.getById("42");

    expect(api.get).toHaveBeenCalledWith("/sales", {
      params: { subpath: "42" },
    });
    expect(result).toEqual({ id: 42, salesName: "Sarah Tan" });
  });
});
