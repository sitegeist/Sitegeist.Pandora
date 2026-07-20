<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Security;

use Neos\Flow\Security\Authorization\Privilege\AbstractPrivilege;
use Neos\Flow\Security\Authorization\Privilege\PrivilegeSubjectInterface;
use Neos\Flow\Security\Exception\InvalidPrivilegeTypeException;

/**
 * A privilege covering access to a single MCP capability, matched by its capability identifier
 * ("<type>:<name>", e.g. "tool:find-children").
 */
class McpCapabilityPrivilege extends AbstractPrivilege
{
    public function matchesSubject(PrivilegeSubjectInterface $subject): bool
    {
        if (!$subject instanceof McpCapabilityPrivilegeSubject) {
            throw new InvalidPrivilegeTypeException(sprintf(
                'Privileges of type "%s" only support subjects of type "%s", but we got a subject of type: "%s".',
                self::class,
                McpCapabilityPrivilegeSubject::class,
                get_class($subject)
            ), 1721400000);
        }

        return $subject->getCapabilityIdentifier() === $this->getParsedMatcher();
    }
}
