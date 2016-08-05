<?php

namespace Lmc\Steward\Console\EventListener\Fixtures;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DummyListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'foo' => 'onFoo',
        ];
    }

    public function onFoo()
    {
        return 'Bar';
    }
}
