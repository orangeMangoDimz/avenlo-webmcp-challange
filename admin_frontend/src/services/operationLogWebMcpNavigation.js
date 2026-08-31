import { OPERATION_LOG_PAGES } from "@/config/operationLogPages";

const modulePage = (module) =>
  Object.values(OPERATION_LOG_PAGES).find(
    (page) => page?.subModuleKey === module,
  );

export const isRegisteredOperationLogModule = (module) =>
  Boolean(modulePage(String(module || "").trim()));

const positiveInteger = (value) => {
  const number = Number(value);
  return Number.isSafeInteger(number) && number > 0 ? number : null;
};

export const buildOperationLogRouteQuery = (filters = {}) => {
  const page = filters.module ? modulePage(filters.module) : null;
  const query = {
    source: "webmcp",
    modelKey: page?.modelKey || "all",
  };
  if (filters.module) query.subModule = filters.module;
  if (filters.operationType) query.operationType = filters.operationType;
  if (filters.operatorId) query.operatorId = String(filters.operatorId);
  if (filters.targetType) query.targetType = filters.targetType;
  if (filters.targetId) query.targetId = String(filters.targetId);
  if (filters.query) query.query = filters.query;
  query.startDate = filters.startDate || "";
  query.endDate = filters.endDate || "";
  return query;
};

export const hydrateOperationLogRouteQuery = (query = {}) => {
  if (query.source !== "webmcp") return null;
  const hydrated = {
    source: "webmcp",
    modelKey: String(query.modelKey || "all"),
    subModule: String(query.subModule || "all"),
    operationType: String(query.operationType || "all"),
  };
  const operatorId = positiveInteger(query.operatorId);
  const targetId = positiveInteger(query.targetId);
  if (operatorId !== null) hydrated.operatorId = operatorId;
  if (query.targetType) hydrated.targetType = String(query.targetType);
  if (targetId !== null) hydrated.targetId = targetId;
  if (query.query) hydrated.query = String(query.query);
  hydrated.startDate = String(query.startDate || "");
  hydrated.endDate = String(query.endDate || "");
  return hydrated;
};
