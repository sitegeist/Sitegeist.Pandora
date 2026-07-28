<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Controller;

use Mcp\Server\Session\SessionStoreInterface;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\Http\Middleware\ProtocolVersionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\ActionResponse;
use Neos\Flow\Mvc\Controller\ControllerInterface;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Http\Factories\ResponseFactory;
use Neos\Http\Factories\StreamFactory;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Sitegeist\Pandora\McpServerBuilderFactory;

class McpController implements ControllerInterface
{
    /**
     * Hostnames the MCP endpoint may be addressed under, see Settings.yaml for the semantics.
     *
     * @var array<int, string>|null
     */
    #[Flow\InjectConfiguration(path: 'http.allowedHosts')]
    protected ?array $allowedHosts = null;

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
            middleware: $this->buildMiddleware(),
        );

        $builtServer = $this->serverBuilderFactory->buildServer();

        $result = $builtServer->server->run($transport);
        $response->replaceHttpResponse($result);
        $request->setDispatched(true);
    }

    /**
     * The SDK's default middleware stack, but with the DNS rebinding protection bound to the
     * configured hostnames instead of the middleware's own localhost-only default - under which every
     * request to a publicly reachable deployment is answered with "403 Forbidden: Invalid Host header."
     *
     * The list must be passed explicitly rather than left to StreamableHttpTransport: omitting the
     * argument installs the default stack (localhost only), and passing an EMPTY list to the transport
     * disables CORS and protocol version validation along with it. Dropping the host check therefore
     * means leaving this one middleware out, not handing the transport an empty stack.
     *
     * @return array<int, MiddlewareInterface>
     */
    private function buildMiddleware(): array
    {
        $allowedHosts = [];
        foreach ($this->allowedHosts ?? [] as $host) {
            if (is_string($host) && trim($host) !== '') {
                $allowedHosts[] = trim($host);
            }
        }

        $middleware = [new CorsMiddleware()];
        if ($allowedHosts !== []) {
            $middleware[] = new DnsRebindingProtectionMiddleware($allowedHosts);
        }
        $middleware[] = new ProtocolVersionMiddleware();

        return $middleware;
    }
}
