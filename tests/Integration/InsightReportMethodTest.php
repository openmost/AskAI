<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AskAI\tests\Integration;

use Piwik\Log\LoggerInterface;
use Piwik\Plugin\ReportsProvider;
use Piwik\Plugins\AIProviders\AIConversationResponse;
use Piwik\Plugins\AskAI\Services\ChatRequestParser;
use Piwik\Plugins\AskAI\Services\ConversationRunner;
use Piwik\Plugins\AskAI\Services\InsightNotAvailableException;
use Piwik\Plugins\AskAI\Services\InsightReport;
use Piwik\Plugins\AskAI\Services\RateLimiter;
use Piwik\Plugins\AskAI\tests\Fakes\FakePluginDependencies;
use Piwik\Plugins\AskAI\tests\Fakes\ScriptedMcpAgent;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;
use Piwik\Plugins\UsersManager\Model as UsersModel;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * Insights only request Matomo reports, never any other API method
 *
 * @group AskAI
 * @group AskAIInsightReportMethodTest
 * @group Plugins
 */
class InsightReportMethodTest extends IntegrationTestCase
{
    private $idSite;
    private $originalGet;
    private $originalPost;

    public function setUp(): void
    {
        parent::setUp();

        Fixture::createSuperUser();
        FakeAccess::clearAccess(true);
        Fixture::createWebsite('2024-01-01 00:00:00');
        // a website that could be deleted, the only one cannot
        $this->idSite = (int) Fixture::createWebsite('2024-01-01 00:00:00');

        // the insight report reads the segment from the request, as when called over HTTP
        $this->originalGet = $_GET;
        $_GET['idSite'] = (string) $this->idSite;
        $this->originalPost = $_POST;
        $_POST = [];
    }

    public function tearDown(): void
    {
        $_GET = $this->originalGet;
        $_POST = $this->originalPost;

        parent::tearDown();
    }

    /**
     * @dataProvider getApiMethodsThatAreNotReports
     */
    public function test_insightReport_onlyResolvesToMatomoReports(array $widgetParams): void
    {
        try {
            $request = (new InsightReport())->buildReportRequest($widgetParams, $this->idSite, 'yesterday', 'day');
        } catch (InsightNotAvailableException $e) {
            $this->addToAssertionCount(1);
            return;
        }

        [$module, $action] = explode('.', $request['method'], 2);
        $this->assertNotNull(ReportsProvider::factory($module, $action), $request['method'] . ' is not a Matomo report');
    }

    /**
     * @dataProvider getApiMethodsThatAreNotReports
     */
    public function test_insightConversation_neverCallsApiMethodsThatAreNotReports(array $widgetParams): void
    {
        $agent = new ScriptedMcpAgent($this->createMock(LoggerInterface::class), FakePluginDependencies::connected(FakePluginDependencies::PLUGIN_MISSING));
        $agent->responses = [new AIConversationResponse('openai', 'OpenAI', 'gpt', [['type' => 'text', 'text' => 'ok']], AIConversationResponse::STOP_END_TURN)];
        $runner = new ConversationRunner($agent, new ChatRequestParser(), new InsightReport(), $this->createMock(RateLimiter::class));

        try {
            $runner->run($this->idSite, 'day', 'yesterday', 'session', function () {
            }, [], $widgetParams);
        } catch (\Exception $e) {
            // refused: fine, as long as nothing was changed
        }

        $this->assertSame($this->idSite, (int) SitesManagerAPI::getInstance()->getSiteFromId($this->idSite)['idsite']);
        $this->assertNotEmpty((new UsersModel())->getUser('superUserLogin'));
    }

    public function getApiMethodsThatAreNotReports(): array
    {
        return [
            'write method' => [['module' => 'SitesManager', 'action' => 'deleteSite']],
            'users write method' => [['module' => 'UsersManager', 'action' => 'deleteUser', 'userLogin' => 'superUserLogin']],
            'read method that is not a report' => [['module' => 'API', 'action' => 'getMatomoVersion']],
            'write method as evolution api method' => [['module' => 'VisitsSummary', 'action' => 'getEvolutionGraph', 'apiMethod' => 'SitesManager.deleteSite']],
            'injected api method' => [['module' => 'KPIWidgets', 'action' => 'kPIWidgetsVisits', 'apiMethod' => 'UsersManager.deleteUser']],
        ];
    }

    /**
     * @dataProvider getReportWidgets
     */
    public function test_insightReport_fetchesReports(array $widgetParams): void
    {
        $data = (new InsightReport())->fetch($widgetParams, $this->idSite, 'yesterday', 'day');

        $this->assertIsArray(json_decode($data, true));
    }

    public function getReportWidgets(): array
    {
        return [
            'visits summary' => [['module' => 'VisitsSummary', 'action' => 'get']],
            'browsers' => [['module' => 'DevicesDetection', 'action' => 'getBrowsers']],
            'event names' => [['module' => 'Events', 'action' => 'getName']],
            'evolution graph' => [['module' => 'VisitsSummary', 'action' => 'getEvolutionGraph']],
        ];
    }

    public function provideContainerConfig()
    {
        return [
            'Piwik\Access' => new FakeAccess(),
        ];
    }
}
