<?php

namespace Stezkoy\FlarumAIOpenReply\Listeners;

use Flarum\Discussion\Event\Deleted;
use Stezkoy\FlarumAIOpenReply\OpencodeClient;
use Stezkoy\FlarumAIOpenReply\OpencodeSession;
use Psr\Log\LoggerInterface;

class CleanupSessionOnDiscussionDeleted
{
    public function __construct(
        protected OpencodeClient $client,
        protected LoggerInterface $logger,
    ) {
    }

    public function handle(Deleted $event): void
    {
        $sessions = OpencodeSession::query()
            ->where('discussion_id', $event->discussion->id)
            ->get();

        // No local record -> nothing to clean up, and no request is made to the server.
        if ($sessions->isEmpty())
            return;

        foreach ($sessions as $session)
        {
            try {
                $this->client->deleteSession($session->session_id);
            } catch (\Throwable $e) {
                $this->logger->error('[AI Open-Reply] Error closing session on discussion delete: ' . $e->getMessage());
            }

            $session->delete();
        }
    }
}
