<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

declare(strict_types=1);

namespace Piwik\Plugins\AskAI\Settings;

use Piwik\Settings\Storage\Backend\MeasurableSettingsTable;
use Piwik\Settings\Storage\Storage;

/**
 * Prompts of a website, edited on the Ask AI page of the Websites administration, stored in the site settings table.
 * An empty prompt follows the general prompt.
 */
final class SiteSettingsStorage
{
    public const PLUGIN_NAME = 'AskAI';

    public const SETTING_NAMES = [
        DefaultPrompts::CHAT,
        DefaultPrompts::INSIGHT,
    ];

    /**
     * @return array<string, string> setting name => value, an empty string when the general prompt applies
     */
    public static function read(int $idSite): array
    {
        $storedValues = [];
        if ($idSite > 0) {
            try {
                $storedValues = (new MeasurableSettingsTable($idSite, self::PLUGIN_NAME))->load();
            } catch (\Throwable $e) {
                $storedValues = [];
            }
        }

        $values = [];
        foreach (self::SETTING_NAMES as $name) {
            $value = $storedValues[$name] ?? '';
            $values[$name] = is_scalar($value) ? trim((string) $value) : '';
        }

        return $values;
    }

    /**
     * @param array<string, string> $values setting name => value, validated by the caller. A setting left out keeps
     *                                      its saved value.
     */
    public static function save(int $idSite, array $values): void
    {
        $storage = new Storage(new MeasurableSettingsTable($idSite, self::PLUGIN_NAME));

        foreach (self::SETTING_NAMES as $name) {
            if (array_key_exists($name, $values)) {
                $storage->setValue($name, $values[$name]);
            }
        }

        $storage->save();
    }
}
