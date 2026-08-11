<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Infrastructure;

use Neos\Flow\Annotations as Flow;
use Sitegeist\Pandora\Domain\AllowedHostNameSourceInterface;

class ConfigurationAllowedHostNameSource implements AllowedHostNameSourceInterface
{
    /**
     * Hostnames the MCP endpoint may be addressed under, see Settings.yaml for the semantics.
     *
     * @var array<int, string>|null
     */
    #[Flow\InjectConfiguration(path: 'http.allowedHosts')]
    protected ?array $allowedHosts = null;


    /**
     * @return array<int,string>
     */
    public function getHostNames(): array
    {
        $allowedHosts = [];
        foreach ($this->allowedHosts ?? [] as $host) {
            if (is_string($host) && trim($host) !== '') {
                $allowedHosts[] = trim($host);
            }
        }

        return $allowedHosts;
    }
}
