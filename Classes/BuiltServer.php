<?php

declare(strict_types=1);

namespace Sitegeist\Pandora;

use Mcp\Capability\RegistryInterface;
use Mcp\Server;
use Neos\Flow\Annotations as Flow;

/**
 * A built MCP server together with the registry it was built from, so callers can prune the
 * registry (e.g. to the capabilities a user is granted) before running the server.
 */
#[Flow\Proxy(false)]
final readonly class BuiltServer
{
    public function __construct(
        public Server $server,
        public RegistryInterface $registry,
    ) {
    }
}
