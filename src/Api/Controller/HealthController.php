<?php

namespace Stezkoy\FlarumAIOpenReply\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Stezkoy\FlarumAIOpenReply\OpencodeClient;

class HealthController implements RequestHandlerInterface
{
    public function __construct(
        protected OpencodeClient $client
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $healthy = $this->client->health();

        $result = [
            'healthy' => $healthy,
            'model' => $this->client->configuredModel(),
        ];

        if ($healthy) {
            $result['serverDefaultModel'] = $this->client->serverDefaultModel();
        }

        return new JsonResponse($result);
    }
}
