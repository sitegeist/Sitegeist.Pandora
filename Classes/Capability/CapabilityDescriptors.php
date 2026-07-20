<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Capability;

use Mcp\Capability\RegistryInterface;
use Neos\Flow\Annotations as Flow;

/**
 * An immutable, iterable collection of {@see CapabilityDescriptor} value objects.
 *
 * @implements \IteratorAggregate<int, CapabilityDescriptor>
 */
#[Flow\Proxy(false)]
final readonly class CapabilityDescriptors implements \IteratorAggregate, \Countable
{
    /**
     * @var list<CapabilityDescriptor>
     */
    private array $items;

    public function __construct(CapabilityDescriptor ...$items)
    {
        $this->items = array_values($items);
    }

    /**
     * Reads the capabilities present in an already loaded MCP registry.
     */
    public static function createFromRegistry(RegistryInterface $registry): self
    {
        $items = [];
        foreach ($registry->getTools()->references as $tool) {
            $items[] = new CapabilityDescriptor(CapabilityType::Tool, $tool->name, $tool->name);
        }
        foreach ($registry->getResources()->references as $resource) {
            $items[] = new CapabilityDescriptor(CapabilityType::Resource, $resource->name, $resource->uri);
        }
        foreach ($registry->getResourceTemplates()->references as $resourceTemplate) {
            $items[] = new CapabilityDescriptor(CapabilityType::ResourceTemplate, $resourceTemplate->name, $resourceTemplate->uriTemplate);
        }
        foreach ($registry->getPrompts()->references as $prompt) {
            $items[] = new CapabilityDescriptor(CapabilityType::Prompt, $prompt->name, $prompt->name);
        }

        return new self(...$items);
    }

    /**
     * @param callable(CapabilityDescriptor): bool $predicate
     */
    public function filter(callable $predicate): self
    {
        return new self(...array_filter($this->items, $predicate));
    }

    /**
     * The canonical matcher string of each capability, e.g. ["tool:find-children", ...].
     *
     * @return list<string>
     */
    public function getMatchers(): array
    {
        return array_map(static fn (CapabilityDescriptor $descriptor): string => $descriptor->getMatcher(), $this->items);
    }

    /**
     * Removes these capabilities from the given MCP registry. The inverse of {@see self::createFromRegistry()},
     * used to prune a built server's registry down to an allowed subset.
     */
    public function unregisterFrom(RegistryInterface $registry): void
    {
        foreach ($this->items as $descriptor) {
            match ($descriptor->type) {
                CapabilityType::Tool => $registry->unregisterTool($descriptor->registryKey),
                CapabilityType::Resource => $registry->unregisterResource($descriptor->registryKey),
                CapabilityType::ResourceTemplate => $registry->unregisterResourceTemplate($descriptor->registryKey),
                CapabilityType::Prompt => $registry->unregisterPrompt($descriptor->registryKey),
            };
        }
    }

    public function getIterator(): \Traversable
    {
        yield from $this->items;
    }

    public function count(): int
    {
        return \count($this->items);
    }
}
