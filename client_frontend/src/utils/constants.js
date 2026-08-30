/**
 * Application constants
 */

export const APP_NAME = "CRM";
export const APP_VERSION = "1.0.0";

export const ROLES = {
  ADMIN: "admin",
  MANAGER: "manager",
  OPERATOR: "operator",
  VIEWER: "viewer",
};

export const PERMISSIONS = {
  MANAGE_CLIENTS: "manage_clients",
  VIEW_TRANSACTIONS: "view_transactions",
  MANAGE_KYC: "manage_kyc",
  VIEW_REPORTS: "view_reports",
  SYSTEM_SETTINGS: "system_settings",
  MANAGE_ACCOUNTS: "manage_accounts",
};

export const STATUS = {
  ACTIVE: "active",
  INACTIVE: "inactive",
};

export const LOGIN_STATUS = {
  SUCCESS: "success",
  FAILED: "failed",
  BLOCKED: "blocked",
};

export const NOTIFICATION_TYPES = {
  INFO: "info",
  SUCCESS: "success",
  WARNING: "warning",
  ERROR: "error",
};

export const DATE_FORMATS = {
  SHORT: "MM/DD/YYYY",
  LONG: "MMMM DD, YYYY",
  FULL: "MMMM DD, YYYY HH:mm:ss",
  TIME: "HH:mm:ss",
};

export const PASSWORD_REQUIREMENTS = {
  MIN_LENGTH: 8,
  REQUIRE_UPPERCASE: true,
  REQUIRE_LOWERCASE: true,
  REQUIRE_NUMBER: true,
  REQUIRE_SPECIAL: true,
};

export const API_ENDPOINTS = {
  AUTH: "/api/auth.php",
  USERS: "/api/users.php",
  ROLES: "/api/roles.php",
  PERMISSIONS: "/api/permissions.php",
  SETTINGS: "/api/settings.php",
  LOGS: "/api/logs.php",
};
