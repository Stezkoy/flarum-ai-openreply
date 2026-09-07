<?php

namespace Stezkoy\FlarumAIOpenReply\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Stezkoy\FlarumAIOpenReply\OpencodeClient;

class ModelsController implements RequestHandlerInterface
{
    public function __construct(
        protected OpencodeClient $client
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $models = $this->client->models();

        if ($models === null) {
            return new JsonResponse([
                'models' => [],
                'reachable' => false,
                'default' => null,
            ]);
        }

        return new JsonResponse($models);
    }
}