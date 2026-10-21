<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

declare(strict_types=1);

namespace Piwik\Plugins\AskAI\tests\Integration;

use Piwik\API\Request;
use Piwik\Container\StaticContainer;
use Piwik\Plugins\AskAI\Agent\PluginDependencies;
use Piwik\Plugins\AskAI\Services\DataPrivacy;
use Piwik\Plugins\AskAI\Settings\DefaultPrompts;
use Piwik\Plugins\AskAI\Settings\EffectivePrompts;
use Piwik\Plugins\AskAI\Settings\SiteSettingsStorage;
use Piwik\Plugins\AskAI\Settings\SystemSettingsForm;
use Piwik\Plugins\AskAI\SystemSettings;
use Piwik\Plugins\AskAI\tests\Fakes\FakePluginDependencies;
use Piwik\Settings\Storage\Backend\PluginSettingsTable;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Translation\Translator;

/**
 * Prompts of the general and website settings, privacy settings saved from the Ask AI page, access control.
 *
 * @group AskAI
 * @group AskAIPromptSettingsTest
 * @group Plugins
 */
class PromptSettingsTest extends IntegrationTestCase
{
    private int $idSite;

    /** @var array<string, array<string, string>> */
    private array $defaults = [];

    public function setUp(): void
    {
        parent::setUp();

        Fixture::createSuperUser();
        FakeAccess::clearAccess(true);
        $this->idSite = (int) Fixture::createWebsite('2024-01-01 00:00:00');

        // the test environment only loads the translations of the core plugins
        $this->getTranslator()->addDirectory(__DIR__ . '/../../lang');
        StaticContainer::getContainer()->set(PluginDependencies::class, FakePluginDependencies::connected());

        foreach (['en', 'fr', 'ja'] as $language) {
            $translations = json_decode((string) file_get_contents(__DIR__ . '/../../lang/' . $language . '.json'), true)['AskAI'];
            $this->defaults[$language] = [
                DefaultPrompts::CHAT => $translations['ChatSystemPrompt'],
                DefaultPrompts::INSIGHT => $translations['InsightSystemPrompt'],
            ];
        }
    }

    public function tearDown(): void
    {
        $this->getTranslator()->setCurrentLanguage('en');

        parent::tearDown();
    }

    public function test_withoutStoredPrompts_theDefaultsAreUsed_andShownOnTheSettingsPage(): void
    {
        $prompts = new EffectivePrompts();
        $this->assertSame($this->defaults['en'][DefaultPrompts::CHAT], $prompts->getChatPrompt($this->idSite));
        $this->assertSame($this->defaults['en'][DefaultPrompts::INSIGHT], $prompts->getInsightPrompt($this->idSite));

        $values = (new SystemSettingsForm())->getValues();
        $this->assertSame($this->defaults['en'][DefaultPrompts::CHAT], $values[DefaultPrompts::CHAT]);
        $this->assertSame($this->defaults['en'][DefaultPrompts::INSIGHT], $values[DefaultPrompts::INSIGHT]);

        $this->assertSame([], $this->getStoredGeneralPrompts());
    }

    public function test_theDefaults_areInTheLanguageOfTheUser(): void
    {
        $this->getTranslator()->setCurrentLanguage('fr');

        $this->assertSame($this->defaults['fr'][DefaultPrompts::CHAT], (new EffectivePrompts())->getChatPrompt($this->idSite));
        $this->assertSame($this->defaults['fr'], DefaultPrompts::getDefaults());
    }

    public function test_aCustomGeneralPrompt_isStored_andUsedByEveryWebsite(): void
    {
        $this->saveSystemSettings(['chatBasePrompt' => 'My chat & prompt', 'insightBasePrompt' => 'My insight prompt:']);

        $this->assertSame(['chatBasePrompt' => 'My chat & prompt', 'insightBasePrompt' => 'My insight prompt:'], $this->getStoredGeneralPrompts());
        $prompts = new EffectivePrompts();
        $this->assertSame('My chat & prompt', $prompts->getChatPrompt($this->idSite));
        $this->assertSame('My insight prompt:', $prompts->getInsightPrompt($this->idSite));
        $this->assertSame('My chat & prompt', (new SystemSettingsForm())->getValues()[DefaultPrompts::CHAT]);
    }

    /**
     * @dataProvider getDefaultLanguages
     */
    public function test_aDefaultPrompt_ofAnyLanguage_isNotStored(string $language): void
    {
        $this->saveSystemSettings(['chatBasePrompt' => 'My chat prompt', 'insightBasePrompt' => 'My insight prompt']);

        // a textarea may submit the line breaks as CRLF
        $this->saveSystemSettings(['chatBasePrompt' => str_replace("\n", "\r\n", $this->defaults[$language][DefaultPrompts::CHAT])]);

        $this->assertSame(['insightBasePrompt' => 'My insight prompt'], $this->getStoredGeneralPrompts());
        $this->assertSame($this->defaults['en'][DefaultPrompts::CHAT], (new EffectivePrompts())->getChatPrompt($this->idSite));
    }

