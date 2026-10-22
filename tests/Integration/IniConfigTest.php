<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

declare(strict_types=1);

namespace Piwik\Plugins\AskAI\tests\Integration;

use Piwik\Config;
use Piwik\Plugins\AskAI\Settings\IniConfig;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group AskAI
 * @group AskAIIniConfigTest
 * @group Plugins
 */
class IniConfigTest extends IntegrationTestCase
{
    public function tearDown(): void
    {
        Config::getInstance()->AskAI = [];

        parent::tearDown();
    }

    public function test_defaults_withoutTheSection(): void
    {
        $this->assertSame(30, IniConfig::getRateLimitRequests());
        $this->assertSame(3600, IniConfig::getRateLimitWindow());
    }

    public function test_readsTheValues_ofTheSection(): void
    {
        Config::getInstance()->AskAI = [
            'rate_limit_requests' => '120',
            'rate_limit_window' => '600',
        ];

        $this->assertSame(120, IniConfig::getRateLimitRequests());
        $this->assertSame(600, IniConfig::getRateLimitWindow());
    }

    public function test_zeroRequests_disablesTheLimit(): void
    {
        Config::getInstance()->AskAI = ['rate_limit_requests' => '0'];

        $this->assertSame(0, IniConfig::getRateLimitRequests());
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function test_invalidValues_fallBackToTheDefaults(array $section): void
    {
        Config::getInstance()->AskAI = $section;

        $this->assertSame(30, IniConfig::getRateLimitRequests());
        $this->assertSame(3600, IniConfig::getRateLimitWindow());
    }

    public function getInvalidValues(): array
    {
        return [
            'empty' => [['rate_limit_requests' => '', 'rate_limit_window' => '']],
            'negative numbers' => [['rate_limit_requests' => '-5', 'rate_limit_window' => '-1']],
            'not numeric' => [['rate_limit_requests' => 'ten', 'rate_limit_window' => '1h']],
            'zero window' => [['rate_limit_window' => '0']],
            'arrays' => [['rate_limit_requests' => ['1'], 'rate_limit_window' => ['1']]],
        ];
    }
}
