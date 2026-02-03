<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Infrastructure;

use GuzzleHttp\Psr7\ServerRequest;
use Neos\Flow\Http\Helper\ResponseInformationHelper;
use Neos\Flow\Http\RequestHandler;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class SseRequestHandler extends RequestHandler
{
    public function canHandleRequest()
    {
        return PHP_SAPI !== 'cli'
            && $this->isEventStreamRequest(ServerRequest::fromGlobals());
    }

    public function getPriority()
    {
        return 200;
    }

    protected function sendResponse(ResponseInterface $response): void
    {
        ob_implicit_flush();
        foreach (ResponseInformationHelper::prepareHeaders($response) as $prepareHeader) {
            header($prepareHeader, false);
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        if ($this->isEventStreamResponse($response)) {
            $body = $response->getBody();
            while (!$body->eof()) {
                echo $body->read(8192);
                @ob_flush();
                flush();
            }

            return;
        }

        $body = $response->getBody()->detach() ?: $response->getBody()->getContents();
        if (is_resource($body)) {
            fpassthru($body);
            fclose($body);
        } else {
            echo $body;
        }
    }

    private function isEventStreamRequest(RequestInterface $request): bool
    {
        return \str_contains(
            \strtolower($request->getHeaderLine('Accept')),
            'text/event-stream'
        );
    }

    private function isEventStreamResponse(ResponseInterface $response): bool
    {
        return \str_contains(
            \strtolower($response->getHeaderLine('Content-Type')),
            'text/event-stream'
        );
    }
}
