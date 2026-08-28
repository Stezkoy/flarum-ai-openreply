<?php

namespace Stezkoy\FlarumAIOpenReply\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Stezkoy\FlarumAIOpenReply\OpencodeClient;
use Stezkoy\FlarumAIOpenReply\OpencodeSession;

class CloseAllSessionsController implements MiddlewareInterface
{
    public function __construct(
        protected OpencodeClient $client
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $closed = 0;

        foreach (OpencodeSession::query()->get() as $session) {
            $this->client->deleteSession($session->session_id);
            $session->delete();
            $closed++;
        }

        return new JsonResponse([
            'closed' => $closed,
        ]);
    }
}
