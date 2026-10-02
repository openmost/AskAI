<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AskAI\tests\Fakes;

use Piwik\Log\LoggerInterface;
use Piwik\Plugins\AIProviders\AIConversationRequest;
use Piwik\Plugins\AIProviders\AIConversationResponse;
use Piwik\Plugins\AskAI\Agent\McpAgent;

/**
 * Agent with scripted MCP tool catalog and tool results. The AI provider answers are scripted on the fake
 * AIProviderService of the dependencies: the agent reaches them through its real converse() path.
 */
class ScriptedMcpAgent extends McpAgent
{
    /** @var list<AIConversationResponse|\Throwable> the responses of the fake AIProviderService */
    public $responses = [];

    /** @var list<AIConversationRequest> the requests the fake AIProviderService received */
    public $requests = [];

    /** @var list<array<string, mixed>> */
    public $catalog = [];

    /** @var \Throwable|null thrown when the catalog is fetched */
    public $catalogError = null;

    /** @var int */
    public $catalogFetches = 0;

    /** @var list<array<string, mixed>|\Throwable> */
    public $toolResults = [];

    /** @var list<array{string, array<string, mixed>, string}> */
    public $toolCalls = [];

    /** @var bool */
    public $superUser = true;

    public function __construct(LoggerInterface $logger, FakePluginDependencies $dependencies)
    {
        parent::__construct($logger, $dependencies);

        $service = $dependencies->fakeService();
        $this->responses = &$service->responses;
        $this->requests = &$service->conversations;
    }

    protected function fetchToolCatalog(): array
    {
        $this->catalogFetches++;
        if ($this->catalogError !== null) {
            throw $this->catalogError;
        }

        return $this->catalog;
    }

    protected function callInternalTool(string $name, array $arguments, string $sessionKey): array
    {
        $this->toolCalls[] = [$name, $arguments, $sessionKey];
        $result = array_shift($this->toolResults);
        if ($result instanceof \Throwable) {
            throw $result;
        }

        return $result;
    }

    protected function translate(string $translationKey): string
    {
        return $translationKey;
    }

    protected function isSuperUser(): bool
    {
        return $this->superUser;
    }
}
