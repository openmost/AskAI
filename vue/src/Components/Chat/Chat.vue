<template>
  <div :class="['ai-chat', 'ai-chat-theme', `ai-chat--${variant}`]">
    <ChatMessagesList
      :loading="loading"
      :errored="errored"
      :error-message="errorMessage"
      :recommendation="recommendation"
      :messages="displayMessages"
      :ai-name="aiName"
      :ai-label="aiLabel"
      :streaming="streaming"
    >
      <template
        v-if="showEmptyState"
        #empty
      >
        <ChatEmptyState
          :ai-name="aiName"
          :ai-label="aiLabel"
          @suggest="onSuggestion"
        />
      </template>
    </ChatMessagesList>
    <div class="ai-chat-composer">
      <ChatForm
        ref="form"
        :loading="loading || streaming"
        :ai-label="aiLabel"
        @prompt="onSubmit"
      />
    </div>
    <p
      class="ai-chat-sr-only"
      aria-live="polite"
      aria-atomic="true"
    >{{ announcement }}</p>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import { MatomoUrl, translate } from 'CoreHome';
import ChatForm from './ChatForm.vue';
import ChatMessagesList from './ChatMessagesList.vue';
import ChatEmptyState from './ChatEmptyState.vue';
import markdownToPlainText from './markdownToPlainText';
import watchDarkSurface from './darkSurface';
import {
  AgentEvent,
  AgentStatus,
  AgentStep,
  Message,
  Recommendation,
} from '../../types';

// the segment and the comparison of the report being viewed, in the GET query of every request
function getContextParams(): Record<string, string> {
  const params: Record<string, string> = {
    idSite: String(MatomoUrl.parsed.value.idSite || ''),
    period: String(MatomoUrl.parsed.value.period || 'day'),
    date: String(MatomoUrl.parsed.value.date || 'today'),
  };
  const { segment } = MatomoUrl.parsed.value;
  if (segment) {
    params.segment = String(segment);
  }
  return params;
}

function appendComparisonParams(params: URLSearchParams): void {
  ['comparePeriods', 'compareDates', 'compareSegments'].forEach((key) => {
    const values = MatomoUrl.parsed.value[key];
    (Array.isArray(values) ? values : []).forEach((value) => {
      params.append(`${key}[]`, String(value));
    });
  });
}

