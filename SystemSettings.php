<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

declare(strict_types=1);

namespace Piwik\Plugins\AskAI;

use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugins\AskAI\Agent\PluginDependencies;
use Piwik\Plugins\AskAI\Services\DataPrivacy;
use Piwik\Plugins\AskAI\Settings\DefaultPrompts;
use Piwik\Settings\FieldConfig;
use Piwik\Settings\Setting;

/**
 * The privacy settings and the general prompts: the provider, the model and the credentials belong to AI Providers.
 * Edited on the Ask AI page of the System administration (see Settings\SystemSettingsForm), by super users only.
 */
class SystemSettings extends \Piwik\Settings\Plugin\SystemSettings
{
    /** @var Setting */
    public $dataSharingAllowed;

    /** @var Setting */
    public $maskPersonalData;

    /** @var Setting */
    public $stripUrlQueryStrings;

    /** @var Setting */
    public $excludeVisitorData;

    /** @var Setting */
    public $chatBasePrompt;

    /** @var Setting */
    public $insightBasePrompt;

    protected function init()
    {
        // nothing is sent to the AI provider until a super user allows it
        foreach (DataPrivacy::DEFAULTS as $name => $default) {
            $this->$name = $this->makeSetting($name, $default, FieldConfig::TYPE_BOOL, function (FieldConfig $field) use ($name) {
                $field->title = Piwik::translate('AskAI_PrivacySetting_' . $name);
                $field->description = Piwik::translate('AskAI_PrivacySetting_' . $name . 'Help');
                $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
            });
        }

        // a default prompt is not stored, so the defaults rewritten by a later version reach this install
        $this->chatBasePrompt = $this->makePromptSetting(DefaultPrompts::CHAT, 'AskAI_ChatBasePrompt', 'AskAI_ChatBasePromptDescription');
        $this->insightBasePrompt = $this->makePromptSetting(DefaultPrompts::INSIGHT, 'AskAI_InsightBasePrompt', 'AskAI_InsightBasePromptDescription');
    }

    /**
     * None is listed in Administration > General settings nor accepted by CorePluginsAdmin.setSystemSettings: the
     * Ask AI page of the System administration groups them with the website prompts.
     *
     * @return Setting[]
     */
    public function getSettingsWritableByCurrentUser()
    {
        return [];
    }

    /**
     * General prompt: the default, in the language of the current user, when none is stored
     */
    public function getPrompt(string $name): string
    {
        $value = $name === DefaultPrompts::INSIGHT ? $this->insightBasePrompt->getValue() : $this->chatBasePrompt->getValue();

        return DefaultPrompts::resolve($name, is_scalar($value) ? (string) $value : '');
    }

    private function makePromptSetting(string $name, string $titleKey, string $descriptionKey): Setting
    {
        return $this->makeSetting($name, '', FieldConfig::TYPE_STRING, function (FieldConfig $field) use ($name, $titleKey, $descriptionKey) {
            $field->title = Piwik::translate($titleKey);
            $field->description = Piwik::translate($descriptionKey);
            $field->uiControl = FieldConfig::UI_CONTROL_TEXTAREA;
            $field->validate = function ($value) {
                if (is_string($value) && mb_strlen($value) > DefaultPrompts::MAX_LENGTH) {
                    throw new \Exception(Piwik::translate('AskAI_PromptTooLong', [DefaultPrompts::MAX_LENGTH]));
                }
            };
            $field->transform = function ($value) use ($name) {
                return DefaultPrompts::toStoredGeneralPrompt($name, $value);
            };
        });
    }

    /**
     * Where the data goes, so the super user knows what the consent covers
     */
    public static function getDataDestination(): string
    {
        try {
            $availability = StaticContainer::get(PluginDependencies::class)->getAiProvidersAvailability();
        } catch (\Throwable $e) {
            return '';
        }

        $providerName = (string) ($availability['providerName'] ?? '');

        return $providerName !== '' ? Piwik::translate('AskAI_DataDestinationAiProviders', $providerName) : '';
    }
}
