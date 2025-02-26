<?php

/*
 * This file is part of Monsieur Biz' No Commerce plugin for Sylius.
 *
 * (c) Monsieur Biz <sylius@monsieurbiz.com>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MonsieurBiz\SyliusNoCommercePlugin\EventListener;

use MonsieurBiz\SyliusSettingsPlugin\Settings\SettingsInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DisableFirewallListener
{
    private const PROFILER_ROUTES = ['_wdt', '_profiler', '_profiler_search', '_profiler_search_results'];

    private FirewallMap $firewallContext;

    private SettingsInterface $nocommerceSettings;

    private ChannelContextInterface $channelContext;

    public function __construct(
        FirewallMap $firewallContext,
        SettingsInterface $nocommerceSettings,
        ChannelContextInterface $channelContext
    ) {
        $this->firewallContext = $firewallContext;
        $this->nocommerceSettings = $nocommerceSettings;
        $this->channelContext = $channelContext;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function __invoke(RequestEvent $event): void
    {
        if (!$this->canCheckRoute($event)) {
            return;
        }

        try {
            $currentChannel = $this->channelContext->getChannel();
            $disabledFirewall = (array) ($this->nocommerceSettings->getCurrentValue($currentChannel, null, 'disabled_firewall_contexts') ?: []);
            $firewallContextName = $this->getFirewallContextName($event->getRequest());
            if (\in_array($firewallContextName, $disabledFirewall, true)) {
                throw new NotFoundHttpException('Route not found');
            }
        } catch (ChannelNotFoundException $exception) {
            // nothing to do without channel
        }
    }

    private function canCheckRoute(RequestEvent $event): bool
    {
        // allow profiler routes
        return $event->isMainRequest()
            && !\in_array($event->getRequest()->attributes->get('_route'), self::PROFILER_ROUTES, true);
    }

    private function getFirewallContextName(Request $request): string
    {
        $firewallConfig = $this->firewallContext->getFirewallConfig($request);
        if (null === $firewallConfig) {
            return '';
        }

        return $firewallConfig->getContext() ?? $firewallConfig->getName();
    }
}
