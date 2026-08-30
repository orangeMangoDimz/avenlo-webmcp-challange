/**
 * Utility helper functions
 */

/**
 * Format date to readable string
 * @param {string|Date} date
 * @param {object} options
 * @returns {string}
 */
export function formatDate(date, options = {}) {
  if (!date) return "-";

  const defaultOptions = {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    ...options,
  };

  return new Date(date).toLocaleString("en-US", defaultOptions);
}

/**
 * Format date to relative time (e.g., "2 hours ago")
 * @param {string|Date} date
 * @returns {string}
 */
export function formatRelativeTime(date) {
  const now = new Date();
  const diff = now - new Date(date);

  const seconds = Math.floor(diff / 1000);
  const minutes = Math.floor(diff / 60000);
  const hours = Math.floor(diff / 3600000);
  const days = Math.floor(diff / 86400000);

  if (seconds < 60) return "just now";
  if (minutes < 60) return `${minutes} minute${minutes !== 1 ? "s" : ""} ago`;
  if (hours < 24) return `${hours} hour${hours !== 1 ? "s" : ""} ago`;
  return `${days} day${days !== 1 ? "s" : ""} ago`;
}

/**
 * Get user initials from full name
 * @param {string} fullName
 * @returns {string}
 */
export function getInitials(fullName) {
  if (!fullName) return "NA";

  const names = fullName.trim().split(" ");
  if (names.length >= 2) {
    return `${names[0][0]}${names[names.length - 1][0]}`.toUpperCase();
  }
  return fullName.substring(0, 2).toUpperCase();
}

/**
 * Validate email address
 * @param {string} email
 * @returns {boolean}
 */
export function validateEmail(email) {
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return regex.test(email);
}

/**
 * Validate password strength
 * @param {string} password
 * @returns {object}
 */
export function validatePassword(password) {
  const minLength = 8;
  const hasUppercase = /[A-Z]/.test(password);
  const hasLowercase = /[a-z]/.test(password);
  const hasNumber = /\d/.test(password);
  const hasSpecial = /[@$!%*?&]/.test(password);

  const isValid =
    password.length >= minLength &&
    hasUppercase &&
    hasLowercase &&
    hasNumber &&
    hasSpecial;

  return {
    isValid,
    minLength: password.length >= minLength,
    hasUppercase,
    hasLowercase,
    hasNumber,
    hasSpecial,
  };
}

/**
 * Debounce function
 * @param {Function} func
 * @param {number} wait
 * @returns {Function}
 */
export function debounce(func, wait = 300) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

/**
 * Deep clone object
 * @param {object} obj
 * @returns {object}
 */
export function deepClone(obj) {
  return JSON.parse(JSON.stringify(obj));
}

/**
 * Generate random color gradient
 * @returns {string}
 */
export function generateAvatarColor() {
  const gradients = [
    "linear-gradient(135deg, #174f46 0%, #b98d3f 100%)",
    "linear-gradient(135deg, #f093fb 0%, #f5576c 100%)",
    "linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)",
    "linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)",
    "linear-gradient(135deg, #fa709a 0%, #fee140 100%)",
    "linear-gradient(135deg, #30cfd0 0%, #330867 100%)",
    "linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)",
    "linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)",
  ];

  return gradients[Math.floor(Math.random() * gradients.length)];
}

/**
 * Format file size
 * @param {number} bytes
 * @returns {string}
 */
export function formatFileSize(bytes) {
  if (bytes === 0) return "0 Bytes";

  const k = 1024;
  const sizes = ["Bytes", "KB", "MB", "GB", "TB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));

  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
}

/**
 * Truncate string
 * @param {string} str
 * @param {number} length
 * @returns {string}
 */
export function truncate(str, length = 50) {
  if (!str) return "";
  if (str.length <= length) return str;
  return str.substring(0, length) + "...";
}

/**
 * Format currency amount with thousand separators and currency symbol
 * @param {number|string} amount - The amount to format
 * @param {string} currencySymbol - Currency symbol (default: '$')
 * @returns {string} Formatted amount with currency symbol, e.g., "$1,234.56"
 */
export function formatCurrency(amount, currencySymbol = "$") {
  if (amount === null || amount === undefined || amount === "")
    return `${currencySymbol}0.00`;

  const numAmount = typeof amount === "string" ? parseFloat(amount) : amount;

  if (isNaN(numAmount)) return `${currencySymbol}0.00`;

  return `${currencySymbol}${numAmount.toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

/**
 * Format number with thousand separators (no currency symbol, no decimal places for integers)
 * @param {number|string} number - The number to format
 * @param {number} decimals - Number of decimal places (default: 0 for integers, can be set for decimals)
 * @returns {string} Formatted number with thousand separators, e.g., "1,234" or "1,234.56"
 */
export function formatNumber(number, decimals = 0) {
  if (number === null || number === undefined || number === "") return "0";

  const numValue = typeof number === "string" ? parseFloat(number) : number;

  if (isNaN(numValue)) return "0";

  // If it's an integer and decimals is 0, format without decimal places
  if (decimals === 0 && Number.isInteger(numValue)) {
    return numValue.toLocaleString("en-US", {
      maximumFractionDigits: 0,
    });
  }

  // Otherwise format with specified decimal places
  return numValue.toLocaleString("en-US", {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  });
}