export default defineComponent({
  components: {
    ChatEmptyState,
    ChatMessagesList,
    ChatForm,
  },
  props: {
    aiName: { type: String, required: true },
    aiLabel: { type: String, required: true },
    aiColor: { type: String, default: '#426CDA' },
    widgetParams: { type: Object, default: () => ({}) },
    // 'panel' in the report insights overlay, 'page' on the chat page
    variant: { type: String, default: 'panel' },
    // suggested questions while the conversation is empty
    showEmptyState: { type: Boolean, default: false },
  },
  data() {
    return {
      loading: false,
      streaming: false,
      errored: false,
      errorMessage: '',
      messages: [] as Message[],
      streamingContent: '',
      agentSteps: [] as AgentStep[],
      agentStatus: null as AgentStatus | null,
      agentStatusPromise: null as Promise<void> | null,
      conversationId: `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 10)}`,
      receivedAgentEvent: false,
      abortController: null as AbortController | null,
      announcement: '',
    };
  },
  created() {
    this.agentStatusPromise = this.loadAgentStatus();
  },
  mounted() {
    watchDarkSurface();
  },
  computed: {
    displayMessages(): Message[] {
      if (this.streaming && (this.streamingContent || this.agentSteps.length)) {
        return [
          ...this.messages,
          { role: 'assistant', content: this.streamingContent, steps: this.agentSteps },
        ];
      }
      return this.messages;
    },
    // the next step to unlock the agent mode, one at a time
    recommendation(): Recommendation | null {
      const recommendations = this.agentStatus?.recommendations;
      return recommendations && recommendations.length ? recommendations[0] : null;
    },
  },
  watch: {
    // complete answers only: the streamed text lives outside messages until it is done
    'messages.length': function onMessagesAdded() {
      const lastMessage = this.messages[this.messages.length - 1];
      if (!lastMessage || lastMessage.role === 'user' || !lastMessage.content) {
        return;
      }
      this.announcement = '';
      this.$nextTick(() => {
        this.announcement = `${translate('AskAI_AnswerAnnouncement')} ${
          markdownToPlainText(lastMessage.content)}`;
      });
    },
  },
  methods: {
    focusInput() {
      const form = this.$refs.form as InstanceType<typeof ChatForm> | undefined;
      if (form) {
        form.focus();
      }
    },
    onSuggestion(text: string) {
      this.onSubmit({ role: 'user', content: text });
    },
    async onSubmit(userPrompt?: Message) {
      if (userPrompt) {
        this.messages.push(userPrompt);
      }
      this.loading = true;
      this.errored = false;
      this.errorMessage = '';

      // the first message may be sent before the agent status is known (insights panel)
      if (this.agentStatusPromise) {
        await this.agentStatusPromise;
      }

      this.fetchAgent();
    },

    async loadAgentStatus(): Promise<void> {
      try {
        const params = new URLSearchParams({
          module: 'AskAI',
          action: 'agentStatus',
          ...getContextParams(),
        });
        appendComparisonParams(params);
        const response = await fetch(`index.php?${params.toString()}`, { credentials: 'include' });
        this.agentStatus = response.ok ? await response.json() as AgentStatus : null;
      } catch {
        // without the status, the chat still answers: only the recommendation notice is missing
        this.agentStatus = null;
      } finally {
        this.agentStatusPromise = null;
      }
    },

    getConversationPayload(): string {
      return JSON.stringify(this.messages.map(({ role, content }) => ({ role, content })));
    },

    async fetchAgent() {
      this.streaming = false;
      this.streamingContent = '';
      this.agentSteps = [];
      this.receivedAgentEvent = false;

      if (this.abortController) {
        this.abortController.abort();
      }
      this.abortController = new AbortController();

      try {
        const params = new URLSearchParams({
          module: 'AskAI',
          action: 'agent',
          ...getContextParams(),
        });
        appendComparisonParams(params);

        const postBody = new URLSearchParams({
          messages: this.getConversationPayload(),
          widgetParams: JSON.stringify(this.widgetParams),
          conversationId: this.conversationId,
          token_auth: this.getTokenAuth(),
          force_api_session: '1',
        });

        const response = await fetch(`index.php?${params.toString()}`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: postBody,
          credentials: 'include',
          signal: this.abortController.signal,
        });

        if (!response.ok) {
          throw new Error(`HTTP error: ${response.status}`);
        }

        const reader = response.body?.getReader();
        if (!reader) {
          throw new Error('No response body');
        }

        await this.processStream(reader, (data) => this.parseAgentData(data));

        if (!this.receivedAgentEvent) {
          this.handleError(translate('AskAI_AnErrorOccurred'));
        } else if (this.streamingContent || this.agentSteps.length) {
          this.messages.push({
            role: 'assistant',
            content: this.streamingContent,
            steps: this.agentSteps,
          });
        }
      } catch (error) {
        if ((error as Error).name === 'AbortError') return;
        this.handleError(error instanceof Error ? error.message : String(error));
      } finally {
        this.loading = false;
        this.streaming = false;
        this.streamingContent = '';
        this.agentSteps = [];
        this.abortController = null;
      }
    },

    parseAgentData(data: string): void {
      let event: AgentEvent;
      try {
        event = JSON.parse(data);
      } catch {
        return;
      }

      this.receivedAgentEvent = true;

      if (event.type === 'error') {
        this.handleError(event.message || translate('AskAI_AnErrorOccurred'));
        return;
      }

      if (!this.streaming) {
        this.streaming = true;
        this.loading = false;
      }

      if (event.type === 'text' && event.content) {
        this.streamingContent = this.streamingContent
          ? `${this.streamingContent}\n\n${event.content}`
          : event.content;
      } else if (event.type === 'tool_call' && event.id) {
        this.agentSteps.push({
          id: event.id,
          name: event.name || '',
          title: event.title || event.name || '',
          status: 'running',
        });
      } else if (event.type === 'tool_result' && event.id) {
        const step = this.agentSteps.find((agentStep) => agentStep.id === event.id);
        if (step) {
          step.status = event.isError ? 'error' : 'done';
        }
      }
    },

    getTokenAuth(): string {
      type MatomoWindow = Window & {
        piwik?: { token_auth?: string };
        broadcast?: { getValueFromUrl: (k: string) => string };
      };
      const win = window as MatomoWindow;
      return win.piwik?.token_auth
        || win.broadcast?.getValueFromUrl('token_auth')
        || String(MatomoUrl.parsed.value.token_auth || '');
    },

    async processStream(
      reader: ReadableStreamDefaultReader<Uint8Array>,
      onData: (data: string) => void,
    ): Promise<void> {
      const decoder = new TextDecoder();
      let buffer = '';

      const processChunk = async (): Promise<void> => {
        const { done, value } = await reader.read();
        if (done) return;

        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split('\n');
        buffer = lines.pop() || '';

        lines.forEach((line) => {
          if (line.startsWith('data: ')) {
            const data = line.slice(6).trim();
            if (data !== '[DONE]') {
              onData(data);
            }
          }
        });

        await processChunk();
      };

      await processChunk();
    },

    handleError(error: string) {
      this.errored = true;
      this.errorMessage = error;
      this.loading = false;
      this.streaming = false;
    },

    cancelRequest() {
      if (this.abortController) {
        this.abortController.abort();
        this.abortController = null;
        this.loading = false;
        this.streaming = false;
        this.streamingContent = '';
      }
    },
  },

  beforeUnmount() {
    this.cancelRequest();
  },
});
</script>

<style lang="less">
@import './theme.less';
</style>

<style lang="less" scoped>
.ai-chat {
  --ai-chat-accent: v-bind(aiColor);

  position: relative;
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  min-width: 0;
  background: var(--ai-chat-surface);
}

.ai-chat-composer {
  flex-shrink: 0;
  box-sizing: border-box;
  width: 100%;
  max-width: var(--ai-chat-column-width, 46rem);
  margin: 0 auto;
  padding: var(--ai-chat-composer-padding, .5rem 1rem .75rem);
}
</style>
