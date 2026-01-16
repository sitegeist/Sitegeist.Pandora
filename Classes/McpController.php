<?php

declare(strict_types=1);

namespace Sitegeist\Pandora;

use Mcp\Server;
use Mcp\Server\Transport\StreamableHttpTransport;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\ActionResponse;
use Neos\Flow\Mvc\Controller\ControllerInterface;
use Neos\Http\Factories\ResponseFactory;
use Neos\Http\Factories\StreamFactory;
use Psr\Log\LoggerInterface;

abstract class McpController implements ControllerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    final public function processRequest(ActionRequest $request, ActionResponse $response): void
    {
        $transport = new StreamableHttpTransport(
            request: $request->getHttpRequest(),
            responseFactory: new ResponseFactory(),
            streamFactory: new StreamFactory(),
            logger: $this->logger,
        );

        $response->setContent(
            $this->getServer()->run($transport),
        );
    }

    /**
     * @see Server::builder()
     */
    abstract protected function getServer(): Server;
}
