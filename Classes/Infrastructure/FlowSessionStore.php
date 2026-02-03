<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Infrastructure;

use Mcp\Server\Session\SessionStoreInterface;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Session\Session;
use Neos\Flow\Utility\Algorithms;
use Symfony\Component\Uid\Uuid;

#[Flow\Scope('singleton')]
final class FlowSessionStore implements SessionStoreInterface
{
    private const DATA_KEY = 'mcp.session.data';

    #[Flow\InjectConfiguration(path: 'session.inactivityTimeout')]
    protected int $inactivityTimeout = 0;

    /**
     * @Flow\Inject
     * @var VariableFrontend
     */
    protected $metaDataCache;

    /**
     * @Flow\Inject
     * @var VariableFrontend
     */
    protected $storageCache;

    public function exists(Uuid $id): bool
    {
        $sessionIdentifier = $id->toRfc4122();
        $sessionInfo = $this->metaDataCache->get($sessionIdentifier);
        if (!is_array($sessionInfo)) {
            return false;
        }

        if ($this->isExpired($sessionInfo)) {
            $this->destroy($id);
            return false;
        }

        return true;
    }

    public function read(Uuid $id): string|false
    {
        $sessionInfo = $this->getSessionInfo($id);
        if ($sessionInfo === null) {
            return false;
        }

        $storageIdentifier = $sessionInfo['storageIdentifier'] ?? null;
        if (!is_string($storageIdentifier) || $storageIdentifier === '') {
            return false;
        }

        $data = $this->storageCache->get($storageIdentifier . md5(self::DATA_KEY));
        if ($data === false) {
            return false;
        }

        $this->touch($id, $sessionInfo);

        return (string)$data;
    }

    public function write(Uuid $id, string $data): bool
    {
        $sessionIdentifier = $id->toRfc4122();
        $sessionInfo = $this->metaDataCache->get($sessionIdentifier);

        if (!is_array($sessionInfo)) {
            $sessionInfo = [
                'lastActivityTimestamp' => time(),
                'storageIdentifier' => Algorithms::generateUUID(),
                'tags' => ['mcp'],
            ];
        } else {
            $sessionInfo['lastActivityTimestamp'] = time();
            if (!isset($sessionInfo['storageIdentifier']) || !is_string($sessionInfo['storageIdentifier'])) {
                $sessionInfo['storageIdentifier'] = Algorithms::generateUUID();
            }
            if (!isset($sessionInfo['tags']) || !is_array($sessionInfo['tags'])) {
                $sessionInfo['tags'] = ['mcp'];
            }
        }

        $this->metaDataCache->set(
            $sessionIdentifier,
            $sessionInfo,
            $this->buildMetaTags($sessionIdentifier, $sessionInfo['tags'])
        );

        $storageIdentifier = $sessionInfo['storageIdentifier'];
        $this->storageCache->set(
            $storageIdentifier . md5(self::DATA_KEY),
            $data,
            [$storageIdentifier],
            0
        );

        return true;
    }

    public function destroy(Uuid $id): bool
    {
        $sessionIdentifier = $id->toRfc4122();
        $sessionInfo = $this->metaDataCache->get($sessionIdentifier);

        if (is_array($sessionInfo) && isset($sessionInfo['storageIdentifier'])) {
            $this->storageCache->flushByTag((string)$sessionInfo['storageIdentifier']);
        }

        $this->metaDataCache->remove($sessionIdentifier);

        return true;
    }

    public function gc(): array
    {
        $deleted = [];
        try {
            foreach ($this->metaDataCache->getIterator() as $sessionIdentifier => $sessionInfo) {
                if (!is_array($sessionInfo)) {
                    continue;
                }
                if ($this->isExpired($sessionInfo)) {
                    $uuid = Uuid::fromString((string)$sessionIdentifier);
                    $this->destroy($uuid);
                    $deleted[] = $uuid;
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return $deleted;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function getSessionInfo(Uuid $id): ?array
    {
        $sessionIdentifier = $id->toRfc4122();
        $sessionInfo = $this->metaDataCache->get($sessionIdentifier);
        if (!is_array($sessionInfo)) {
            return null;
        }

        if ($this->isExpired($sessionInfo)) {
            $this->destroy($id);
            return null;
        }

        return $sessionInfo;
    }

    /**
     * @param array<string,mixed> $sessionInfo
     */
    private function touch(Uuid $id, array $sessionInfo): void
    {
        $sessionIdentifier = $id->toRfc4122();
        $sessionInfo['lastActivityTimestamp'] = time();
        $tags = isset($sessionInfo['tags']) && is_array($sessionInfo['tags']) ? $sessionInfo['tags'] : [];

        $this->metaDataCache->set(
            $sessionIdentifier,
            $sessionInfo,
            $this->buildMetaTags($sessionIdentifier, $tags)
        );
    }

    /**
     * @param array<string,mixed> $sessionInfo
     */
    private function isExpired(array $sessionInfo): bool
    {
        if ($this->inactivityTimeout === 0) {
            return false;
        }

        $lastActivity = $sessionInfo['lastActivityTimestamp'] ?? 0;
        if (!is_int($lastActivity)) {
            return false;
        }

        return (time() - $lastActivity) > $this->inactivityTimeout;
    }

    /**
     * @param array<int, string> $tags
     * @return array<int, string>
     */
    private function buildMetaTags(string $sessionIdentifier, array $tags): array
    {
        $tagsForCacheEntry = [];
        foreach ($tags as $tag) {
            $tagsForCacheEntry[] = Session::TAG_PREFIX . $tag;
        }
        $tagsForCacheEntry[] = $sessionIdentifier;
        $tagsForCacheEntry[] = 'session';

        return $tagsForCacheEntry;
    }
}
