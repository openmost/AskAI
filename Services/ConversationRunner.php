<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

declare(strict_types=1);

namespace Piwik\Plugins\AskAI\Services;

use Piwik\Piwik;
use Piwik\Plugins\AskAI\Agent\McpAgent;

/**
 * Runs one turn of the chat or of a report insight: rate limit, conversation, system prompt, then the agent.
 *
 * The system prompts are fixed translations, there is no setting to change them.
 */
class ConversationRunner
{
    public const FEATURE_CHAT = 'chat';
    public const FEATURE_INSIGHTS = 'insights';

    public function __construct(
        private McpAgent $agent,
        private ChatRequestParser $parser,
        private InsightReport $insightReport,
        private RateLimiter $rateLimiter
    ) {
    }

    /**
     * @param mixed $messages the posted conversation, read from the request when empty
     * @param mixed $widgetParams the posted report widget of an insight, read from the request when empty
     * @param callable(string, array<string, mixed>): void $emit receives the agent events
     */
    public function run(int $idSite, string $period, string $date, string $sessionKey, callable $emit, $messages = [], $widgetParams = []): void
    {
        $dataSharingError = $this->agent->getPrivacy()->getDataSharingError();
        if ($dataSharingError !== null) {
            $emit('error', ['message' => $dataSharingError]);
            return;
        }

        $this->rateLimiter->check($idSite);

        if (!$this->agent->isAiAvailable()) {
            $emit('error', ['message' => Piwik::translate('AskAI_NotAvailableText')]);
            return;
        }

        $messages = $this->parser->sanitizeConversation($this->parser->parseMessages($messages), ['user', 'assistant']);
        $widgetParams = $this->parser->parseWidgetParams($widgetParams);
        $withTools = $this->agent->hasTools();

        if ($this->insightReport->isInsightRequest($widgetParams)) {
            $basePrompt = Piwik::translate('AskAI_InsightSystemPrompt');
            $reportData = $this->insightReport->fetch($widgetParams, $idSite, $date, $period);

            // the insights panel starts the conversation without a message: ask for the analysis
            if ($messages === [] || $messages[0]['role'] !== 'user') {
                array_unshift($messages, ['role' => 'user', 'content' => Piwik::translate('AskAI_InsightAgentPrompt')]);
            }
            // opened again on the same report, the panel posts its previous answer last
            $messages = $this->parser->endWithQuestion($messages, Piwik::translate('AskAI_InsightAgentPrompt'));
            $featureKey = self::FEATURE_INSIGHTS;
        } else {
            $basePrompt = Piwik::translate('AskAI_ChatSystemPrompt');
            $reportData = null;
            $featureKey = self::FEATURE_CHAT;
        }

        $systemPrompt = $this->agent->buildSystemPrompt($basePrompt, $idSite, $period, $date, $reportData, $withTools);
        $systemPromptWithoutTools = $withTools
            ? $this->agent->buildSystemPrompt($basePrompt, $idSite, $period, $date, $reportData, false)
            : $systemPrompt;

        $this->agent->run($messages, $systemPrompt, $featureKey, $sessionKey, $emit, $systemPromptWithoutTools);
    }
}
