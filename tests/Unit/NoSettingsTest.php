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
use Piwik\Plugins\AskAI\Controller;
use Piwik\Plugins\AskAI\Menu;
use Piwik\Plugins\AskAI\Services\DataPrivacy;
use Piwik\Plugins\AskAI\SystemSettings;

/**
 * AskAI has no settings of its own but the privacy ones: the provider, the model and the credentials belong to AI
 * Providers, the prompts are fixed translations. Every engine call goes through the AIProviderService.
 *
 * @group AskAI
 * @group AskAINoSettingsTest
 * @group Plugins
 */
class NoSettingsTest extends TestCase
{
    private const PLUGIN_DIR = __DIR__ . '/../..';

    public function test_thePluginShipsNoSettingsClass_butThePrivacyOne_noApiClass_andNoSettingsTemplate(): void
    {
        foreach (['MeasurableSettings.php', 'UserSettings.php', 'SettingsBase.php', 'API.php', 'Config.php'] as $file) {
            $this->assertFalse(file_exists(self::PLUGIN_DIR . '/' . $file), $file);
        }
        $this->assertFalse(is_dir(self::PLUGIN_DIR . '/Settings'));

        $templates = array_map('basename', glob(self::PLUGIN_DIR . '/templates/*') ?: []);
        $this->assertSame(['index.twig'], $templates);

        foreach ($this->getPhpSources() as $file) {
            if (basename($file) === 'SystemSettings.php') {
                continue;
            }
            $source = (string) file_get_contents($file);
            $this->assertSame(0, preg_match('/extends\s+[\\\\\w]*Settings\b/', $source), $file);
            $this->assertStringNotContainsString('Piwik\Settings\\', $source, $file);
        }
    }

    public function test_theOnlySettingsAreThePrivacySettings(): void
    {
        $settings = [];
        foreach ((new \ReflectionClass(SystemSettings::class))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() === SystemSettings::class) {
                $settings[] = $property->getName();
            }
        }
        sort($settings);

        $privacySettings = array_keys(DataPrivacy::DEFAULTS);
        sort($privacySettings);

        $this->assertSame($privacySettings, $settings);
    }

    public function test_theControllerOnlyRoutesTheChatPage_theAgentStatus_andTheAgent(): void
    {
        $actions = [];
        foreach ((new \ReflectionClass(Controller::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() === Controller::class && !$method->isConstructor()) {
                $actions[] = $method->getName();
            }
        }
        sort($actions);

        $this->assertSame(['agent', 'agentStatus', 'index'], $actions);
    }

    public function test_theMenuOnlyAddsTheChatPage_noAdministrationEntry(): void
    {
        $methods = [];
        foreach ((new \ReflectionClass(Menu::class))->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() === Menu::class) {
                $methods[] = $method->getName();
            }
        }

        $this->assertSame(['configureTopMenu'], $methods);
    }

    public function test_theTranslationsHoldNoSettingText(): void
    {
        $english = json_decode((string) file_get_contents(self::PLUGIN_DIR . '/lang/en.json'), true)['AskAI'];

        foreach (array_keys($english) as $key) {
            if (strpos($key, 'PrivacySetting') === 0) {
                continue;
            }
            $this->assertSame(0, preg_match('/ApiKey|Host|Model|Setting|BasePrompt|Reset|UseGeneral/', $key));
        }
    }

    public function test_theVueLibraryHasNoSettingsPage(): void
    {
        $index = (string) file_get_contents(self::PLUGIN_DIR . '/vue/src/index.ts');

        $this->assertStringNotContainsString('Settings', $index);
        $this->assertSame([], glob(self::PLUGIN_DIR . '/vue/src/Manage*') ?: []);
    }

    public function test_noSourceTalksToAModelDirectly_everyEngineCallGoesThroughTheAiProviderService(): void
    {
        $converseCalls = 0;
        foreach ($this->getPhpSources() as $file) {
            $source = (string) file_get_contents($file);
            foreach (['curl_init', 'curl_exec', 'sendHttpRequest', 'fsockopen', 'stream_context_create', 'api.openai.com'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $source, $file . ' must not call a model itself');
            }
            $converseCalls += preg_match_all('/(?<!\$this)->converse\(/', $source);
        }

        // McpAgent::converse() is the single call site, on the AIProviderService of the dependencies
        $this->assertSame(1, $converseCalls);
        $agent = (string) file_get_contents(self::PLUGIN_DIR . '/Agent/McpAgent.php');
        $this->assertStringContainsString('$this->dependencies->getAiProvidersService()->converse($request)', $agent);
    }

    /**
     * @return list<string>
     */
    private function getPhpSources(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator((string) realpath(self::PLUGIN_DIR), \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $path = str_replace('\\', '/', (string) $file);
            if (substr($path, -4) !== '.php' || preg_match('~/(tests|node_modules|vendor)/~', $path)) {
                continue;
            }
            $files[] = $path;
        }
        sort($files);

        return $files;
    }
}
