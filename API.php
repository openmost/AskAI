<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

declare(strict_types=1);

namespace Piwik\Plugins\AskAI;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugins\AskAI\Settings\DefaultPrompts;
use Piwik\Plugins\AskAI\Settings\SiteSettingsStorage;
use Piwik\Plugins\AskAI\Settings\SystemSettingsForm;

/**
 * Saves the settings of the Ask AI pages of the administration. The chat and the insights run through the controller,
 * the provider, the model and the credentials belong to AI Providers.
 *
 * @method static \Piwik\Plugins\AskAI\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    /**
     * Prompts of a website, an empty prompt follows the general prompt
     *
     * @return array<string, string>
     */
    public function getSiteSettings(int $idSite): array
    {
        Piwik::checkUserHasAdminAccess($idSite);

        return SiteSettingsStorage::read($idSite);
    }

    /**
     * Sets the prompts of a website. A parameter left out keeps its saved value.
     *
     * A prompt equal to the general prompt, or to a default while the general prompt is a default too, is saved empty:
     * the website then follows the general prompt.
     */
    public function setSiteSettings(int $idSite, ?string $chatBasePrompt = null, ?string $insightBasePrompt = null): bool
    {
        Piwik::checkUserHasAdminAccess($idSite);

        $systemSettings = new SystemSettings();
        $values = [];
        foreach ([DefaultPrompts::CHAT => $chatBasePrompt, DefaultPrompts::INSIGHT => $insightBasePrompt] as $name => $value) {
            if ($value === null) {
                continue;
            }
            $value = trim(Common::unsanitizeInputValue($value));
            self::checkPromptLength($value);
            $values[$name] = DefaultPrompts::toStoredSitePrompt($name, $value, $systemSettings->getPrompt($name));
        }

        SiteSettingsStorage::save($idSite, $values);

        return true;
    }

    /**
     * Sets the general settings, edited on the Ask AI page of the System administration. A parameter left out keeps
     * its saved value.
     *
     * @param string|null $dataSharingAllowed "1" lets the plugin send Matomo data to the AI provider, off by default
     * @param string|null $chatBasePrompt an empty prompt or a default prompt is saved as the default
     */
    public function setSystemSettings(
        ?string $dataSharingAllowed = null,
        ?string $maskPersonalData = null,
        ?string $stripUrlQueryStrings = null,
        ?string $excludeVisitorData = null,
        ?string $chatBasePrompt = null,
        ?string $insightBasePrompt = null
    ): bool {
        Piwik::checkUserHasSuperUserAccess();

        $values = [
            'dataSharingAllowed' => $dataSharingAllowed,
            'maskPersonalData' => $maskPersonalData,
            'stripUrlQueryStrings' => $stripUrlQueryStrings,
            'excludeVisitorData' => $excludeVisitorData,
            DefaultPrompts::CHAT => $chatBasePrompt,
            DefaultPrompts::INSIGHT => $insightBasePrompt,
        ];
        foreach ($values as $name => $value) {
            if ($value !== null) {
                $values[$name] = Common::unsanitizeInputValue($value);
            }
        }

        (new SystemSettingsForm())->save($values);

        return true;
    }

    private static function checkPromptLength(string $prompt): void
    {
        if (mb_strlen($prompt) > DefaultPrompts::MAX_LENGTH) {
            throw new \Exception(Piwik::translate('AskAI_PromptTooLong', [DefaultPrompts::MAX_LENGTH]));
        }
    }
}
