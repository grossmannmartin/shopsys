<?php

namespace Tests\FrameworkBundle\Unit\Component\Router;

use PHPUnit\Framework\TestCase;
use Shopsys\FrameworkBundle\Component\Domain\Config\DomainConfig;
use Shopsys\FrameworkBundle\Component\Domain\Domain;
use Shopsys\FrameworkBundle\Component\Router\DomainRouterFactory;
use Shopsys\FrameworkBundle\Component\Router\FriendlyUrl\FriendlyUrlRouter;
use Shopsys\FrameworkBundle\Component\Router\FriendlyUrl\FriendlyUrlRouterFactory;
use Shopsys\FrameworkBundle\Component\Router\LocalizedRouterFactory;
use Shopsys\FrameworkBundle\Component\Setting\Setting;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

class DomainRouterFactoryTest extends TestCase
{
    public function testGetRouter()
    {
        $domainConfig = new DomainConfig(Domain::THIRD_DOMAIN_ID, 'http://example.com:8080', 'example', 'en');
        $settingMock = $this->createMock(Setting::class);
        $domain = new Domain([$domainConfig], $settingMock);

        $localizedRouterMock = $this->getMockBuilder(RouterInterface::class)->getMockForAbstractClass();
        $friendlyUrlRouterMock = $this->getMockBuilder(FriendlyUrlRouter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $localizedRouterFactoryMock = $this->getMockBuilder(LocalizedRouterFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['getRouter'])
            ->getMock();
        $localizedRouterFactoryMock
            ->expects($this->once())
            ->method('getRouter')
            ->willReturnCallback(function ($locale, RequestContext $context) use ($localizedRouterMock) {
                $this->assertSame('en', $locale);
                $this->assertSame('example.com', $context->getHost());

                return $localizedRouterMock;
            });

        $friendlyUrlRouterFactoryMock = $this->getMockBuilder(FriendlyUrlRouterFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['createRouter'])
            ->getMock();
        $friendlyUrlRouterFactoryMock
            ->expects($this->once())
            ->method('createRouter')
            ->willReturnCallback(
                function (DomainConfig $actualDomainConfig, RequestContext $context) use ($domainConfig, $friendlyUrlRouterMock) {
                    $this->assertSame($domainConfig, $actualDomainConfig);
                    $this->assertSame('example.com', $context->getHost());

                    return $friendlyUrlRouterMock;
                }
            );

        $requestStackMock = $this->createMock(RequestStack::class);
        $containerMock = $this->createMock(ContainerInterface::class);

        $domainRouterFactory = new DomainRouterFactory(
            'routerConfiguration',
            $localizedRouterFactoryMock,
            $friendlyUrlRouterFactoryMock,
            $domain,
            $requestStackMock,
            $containerMock,
            __DIR__
        );

        $router = $domainRouterFactory->getRouter(Domain::THIRD_DOMAIN_ID);

        $this->assertInstanceOf(RouterInterface::class, $router);
    }
}
