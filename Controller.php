<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AskAI;

use Piwik\Container\StaticContainer;
use Piwik\Http\JsonResponse;
use Piwik\Log\LoggerInterface;
use Piwik\Piwik;
use Piwik\Plugins\AIProviders\Exception\AIProviderException;
use Piwik\Plugins\AskAI\Agent\McpAgent;
use Piwik\Plugins\AskAI\Services\ConversationRunner;
use Piwik\Plugins\AskAI\Services\InsightNotAvailableException;
use Piwik\Plugins\AskAI\Services\SafeErrorMessage;
use Piwik\Request;
use Piwik\Session;

class Controller extends \Piwik\Plugin\Controller
{
    /**
     * The chat page, or what to set up first while AI Providers cannot answer
     */
    public function index()
    {
        Piwik::checkUserHasSomeViewAccess();

        $request = Request::fromRequest();
        $idSite = $request->getIntegerParameter('idSite', 0);
        $status = $this->getAgent()->getStatus($idSite, [
            'idSite' => $idSite > 0 ? $idSite : '',
            'period' => $request->getStringParameter('period', 'day'),
            'date' => $request->getStringParameter('date', 'yesterday'),
        ]);

        return $this->renderTemplate('index', [
            'is_available' => $status['available'],
            'recommendations' => $status['recommendations'],
        ]);
    }

    /**
     * Whether the chat can run as an agent using the Matomo tools, and why not otherwise
     */
    #[JsonResponse]
    public function agentStatus(): string
    {
        Piwik::checkUserHasSomeViewAccess();

        $request = Request::fromRequest();
        $idSite = $request->getIntegerParameter('idSite', 0);
        if ($idSite > 0) {
            Piwik::checkUserHasViewAccess($idSite);
        }

        return (string) json_encode($this->getAgent()->getStatus($idSite, [
            'idSite' => $idSite > 0 ? $idSite : '',
            'period' => $request->getStringParameter('period', 'day'),
            'date' => $request->getStringParameter('date', 'yesterday'),
        ]));
    }

    /**
     * Runs the agent and streams its events (text, tool calls, errors) as Server-Sent Events.
     *
     * A controller action rather than an API method: the McpServer plugin only accepts internal
     * tool calls when the root request is not an API request.
     */
    public function agent(): void
    {
        Piwik::checkUserIsNotAnonymous();
        Piwik::checkUserHasSomeViewAccess();
        $this->checkTokenInUrl();

        $request = Request::fromRequest();
        $idSite = $request->getIntegerParameter('idSite');
        Piwik::checkUserHasViewAccess($idSite);

        $period = $request->getStringParameter('period', 'day');
        $date = $request->getStringParameter('date', 'today');
        $conversationId = (string) preg_replace('/[^a-zA-Z0-9_-]/', '', $request->getStringParameter('conversationId', ''));
        $sessionKey = Piwik::getCurrentUserLogin() . '-' . $conversationId;

        // the agent can run for a while, do not block the other requests of the user meanwhile
        Session::close();

        $this->streamEvents(function (callable $emit) use ($idSite, $period, $date, $sessionKey) {
            StaticContainer::get(ConversationRunner::class)->run($idSite, $period, $date, $sessionKey, $emit);
        });
    }

    private function getAgent(): McpAgent
    {
        return StaticContainer::get(McpAgent::class);
    }

    /**
     * @param callable(callable(string, array<string, mixed>): void): void $producer
     */
    private function streamEvents(callable $producer): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        set_time_limit(0);

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Nginx
        header('X-Content-Type-Options: nosniff');
        flush();

        $emit = static function (string $type, array $data = []): void {
            echo 'data: ' . json_encode(['type' => $type] + $data) . "\n\n";
            flush();
        };

        try {
            $producer($emit);
        } catch (AIProviderException | InsightNotAvailableException $e) {
            $emit('error', ['message' => SafeErrorMessage::fromThrowable($e)]);
        } catch (\Throwable $e) {
            StaticContainer::get(LoggerInterface::class)->error('AskAI agent error: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            $emit('error', ['message' => SafeErrorMessage::fromThrowable($e)]);
        }

        echo "data: [DONE]\n\n";
        flush();
    }
}
