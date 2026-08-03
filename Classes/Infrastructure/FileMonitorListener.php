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

    /**
     * @param array<string,mixed> $changedFiles
     */
    public function flushCapabilitiesCacheOnFileChanges(string $fileMonitorIdentifier, array $changedFiles): void
    {
        $this->flowCacheManager->getCache('MCP_Capabilities')->flush();
    }
}
