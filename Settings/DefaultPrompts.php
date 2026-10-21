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

/**
 * Default prompts of the general and website settings, the system prompts translated in the language of the user.
 *
 * A prompt equal to a default is never stored: the general setting is deleted and the website setting is left
 * empty, so a later rewrite of the defaults reaches every user who did not customise the prompt.
 */
final class DefaultPrompts
{
    public const CHAT = 'chatBasePrompt';
    public const INSIGHT = 'insightBasePrompt';

    /**
     * Setting name => translation key of its default
     */
    public const SETTING_NAMES = [
        self::CHAT => 'AskAI_ChatSystemPrompt',
        self::INSIGHT => 'AskAI_InsightSystemPrompt',
    ];

    /**
     * Longest prompt that can be saved, far above the defaults of every language
     */
    public const MAX_LENGTH = 20000;

    /** @var array<string, string[]>|null */
    private static $translatedDefaults = null;

    /**
     * Current default prompt, in the language of the current user
     */
    public static function getDefault(string $name): string
    {
        return Piwik::translate(self::SETTING_NAMES[$name]);
    }

    /**
     * @return array<string, string> setting name => default prompt in the language of the current user
     */
    public static function getDefaults(): array
    {
        $defaults = [];
        foreach (array_keys(self::SETTING_NAMES) as $name) {
            $defaults[$name] = self::getDefault($name);
        }

        return $defaults;
    }

    /**
     * The default, in the language of the current user, replaces an empty prompt and a default of another language.
     * A custom prompt is kept.
     */
    public static function resolve(string $name, string $prompt): string
    {
        return self::isDefault($name, $prompt) ? self::getDefault($name) : $prompt;
    }

    /**
     * Whether a prompt is empty or the default of any language
     */
    public static function isDefault(string $name, string $prompt): bool
    {
        $prompt = self::normalize($prompt);
        if ($prompt === '') {
            return true;
        }

        return in_array($prompt, self::getTranslatedDefaults()[$name] ?? [], true)
            || $prompt === self::normalize(self::getDefault($name));
    }

    /**
     * General setting value to store: null, which deletes the stored value, for a default prompt
     *
     * @param mixed $prompt
     */
    public static function toStoredGeneralPrompt(string $name, $prompt): ?string
    {
        $prompt = is_scalar($prompt) ? (string) $prompt : '';

        return self::isDefault($name, $prompt) ? null : $prompt;
    }

    /**
     * Website setting value to store: empty, so the general prompt applies, when the prompt is the general prompt or a
     * default while the general prompt is a default too
     */
    public static function toStoredSitePrompt(string $name, string $prompt, string $generalPrompt): string
    {
        if (self::normalize($prompt) === '' || self::normalize($prompt) === self::normalize($generalPrompt)) {
            return '';
        }
        if (self::isDefault($name, $prompt) && self::isDefault($name, $generalPrompt)) {
            return '';
        }

        return $prompt;
    }

    /**
     * A textarea may submit its line breaks as CRLF, the defaults are written with LF
     */
    private static function normalize(string $prompt): string
    {
        return str_replace("\r\n", "\n", trim($prompt));
    }

    /**
     * Current default prompts in every language of the plugin
     *
     * @return array<string, string[]>
     */
    private static function getTranslatedDefaults(): array
    {
        if (self::$translatedDefaults !== null) {
            return self::$translatedDefaults;
        }

        $defaults = [];
        foreach (glob(__DIR__ . '/../lang/*.json') ?: [] as $file) {
            $translations = json_decode((string) file_get_contents($file), true);
            foreach (self::SETTING_NAMES as $name => $translationKey) {
                [$plugin, $key] = explode('_', $translationKey, 2);
                $value = $translations[$plugin][$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    $defaults[$name][] = self::normalize($value);
                }
            }
        }

        return self::$translatedDefaults = $defaults;
    }
}
