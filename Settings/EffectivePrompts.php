<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

declare(strict_types=1);

namespace Piwik\Plugins\AskAI\Settings;

use Piwik\Plugins\AskAI\SystemSettings;

/**
 * Prompt applied to a website: its own prompt, then the general prompt, then the default in the language of the user.
 */
class EffectivePrompts
{
    public function getChatPrompt(int $idSite): string
    {
        return $this->get($idSite, DefaultPrompts::CHAT);
    }

    public function getInsightPrompt(int $idSite): string
    {
        return $this->get($idSite, DefaultPrompts::INSIGHT);
    }

    private function get(int $idSite, string $name): string
    {
        $sitePrompt = SiteSettingsStorage::read($idSite)[$name];
        if ($sitePrompt !== '') {
            return $sitePrompt;
        }

        return (new SystemSettings())->getPrompt($name);
    }
}
