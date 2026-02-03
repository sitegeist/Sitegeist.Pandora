<?php

declare(strict_types=1);

namespace Sitegeist\Pandora;

use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package\Package as BasePackage;
use Sitegeist\Pandora\Infrastructure\SseRequestHandler;

final class Package extends BasePackage
{
    public function boot(Bootstrap $bootstrap)
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $bootstrap->registerRequestHandler(new SseRequestHandler($bootstrap));
    }
}
