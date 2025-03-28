<?php

namespace ThinkNeverland\Tapped\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * The configured webhooks.
     */
    protected $webhooks;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->webhooks = config('tapped.webhooks', []);
    }

    /**
     * Send a notification to all registered webhooks.
     *
     * @param string $event Event type
     * @param array $payload Event data
     * @return array Results of webhook delivery attempts
     */
    public function notify(string $event, array $payload): array
    {
        $results = [];
        
        foreach ($this->webhooks as $webhook) {
            if (!$this->shouldSendToWebhook($webhook, $event)) {
                continue;
            }
            
            $result = $this->sendToWebhook($webhook, $event, $payload);
            $results[] = $result;
        }
        
        return $results;
    }

    /**
     * Determine if a webhook should receive a specific event.
     *
     * @param array $webhook Webhook configuration
     * @param string $event Event type
     * @return bool
     */
    protected function shouldSendToWebhook(array $webhook, string $event): bool
    {
        if (!isset($webhook['url']) || !filter_var($webhook['url'], FILTER_VALIDATE_URL)) {
            return false;
        }
        
        if (isset($webhook['events']) && is_array($webhook['events'])) {
            return in_array($event, $webhook['events']) || in_array('*', $webhook['events']);
        }
        
        // By default, send all events
        return true;
    }

    /**
     * Send a payload to a webhook.
     *
     * @param array $webhook Webhook configuration
     * @param string $event Event type
     * @param array $payload Event data
     * @return array Result of the delivery attempt
     */
    protected function sendToWebhook(array $webhook, string $event, array $payload): array
    {
        $url = $webhook['url'];
        $secret = $webhook['secret'] ?? null;
        
        $data = [
            'event' => $event,
            'timestamp' => time(),
            'payload' => $payload,
        ];
        
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'Tapped-Webhook-Notifier/1.0',
            'X-Tapped-Event' => $event,
            'X-Tapped-Delivery' => uniqid('tapped_'),
        ];
        
        if ($secret) {
            $signature = $this->generateSignature($data, $secret);
            $headers['X-Tapped-Signature'] = $signature;
        }
        
        try {
            $response = Http::withHeaders($headers)
                ->timeout(5)
                ->post($url, $data);
            
            return [
                'webhook' => $url,
                'event' => $event,
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'response' => $response->successful() ? $response->json() : $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Webhook delivery failed', [
                'url' => $url,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'webhook' => $url,
                'event' => $event,
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate a signature for webhook payload verification.
     *
     * @param array $data Payload data
     * @param string $secret Webhook secret
     * @return string HMAC signature
     */
    protected function generateSignature(array $data, string $secret): string
    {
        $payload = json_encode($data);
        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Register a new webhook.
     *
     * @param string $url Webhook URL
     * @param array $events Events to listen for
     * @param string|null $secret Secret for signature verification
     * @return bool Success
     */
    public function registerWebhook(string $url, array $events = ['*'], string $secret = null): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        $webhook = [
            'url' => $url,
            'events' => $events,
        ];
        
        if ($secret) {
            $webhook['secret'] = $secret;
        }
        
        // Add to configuration
        $webhooks = config('tapped.webhooks', []);
        $webhooks[] = $webhook;
        
        // Update config (for runtime only)
        config(['tapped.webhooks' => $webhooks]);
        
        // In a real application, you would likely want to persist this
        // configuration to a database or config file
        
        return true;
    }

    /**
     * Test a webhook with a ping event.
     *
     * @param string $url Webhook URL
     * @param string|null $secret Secret for signature verification
     * @return array Result of the test
     */
    public function testWebhook(string $url, string $secret = null): array
    {
        $webhook = [
            'url' => $url,
        ];
        
        if ($secret) {
            $webhook['secret'] = $secret;
        }
        
        return $this->sendToWebhook($webhook, 'ping', [
            'message' => 'This is a test ping from Tapped',
            'time' => time(),
        ]);
    }

    /**
     * Get all registered webhooks.
     *
     * @return array
     */
    public function getWebhooks(): array
    {
        return $this->webhooks;
    }
}
