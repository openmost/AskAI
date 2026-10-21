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
use Piwik\Plugins\AskAI\API;
use Piwik\Plugins\AskAI\Controller;
use Piwik\Plugins\AskAI\Services\DataPrivacy;
use Piwik\Plugins\AskAI\Settings\DefaultPrompts;
use Piwik\Plugins\AskAI\SystemSettings;

/**
 * AskAI only has privacy settings and prompts: the provider, the model and the credentials belong to AI Providers, and
 * every engine call goes through the AIProviderService.
 *
 * @group AskAI
 * @group AskAINoProviderSettingsTest
 * @group Plugins
 */
class NoProviderSettingsTest extends TestCase
{
    private const PLUGIN_DIR = __DIR__ . '/../..';

    public function test_thePluginShipsNoMeasurableOrUserSettingsClass_andNoConnectionConfig(): void
    {
        foreach (['MeasurableSettings.php', 'UserSettings.php', 'SettingsBase.php', 'Config.php'] as $file) {
            $this->assertFalse(file_exists(self::PLUGIN_DIR . '/' . $file), $file);
        }

        $settings = array_map('basename', glob(self::PLUGIN_DIR . '/Settings/*') ?: []);
        sort($settings);
        $this->assertSame(['DefaultPrompts.php', 'EffectivePrompts.php', 'SiteSettingsStorage.php', 'SystemSettingsForm.php'], $settings);

        $templates = array_map('basename', glob(self::PLUGIN_DIR . '/templates/*') ?: []);
        sort($templates);
        $this->assertSame(['index.twig', 'manage.twig', 'settings.twig'], $templates);
    }

    public function test_theSettingsAreThePrivacySettings_andThePrompts(): void
    {
        $settings = [];
        foreach ((new \ReflectionClass(SystemSettings::class))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() === SystemSettings::class) {
                $settings[] = $property->getName();
            }
        }
        sort($settings);

        $expected = array_merge(array_keys(DataPrivacy::DEFAULTS), array_keys(DefaultPrompts::SETTING_NAMES));
        sort($expected);

        $this->assertSame($expected, $settings);
    }

    public function test_theApiOnlyReadsAndSavesTheSettings(): void
    {
        $this->assertSame(['getSiteSettings', 'setSiteSettings', 'setSystemSettings'], $this->getPublicMethods(API::class));
    }

    public function test_theControllerRoutesTheChatPage_theAgent_andTheTwoSettingsPages(): void
    {
        $this->assertSame(['agent', 'agentStatus', 'index', 'manage', 'settings'], $this->getPublicMethods(Controller::class));
    }

    public function test_theTranslationsHoldNoProviderSettingText(): void
    {
        $english = json_decode((string) file_get_contents(self::PLUGIN_DIR . '/lang/en.json'), true)['AskAI'];

        foreach (array_keys($english) as $key) {
            $this->assertSame(0, preg_match('/ApiKey|Host|Model/', $key), $key);
        }
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
    private function getPublicMethods(string $class): array
    {
        $methods = [];
        foreach ((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() === $class && !$method->isConstructor()) {
                $methods[] = $method->getName();
            }
        }
        sort($methods);

        return $methods;
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
