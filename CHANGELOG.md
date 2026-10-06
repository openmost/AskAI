## Changelog

### 6.1.0

> **Action required.** Nothing is sent to the AI provider any more until a super user checks *Allow sending Matomo data to the AI provider* in *Administration > System > General settings > AskAI*. Until then, the chat and the insights ask users to contact a super user.

**Privacy**

- New privacy settings in *Administration > System > General settings > AskAI*: nothing is sent to the AI provider until a super user checks *Allow sending Matomo data to the AI provider*, off by default. They also show where the data goes.
- Before sending, e-mail and IP addresses are masked and URL query strings are removed, in the report data and in the results of the Matomo tools. Both are on by default.
- Visitor-level data (Visits Log, visitor profiles, real-time and User ID reports) is excluded by default from the insights and the agent.
- The chat tells users that their questions, and the Matomo data read to answer them, are sent to the AI provider configured by the administrator.
- When a Matomo tool fails in agent mode, the AI provider only receives a generic error with a reference: the details stay in the Matomo logs.

### 6.0.0

First release of Ask AI, the AI assistant for Matomo that runs on AI Providers.

- **Any provider, any model**: the chat and the insights answer with the provider and model connected in *Administration > System > AI Providers* (OpenAI, Anthropic, Google, Amazon Bedrock or a custom endpoint). The plugin has no settings, no API key and no model of its own.
- **Insights on any report**: an *Ask AI* button in the report header opens an insight panel on the full report, following the active segment, period and comparisons, with follow-up questions.
- The insight panel closes the panel of the other Openmost AI plugins (ChatGPT, Mistral AI, Claude) when it opens.
- **Chat page**: an *Ask AI* page in the main menu, with suggested questions, copy buttons, scrollable tables and code blocks, and auto-scroll.
- **Agent mode** with McpServer: the assistant queries your live reports through the Matomo tools, with a timeline of the tools used. Write actions are only performed after an explicit confirmation in the conversation. A model without tool support answers as a plain chat.
- **Guided setup**: while AI Providers cannot answer, the plugin shows the steps to take instead of a form, with direct links for super users.
- Fixed chat and insight prompts written for analytics, interface translated into 13 languages, light and dark themes, rate limit of 30 requests per hour per user and website.
- The prompts ask the assistant to flag the figures of a period that has not ended yet as partial and to compare the same number of elapsed days instead of calling a drop a decline, and to compute every difference, percentage and ratio from the exact numbers.
