<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Domain;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Reflection\ReflectionService;

#[Flow\Scope("singleton")]
class AllowedHostNameProvider
{
    public function __construct(
        private readonly ReflectionService $reflectionService,
        private readonly ObjectManagerInterface $objectManager,
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
            ) as $allowedHostNameSourceName
        ) {
            /** @var AllowedHostNameSourceInterface $allowedHostNameSource */
            $allowedHostNameSource = $this->objectManager->get($allowedHostNameSourceName);
            $allowedHostNames = array_merge($allowedHostNames, $allowedHostNameSource->getHostNames());
        }

        return $allowedHostNames;
    }
}
