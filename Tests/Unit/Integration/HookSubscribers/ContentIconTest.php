<?php
namespace FluidTYPO3\Flux\Tests\Unit\Integration\HookSubscribers;

/*
 * This file is part of the FluidTYPO3/Flux project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use FluidTYPO3\Flux\Form;
use FluidTYPO3\Flux\Integration\HookSubscribers\ContentIcon;
use FluidTYPO3\Flux\Provider\Provider;
use FluidTYPO3\Flux\Service\FluxService;
use FluidTYPO3\Flux\Tests\Unit\AbstractTestCase;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;
use TYPO3\CMS\Backend\View\PageLayoutView;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Lang\LanguageService;

/**
 * Class ContentIconHookSubscriberTest
 */
class ContentIconTest extends AbstractTestCase
{
    private ?ObjectManagerInterface $objectManager;
    private ?FluxService $fluxService;
    private ?CacheManager $cacheManager;
    private ?FrontendInterface $cache;

    protected function setUp(): void
    {
        $this->cache = $this->getMockBuilder(FrontendInterface::class)->getMockForAbstractClass();
        $this->fluxService = $this->getMockBuilder(FluxService::class)->disableOriginalConstructor()->getMock();
        $this->cacheManager = $this->getMockBuilder(CacheManager::class)
            ->setMethods(['getCache'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->cacheManager->method('getCache')->willReturn($this->cache);

        parent::setUp();

        // Mocking the singleton of IconRegistry is apparently required for unit tests to work on some environments.
        // Since it doesn't matter much what this method actually responds for these tests, we mock it for all envs.
        $iconRegistryMock = $this->getMockBuilder(IconRegistry::class)->setMethods(['isRegistered', 'getIconConfigurationByIdentifier'])->disableOriginalConstructor()->getMock();
        $iconRegistryMock->expects($this->any())->method('isRegistered')->willReturn(true);
        $iconRegistryMock->expects($this->any())->method('getIconConfigurationByIdentifier')->willReturn([
            'provider' => SvgIconProvider::class,
            'options' => [
                'source' => 'EXT:core/Resources/Public/Icons/T3Icons/default/default-not-found.svg'
            ]
        ]);
        GeneralUtility::setSingletonInstance(IconRegistry::class, $iconRegistryMock);
    }

    protected function tearDown(): void
    {
        GeneralUtility::removeSingletonInstance(IconRegistry::class, GeneralUtility::makeInstance(IconRegistry::class));
    }

    public function testCreatesInstancesInConstructor(): void
    {
        $subject = new ContentIcon();
        self::assertInstanceOf(FluxService::class, $this->getInaccessiblePropertyValue($subject, 'fluxService'));
        self::assertInstanceOf(FrontendInterface::class, $this->getInaccessiblePropertyValue($subject, 'cache'));
        self::assertInstanceOf(
            ObjectManagerInterface::class,
            $this->getInaccessiblePropertyValue($subject, 'objectManager')
        );
    }

    /**
     * @test
     */
    public function testAddSubIconUsesCache()
    {
        $cache = $this->getMockBuilder(VariableFrontend::class)->disableOriginalConstructor()->setMethods(array('get', 'set'))->getMock();
        $cache->expects($this->once())->method('get')->willReturn('icon');
        $instance = $this->getMockBuilder(ContentIcon::class)->setMethods(['drawGridToggle'])->disableOriginalConstructor()->getMock();
        $instance->method('drawGridToggle')->willReturn('foobar');
        $this->setInaccessiblePropertyValue($instance, 'cache', $cache);
        $result = $instance->addSubIcon(array('tt_content', 123, ['foo' => 'bar']), $this->getMockBuilder(PageLayoutView::class)->disableOriginalConstructor()->getMock());
        $this->assertEquals('icon', $result);
    }

    /**
     * @test
     */
    public function testDrawGridToggle()
    {
        $GLOBALS['LANG'] = $this->getMockBuilder(LanguageService::class)->setMethods(array('sL'))->disableOriginalConstructor()->getMock();
        $GLOBALS['LANG']->expects($this->any())->method('sL')->will($this->returnArgument(0));
        $icon = $this->getMockBuilder(Icon::class)->disableOriginalConstructor()->getMock();
        $icon->method('render')->willReturn('foobar');
        $iconFactory = $this->getMockBuilder(IconFactory::class)->setMethods(['getIcon'])->disableOriginalConstructor()->getMock();
        $iconFactory->method('getIcon')->willReturn($icon);
        $subject = $this->getMockBuilder(ContentIcon::class)->setMethods(['getIconFactory'])->disableOriginalConstructor()->getMock();
        $subject->method('getIconFactory')->willReturn($iconFactory);
        $result = $this->callInaccessibleMethod($subject, 'drawGridToggle', ['uid' => 123]);
        $this->assertStringContainsString('LLL:EXT:flux/Resources/Private/Language/locallang.xlf:toggle_content', $result);
        $this->assertStringContainsString('foobar', $result);
    }

    /**
     * @dataProvider getAddSubIconTestValues
     * @param array $parameters
     * @param ProviderInterface|NULL
     */
    public function testAddSubIcon(array $parameters, $provider)
    {
        $GLOBALS['BE_USER'] = $this->getMockBuilder(BackendUserAuthentication::class)->setMethods(array('calcPerms'))->disableOriginalConstructor()->getMock();
        $GLOBALS['BE_USER']->expects($this->any())->method('calcPerms');
        $GLOBALS['LANG'] = $this->getMockBuilder(LanguageService::class)->setMethods(array('sL'))->disableOriginalConstructor()->getMock();
        $GLOBALS['LANG']->expects($this->any())->method('sL')->will($this->returnArgument(0));

        $GLOBALS['TCA']['tt_content']['columns']['field']['config']['type'] = 'flex';
        $cache = $this->getMockBuilder(VariableFrontend::class)->disableOriginalConstructor()->setMethods(array('get', 'set'))->getMock();
        $cache->method('get')->willReturn(null);
        $cache->method('set')->with($this->anything());

        $configurationManager = $this->getMockBuilder(ConfigurationManager::class)->disableOriginalConstructor()->getMock();
        $service = $this->getMockBuilder(FluxService::class)->setMethods(array('resolvePrimaryConfigurationProvider','getConfiguration'))->getMock();
        $service->injectConfigurationManager($configurationManager);
        $service->expects($this->any())->method('resolvePrimaryConfigurationProvider')->willReturn($provider);
        $instance = $this->getMockBuilder(ContentIcon::class)->setMethods(['dummy'])->disableOriginalConstructor()->getMock();
        $instance->injectFluxService($service);
        $this->setInaccessiblePropertyValue($instance, 'cache', $cache);
        if ($provider !== null) {
            $configurationServiceMock = $this->getMockBuilder(FluxService::class)->setMethods(['resolveConfigurationProviders'])->getMock();
            $this->setInaccessiblePropertyValue($configurationServiceMock, 'configurationManager', $configurationManager);
            $this->setInaccessiblePropertyValue($provider, 'configurationService', $configurationServiceMock);
        }

        $icon = $instance->addSubIcon($parameters, $this->getMockBuilder(PageLayoutView::class)->disableOriginalConstructor()->getMock());
        $this->assertSame('', $icon);
    }

    /**
     * @return array
     */
    public function getAddSubIconTestValues()
    {
        $formWithoutIcon = $this->getMockBuilder(Form::class)->setMethods(['dummy'])->getMock();
        $formWithIcon = Form::create(array('options' => array('icon' => 'icon')));
        $providerWithoutForm = $this->getMockBuilder(Provider::class)->setMethods(array('getForm', 'getGrid'))->getMock();
        $providerWithoutForm->expects($this->any())->method('getForm')->willReturn(null);
        $providerWithoutForm->expects($this->any())->method('getGrid')->willReturn(Form\Container\Grid::create());
        $providerWithFormWithoutIcon = $this->getMockBuilder(Provider::class)->setMethods(array('getForm', 'getGrid'))->getMock();
        $providerWithFormWithoutIcon->expects($this->any())->method('getForm')->willReturn($formWithoutIcon);
        $providerWithFormWithoutIcon->expects($this->any())->method('getGrid')->willReturn(Form\Container\Grid::create());
        $providerWithFormWithIcon = $this->getMockBuilder(Provider::class)->setMethods(array('getForm', 'getGrid'))->getMock();
        $providerWithFormWithIcon->expects($this->any())->method('getForm')->willReturn($formWithIcon);
        $providerWithFormWithIcon->expects($this->any())->method('getGrid')->willReturn(Form\Container\Grid::create());
        return array(
            'no provider' => array(array('tt_content', 1, array()), null),
            'provider without form without field' => array(array('tt_content', 1, array()), $providerWithoutForm),
            'provider without form with field' => array(array('tt_content', 1, array('field' => 'test')), $providerWithoutForm),
            'provider with form without icon' => array(array('tt_content', 1, array('field' => 'test')), $providerWithFormWithoutIcon),
            'provider with form with icon' => array(array('tt_content', 1, array('field' => 'test')), $providerWithFormWithIcon),
        );
    }

    public function testReturnsEmptyStringWithInvalidCaller(): void
    {
        $subject = new ContentIcon();
        self::assertSame('', $subject->addSubIcon([], $this));
    }

    public function testReturnsEmptyStringWithInvalidTable(): void
    {
        $subject = new ContentIcon();
        self::assertSame('', $subject->addSubIcon(['foo', '', ''], $this->getMockBuilder(GridColumnItem::class)->disableOriginalConstructor()->getMock()));
    }

    protected function createObjectManagerInstance(): ObjectManagerInterface
    {
        $instance = parent::createObjectManagerInstance();
        $instance->method('get')->willReturnMap(
            [
                [FluxService::class, $this->fluxService],
                [CacheManager::class, $this->cacheManager],
            ]
        );
        return $instance;
    }
}
