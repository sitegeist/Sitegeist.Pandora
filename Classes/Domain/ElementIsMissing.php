<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Domain;

final class ElementIsMissing extends \Exception
{
    public static function becauseMethodDoesNotExist(string $className, string $methodName): self
    {
        return new self(
            'Tool ' . $className . '::' . $methodName . ' cannot be used since that method does not exist',
            1770276283
        );
    }

    public static function becauseMethodIsNotAttributed(string $className, string $methodName): self
    {
        return new self(
            'Tool ' . $className . '::' . $methodName . ' cannot be used since it has no MCP element attribute',
            1770276720
        );
    }
}
