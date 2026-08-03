<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Instruction;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Security\Context as SecurityContext;

#[Flow\Scope('singleton')]
final class InstructionsRegistry
{
    public function __construct(
        /**
         * @phpstan-var array<string,array<string,class-string<InstructionsProvider>>>
         * indexed by role and custom name
         */
        protected array $instructionConfiguration,
        private readonly SecurityContext $securityContext,
        private readonly ObjectManagerInterface $objectManager,
    ) {
    }

    /**
     * @param array<string> $roles
     */
    public function findForRoles(array $roles): ?string
    {
        $instructions = [];
        foreach ($this->resolveInstructionProviders($roles) as $provider) {
            $instructions[] = $provider->getInstructions();
        }

        return $instructions !== [] ? implode("\n\n", $instructions) : null;
    }

    public function findForCurrentAccount(): ?string
    {
        return $this->findForRoles(array_keys($this->securityContext->getRoles() ?: []));
    }

    /**
     * @param array<string> $roles
     * @return array<string,InstructionsProvider>
     */
    private function resolveInstructionProviders(array $roles): array
    {
        $providers = [];
        foreach ($this->instructionConfiguration as $role => $providersPerRole) {
            if (!in_array($role, $roles, true)) {
                continue;
            }
            foreach ($providersPerRole as $providerFQN) {
                $provider = $this->objectManager->get($providerFQN);
                if ($provider instanceof InstructionsProvider) {
                    $providers[$providerFQN] = $provider;
                }
            }
        }

        return $providers;
    }
}
