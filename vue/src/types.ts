/*!
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

export type AgentStepStatus = 'running' | 'done' | 'error';

// a tool called by the agent while answering
export interface AgentStep {
  id: string;
  name: string;
  title: string;
  status: AgentStepStatus;
}

export interface Message {
  role: string;
  content: string;
  steps?: AgentStep[];
}

// a step that makes the assistant available or unlocks the agent mode, texts are translation keys
export interface Recommendation {
  id: string;
  message: string;
  // empty when the user cannot take the step
  action: string;
  url: string;
  askAdministrator: boolean;
}

export interface AgentStatus {
  mode: 'agent' | 'chat';
  // whether the provider connected in AI Providers can answer
  available: boolean;
  mcp: string;
  ai: string;
  providerName: string | null;
  toolCount: number;
  // false when McpServer only exposes read-only tools
  canPerformActions: boolean;
  recommendations: Recommendation[];
}

export interface AgentEvent {
  type: 'text' | 'tool_call' | 'tool_result' | 'error';
  content?: string;
  id?: string;
  name?: string;
  title?: string;
  isError?: boolean;
  message?: string;
}
