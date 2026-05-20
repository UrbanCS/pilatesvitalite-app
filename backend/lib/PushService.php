<?php

declare(strict_types=1);

final class PushService
{
    public function __construct(private readonly array $config, private readonly SnapshotRepository $repository)
    {
    }

    public function send(string $title, string $message, string $url, string $section): array
    {
        if (!($this->config['ENABLE_PUSH_SEND'] ?? true)) {
            return ['sent' => 0, 'failed' => 0, 'errors' => ['Push sending disabled in config.']];
        }

        if (!class_exists(\Minishlink\WebPush\WebPush::class) || !class_exists(\Minishlink\WebPush\Subscription::class)) {
            return [
                'sent' => 0,
                'failed' => 0,
                'errors' => ['Missing minishlink/web-push. Run composer install locally or on cPanel, then upload vendor/.'],
            ];
        }

        $publicKey = (string) ($this->config['VAPID_PUBLIC_KEY'] ?? '');
        $privateKey = (string) ($this->config['VAPID_PRIVATE_KEY'] ?? '');
        $subject = (string) ($this->config['VAPID_SUBJECT'] ?? 'mailto:info@pilatesvitalite.ca');

        if ($publicKey === '' || $privateKey === '') {
            return ['sent' => 0, 'failed' => 0, 'errors' => ['VAPID keys are not configured.']];
        }

        $subscriptions = $this->repository->activeSubscriptions();
        $webPush = new \Minishlink\WebPush\WebPush([
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);

        $payload = json_encode([
            'title' => $title,
            'body' => $message,
            'url' => $url,
            'section' => $section,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                \Minishlink\WebPush\Subscription::create([
                    'endpoint' => $subscription['endpoint'],
                    'publicKey' => $subscription['p256dh'],
                    'authToken' => $subscription['auth'],
                ]),
                $payload
            );
        }

        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($webPush->flush() as $report) {
            $endpoint = (string) $report->getRequest()->getUri();
            if ($report->isSuccess()) {
                $sent++;
                continue;
            }

            $failed++;
            $reason = $report->getReason();
            $errors[] = $reason;

            if ($report->isSubscriptionExpired()) {
                $this->repository->deactivateEndpoint($endpoint);
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }
}

