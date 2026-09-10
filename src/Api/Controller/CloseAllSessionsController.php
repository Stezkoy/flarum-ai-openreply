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

        // Deleting runs synchronously within this admin request, so it is
        // bounded: short per-call timeouts, a time budget and a stop at the
        // first failure (see OpencodeClient::deleteSessions()). Whatever is
        // left over, the admin simply clicks the button again.
        $sessionIds = OpencodeSession::query()->pluck('session_id')->all();

        $result = $this->client->deleteSessions($sessionIds);

        OpencodeSession::query()->whereIn('session_id', $result['deleted'])->delete();

        return new JsonResponse([
            'closed' => count($result['deleted']),
            'remaining' => (int)OpencodeSession::query()->count(),
            'stoppedEarly' => $result['stoppedEarly'],
            'total' => $total,
        ]);
    }
}
