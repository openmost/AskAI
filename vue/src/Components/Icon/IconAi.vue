<template>
  <!-- Ask AI mark: three sparkles, neutral towards the AI providers -->
  <svg
    xmlns="http://www.w3.org/2000/svg"
    width="18"
    height="18"
    viewBox="0 0 24 24"
    fill="none"
    :data-ai-name="aiName"
  >
    <defs>
      <linearGradient
        :id="`${uid}-b`"
        x1="0.2"
        y1="4.4"
        x2="19"
        y2="23.2"
        gradientUnits="userSpaceOnUse"
      >
        <stop offset="0" stop-color="#7A9BF5" />
        <stop offset=".55" stop-color="#426CDA" />
        <stop offset="1" stop-color="#2E4FB8" />
      </linearGradient>
      <linearGradient
        :id="`${uid}-y`"
        x1="13"
        y1="0.3"
        x2="23.6"
        y2="10.9"
        gradientUnits="userSpaceOnUse"
      >
        <stop offset="0" stop-color="#FFCB6B" />
        <stop offset="1" stop-color="#FF8C1A" />
      </linearGradient>
      <linearGradient
        :id="`${uid}-p`"
        x1="15.4"
        y1="14.6"
        x2="23"
        y2="22.2"
        gradientUnits="userSpaceOnUse"
      >
        <stop offset="0" stop-color="#FF8AAE" />
        <stop offset="1" stop-color="#F84B5C" />
      </linearGradient>
    </defs>
    <path
      :fill="`url(#${uid}-b)`"
      d="M9.60 4.40C10.54 10.98 12.42 12.86 19.00 13.80
        C12.42 14.74 10.54 16.62 9.60 23.20C8.66 16.62 6.78 14.74 0.20 13.80
        C6.78 12.86 8.66 10.98 9.60 4.40Z"
    />
    <path
      :fill="`url(#${uid}-y)`"
      d="M18.30 0.30C18.83 4.01 19.89 5.07 23.60 5.60
        C19.89 6.13 18.83 7.19 18.30 10.90C17.77 7.19 16.71 6.13 13.00 5.60
        C16.71 5.07 17.77 4.01 18.30 0.30Z"
    />
    <path
      :fill="`url(#${uid}-p)`"
      d="M19.20 14.60C19.58 17.26 20.34 18.02 23.00 18.40
        C20.34 18.78 19.58 19.54 19.20 22.20
        C18.82 19.54 18.06 18.78 15.40 18.40
        C18.06 18.02 18.82 17.26 19.20 14.60Z"
    />
  </svg>
</template>

<script lang="ts">
import { defineComponent } from 'vue';

// gradient ids must stay unique, the mark is rendered several times on the same page
let instanceCount = 0;

export default defineComponent({
  props: {
    aiName: {
      type: String,
      default: 'ask-ai',
    },
  },
  setup() {
    instanceCount += 1;
    return { uid: `ask-ai-mark-${instanceCount}` };
  },
});
</script>
