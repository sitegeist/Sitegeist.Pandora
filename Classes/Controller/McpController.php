<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Controller;

use Mcp\Server\Session\SessionStoreInterface;
use Mcp\Server\Transport\StreamableHttpTransport;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\ActionResponse;
use Neos\Flow\Mvc\Controller\ControllerInterface;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Http\Factories\ResponseFactory;
use Neos\Http\Factories\StreamFactory;
use Psr\Log\LoggerInterface;
use Sitegeist\Pandora\McpServerBuilderFactory;

class McpController implements ControllerInterface
{
    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly SessionStoreInterface $sessionStore,
        protected readonly ObjectManagerInterface $objectManager,
        protected readonly McpServerBuilderFactory $serverBuilderFactory,
    ) {
    }

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

        $builtServer = $this->serverBuilderFactory->buildServer();

        $result = $builtServer->server->run($transport);
        $response->replaceHttpResponse($result);
        $request->setDispatched(true);
    }
}
