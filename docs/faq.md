## FAQ

__How do I install and configure this plugin?__

1. Install and activate **AskAI** from **Administration > Platform > Marketplace**.
2. As a super user, connect a provider in **Administration > System > AI Providers**.
3. Check **Allow sending Matomo data to the AI provider** in **Administration > System > General settings > AskAI**: it is off by default.

Apart from this consent and the other privacy settings, there is nothing to configure in AskAI itself. You can also download the plugin from [GitHub](https://github.com/openmost/AskAI), extract it to your `plugins/` folder and activate it.

__The chat says that data sharing is not allowed__

No Matomo data leaves your instance without an explicit decision: a super user must check **Allow sending Matomo data to the AI provider** in **Administration > System > General settings > AskAI**. Other users are asked to contact a super user.

__What do I need to make it work?__

The **AI Providers** plugin, bundled with Matomo, with at least one connected provider. AskAI has no API key, host or model setting of its own.

__Which AI providers and models are supported?__

Every provider and model AI Providers supports: OpenAI, Anthropic, Google, Amazon Bedrock and custom OpenAI-compatible endpoints. AskAI always uses the provider and the model chosen in AI Providers, so changing them there is enough.

__Where are the settings?__

There are none. The provider, the model and the credentials are managed in AI Providers, and the chat and insight prompts are fixed, written for analytics and translated into 13 languages.

__The Ask AI page shows steps instead of the chat__

AI Providers cannot answer yet: the plugin is missing, not activated, or no provider is connected. The page lists the steps to take, with a direct link for super users. Other users are asked to contact their Matomo administrator.

__What is the agent mode?__

When the **McpServer** plugin is enabled, the chat and the insight panel work as an agent: they query your live reports, websites, goals, dimensions and segments through the Matomo tools to answer with real figures. A timeline shows each tool used.

To enable it, install, activate and enable McpServer in **Administration > System > General settings > McpServer**. The chat recommends each missing step, with a direct link for super users.

__What happens when my model does not support tools?__

The assistant answers as a plain chat, without the Matomo tools and without any error. Choose a model with tool support in AI Providers to use the agent mode.

__Can the agent change things in Matomo?__

Only if McpServer allows write methods (Raw Matomo API tool access), and only with your confirmation: before any create, update or delete, the agent describes the exact change and waits for your explicit confirmation in the conversation. The agent never has more access than the current user.

__Do I need to expose my Matomo instance or configure OAuth for the agent?__

No. The agent calls the Matomo tools inside your Matomo, with the permissions of the logged in user. It also works on private and intranet instances.

__What do insights analyse?__

The whole report you are looking at, not only the visible rows: its rows, totals and metrics, with the active segment, period and comparisons. Insights work on data tables, evolution graphs, goals, custom reports and the other reports Matomo declares as widgets.

__Can I use AskAI together with the other Openmost AI plugins?__

Yes. They share a single insight panel: opening one closes the others, so two panels never stack.

__Which data is sent to the AI provider?__

Insights send the data of the report you are looking at (labels, metrics, totals, period, segment and comparisons) and the conversation to the provider connected in AI Providers. In agent mode, the results of the tools the agent calls are sent too. Raw visitor data is never sent, unless the report itself contains it, such as the Visits Log. Conversations are not stored by the plugin.

__Is there a usage limit?__

Yes, 30 AI requests per hour, per user and per website, to protect your AI budget.

__Which languages are supported?__

English, Arabic, Chinese (Simplified and Traditional), Dutch, French, German, Italian, Japanese, Polish, Portuguese, Spanish and Swedish.

__What are the requirements?__

- Matomo 5.13.0 or higher
- PHP 7.2.5 or higher (8.1 or higher for McpServer)
- AI Providers (bundled with Matomo) with a connected provider
- For the agent mode: the McpServer plugin

__How do I get support?__

Email ronan@openmost.com, or open an issue on [GitHub](https://github.com/openmost/AskAI/issues). More on https://openmost.com/matomo/extensions/ask-ai.
