<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Domain;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Reflection\ReflectionService;

#[Flow\Scope("singleton")]
class AllowedHostNameProvider
{
    public function __construct(
        private readonly ReflectionService $reflectionService,
    ) {
    }

    /**
     * @return array<int,string>
     */
    public function resolveAllowedHostNames(): array
    {
        $allowedHostNames = [];
        foreach (
            $this->reflectionService->getAllImplementationClassNamesForInterface(
                AllowedHostNameSourceInterface::class
            ) as $allowedHostNameSource
        ) {
            /** @var AllowedHostNameSourceInterface $allowedHostNameSource */
            $allowedHostNames = array_merge($allowedHostNames, $allowedHostNameSource->getHostNames());
        }

        return $allowedHostNames;
    }
}
