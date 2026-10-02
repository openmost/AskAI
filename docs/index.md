## Documentation

### Features

#### Insights on any report

- An **Ask AI** button in the report header opens an insight panel that analyses the report you are looking at, then lets you ask follow-up questions.
- The whole report is analysed, not only the rows visible on screen: the plugin sends a compact payload with the rows, the report totals and the names and units of the metrics.
- The analysis follows what you see: the active segment, the period and date, and the compared periods or segments.
- Works with data tables, evolution graphs, goals, ecommerce, custom dimensions, custom reports, row evolution and the other reports Matomo declares as widgets.
- When a widget has no report data, or the report cannot be loaded (for example a missing access), a clear message is displayed instead of sending the error to the model.
- The panel is an accessible dialog: keyboard navigation, focus kept inside the panel, Escape to close. Opening it closes the insight panel of any other Openmost AI plugin, so two panels never stack.

#### Chat

- An **Ask AI** page in the main menu, with suggested questions to start a conversation and a "New conversation" button.
- Auto-scroll that follows the answer while it is written, stops when you scroll up, and a button to jump back to the latest message.
- Markdown answers with scrollable tables and code blocks, and buttons to copy an answer or a code block.
- Enter sends the message, Shift+Enter adds a new line.

#### Any provider, any model, no settings

- The plugin has no settings, no API key and no model list of its own: it answers with the provider and model connected in **Administration > System > AI Providers**, whichever they are (OpenAI, Anthropic, Google, Amazon Bedrock or a custom OpenAI-compatible endpoint).
- Change the provider or the model in AI Providers and the assistant follows, without any change in the plugin.
- The chat and insight prompts are fixed, written for analytics and translated: the chat answers as a senior analytics consultant (direct answer, key figures, prioritised recommendations, no invented figures), and insights follow a fixed structure (summary, key figures, notable patterns, recommendations).
- While AI Providers cannot answer, the plugin shows what to set up instead of a form: the AI Providers plugin is missing, not activated, or no provider is connected. Super users get a direct link for each step, other users are asked to contact their Matomo administrator.

#### Agent mode, with MCP Server

When the **McpServer** plugin is enabled, the chat and the insight panel work as an agent:

- The agent queries your live reports, websites, goals, dimensions and segments by itself, for any period, to answer with real figures.
- A timeline shows each Matomo tool used for an answer and its status.
- Tools run inside Matomo with the permissions of the current user: no public URL, OAuth client or extra token is needed.
- Write actions (for example creating a goal, an annotation or a segment) are only possible when McpServer allows write methods, and only after the agent has described the exact change and you have confirmed it explicitly in the conversation. This rule is added by the plugin to every agent conversation.
- When something is missing, the chat recommends the next step, one at a time: install, activate or enable MCP Server, allow write methods.
- The agent mode is optional. Without McpServer, the connected provider answers without tools. A provider or model that does not support tools also answers without them, with no error.

#### More

- Interface translated into 13 languages: English, Arabic, Chinese (Simplified and Traditional), Dutch, French, German, Italian, Japanese, Polish, Portuguese, Spanish and Swedish.
- Follows the Matomo light and dark themes.
- Rate limit of 30 AI requests per hour, per user and per website.

### Requirements

- Matomo 6.x (`>=6.0.0-b1,<7.0.0-b1`)
- PHP 8.1 or higher
- The **AI Providers** plugin (bundled with Matomo) with at least one connected provider
- Optional, for the agent mode: the **McpServer** plugin from the Marketplace. Write actions also require write methods to be allowed in the McpServer settings (Raw Matomo API tool access).

### Installation / Configuration

1. Install and activate **AskAI** from **Administration > Platform > Marketplace**.
2. As a super user, connect a provider in **Administration > System > AI Providers**. There is nothing to configure in AskAI itself.
3. Optionally, for the agent mode, install, activate and enable **McpServer** (**Administration > System > General settings > McpServer**). The chat guides you through each missing step.

Then open the **Ask AI** page in the main menu, or click the **Ask AI** button in the header of a report.

### Privacy and data

- Insights send the data of the report you are looking at (labels, metrics and totals), its period, segment and comparisons, and the conversation, to the provider connected in AI Providers.
- The chat sends your messages and the prompt. In agent mode, the results of the Matomo tools the agent calls are also sent to the provider.
- Raw visitor data is never sent, unless the report itself contains it, for example the Visits Log (limited to 100 visits).
- Insight requests are restricted to the report and data methods of widgets declared by Matomo, never to an arbitrary API method, and run with the permissions of the current user.
- AskAI stores no API key and no conversation: the credentials stay in AI Providers.
