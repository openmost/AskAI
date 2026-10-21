<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

declare(strict_types=1);

namespace Piwik\Plugins\AskAI\tests\Integration;

use Piwik\API\Request;
use Piwik\Container\StaticContainer;
use Piwik\FrontController;
use Piwik\Menu\MenuAdmin;
use Piwik\Plugins\AskAI\Agent\PluginDependencies;
use Piwik\Plugins\AskAI\Controller;
use Piwik\Plugins\AskAI\Menu;
use Piwik\Plugins\AskAI\tests\Fakes\FakePluginDependencies;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * The Ask AI pages of the System and Websites administration, and their menu entries
 *
 * @group AskAI
 * @group AskAISettingsPagesTest
 * @group Plugins
 */
class SettingsPagesTest extends IntegrationTestCase
{
    private int $idSite;

    /** @var array<string, mixed> */
    private array $originalGet;

    public function setUp(): void
    {
        parent::setUp();

        Fixture::createSuperUser();
        FakeAccess::clearAccess(true);
        $this->idSite = (int) Fixture::createWebsite('2024-01-01 00:00:00');

        // the test environment only loads the translations of the core plugins
        StaticContainer::get('Piwik\Translation\Translator')->addDirectory(__DIR__ . '/../../lang');
        StaticContainer::getContainer()->set(PluginDependencies::class, FakePluginDependencies::connected());

        $this->originalGet = $_GET;
    }

    public function tearDown(): void
    {
        $_GET = $this->originalGet;

        parent::tearDown();
    }

    public function test_theSystemPage_showsThePrivacySettings_andTheGeneralPrompts(): void
    {
        Request::processRequest('AskAI.setSystemSettings', ['chatBasePrompt' => 'My <b>chat</b> prompt']);

        $html = $this->render('settings', ['module' => 'AskAI', 'action' => 'settings']);

        $this->assertStringContainsString('vue-entry="AskAI.ManageSystemSettings"', $html);
        $decoded = html_entity_decode($html);
        $this->assertStringContainsString('"dataSharingAllowed"', $decoded);
        $this->assertStringContainsString('"chatBasePrompt"', $decoded);
        // the prompt is JSON encoded in an escaped attribute, never rendered as markup
        $this->assertStringNotContainsString('My <b>chat</b> prompt', $html);
        $this->assertStringContainsString('My <b>chat<\/b> prompt', $decoded);
    }

    /**
     * @dataProvider getNonSuperUsers
     */
    public function test_theSystemPage_isDenied_toAdminAndViewUsers(string $access): void
    {
        $this->setAccess($access);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('checkUserHasSuperUserAccess');
        (new Controller())->settings();
    }

    public function getNonSuperUsers(): array
    {
        return [
            'admin' => ['admin'],
            'view' => ['view'],
        ];
    }

    public function test_theWebsitePage_showsThePromptsOfTheWebsite_andTheGeneralPrompts(): void
    {
        Request::processRequest('AskAI.setSiteSettings', ['idSite' => $this->idSite, 'insightBasePrompt' => 'Website insight prompt']);
        $this->setAccess('admin');

        $html = $this->render('manage', ['module' => 'AskAI', 'action' => 'manage', 'idSite' => (string) $this->idSite]);

        $this->assertStringContainsString('vue-entry="AskAI.ManageSiteSettings"', $html);
        $decoded = html_entity_decode($html);
        $this->assertStringContainsString('Website insight prompt', $decoded);
        $this->assertStringContainsString('general-prompts=', $html);
        // the link to the general settings is only given to super users
        $this->assertStringContainsString('general-settings-url="&quot;&quot;"', $html);
    }

    public function test_theWebsitePage_isDenied_toViewUsers(): void
    {
        $this->setAccess('view');
        $_GET = ['module' => 'AskAI', 'action' => 'manage', 'idSite' => (string) $this->idSite];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('checkUserHasAdminAccess');
        (new Controller())->manage();
    }

    public function test_theMenu_addsTheSystemPage_forSuperUsers_andTheWebsitePage_forAdmins(): void
    {
        $_GET = ['idSite' => (string) $this->idSite];

        $this->assertSame(['AskAI_SiteSettingsMenu', 'AskAI_SystemSettingsMenu'], $this->getAdminMenuItems());

        $this->setAccess('admin');
        $this->assertSame(['AskAI_SiteSettingsMenu'], $this->getAdminMenuItems());

        $this->setAccess('view');
        $this->assertSame([], $this->getAdminMenuItems());
    }

    public function provideContainerConfig()
    {
        return [
            'Piwik\Access' => new FakeAccess(),
        ];
    }

    /**
     * @param array<string, string> $get
     */
    private function render(string $action, array $get): string
    {
        $_GET = $get;

        return (string) FrontController::getInstance()->fetchDispatch('AskAI', $action);
    }

    /**
     * @return list<string> the Ask AI items of the administration menu
     */
    private function getAdminMenuItems(): array
    {
        $menu = $this->getMockBuilder(MenuAdmin::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addSystemItem', 'addMeasurableItem'])
            ->getMock();

        $items = [];
        $record = function (string $name) use (&$items) {
            $items[] = $name;
        };
        $menu->method('addSystemItem')->willReturnCallback($record);
        $menu->method('addMeasurableItem')->willReturnCallback($record);

        (new Menu())->configureAdminMenu($menu);
        sort($items);

        return $items;
    }

    private function setAccess(string $access): void
    {
        if ($access === 'admin') {
            FakeAccess::clearAccess(false, [$this->idSite], [], 'admin_user');
        } else {
            FakeAccess::clearAccess(false, [], [$this->idSite], 'view_user');
        }
    }
}
