## Changelog

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
