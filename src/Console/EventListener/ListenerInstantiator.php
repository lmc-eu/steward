<?php

namespace Lmc\Steward\Console\EventListener;

use Nette\Reflection\AnnotationsParser;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Search and instantiate event-listeners for the commands
 */
class ListenerInstantiator
{
    /** @var string Searched pattern for listeners, used to exclude all other paths and speed up the search */
    protected $searchPathPattern = 'src/Console/EventListener';

    /**
     * Instantiate listeners in given directory and register them to given dispatcher
     * @param EventDispatcher $dispatcher
     * @param string $dir Directory to search in for listeners
     */
    public function instantiate(EventDispatcher $dispatcher, $dir)
    {
        $listeners = $this->searchListeners($dir);

        foreach ($listeners as $listener) {
            $r = new \ReflectionClass($listener);
            if ($r->implementsInterface('Symfony\\Component\\EventDispatcher\\EventSubscriberInterface')
                && !$r->isAbstract()
            ) {
                /** @var EventSubscriberInterface $listenerInstance */
                $listenerInstance = $r->newInstanceWithoutConstructor();
                $dispatcher->addSubscriber($listenerInstance);
            }
        }
    }

    /**
     * @param string $dir Directory to search in
     * @return array Array of listener class names
     */
    protected function searchListeners($dir)
    {
        $files = (new Finder())
            ->files()
            ->in($dir)
            ->path($this->searchPathPattern)
            ->name('*Listener.php');

        $listeners = [];
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $listeners[] = key(AnnotationsParser::parsePhp(\file_get_contents($file->getRealPath())));
        }

        return $listeners;
    }

    /**
     * Set searched pattern path for event listeners.
     *
     * @param string $searchPathPattern
     * @internal Should be only overridden in testing.
     */
    public function setSearchPathPattern($searchPathPattern)
    {
        $this->searchPathPattern = $searchPathPattern;
    }
}
