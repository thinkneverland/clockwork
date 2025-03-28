<?php

namespace ThinkNeverland\Tapped\Tests\Unit\Services;

use ThinkNeverland\Tapped\Services\WebhookService;
use ThinkNeverland\Tapped\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class WebhookServiceTest extends TestCase
{
    protected WebhookService $webhookService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Fake HTTP requests
        Http::fake();
        
        // Configure test webhooks
        Config::set('tapped.webhooks', [
            [
                'url' => 'https://example.com/webhook1',
                'events' => ['component.updated', 'query.executed'],
                'secret' => 'webhook-secret-1',
            ],
            [
                'url' => 'https://example.com/webhook2',
                'events' => ['*'],
                'secret' => 'webhook-secret-2',
            ],
        ]);
        
        $this->webhookService = new WebhookService();
    }
    
    public function testSendNotification()
    {
        // Send a notification for a component update event
        $data = [
            'component' => 'Counter',
            'property' => 'count',
            'old_value' => 5,
            'new_value' => 6,
        ];
        
        $this->webhookService->sendNotification('component.updated', $data);
        
        // Should have sent to webhook1 (specific event match) and webhook2 (wildcard)
        Http::assertSent(function ($request) use ($data) {
            return $request->url() == 'https://example.com/webhook1' &&
                   $request->hasHeader('X-Tapped-Signature') &&
                   $request->body() === json_encode([
                       'event' => 'component.updated',
                       'timestamp' => $request->json('timestamp'),
                       'payload' => $data,
                   ]);
        });
        
        Http::assertSent(function ($request) use ($data) {
            return $request->url() == 'https://example.com/webhook2' &&
                   $request->hasHeader('X-Tapped-Signature') &&
                   $request->body() === json_encode([
                       'event' => 'component.updated',
                       'timestamp' => $request->json('timestamp'),
                       'payload' => $data,
                   ]);
        });
    }
    
    public function testSendNotificationForFilteredEvent()
    {
        // Send a notification for an event that only webhook2 subscribes to
        $data = [
            'event_name' => 'custom.event',
            'data' => 'test',
        ];
        
        $this->webhookService->sendNotification('custom.event', $data);
        
        // Should have sent to webhook2 only (wildcard match)
        Http::assertNotSent(function ($request) {
            return $request->url() == 'https://example.com/webhook1';
        });
        
        Http::assertSent(function ($request) use ($data) {
            return $request->url() == 'https://example.com/webhook2' &&
                   $request->hasHeader('X-Tapped-Signature') &&
                   $request->body() === json_encode([
                       'event' => 'custom.event',
                       'timestamp' => $request->json('timestamp'),
                       'payload' => $data,
                   ]);
        });
    }
    
    public function testGenerateSignature()
    {
        $payload = json_encode([
            'event' => 'test.event',
            'timestamp' => 1652624096,
            'payload' => ['test' => 'data'],
        ]);
        
        $secret = 'webhook-secret';
        
        $signature = $this->webhookService->generateSignature($payload, $secret);
        
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        $this->assertEquals($expectedSignature, $signature);
    }
    
    public function testVerifySignature()
    {
        $payload = json_encode([
            'event' => 'test.event',
            'timestamp' => 1652624096,
            'payload' => ['test' => 'data'],
        ]);
        
        $secret = 'webhook-secret';
        
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        
        $this->assertTrue($this->webhookService->verifySignature($payload, $signature, $secret));
        
        // Invalid signature
        $invalidSignature = 'sha256=invalid';
        $this->assertFalse($this->webhookService->verifySignature($payload, $invalidSignature, $secret));
    }
    
    public function testRegisterWebhook()
    {
        // Register a new webhook dynamically
        $webhook = [
            'url' => 'https://example.com/new-webhook',
            'events' => ['snapshot.created'],
            'secret' => 'new-secret',
        ];
        
        $result = $this->webhookService->registerWebhook($webhook);
        
        $this->assertTrue($result);
        
        // Send a notification to trigger the new webhook
        $data = ['snapshot_id' => 'snap123'];
        $this->webhookService->sendNotification('snapshot.created', $data);
        
        // Should have sent to the new webhook
        Http::assertSent(function ($request) use ($data) {
            return $request->url() == 'https://example.com/new-webhook' &&
                   $request->hasHeader('X-Tapped-Signature') &&
                   $request->json('event') == 'snapshot.created' &&
                   $request->json('payload') == $data;
        });
    }
    
    public function testUnregisterWebhook()
    {
        // Register a webhook first
        $webhook = [
            'url' => 'https://example.com/temp-webhook',
            'events' => ['test.event'],
            'secret' => 'temp-secret',
        ];
        
        $this->webhookService->registerWebhook($webhook);
        
        // Send a notification to verify it works
        $this->webhookService->sendNotification('test.event', ['test' => 'data']);
        
        Http::assertSent(function ($request) {
            return $request->url() == 'https://example.com/temp-webhook';
        });
        
        // Clear fake requests
        Http::fake();
        
        // Unregister the webhook
        $result = $this->webhookService->unregisterWebhook($webhook['url']);
        
        $this->assertTrue($result);
        
        // Send another notification - should not trigger the webhook
        $this->webhookService->sendNotification('test.event', ['test' => 'data2']);
        
        Http::assertNotSent(function ($request) {
            return $request->url() == 'https://example.com/temp-webhook';
        });
    }
}
