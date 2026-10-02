<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AskAI\tests\Fakes;

/**
 * Stands for the AIProviderService of the AIProviders plugin, which may be missing. Every conversation the agent
 * runs lands here: the scripted responses are returned in order, a \Throwable among them is thrown.
 */
class FakeAiProviderService
{
    /** @var FakePluginDependencies the availability and managed state are read from it on each call */
    private $dependencies;

    /** @var list<\Piwik\Plugins\AIProviders\AIConversationRequest> */
    public $conversations = [];

    /** @var list<\Piwik\Plugins\AIProviders\AIConversationResponse|\Throwable> */
    public $responses = [];

    public function __construct(FakePluginDependencies $dependencies)
    {
        $this->dependencies = $dependencies;
    }

    /**
     * @return mixed
     */
    public function getConversationAvailability()
    {
        if ($this->dependencies->availability instanceof \Throwable) {
            throw $this->dependencies->availability;
        }

        return $this->dependencies->availability;
    }

    /**
     * @param \Piwik\Plugins\AIProviders\AIConversationRequest $request
     * @return \Piwik\Plugins\AIProviders\AIConversationResponse
     */
    public function converse($request)
    {
        $this->conversations[] = $request;

        $response = array_shift($this->responses);
        if ($response instanceof \Throwable) {
            throw $response;
        }
        if ($response === null) {
            throw new \RuntimeException('No scripted AI provider response left.');
        }

        return $response;
    }

    public function isManaged(): bool
    {
        if ($this->dependencies->managed instanceof \Throwable) {
            throw $this->dependencies->managed;
        }

        return $this->dependencies->managed;
    }
}
