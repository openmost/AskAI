<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AskAI;

use Piwik\Plugins\AskAI\Agent\McpAgent;

class AskAI extends \Piwik\Plugin
{
    public function registerEvents()
    {
        return array(
            'Template.afterEventsReport' => 'renderOpenmostCommunicationAfterEvents',
            'Widget.filterWidgets' => 'addOpenmostCommunicationWidgets',
            'Template.beforeContent' => 'renderOpenmostCommunication',
            'AssetManager.getJavaScriptFiles' => 'getJavaScriptFiles',
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
        );
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = 'AskAI_AskAI';
        $translationKeys[] = 'AskAI_AiAssistant';
        $translationKeys[] = 'AskAI_Insights';
        $translationKeys[] = 'AskAI_Submit';
        $translationKeys[] = 'AskAI_You';
        $translationKeys[] = 'AskAI_MessagePlaceholder';
        $translationKeys[] = 'AskAI_AskQuestion';
        $translationKeys[] = 'AskAI_AnErrorOccurred';
        $translationKeys[] = 'AskAI_WaitingForResponse';
        $translationKeys[] = 'AskAI_AgentToolStep';
        $translationKeys[] = 'AskAI_AgentMcpUnavailable';
        $translationKeys[] = 'AskAI_AskAdministrator';
        $translationKeys[] = 'AskAI_CloseInsights';
        $translationKeys[] = 'AskAI_CopyAnswer';
        $translationKeys[] = 'AskAI_AnswerCopied';
        $translationKeys[] = 'AskAI_ScrollToLatest';
        $translationKeys[] = 'AskAI_ComposerHint';
        $translationKeys[] = 'AskAI_AnswerAnnouncement';
        $translationKeys[] = 'AskAI_AgentStepsSummary';
        $translationKeys[] = 'AskAI_AgentStepsFailed';
        $translationKeys[] = 'AskAI_AgentStepRunning';
        $translationKeys[] = 'AskAI_AgentStepDone';
        $translationKeys[] = 'AskAI_AgentStepError';
        $translationKeys[] = 'AskAI_EmptyStateTitle';
        $translationKeys[] = 'AskAI_EmptyStateText';
        $translationKeys[] = 'AskAI_SuggestionsLabel';
        $translationKeys[] = 'AskAI_SuggestionWeeklyKpis';
        $translationKeys[] = 'AskAI_SuggestionTopPages';
        $translationKeys[] = 'AskAI_SuggestionTrafficSources';
        $translationKeys[] = 'AskAI_SuggestionGoals';
        $translationKeys[] = 'AskAI_NewConversation';
        $translationKeys[] = 'AskAI_ScrollableTable';
        $translationKeys[] = 'AskAI_ScrollableCode';
        $translationKeys[] = 'AskAI_CopyCode';
        $translationKeys[] = 'AskAI_CodeCopied';
        $translationKeys[] = 'AskAI_NotAvailableTitle';
        $translationKeys[] = 'AskAI_NotAvailableText';
        foreach (['InstallAiProviders', 'ActivateAiProviders', 'ConnectProvider', 'InstallMcpServer', 'ActivateMcpServer', 'EnableMcp', 'EnableWriteMode'] as $step) {
            $translationKeys[] = 'AskAI_Recommend' . $step;
            $translationKeys[] = 'AskAI_Recommend' . $step . 'Action';
        }
    }

    public function getJavaScriptFiles(&$files)
    {
        if (McpAgent::isAvailable()) {
            $files[] = "plugins/AskAI/assets/js/app.js";
        }
    }

    public function getStylesheetFiles(&$files)
    {
        // also styles the setup steps shown on the chat page while AI Providers cannot answer
        $files[] = "plugins/AskAI/assets/css/app.css";
    }

    public function renderOpenmostCommunication(&$out, $layout, $module = '', $action = '')
    {
        OpenmostCommunication::beforeContent($out, (string) $layout, (string) $module, (string) $action, $this->getPluginName());
    }

    public function addOpenmostCommunicationWidgets($list)
    {
        OpenmostCommunication::filterWidgets($list, $this->getPluginName());
    }

    public function renderOpenmostCommunicationAfterEvents(&$out, $dataTable = null)
    {
        OpenmostCommunication::afterEventsReport($out, $this->getPluginName());
    }
}
