<!--
  Matomo - free/libre analytics platform

  @link    https://matomo.org
  @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
-->

<template>
  <div>
    <ContentBlock :content-title="translate('AskAI_SettingsPromptsTitle')">
      <p>{{ translate('AskAI_SiteSettingsPromptsIntro') }}</p>

      <div
        v-for="field in fields"
        :key="field.name"
        class="askaiPromptField"
      >
        <Field
          :uicontrol="field.uicontrol"
          :name="`askai_${field.name}`"
          :title="field.title"
          :inline-help="field.description"
          v-model="values[field.name]"
        />
      </div>

      <div id="askaiSiteNotice_prompts" />
      <div class="askaiActions">
        <SaveButton
          class="askaiSavePrompts"
          :saving="isSaving"
          @confirm="save()"
        />
        <button
          v-if="hasCustomPrompt"
          type="button"
          class="btn btn-outline askaiResetPrompts"
          :title="translate('AskAI_UseGeneralPromptHelp')"
          @click="resetPrompts()"
        >
          {{ translate('AskAI_UseGeneralPrompt') }}
        </button>
      </div>

      <div
        v-if="generalSettingsUrl"
        class="askaiGeneralSettingsLink"
      >
        {{ translate('AskAI_SiteSettingsGeneralSettings') }}
        <a :href="generalSettingsUrl">{{ translate('AskAI_SystemSettingsLink') }}</a>
      </div>
    </ContentBlock>
  </div>
</template>

<script lang="ts">
import {
  computed,
  defineComponent,
  PropType,
  reactive,
  ref,
} from 'vue';
import {
  AjaxHelper,
  ContentBlock,
  NotificationsStore,
  translate,
} from 'CoreHome';
import { Field, SaveButton } from 'CorePluginsAdmin';
import { SettingField } from '../types';

/**
 * Prompts of a website, rendered on the Ask AI page of the Websites administration.
 * An empty prompt follows the general prompt, the site is chosen with the site selector.
 */
export default defineComponent({
  name: 'ManageSiteSettings',
  components: {
    ContentBlock,
    Field,
    SaveButton,
  },
  props: {
    idSite: {
      type: [Number, String],
      required: true,
    },
    fields: {
      type: Array as PropType<SettingField[]>,
      required: true,
    },
    settings: {
      type: Object as PropType<Record<string, string>>,
      required: true,
    },
    generalSettingsUrl: {
      type: String,
      default: '',
    },
    // prompts of the general settings, used by the website when its own prompt is empty
    generalPrompts: {
      type: Object as PropType<Record<string, string>>,
      default: () => ({}),
    },
  },
  setup(props) {
    const values = reactive<Record<string, string>>({ ...props.settings });
    const isSaving = ref(false);

    const hasCustomPrompt = computed(
      () => props.fields.some((field) => !!(values[field.name] || '').trim()),
    );

    // the website goes back to the general prompts, the fields are left empty rather than copied
    const resetPrompts = () => {
      props.fields.forEach((field) => {
        values[field.name] = '';
      });
    };

    const notify = (context: 'success' | 'error', message: string) => {
      NotificationsStore.show({
        message,
        context,
        type: 'transient',
        id: 'askaiSiteSettingsNotice_prompts',
        placeat: '#askaiSiteNotice_prompts',
      });
    };

    const save = () => {
      isSaving.value = true;

      const postParams: Record<string, string | number> = { idSite: props.idSite };
      props.fields.forEach((field) => {
        postParams[field.name] = values[field.name] || '';
      });

      AjaxHelper.post(
        { method: 'AskAI.setSiteSettings' },
        postParams,
        { createErrorNotification: false },
      ).then(() => {
        // a prompt equal to the general prompt is saved empty
        props.fields.forEach((field) => {
          const general = (props.generalPrompts[field.name] || '').trim();
          if (general && (values[field.name] || '').trim() === general) {
            values[field.name] = '';
          }
        });
        notify('success', translate('General_YourChangesHaveBeenSaved'));
      }).catch((error: Error) => {
        notify('error', (error && error.message) || translate('AskAI_AnErrorOccurred'));
      }).finally(() => {
        isSaving.value = false;
      });
    };

    return {
      translate,
      values,
      isSaving,
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

// a div: the Matomo card resets the margin of its paragraphs
.askaiGeneralSettingsLink {
  margin-top: 2rem;
}
</style>
