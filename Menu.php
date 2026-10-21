<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AskAI;

use Piwik\Menu\MenuAdmin;
use Piwik\Menu\MenuTop;
use Piwik\Piwik;
use Piwik\Plugins\AskAI\Settings\SystemSettingsForm;
use Piwik\Plugins\UsersManager\UserPreferences;
use Piwik\Request;

class Menu extends \Piwik\Plugin\Menu
{
    public function configureTopMenu(MenuTop $menu)
    {
        $menu->addItem('AskAI_AskAI', null, $this->urlForDefaultAction(), $orderId = 30);
    }

    public function configureAdminMenu(MenuAdmin $menu)
    {
        if (Piwik::hasUserSuperUserAccess()) {
            // next to AI Providers
            $menu->addSystemItem('AskAI_SystemSettingsMenu', $this->urlForAction(SystemSettingsForm::ACTION), 39);
        }

        $defaultIdSite = (int) (new UserPreferences())->getDefaultWebsiteId();
        $idSite = Request::fromRequest()->getIntegerParameter('idSite', $defaultIdSite);

        if ($idSite > 0 && Piwik::isUserHasAdminAccess($idSite)) {
            $menu->addMeasurableItem('AskAI_SiteSettingsMenu', $this->urlForAction('manage', ['idSite' => $idSite]), 45);
        }
    }
}
