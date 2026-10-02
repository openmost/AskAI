<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

declare(strict_types=1);

namespace Piwik\Plugins\AskAI\tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The fixed system prompts and the texts of every language
 *
 * @group AskAI
 * @group AskAIPromptTranslationsTest
 * @group Plugins
 */
class PromptTranslationsTest extends TestCase
{
    private const PROMPT_KEYS = ['ChatSystemPrompt', 'InsightSystemPrompt'];

    // the assistant is neutral towards the providers: no vendor or model name in its texts
    private const VENDOR_NAMES = ['ChatGPT', 'OpenAI', 'GPT', 'Anthropic', 'Claude', 'Gemini', 'Mistral', 'Bedrock'];

    /**
     * @dataProvider getLanguageFiles
     */
    public function test_theLanguageFile_isValid_withEveryEnglishKey_andNoEmDash(string $file): void
    {
        $content = (string) file_get_contents($file);
        $translations = json_decode($content, true);

        $this->assertIsArray($translations, json_last_error_msg());
        $this->assertStringNotContainsString("\u{2014}", $content);

        $english = $this->getEnglish();
        $this->assertSame(array_keys($english), array_keys($translations['AskAI']));
        foreach ($translations['AskAI'] as $key => $value) {
            $this->assertNotSame('', trim($value), $key);
        }
    }

    /**
     * @dataProvider getLanguageFiles
     */
    public function test_theTexts_nameNoAiVendor(string $file): void
    {
        $translations = json_decode((string) file_get_contents($file), true)['AskAI'];

        foreach ($translations as $key => $value) {
            foreach (self::VENDOR_NAMES as $vendor) {
                $this->assertSame(0, preg_match('/\b' . $vendor . '\b/', $value), $key);
            }
        }
    }

    /**
     * @dataProvider getLanguageFiles
     */
    public function test_thePrompts_keepTheStructureOfTheEnglishPrompts(string $file): void
    {
        $english = $this->getEnglish();
        $translations = json_decode((string) file_get_contents($file), true)['AskAI'];

        foreach (self::PROMPT_KEYS as $key) {
            $this->assertSame(substr_count($english[$key], "\n"), substr_count($translations[$key], "\n"), $key);
            $this->assertSame(substr_count($english[$key], '**'), substr_count($translations[$key], '**'), $key);
            $this->assertStringContainsString('Matomo', $translations[$key], $key);
        }
    }

    public function test_theInsightPrompt_leavesTheReportDataToTheAgent(): void
    {
        $prompt = $this->getEnglish()['InsightSystemPrompt'];

        // McpAgent::buildSystemPrompt() introduces the report JSON itself
        $this->assertSame(0, preg_match('/:\s*$/', $prompt), $prompt);
        $this->assertStringContainsString('JSON', $prompt);
    }

    public function test_theChatPrompt_presentsTheAssistantAsAnAiAssistant(): void
    {
        $this->assertStringStartsWith('You are the AI assistant built into Matomo', $this->getEnglish()['ChatSystemPrompt']);
    }

    public function getLanguageFiles(): array
    {
        $files = [];
        foreach (glob(__DIR__ . '/../../lang/*.json') as $file) {
            $files[basename($file)] = [$file];
        }

        return $files;
    }

    /**
     * @return array<string, string>
     */
    private function getEnglish(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/../../lang/en.json'), true)['AskAI'];
    }
}