    public function getDefaultLanguages(): array
    {
        return [
            'English' => ['en'],
            'French' => ['fr'],
            'Japanese' => ['ja'],
        ];
    }

    public function test_anEmptyGeneralPrompt_isSavedAsTheDefault(): void
    {
        $this->saveSystemSettings(['insightBasePrompt' => 'My insight prompt']);

        $this->saveSystemSettings(['insightBasePrompt' => '  ']);

        $this->assertSame([], $this->getStoredGeneralPrompts());
        $this->assertSame($this->defaults['en'][DefaultPrompts::INSIGHT], (new EffectivePrompts())->getInsightPrompt($this->idSite));
    }

    public function test_aParameterLeftOut_keepsItsSavedValue(): void
    {
        $this->saveSystemSettings(['chatBasePrompt' => 'My chat prompt', 'dataSharingAllowed' => '1']);

        $this->saveSystemSettings(['insightBasePrompt' => 'My insight prompt']);

        $this->assertSame('My chat prompt', (new SystemSettings())->getPrompt(DefaultPrompts::CHAT));
        $this->assertTrue((new DataPrivacy())->isDataSharingAllowed());
    }

    public function test_thePrivacySettings_areSavedFromTheAskAiPage(): void
    {
        $this->saveSystemSettings(['dataSharingAllowed' => '1', 'maskPersonalData' => '0']);

        $privacy = new DataPrivacy();
        $this->assertTrue($privacy->isDataSharingAllowed());
        $this->assertSame('[email] test@example.com', $privacy->redact('[email] test@example.com'));

        $values = (new SystemSettingsForm())->getValues();
        $this->assertSame('1', $values['dataSharingAllowed']);
        $this->assertSame('0', $values['maskPersonalData']);
        $this->assertSame('1', $values['stripUrlQueryStrings']);

        $this->saveSystemSettings(['dataSharingAllowed' => '0']);
        $this->assertFalse((new DataPrivacy())->isDataSharingAllowed());
    }

    public function test_noSetting_isListedInTheGeneralSettings(): void
    {
        $this->assertSame([], (new SystemSettings())->getSettingsWritableByCurrentUser());
    }

    public function test_aWebsitePrompt_overridesTheGeneralPrompt_forThisWebsiteOnly(): void
    {
        $otherIdSite = (int) Fixture::createWebsite('2024-01-01 00:00:00');
        $this->saveSystemSettings(['chatBasePrompt' => 'General chat prompt']);

        $this->saveSiteSettings(['chatBasePrompt' => 'Website chat prompt']);

        $prompts = new EffectivePrompts();
        $this->assertSame('Website chat prompt', $prompts->getChatPrompt($this->idSite));
        $this->assertSame('General chat prompt', $prompts->getChatPrompt($otherIdSite));
        $this->assertSame($this->defaults['en'][DefaultPrompts::INSIGHT], $prompts->getInsightPrompt($this->idSite));
        $this->assertSame(
            ['chatBasePrompt' => 'Website chat prompt', 'insightBasePrompt' => ''],
            Request::processRequest('AskAI.getSiteSettings', ['idSite' => $this->idSite])
        );
    }

    public function test_anEmptyWebsitePrompt_followsTheGeneralPrompt(): void
    {
        $this->saveSiteSettings(['chatBasePrompt' => 'Website chat prompt']);
        $this->saveSystemSettings(['chatBasePrompt' => 'General chat prompt']);

        $this->saveSiteSettings(['chatBasePrompt' => '']);

        $this->assertSame('', SiteSettingsStorage::read($this->idSite)[DefaultPrompts::CHAT]);
        $this->assertSame('General chat prompt', (new EffectivePrompts())->getChatPrompt($this->idSite));
    }

    public function test_aWebsitePrompt_equalToTheGeneralPrompt_isSavedEmpty(): void
    {
        $this->saveSystemSettings(['chatBasePrompt' => 'General chat prompt']);

        $this->saveSiteSettings(['chatBasePrompt' => " General chat prompt \n"]);

        $this->assertSame('', SiteSettingsStorage::read($this->idSite)[DefaultPrompts::CHAT]);
    }

