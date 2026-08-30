<template>
  <!-- 与 mct_web_pc 登录页语言下拉一致：内联 SVG，不依赖 emoji 字体 -->
  <span class="admin-lang-flag-icon" aria-hidden="true">
    <svg
      v-if="isZh"
      class="lang-flag-svg lang-flag-svg--sm"
      viewBox="0 0 30 30"
      xmlns="http://www.w3.org/2000/svg"
    >
      <rect width="30" height="30" fill="#DE2910" />
      <polygon
        points="5,3 6.2,6.8 10,6.8 6.9,9.2 8.1,13 5,10.6 1.9,13 3.1,9.2 0,6.8 3.8,6.8"
        fill="#FFDE00"
        transform="translate(2,2) scale(0.7)"
      />
      <polygon
        points="2,0 2.6,1.8 4.5,1.8 3,2.9 3.5,4.7 2,3.6 0.5,4.7 1,2.9 -0.5,1.8 1.4,1.8"
        fill="#FFDE00"
        transform="translate(11,2)"
      />
      <polygon
        points="2,0 2.6,1.8 4.5,1.8 3,2.9 3.5,4.7 2,3.6 0.5,4.7 1,2.9 -0.5,1.8 1.4,1.8"
        fill="#FFDE00"
        transform="translate(14,5)"
      />
      <polygon
        points="2,0 2.6,1.8 4.5,1.8 3,2.9 3.5,4.7 2,3.6 0.5,4.7 1,2.9 -0.5,1.8 1.4,1.8"
        fill="#FFDE00"
        transform="translate(14,9)"
      />
      <polygon
        points="2,0 2.6,1.8 4.5,1.8 3,2.9 3.5,4.7 2,3.6 0.5,4.7 1,2.9 -0.5,1.8 1.4,1.8"
        fill="#FFDE00"
        transform="translate(11,13)"
      />
    </svg>
    <svg
      v-else
      class="lang-flag-svg lang-flag-svg--sm"
      viewBox="0 0 30 30"
      xmlns="http://www.w3.org/2000/svg"
    >
      <rect x="0" y="0" width="30" height="2.30769" fill="#B22234" />
      <rect x="0" y="2.30769" width="30" height="2.30769" fill="#fff" />
      <rect x="0" y="4.61538" width="30" height="2.30769" fill="#B22234" />
      <rect x="0" y="6.92308" width="30" height="2.30769" fill="#fff" />
      <rect x="0" y="9.23077" width="30" height="2.30769" fill="#B22234" />
      <rect x="0" y="11.53846" width="30" height="2.30769" fill="#fff" />
      <rect x="0" y="13.84615" width="30" height="2.30769" fill="#B22234" />
      <rect x="0" y="16.15385" width="30" height="2.30769" fill="#fff" />
      <rect x="0" y="18.46154" width="30" height="2.30769" fill="#B22234" />
      <rect x="0" y="20.76923" width="30" height="2.30769" fill="#fff" />
      <rect x="0" y="23.07692" width="30" height="2.30769" fill="#B22234" />
      <rect x="0" y="25.38462" width="30" height="2.30769" fill="#fff" />
      <rect x="0" y="27.69231" width="30" height="2.30769" fill="#B22234" />
      <rect x="0" y="0" width="12" height="16.15385" fill="#3C3B6E" />
      <g fill="#fff">
        <circle cx="2.2" cy="2.6" r="0.65" />
        <circle cx="4.6" cy="2.6" r="0.65" />
        <circle cx="7" cy="2.6" r="0.65" />
        <circle cx="9.4" cy="2.6" r="0.65" />
        <circle cx="3.4" cy="4.9" r="0.65" />
        <circle cx="5.8" cy="4.9" r="0.65" />
        <circle cx="8.2" cy="4.9" r="0.65" />
        <circle cx="2.2" cy="7.2" r="0.65" />
        <circle cx="4.6" cy="7.2" r="0.65" />
        <circle cx="7" cy="7.2" r="0.65" />
        <circle cx="9.4" cy="7.2" r="0.65" />
        <circle cx="3.4" cy="9.5" r="0.65" />
        <circle cx="5.8" cy="9.5" r="0.65" />
        <circle cx="8.2" cy="9.5" r="0.65" />
        <circle cx="2.2" cy="11.8" r="0.65" />
        <circle cx="4.6" cy="11.8" r="0.65" />
        <circle cx="7" cy="11.8" r="0.65" />
        <circle cx="9.4" cy="11.8" r="0.65" />
        <circle cx="3.4" cy="14.1" r="0.65" />
        <circle cx="5.8" cy="14.1" r="0.65" />
        <circle cx="8.2" cy="14.1" r="0.65" />
      </g>
    </svg>
  </span>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
  languageCode: {
    type: String,
    required: true,
  },
});

const isZh = computed(() => {
  const c = String(props.languageCode || "").toLowerCase();
  return c === "zh" || c.startsWith("zh-");
});
</script>

<style scoped>
.admin-lang-flag-icon {
  width: 26px;
  height: 26px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  overflow: hidden;
}

.lang-flag-svg {
  width: 100%;
  height: 100%;
  display: block;
}

.lang-flag-svg--sm {
  width: 26px;
  height: 26px;
  border-radius: 4px;
}
</style>
