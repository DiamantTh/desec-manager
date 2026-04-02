<?php

declare(strict_types=1);

namespace App\Handler;

use App\Repository\ApiKeyRepository;
use App\Service\ThemeManager;
use App\Service\AuthorizationService;
use App\Session\SessionContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class KeyHandler extends AbstractHandler implements RequestHandlerInterface
{
    public function __construct(
        ThemeManager $theme,
        SessionContext $sessionContext,
        AuthorizationService $authz,
        private readonly ApiKeyRepository $apiKeys,
    ) {
        parent::__construct($theme, $sessionContext, $authz);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userId  = $this->userId();
        $message = null;
        $messageType = 'is-success';

        if ($request->getMethod() === 'POST') {
            if ($csrfError = $this->validateCsrf($request)) {
                return $csrfError;
            }

            $body   = $request->getParsedBody();
            $action = $this->bodyString($body, 'action');

            try {
                if ($action === 'create') {
                    $name   = $this->bodyString($body, 'name');
                    $apiKey = $this->bodyString($body, 'api_key');

                    if ($name === '' || $apiKey === '') {
                        throw new \InvalidArgumentException(__('Name and API key are required.'));
                    }

                    $this->apiKeys->create([
                        'user_id' => $userId,
                        'name'    => $name,
                        'api_key' => $apiKey,
                    ]);
                    $message = 'API-Key wurde gespeichert.';

                } elseif ($action === 'deactivate') {
                    $keyId = $this->bodyInt($body, 'key_id');
                    if ($keyId === 0) {
                        throw new \InvalidArgumentException('API-Key nicht gefunden.');
                    }
                    $this->apiKeys->deactivate($keyId, $userId);
                    $message = 'API-Key wurde deaktiviert.';
                }
            } catch (\Throwable $e) {
                $message     = $e->getMessage();
                $messageType = 'is-danger';
            }
        }

        return $this->render('keys/index', [
            'apiKeys'     => $this->apiKeys->findByUserId($userId),
            'csrfToken'   => $this->generateCsrfToken($request),
            'message'     => $message,
            'messageType' => $messageType,
        ], $request);
    }
}
