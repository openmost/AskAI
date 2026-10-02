<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

declare(strict_types=1);

namespace Piwik\Plugins\AskAI\tests\Integration;

use Piwik\Container\StaticContainer;
use Piwik\FrontController;
use Piwik\Log\LoggerInterface;
use Piwik\Piwik;
use Piwik\Plugins\AskAI\Agent\McpAgent;
use Piwik\Plugins\AskAI\Agent\PluginDependencies;
use Piwik\Plugins\AskAI\tests\Fakes\FakePluginDependencies;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * The Ask AI page: the chat when AI Providers can answer, the setup steps otherwise, never a settings form
 *
 * @group AskAI
 * @group AskAIChatPageTest
 * @group Plugins
 */
class ChatPageTest extends IntegrationTestCase
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

        $this->originalGet = $_GET;
    }

    public function tearDown(): void
    {
        $_GET = $this->originalGet;

        parent::tearDown();
    }

    public function test_showsTheChat_whenAProviderIsConnected(): void
    {
        $this->useDependencies(FakePluginDependencies::connected(PluginDependencies::PLUGIN_MISSING));

        $html = $this->renderPage();

        $this->assertStringContainsString('vue-entry="AskAI.ChatIndexPage"', $html);
        $this->assertStringNotContainsString('askai-unavailable', $html);
        $this->assertStringNotContainsString('<form', $this->getContent($html));
    }

    /**
     * @dataProvider getUnavailableStates
     */
    public function test_showsTheSetupSteps_withLinks_forSuperUsers(callable $configure, string $messageKey, string $link): void
    {
        $dependencies = FakePluginDependencies::connected(PluginDependencies::PLUGIN_MISSING);
        $configure($dependencies);
        $this->useDependencies($dependencies);

        $html = $this->renderPage();

        $this->assertStringNotContainsString('AskAI.ChatIndexPage', $html);
        $this->assertStringContainsString('askai-unavailable', $html);
        $content = $this->getContent($html);
        $this->assertStringContainsString(htmlspecialchars(Piwik::translate($messageKey), ENT_QUOTES), $content);
        $this->assertStringContainsString($link, html_entity_decode($content));
        $this->assertStringNotContainsString(Piwik::translate('AskAI_AskAdministrator'), $content);
        $this->assertStringNotContainsString('<form', $content);
    }

    /**
     * @dataProvider getUnavailableStates
     */
    public function test_showsTheSetupSteps_askingTheAdministrator_forOtherUsers(callable $configure, string $messageKey, string $link): void
    {
        $dependencies = FakePluginDependencies::connected(PluginDependencies::PLUGIN_MISSING);
        $configure($dependencies);
        $this->useDependencies($dependencies);
        FakeAccess::clearAccess(false, [], [$this->idSite], 'view_user');

        $html = $this->renderPage();

        $content = $this->getContent($html);
        $this->assertStringContainsString(htmlspecialchars(Piwik::translate($messageKey), ENT_QUOTES), $content);
        $this->assertStringContainsString(htmlspecialchars(Piwik::translate('AskAI_AskAdministrator'), ENT_QUOTES), $content);
        $this->assertStringNotContainsString($link, html_entity_decode($content));
    }

    public function getUnavailableStates(): array
    {
        return [
            'AI Providers missing' => [
                function (FakePluginDependencies $dependencies) {
                    $dependencies->plugins[PluginDependencies::AI_PROVIDERS] = PluginDependencies::PLUGIN_MISSING;
                },
                'AskAI_RecommendInstallAiProviders',
                'module=Installation&action=systemCheckPage',
            ],
            'AI Providers deactivated' => [
                function (FakePluginDependencies $dependencies) {
                    $dependencies->plugins[PluginDependencies::AI_PROVIDERS] = PluginDependencies::PLUGIN_INACTIVE;
                },
                'AskAI_RecommendActivateAiProviders',
                'module=CorePluginsAdmin&action=plugins',
            ],
            'no provider connected' => [
                function (FakePluginDependencies $dependencies) {
                    $dependencies->availability = ['status' => PluginDependencies::AI_NOT_CONFIGURED, 'providerId' => null, 'providerName' => null];
                },
                'AskAI_RecommendConnectProvider',
                'module=AIProviders&action=index',
            ],
            'detection failure' => [
                function (FakePluginDependencies $dependencies) {
                    $dependencies->availability = new \RuntimeException('AIProviders failure');
                },
                'AskAI_RecommendConnectProvider',
                'module=AIProviders&action=index',
            ],
        ];
    }

    public function test_alsoListsTheMcpServerStep_onTheSetupPage(): void
    {
        $dependencies = FakePluginDependencies::connected(PluginDependencies::PLUGIN_MISSING);
        $dependencies->availability = ['status' => PluginDependencies::AI_NOT_CONFIGURED, 'providerId' => null, 'providerName' => null];
        $this->useDependencies($dependencies);

        $html = $this->renderPage();

        $this->assertStringContainsString(htmlspecialchars(Piwik::translate('AskAI_RecommendInstallMcpServer'), ENT_QUOTES), $html);
    }

    public function provideContainerConfig()
    {
        return [
            'Piwik\Access' => new FakeAccess(),
        ];
    }

    private function renderPage(): string
    {
        $_GET = ['module' => 'AskAI', 'action' => 'index', 'idSite' => (string) $this->idSite, 'period' => 'day', 'date' => 'yesterday'];

        return (string) FrontController::getInstance()->fetchDispatch('AskAI', 'index');
    }

    /**
     * The page content, without the Matomo layout around it (its search and segment forms)
     */
    private function getContent(string $html): string
    {
        $start = strpos($html, 'askai-unavailable');
        if ($start === false) {
            $start = (int) strpos($html, 'AskAI.ChatIndexPage');
        }

        return substr($html, $start, 4000);
    }

    private function useDependencies(PluginDependencies $dependencies): void
    {
        StaticContainer::getContainer()->set(PluginDependencies::class, $dependencies);
        StaticContainer::getContainer()->set(McpAgent::class, new McpAgent($this->createMock(LoggerInterface::class), $dependencies));
    }
}
