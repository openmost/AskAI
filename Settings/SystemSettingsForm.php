<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

declare(strict_types=1);

namespace Piwik\Plugins\AskAI\Settings;

use Piwik\Piwik;
use Piwik\Plugins\AskAI\Services\DataPrivacy;
use Piwik\Plugins\AskAI\SystemSettings;
use Piwik\Settings\Setting;
use Piwik\Url;

/**
 * General settings of the plugin (privacy and prompts), edited on its page of the System administration instead of the
 * general settings.
 *
 * The values are read and written through SystemSettings, so they keep their storage, defaults and validators.
 */
final class SystemSettingsForm
{
    public const ACTION = 'settings';

    /** @var SystemSettings */
    private $settings;

    public function __construct(?SystemSettings $settings = null)
    {
        $this->settings = $settings ?? new SystemSettings();
    }

    /**
     * @param array<string, string|int> $params
     */
    public static function getUrl(array $params = []): string
    {
        return 'index.php?' . Url::getQueryStringFromParameters([
            'module' => SiteSettingsStorage::PLUGIN_NAME,
            'action' => self::ACTION,
        ] + $params);
    }

    /**
     * @return list<array{name: string, uicontrol: string, title: string, description: string, disabled: bool}>
     */
    public function getFields(): array
    {
        $fields = [];
        foreach ($this->getSettings() as $name => $setting) {
            $config = $setting->configureField();
            $fields[] = [
                'name' => $name,
                'uicontrol' => (string) $config->uiControl,
                'title' => (string) $config->title,
                'description' => (string) $config->description,
                // a value set in the config file cannot be changed from the UI
                'disabled' => !$setting->isWritableByCurrentUser(),
            ];
        }

        return $fields;
    }

    /**
     * @return array<string, string> setting name => value, "1" or "0" for the privacy settings, the default shown for
     *                               a prompt that is not stored
     */
    public function getValues(): array
    {
        $values = [];
        foreach (array_keys(DataPrivacy::DEFAULTS) as $name) {
            $values[$name] = $this->settings->$name->getValue() ? '1' : '0';
        }
        foreach (array_keys(DefaultPrompts::SETTING_NAMES) as $name) {
            $values[$name] = $this->settings->getPrompt($name);
        }

        return $values;
    }

    /**
     * Fields that cannot be edited are ignored, they keep their saved values.
     *
     * @param array<string, string|null> $values setting name => value, null to keep the saved value
     */
    public function save(array $values): void
    {
        Piwik::checkUserHasSuperUserAccess();

        foreach ($this->getSettings() as $name => $setting) {
            $value = $values[$name] ?? null;
            if ($value === null || !$setting->isWritableByCurrentUser()) {
                continue;
            }
            $setting->setValue(array_key_exists($name, DataPrivacy::DEFAULTS) ? $value === '1' : $value);
        }

        $this->settings->save();
    }

    /**
     * @return array<string, Setting>
     */
    private function getSettings(): array
    {
        $settings = [];
        foreach (array_merge(array_keys(DataPrivacy::DEFAULTS), array_keys(DefaultPrompts::SETTING_NAMES)) as $name) {
            $settings[$name] = $this->settings->$name;
        }

        return $settings;
    }
}
