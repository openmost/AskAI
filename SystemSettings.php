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
use Piwik\Settings\FieldConfig;
use Piwik\Settings\Setting;

/**
 * The privacy settings, the only settings of AskAI: the provider, the model and the credentials belong to AI Providers.
 * Listed in Administration > System > General settings, editable by super users only.
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

    protected function init()
    {
        // nothing is sent to the AI provider until a super user allows it
        foreach (DataPrivacy::DEFAULTS as $name => $default) {
            $this->$name = $this->makeSetting($name, $default, FieldConfig::TYPE_BOOL, function (FieldConfig $field) use ($name) {
                $field->title = Piwik::translate('AskAI_PrivacySetting_' . $name);
                $field->description = Piwik::translate('AskAI_PrivacySetting_' . $name . 'Help');
                $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
                if ($name === DataPrivacy::SETTING_DATA_SHARING) {
                    $field->introduction = trim(Piwik::translate('AskAI_PrivacySettingsIntro') . ' ' . self::getDataDestination());
                }
            });
        }
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
