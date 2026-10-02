<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AskAI;

use Piwik\Menu\MenuTop;

class Menu extends \Piwik\Plugin\Menu
{
    public function configureTopMenu(MenuTop $menu)
    {
        $menu->addItem('AskAI_AskAI', null, $this->urlForDefaultAction(), $orderId = 30);
    }
}
