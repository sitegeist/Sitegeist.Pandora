<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Infrastructure;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cache\CacheManager;

/**
 * Listener to clear the capabilities caches if important files have changed
 *
 * It's used in the Package bootstrap as an early instance, so no full dependency injection is available.
 *
 * @Flow\Proxy(false)
 */
class FileMonitorListener
{
    public function __construct(
        private readonly CacheManager $flowCacheManager
    ) {
    }

    public function flushCapabilitiesCacheOnFileChanges($fileMonitorIdentifier, array $changedFiles)
    {
        $this->flowCacheManager->getCache('MCP_Capabilities')->flush();
    }
}
