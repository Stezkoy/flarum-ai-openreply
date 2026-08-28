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
            $providers = $this->client->providers();

            if (is_array($providers)) {
                $result['connectedProviders'] = $providers['connected'] ?? [];
                $result['serverDefault'] = $providers['default'] ?? [];
                $result['serverDefaultModel'] = $this->resolveServerDefault($providers['default'] ?? []);
            }
        }

        return new JsonResponse($result);
    }

    private function resolveServerDefault(array $default): ?array
    {
        foreach ($default as $provider => $modelID) {
            if (is_string($modelID) && is_string($provider) && $modelID !== '') {
                return ['provider' => $provider, 'model' => $modelID];
            }
        }

        return null;
    }
}