    public function test_aWebsitePrompt_equalToADefault_isSavedEmpty_whileTheGeneralPromptIsADefault(): void
    {
        $this->saveSiteSettings(['insightBasePrompt' => $this->defaults['fr'][DefaultPrompts::INSIGHT]]);

        $this->assertSame('', SiteSettingsStorage::read($this->idSite)[DefaultPrompts::INSIGHT]);
        // the website follows the default in the language of each user
        $this->assertSame($this->defaults['en'][DefaultPrompts::INSIGHT], (new EffectivePrompts())->getInsightPrompt($this->idSite));
    }

    public function test_aWebsitePrompt_equalToADefault_isKept_whenTheGeneralPromptIsCustom(): void
    {
        $this->saveSystemSettings(['insightBasePrompt' => 'General insight prompt']);

        $this->saveSiteSettings(['insightBasePrompt' => $this->defaults['en'][DefaultPrompts::INSIGHT]]);

        $this->assertSame($this->defaults['en'][DefaultPrompts::INSIGHT], (new EffectivePrompts())->getInsightPrompt($this->idSite));
    }

    /**
     * @dataProvider getTooLongSaves
     */
    public function test_aTooLongPrompt_isRefused(string $method): void
    {
        $prompt = str_repeat('a', DefaultPrompts::MAX_LENGTH + 1);

        try {
            $method === 'site' ? $this->saveSiteSettings(['chatBasePrompt' => $prompt]) : $this->saveSystemSettings(['chatBasePrompt' => $prompt]);
            $this->fail('A too long prompt must be refused');
        } catch (\Exception $e) {
            $this->assertStringContainsString((string) DefaultPrompts::MAX_LENGTH, $e->getMessage());
        }

        $this->assertSame($this->defaults['en'][DefaultPrompts::CHAT], (new EffectivePrompts())->getChatPrompt($this->idSite));
    }

    public function getTooLongSaves(): array
    {
        return [
            'general prompt' => ['system'],
            'website prompt' => ['site'],
        ];
    }

    /**
     * @dataProvider getNonSuperUsers
     */
    public function test_theGeneralSettings_canOnlyBeSavedBySuperUsers(string $access): void
    {
        $this->setAccess($access);

        try {
            $this->saveSystemSettings(['chatBasePrompt' => 'Hijacked', 'dataSharingAllowed' => '1']);
            $this->fail('The save must be denied');
        } catch (\Exception $e) {
            $this->assertStringContainsString('checkUserHasSuperUserAccess', $e->getMessage());
        }

        $this->assertSame([], $this->getStoredGeneralPrompts());
        $this->assertFalse((new DataPrivacy())->isDataSharingAllowed());
    }

    public function getNonSuperUsers(): array
    {
        return [
            'admin' => ['admin'],
            'view' => ['view'],
        ];
    }

    public function test_theWebsitePrompts_canBeSavedByAnAdminOfTheWebsite(): void
    {
        $this->setAccess('admin');

        $this->saveSiteSettings(['chatBasePrompt' => 'Admin chat prompt']);

        $this->assertSame('Admin chat prompt', SiteSettingsStorage::read($this->idSite)[DefaultPrompts::CHAT]);
    }

    /**
     * @dataProvider getDeniedSiteAccess
     */
    public function test_theWebsitePrompts_areDenied_withoutAdminAccessToTheWebsite(string $method): void
    {
        $otherIdSite = (int) Fixture::createWebsite('2024-01-01 00:00:00');
        FakeAccess::clearAccess(false, [$otherIdSite], [$this->idSite], 'view_user');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('checkUserHasAdminAccess');
        Request::processRequest('AskAI.' . $method, ['idSite' => $this->idSite, 'chatBasePrompt' => 'Hijacked']);
    }

    public function getDeniedSiteAccess(): array
    {
        return [
            'read' => ['getSiteSettings'],
            'save' => ['setSiteSettings'],
        ];
    }

    public function provideContainerConfig()
    {
        return [
            'Piwik\Access' => new FakeAccess(),
        ];
    }

    /**
     * @param array<string, string> $values
     */
    private function saveSystemSettings(array $values): void
    {
        Request::processRequest('AskAI.setSystemSettings', $values);
    }

    /**
     * @param array<string, string> $values
     */
    private function saveSiteSettings(array $values): void
    {
        Request::processRequest('AskAI.setSiteSettings', ['idSite' => $this->idSite] + $values);
    }

    /**
     * @return array<string, string>
     */
    private function getStoredGeneralPrompts(): array
    {
        return array_intersect_key((new PluginSettingsTable('AskAI', ''))->load(), DefaultPrompts::SETTING_NAMES);
    }

    private function setAccess(string $access): void
    {
        if ($access === 'admin') {
            FakeAccess::clearAccess(false, [$this->idSite], [], 'admin_user');
        } else {
            FakeAccess::clearAccess(false, [], [$this->idSite], 'view_user');
        }
    }

    private function getTranslator(): Translator
    {
        return StaticContainer::get(Translator::class);
    }
}
