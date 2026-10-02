<template>
  <!-- Ask AI mark: a chat bubble holding a sparkle, neutral towards the AI providers -->
  <svg
    xmlns="http://www.w3.org/2000/svg"
    width="18"
    height="18"
    viewBox="0 0 24 24"
    fill="none"
    :data-ai-name="aiName"
  >
    <path
      d="M12 3C7 3 3 6.6 3 11c0 2.2 1 4.2 2.6 5.6L5 20.4a.5.5 0 0 0 .7.6l3.7-2c.8.2 1.7.3 2.6.3
        5 0 9-3.6 9-8s-4-8-9-8Z"
      stroke="currentColor"
      stroke-width="1.8"
      stroke-linejoin="round"
    />
    <path
      d="M12 6.6l1.1 2.7c.1.4.4.7.8.8l2.7 1.1-2.7 1.1c-.4.1-.7.4-.8.8L12 15.8l-1.1-2.7
        a1.5 1.5 0 0 0-.8-.8l-2.7-1.1 2.7-1.1c.4-.1.7-.4.8-.8L12 6.6Z"
      fill="currentColor"
    />
  </svg>
</template>

<script lang="ts">
import { defineComponent } from 'vue';

export default defineComponent({
  props: {
    aiName: {
      type: String,
      default: 'ask-ai',
    },
  },
});
</script>
