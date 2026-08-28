<?php

namespace Stezkoy\FlarumAIOpenReply\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Stezkoy\FlarumAIOpenReply\OpencodeClient;
use Stezkoy\FlarumAIOpenReply\OpencodeSession;

class CloseAllSessionsController implements RequestHandlerInterface
{
    public function __construct(
        protected OpencodeClient $client
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $total = $this->client->sessionCount();

        $closed = 0;

        foreach (OpencodeSession::query()->get() as $session) {
            $this->client->deleteSession($session->session_id);
            $session->delete();
            $closed++;
        }

        return new JsonResponse([
            'closed' => $closed,
            'total' => $total,
        ]);
    }
}
