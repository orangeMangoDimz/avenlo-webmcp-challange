<template>
  <router-view />
</template>

<script setup>
// Root component - just renders router views
</script>

<style>
@import "./assets/styles/workspace.css";

/* iOS Safari 在 input font-size < 16px 时会自动缩放页面，统一在移动端把所有可输入元素提到 16px。
   选择器特异性（0,5,1）高于 Vue scoped 样式的常规规则（最多 0,2,0），无需 !important 即可覆盖。 */
@media (max-width: 768px) {
  input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not(
      [type="submit"]
    ):not([type="reset"]):not([type="file"]),
  textarea,
  select {
    font-size: 16px;
  }
}

/* iOS Safari 对 <input type="date"> 应用原生外观（灰底 / 居中 / 文字看起来像按钮），
   清掉 webkit 默认样式让它跟普通文本框一致。同样需要处理 datetime-local / time / month / week。 */
input[type="date"],
input[type="datetime-local"],
input[type="time"],
input[type="month"],
input[type="week"] {
  -webkit-appearance: none;
  appearance: none;
  background-color: var(--color-surface);
  text-align: left;
}

/* iOS 把 date 值渲染在 ::-webkit-date-and-time-value 这个 shadow 元素里，单独控制对齐和高度
   防止值文字仍然居中或上下贴边 */
input[type="date"]::-webkit-date-and-time-value,
input[type="datetime-local"]::-webkit-date-and-time-value,
input[type="time"]::-webkit-date-and-time-value,
input[type="month"]::-webkit-date-and-time-value,
input[type="week"]::-webkit-date-and-time-value {
  text-align: left;
  min-height: 1.4em;
}
</style>
