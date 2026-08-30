/**
 * 通知预览工具：对齐后端 ClientNotificationController
 * buildEmailPayload / sanitizeSystemMessage / replacePlaceholders
 */

function escapeHtml(text) {
  if (typeof document === "undefined") {
    return String(text)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

function nl2br(text) {
  return escapeHtml(text).replace(/\n/g, "<br>");
}

function stripHtmlTags(html) {
  if (!html) return "";
  if (typeof document === "undefined") {
    return html.replace(/<[^>]+>/g, " ");
  }
  const div = document.createElement("div");
  div.innerHTML = html;
  return div.textContent || div.innerText || "";
}

function formatNow() {
  const d = new Date();
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/** 判断内容是否为 HTML（含粘贴的邮件模板） */
export function looksLikeHtml(text) {
  const s = String(text ?? "").trim();
  if (!s) return false;
  return (
    /<!DOCTYPE/i.test(s) ||
    /<\s*html[\s>]/i.test(s) ||
    /<\s*(div|table|p|h[1-6]|span|center|body|head|style|section|article|header|footer|ul|ol|li|tr|td|th|a|img|br|hr)\b/i.test(
      s,
    )
  );
}

/** 站内通知：去除 HTML，与后端 sanitizeSystemMessage 一致 */
export function sanitizeSystemMessage(message) {
  const text = stripHtmlTags(message ?? "");
  return text.replace(/\s+/g, " ").trim();
}

/** 占位符替换 */
export function replaceNotificationPlaceholders(text, client, message, isHtml) {
  if (!text) return "";

  const firstName = client?.firstName ?? "";
  const lastName = client?.lastName ?? "";
  const fullName = `${firstName} ${lastName}`.trim();
  const safeMessage = String(message ?? "").trim();

  const replacements = {
    "{{firstName}}": firstName,
    "{{lastName}}": lastName,
    "{{fullName}}": fullName,
    "{{email}}": client?.email ?? "",
    "{{clientId}}": client?.id != null ? String(client.id) : "",
    "{{now}}": formatNow(),
  };

  if (isHtml) {
    replacements["{{message}}"] = nl2br(safeMessage);
  } else {
    replacements["{{message}}"] = safeMessage.replace(/\s+/g, " ");
  }

  let result = text;
  Object.entries(replacements).forEach(([key, value]) => {
    result = result.split(key).join(value);
  });
  return result;
}

/** 构建邮件预览 HTML，与后端 buildEmailPayload 一致；HTML 内容在预览中直接渲染 */
export function buildEmailPreviewPayload(
  client,
  subject,
  message,
  template = null,
) {
  const rawMessage = String(message ?? "").trim();
  const messageIsHtml = looksLikeHtml(rawMessage);

  // Message 整段是 HTML 模板时，{{message}} 占位符无独立正文
  const messageForPlaceholder =
    messageIsHtml && !template?.body ? "" : rawMessage;

  const safeMessageHtml = nl2br(messageForPlaceholder);

  let finalSubject = replaceNotificationPlaceholders(
    subject,
    client,
    messageForPlaceholder,
    false,
  );
  let finalBody = `<p>${safeMessageHtml}</p>`;

  if (template?.body) {
    const templateSubject = template.subject ?? subject;
    finalSubject = replaceNotificationPlaceholders(
      templateSubject,
      client,
      rawMessage,
      false,
    );

    const templateBody = template.body;
    if (templateBody) {
      finalBody = replaceNotificationPlaceholders(
        templateBody,
        client,
        rawMessage,
        true,
      );
      if (!templateBody.includes("{{message}}")) {
        finalBody += `<p>${nl2br(rawMessage)}</p>`;
      }
    }
  } else if (messageIsHtml) {
    finalBody = replaceNotificationPlaceholders(
      rawMessage,
      client,
      messageForPlaceholder,
      true,
    );
  } else {
    finalBody = `<p>${safeMessageHtml}</p>`;
  }

  return {
    subject: finalSubject,
    body: finalBody,
  };
}

/** iframe 隔离渲染，避免模板 style 污染弹窗 */
export function wrapEmailForIframe(html) {
  if (!html) {
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body></body></html>';
  }

  const trimmed = html.trim();

  if (typeof document === "undefined") {
    const withoutStyle = trimmed.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, "");
    if (
      /<!DOCTYPE/i.test(withoutStyle) ||
      /<\s*html[\s>]/i.test(withoutStyle)
    ) {
      return withoutStyle;
    }
    return `<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>${withoutStyle}</body></html>`;
  }

  if (/<!DOCTYPE/i.test(trimmed) || /<\s*html[\s>]/i.test(trimmed)) {
    const doc = trimmed.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, "");
    return doc;
  }

  const tempDiv = document.createElement("div");
  tempDiv.innerHTML = trimmed;
  tempDiv.querySelectorAll("style").forEach((tag) => tag.remove());
  return `<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;">${tempDiv.innerHTML}</body></html>`;
}
