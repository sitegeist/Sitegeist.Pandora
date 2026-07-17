<?php

declare(strict_types=1);

namespace Sitegeist\Pandora;

use Neos\Flow\Cache\CacheManager;
use Neos\Flow\Core\Booting\Sequence;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Monitor\FileMonitor;
use Neos\Flow\Package\Package as BasePackage;
use Neos\Flow\Package\PackageManager;
use Sitegeist\Pandora\Infrastructure\FileMonitorListener;
use Sitegeist\Pandora\Infrastructure\SseRequestHandler;

final class Package extends BasePackage
{
    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();

        $context = $bootstrap->getContext();
        if (!$context->isProduction()) {
            $dispatcher->connect(Sequence::class, 'afterInvokeStep', function ($step) use ($bootstrap, $dispatcher) {
                if ($step->getIdentifier() === 'neos.flow:systemfilemonitor') {
                    $classFileMonitor = FileMonitor::createFileMonitorAtBoot('Flow_PHP_Files', $bootstrap);
                    /** @var PackageManager $packageManager */
                    $packageManager = $bootstrap->getEarlyInstance(PackageManager::class);
                    foreach ($packageManager->getFlowPackages() as $packageKey => $package) {
                        if ($packageManager->isPackageFrozen($packageKey)) {
                            continue;
                        }

                        $classPaths = [
                            $package->getPackagePath() . 'Classes'
                        ];
                        foreach ($classPaths as $classPath) {
                            if (is_dir($classPath)) {
                                $classFileMonitor->monitorDirectory($classPath);
                            }
                        }
                    }

                    $classFileMonitor->detectChanges();
                    $classFileMonitor->shutdownObject();
                }

                if ($step->getIdentifier() === 'neos.flow:cachemanagement') {
                    $cacheManager = $bootstrap->getEarlyInstance(CacheManager::class);
                    $listener = new FileMonitorListener($cacheManager);
                    $dispatcher->connect(FileMonitor::class, 'filesHaveChanged', $listener, 'flushCapabilitiesCacheOnFileChanges');
                }
            });
        }

        if (PHP_SAPI === 'cli') {
            return;
        }

        $bootstrap->registerRequestHandler(new SseRequestHandler($bootstrap));
    }
}
