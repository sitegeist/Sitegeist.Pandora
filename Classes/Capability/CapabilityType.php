<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Capability;

/**
 * The kinds of MCP capability that can be discovered and exposed by a server. The string values
 * are used as the type segment of a capability's matcher and privilege target identifier.
 */
enum CapabilityType: string
{
    case Tool = 'tool';
    case Resource = 'resource';
    case ResourceTemplate = 'resourceTemplate';
    case Prompt = 'prompt';

    /**
     * Capitalized segment used within a privilege target identifier,
     * e.g. "Tool" in "Sitegeist.Pandora:Capability.Tool.find-children".
     */
    public function getIdentifierSegment(): string
    {
        return ucfirst($this->value);
    }
}
