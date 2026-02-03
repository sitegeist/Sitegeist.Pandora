<?php

declare(strict_types=1);

namespace Sitegeist\Pandora;

use Mcp\Server\Session\SessionStoreInterface;
use Mcp\Server;
use Mcp\Server\Builder as ServerBuilder;
use Mcp\Server\Transport\StreamableHttpTransport;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\ActionResponse;
use Neos\Flow\Mvc\Controller\ControllerInterface;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Http\Factories\ResponseFactory;
use Neos\Http\Factories\StreamFactory;
use Psr\Log\LoggerInterface;

abstract class McpController implements ControllerInterface
{
    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly SessionStoreInterface $sessionStore,
        protected readonly ObjectManagerInterface $objectManager,
    ) {
    }

    /**
     * @internal
     * do not override; cannot be final because of AOP
     */
    public function processRequest(ActionRequest $request, ActionResponse $response): void
    {
        if ($request->getHttpRequest()->getBody()->isSeekable()) {
            $request->getHttpRequest()->getBody()->rewind();
        }
        $transport = new StreamableHttpTransport(
            request: $request->getHttpRequest(),
            responseFactory: new ResponseFactory(),
            streamFactory: new StreamFactory(),
            logger: $this->logger,
        );

        $serverBuilder = Server::builder()
            ->setContainer($this->objectManager)
            ->setLogger($this->logger)
            ->setSession($this->sessionStore);
        $this->populateServer($serverBuilder);
        $result = $serverBuilder->build()->run($transport);
        $response->replaceHttpResponse($result);
        $request->setDispatched(true);
    }

    abstract protected function populateServer(ServerBuilder $serverBuilder): void;

    /**
     * @template T of object
     * @param class-string<T> $fqn
     * @return T
     */
    public function getObject(string $fqn): object
    {
        return $this->objectManager->get($fqn);
    }
}
