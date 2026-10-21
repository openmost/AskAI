<!--
  Matomo - free/libre analytics platform

  @link    https://matomo.org
  @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
-->

<template>
  <div>
    <ContentBlock
      id="askaiPrivacy"
      :content-title="translate('AskAI_SettingsPrivacyTitle')"
    >
      <p>{{ translate('AskAI_SystemSettingsIntro') }}</p>
      <p>{{ translate('AskAI_PrivacySettingsIntro') }}</p>
      <ul class="askaiDestination">
        <li
          v-for="line in destination"
          :key="line"
        >{{ line }}</li>
      </ul>

      <div
        v-for="field in privacyFields"
        :key="field.name"
      >
        <Field
          :uicontrol="field.uicontrol"
          :name="`askaiSystem_${field.name}`"
          :title="field.title"
          :description="field.description"
          :disabled="field.disabled"
          v-model="privacy[field.name]"
        />
      </div>

      <div id="askaiSystemNotice_privacy" />
      <div class="askaiActions">
        <SaveButton
          class="askaiSavePrivacy"
          :saving="isSaving.privacy"
          @confirm="save('privacy')"
        />
      </div>
    </ContentBlock>

    <ContentBlock
      id="askaiPrompts"
      :content-title="translate('AskAI_SettingsPromptsTitle')"
    >
      <p>{{ translate('AskAI_SystemSettingsPromptsIntro') }}</p>

      <div
        v-for="field in promptFields"
        :key="field.name"
        class="askaiPromptField"
      >
        <Field
          :uicontrol="field.uicontrol"
          :name="`askaiSystem_${field.name}`"
          :title="field.title"
          :description="field.description"
          :disabled="field.disabled"
          v-model="values[field.name]"
        />
      </div>

      <div id="askaiSystemNotice_prompts" />
      <div class="askaiActions">
        <SaveButton
          class="askaiSavePrompts"
          :saving="isSaving.prompts"
          @confirm="save('prompts')"
        />
        <button
          v-if="hasCustomPrompt"
          type="button"
          class="btn btn-outline askaiResetPrompts"
          :title="translate('AskAI_ResetPromptToDefaultHelp')"
          @click="resetPrompts()"
        >
          {{ translate('AskAI_ResetPromptToDefault') }}
        </button>
      </div>
    </ContentBlock>
  </div>
</template>

<script lang="ts">
import {
  computed,
  defineComponent,
  onMounted,
  PropType,
  reactive,
} from 'vue';
import {
  AjaxHelper,
  ContentBlock,
  NotificationsStore,
  translate,
} from 'CoreHome';
import { Field, SaveButton } from 'CorePluginsAdmin';
import { SettingField } from '../types';

export const PROMPT_FIELDS = ['chatBasePrompt', 'insightBasePrompt'];

export const PRIVACY_FIELDS = [
  'dataSharingAllowed',
  'maskPersonalData',
  'stripUrlQueryStrings',
  'excludeVisitorData',
];

type Card = 'privacy' | 'prompts';

/**
 * Privacy settings and general prompts, rendered on the Ask AI page of the System administration.
 * Each card saves only its own fields, the unsaved edits of the other card are kept.
 */
export default defineComponent({
  name: 'ManageSystemSettings',
  components: {
    ContentBlock,
    Field,
    SaveButton,
  },
  props: {
    fields: {
      type: Array as PropType<SettingField[]>,
      required: true,
    },
    settings: {
      type: Object as PropType<Record<string, string>>,
      required: true,
    },
    // default prompts in the language of the user, a prompt equal to its default is not stored
    defaultPrompts: {
      type: Object as PropType<Record<string, string>>,
      default: () => ({}),
    },
    // where the data goes, so the super user knows what the consent covers
    destination: {
      type: Array as PropType<string[]>,
      default: () => [],
    },
  },
  setup(props) {
    const values = reactive<Record<string, string>>({ ...props.settings });
    const isSaving = reactive<Record<Card, boolean>>({ privacy: false, prompts: false });
    const privacy = reactive<Record<string, boolean>>({});
    PRIVACY_FIELDS.forEach((name) => {
      privacy[name] = props.settings[name] === '1';
    });

    const privacyFields = computed(
      () => props.fields.filter((field) => PRIVACY_FIELDS.includes(field.name)),
    );
    const promptFields = computed(
      () => props.fields.filter((field) => PROMPT_FIELDS.includes(field.name)),
    );

    const isDefaultPrompt = (name: string) => (
      (values[name] || '').trim() === (props.defaultPrompts[name] || '').trim()
    );

    // a prompt set in the config file cannot be edited, it is left as is
    const hasCustomPrompt = computed(() => promptFields.value.some(
      (field) => !field.disabled && !isDefaultPrompt(field.name),
    ));

    const resetPrompts = () => {
      promptFields.value.forEach((field) => {
        if (!field.disabled) {
          values[field.name] = props.defaultPrompts[field.name] || '';
        }
      });
    };

    const notify = (card: Card, context: 'success' | 'error', message: string) => {
      NotificationsStore.show({
        message,
        context,
        type: 'transient',
        id: `askaiSystemSettingsNotice_${card}`,
        placeat: `#askaiSystemNotice_${card}`,
      });
    };

    const save = (card: Card) => {
      isSaving[card] = true;

      // the fields that cannot be edited and the fields of the other card are left out, they keep
      // their saved values
      const cardFields = card === 'privacy' ? privacyFields.value : promptFields.value;
      const postParams: Record<string, string> = {};
      cardFields.forEach((field) => {
        if (field.disabled) {
          return;
        }
        if (card === 'privacy') {
          postParams[field.name] = privacy[field.name] ? '1' : '0';
        } else {
          postParams[field.name] = values[field.name] || '';
        }
      });

      AjaxHelper.post(
        { method: 'AskAI.setSystemSettings' },
        postParams,
        { createErrorNotification: false },
      ).then(() => {
        if (card === 'prompts') {
          // an empty prompt is saved as the default
          PROMPT_FIELDS.forEach((name) => {
            if (name in postParams && !(values[name] || '').trim()) {
              values[name] = props.defaultPrompts[name] || '';
            }
          });
        }
        notify(card, 'success', translate('General_YourChangesHaveBeenSaved'));
      }).catch((error: Error) => {
        notify(card, 'error', (error && error.message) || translate('AskAI_AnErrorOccurred'));
      }).finally(() => {
        isSaving[card] = false;
      });
    };

    onMounted(() => {
      if (window.location.hash === '#askaiPrompts') {
        const promptsCard = document.getElementById('askaiPrompts');
        if (promptsCard) {
          promptsCard.scrollIntoView();
        }
      }
    });

    return {
      translate,
      values,
      isSaving,
      privacy,
      privacyFields,
      promptFields,
      hasCustomPrompt,
      resetPrompts,
      save,
    };
  },
});
</script>

<style lang="less" scoped>
// the secondary actions sit on the line of the Save button, and wrap below it on narrow screens
.askaiActions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 1rem;
}

.askaiDestination {
  margin: 0 0 1rem;
  padding: 0;
  list-style: none;

  li {
    margin: 0 0 .25rem;
  }
}
</style>
