<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Domain;

interface AllowedHostNameSourceInterface
{
    /**
     * @return array<int,string>
     */
    public function getHostNames(): array;
}
