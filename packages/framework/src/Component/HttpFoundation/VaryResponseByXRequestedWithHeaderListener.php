<?php

namespace Shopsys\FrameworkBundle\Component\HttpFoundation;

use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * VaryResponseByXRequestedWithHeaderListener sets "Vary: X-Requested-With" header to every response
 *
 * Responses may differ in format or can contain only part of content when loaded via AJAX.
 * When available under the same URL, browsers may cache the wrong response and serve it afterwards.
 * Therefore "Vary: X-Requested-With" response header is added to every response in order to
 * notify browsers that the content may differ based on the value of "X-Requested-With" header.
 *
 * @see \Symfony\Component\HttpFoundation\Request::isXmlHttpRequest()
 */
class VaryResponseByXRequestedWithHeaderListener
{
    /**
     * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if ($event->isMainRequest()) {
            $event->getResponse()->headers->set('Vary', 'X-Requested-With');
        }
    }
}
