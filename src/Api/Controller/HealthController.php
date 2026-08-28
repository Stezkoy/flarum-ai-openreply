<?php

namespace Stezkoy\FlarumAIOpenReply\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Stezkoy\FlarumAIOpenReply\OpencodeClient;

class HealthController implements MiddlewareInterface
{
    public function __construct(
        protected OpencodeClient $client
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $healthy = $this->client->health();

        return new JsonResponse([
            'healthy' => $healthy,
        ]);
    }
}
