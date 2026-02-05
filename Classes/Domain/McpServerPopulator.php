<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Domain;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Server\Builder as ServerBuilder;

final class McpServerPopulator
{
    /**
     * @template T of object
     * @param class-string<T> $className
     * @throws ElementIsMissing
     */
    public static function addElementFromReflection(
        ServerBuilder $serverBuilder,
        string $className,
        string $methodName,
    ): void {
        $classReflector = new \ReflectionClass($className);
        try {
            $methodReflector = $classReflector->getMethod($methodName);
        } catch (\ReflectionException $e) {
            throw ElementIsMissing::becauseMethodDoesNotExist($className, $methodName);
        }

        foreach ($methodReflector->getAttributes() as $attribute) {
            $attributeInstance = $attribute->newInstance();
            $builder = match (get_class($attributeInstance)) {
                McpPrompt::class => $serverBuilder->addPrompt(
                    handler: [$className, $methodName],
                    name: $attributeInstance->name,
                    description: $attributeInstance->description,
                    icons: $attributeInstance->icons,
                    meta: $attributeInstance->meta,
                ),
                McpResource::class => $serverBuilder->addResource(
                    handler: [$className, $methodName],
                    uri: $attributeInstance->uri,
                    name: $attributeInstance->name,
                    description: $attributeInstance->description,
                    mimeType: $attributeInstance->mimeType,
                    size: $attributeInstance->size,
                    annotations: $attributeInstance->annotations,
                    icons: $attributeInstance->icons,
                    meta: $attributeInstance->meta,
                ),
                McpResourceTemplate::class => $serverBuilder->addResourceTemplate(
                    handler: [$className, $methodName],
                    uriTemplate: $attributeInstance->uriTemplate,
                    name: $attributeInstance->name,
                    description: $attributeInstance->description,
                    mimeType: $attributeInstance->mimeType,
                    annotations: $attributeInstance->annotations,
                    meta: $attributeInstance->meta,
                ),
                McpTool::class => $serverBuilder->addTool(
                    handler: [$className, $methodName],
                    name: $attributeInstance->name,
                    description: $attributeInstance->description,
                    annotations: $attributeInstance->annotations,
                    icons: $attributeInstance->icons,
                    meta: $attributeInstance->meta,
                    outputSchema: $attributeInstance->outputSchema
                ),
                default => null,
            };
            if ($builder !== null) {
                return;
            }
        }

        throw ElementIsMissing::becauseMethodIsNotAttributed($className, $methodName);
    }
}
