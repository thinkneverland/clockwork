# Tapped AI IDE Integration Guide

This guide explains how to integrate Tapped with AI-powered IDE tools and assistants.

## Table of Contents

1. [Introduction](#introduction)
2. [API Overview](#api-overview)
3. [Integration Examples](#integration-examples)
4. [Authentication](#authentication)
5. [Debug State Serialization](#debug-state-serialization)
6. [Screenshot and Recording](#screenshot-and-recording)
7. [GraphQL Integration](#graphql-integration)
8. [Webhook Integration](#webhook-integration)
9. [AI Integration Examples](#ai-integration-examples)

## Introduction

Tapped provides a rich set of APIs that allow AI tools, IDE plugins, and assistants to access real-time debug data from Laravel Livewire applications. This enables powerful debugging experiences, including:

- Real-time component state inspection
- Code suggestions based on debugging data
- Automated optimization recommendations
- Intelligent N+1 query detection and fixes
- Visual debugging with screenshots and recordings
- Time-travel debugging with snapshots

## API Overview

Tapped provides both RESTful and GraphQL APIs for accessing debug data:

### RESTful API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/tapped/api/status` | GET | Check API status and connection |
| `/tapped/api/debug-data` | GET | Get all debug data (Livewire, queries, events, requests) |
| `/tapped/api/debug-data/livewire` | GET | Get Livewire component data |
| `/tapped/api/debug-data/queries` | GET | Get database query data |
| `/tapped/api/debug-data/events` | GET | Get event data |
| `/tapped/api/debug-data/requests` | GET | Get HTTP request data |
| `/tapped/api/debug-data/snapshots` | GET | Get list of debug snapshots |
| `/tapped/api/debug-data/snapshots` | POST | Create a new debug snapshot |
| `/tapped/api/debug-data/snapshots/{id}` | GET | Get a specific snapshot |
| `/tapped/api/debug-data/snapshots/{id}` | DELETE | Delete a snapshot |
| `/tapped/api/screenshot/capture` | POST | Capture a screenshot |
| `/tapped/api/screenshot` | GET | Get list of screenshots |
| `/tapped/api/screenshot/recording/start` | POST | Start a recording session |
| `/tapped/api/screenshot/recording/{id}/complete` | POST | Complete a recording session |
| `/tapped/api/screenshot/recordings` | GET | Get list of recordings |

### GraphQL API

Tapped provides a GraphQL API at `/tapped/graphql` that allows for complex queries and fine-grained data access.

## Integration Examples

This repository includes several example integrations:

- **VS Code Extension**: A VS Code extension for debugging Laravel Livewire applications
- **JetBrains Plugin**: A plugin for PhpStorm and other JetBrains IDEs
- **CLI Tool**: A command-line interface for Tapped
- **REST Client**: A JavaScript client for the Tapped API

## Authentication

API requests can be authenticated using bearer tokens:

```http
GET /tapped/api/debug-data
Authorization: Bearer your-auth-token
```

Configure authentication in the Tapped settings file:

```php
// config/tapped.php
return [
    'api' => [
        'auth' => [
            'enabled' => true,
            'token' => env('TAPPED_API_TOKEN'),
        ],
    ],
];
```

## Debug State Serialization

Tapped provides state serialization in both JSON and binary formats:

```http
GET /tapped/api/debug-data?format=json
GET /tapped/api/debug-data?format=binary
```

The binary format is more efficient for large debug states and includes a schema version header for backward compatibility.

## Screenshot and Recording

Tapped can capture screenshots and screen recordings of your application:

```javascript
// Capture a screenshot
const screenshot = await client.captureScreenshot();

// Start a recording
const recording = await client.startRecording();

// Complete a recording
await client.completeRecording(recording.id);
```

## GraphQL Integration

Example GraphQL query:

```graphql
query {
  livewireComponents {
    id
    name
    class
    properties {
      name
      value
      type
    }
    methods {
      name
      parameters {
        name
        type
      }
    }
  }
}
```

## Webhook Integration

Tapped can send webhook notifications for various events:

```php
// config/tapped.php
return [
    'webhooks' => [
        [
            'url' => 'https://your-webhook-endpoint.com/hook',
            'events' => ['component.updated', 'query.executed'],
            'secret' => 'your-webhook-secret',
        ],
    ],
];
```

## AI Integration Examples

### Example: VS Code + GPT-4 Integration

```javascript
const vscode = require('vscode');
const TappedClient = require('./tapped-client');
const { OpenAI } = require('openai');

// Initialize clients
const tappedClient = new TappedClient({
    baseUrl: 'http://localhost:8000/tapped/api'
});
const openai = new OpenAI({ apiKey: 'your-api-key' });

// Analyze N+1 queries
async function analyzeN1QueriesWithAI() {
    try {
        // Get query data with N+1 detection
        const response = await tappedClient.getQueryData(true);
        
        if (!response.success) {
            return vscode.window.showErrorMessage(`API error: ${response.message}`);
        }
        
        const n1Issues = response.data.n1_issues || [];
        
        if (n1Issues.length === 0) {
            return vscode.window.showInformationMessage('No N+1 query issues detected.');
        }
        
        // Create prompt for AI
        const prompt = `
        I have detected ${n1Issues.length} N+1 query issues in my Laravel Livewire application:
        
        ${n1Issues.map(issue => `
        Pattern: ${issue.pattern}
        Count: ${issue.count}
        Component: ${issue.component}
        `).join('\n')}
        
        Please suggest optimal fixes for these N+1 query issues, with code examples.
        `;
        
        // Get AI suggestions
        const aiResponse = await openai.chat.completions.create({
            model: 'gpt-4',
            messages: [{ role: 'user', content: prompt }],
        });
        
        // Display AI suggestions
        const panel = vscode.window.createWebviewPanel(
            'tappedAISuggestions',
            'Tapped AI Suggestions',
            vscode.ViewColumn.Active,
            { enableScripts: true }
        );
        
        panel.webview.html = `
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: var(--vscode-font-family); }
                    pre { background-color: var(--vscode-editor-background); padding: 1em; }
                </style>
            </head>
            <body>
                <h1>AI Optimization Suggestions</h1>
                <div>${aiResponse.choices[0].message.content.replace(/\n/g, '<br>')}</div>
            </body>
            </html>
        `;
    } catch (error) {
        vscode.window.showErrorMessage(`Error: ${error.message}`);
    }
}
```

### Example: JetBrains Plugin + AI Code Generation

```java
public class TappedAIAssistant {
    private TappedApiClient apiClient;
    private OpenAIClient aiClient;
    
    public TappedAIAssistant(TappedApiClient apiClient, OpenAIClient aiClient) {
        this.apiClient = apiClient;
        this.aiClient = aiClient;
    }
    
    public String generateOptimizedComponentCode(String componentId) throws IOException {
        // Get component details
        ApiResponse<Map<String, Object>> response = apiClient.getComponentDetails(componentId);
        
        if (!response.isSuccess() || response.getData() == null) {
            throw new IOException("Failed to get component data: " + response.getMessage());
        }
        
        Map<String, Object> component = response.getData();
        
        // Create prompt for AI
        String prompt = "Based on this Livewire component data, generate an optimized version of the component:\n\n" +
                "Name: " + component.get("name") + "\n" +
                "Class: " + component.get("class") + "\n" +
                "Properties: " + new Gson().toJson(component.get("properties")) + "\n" +
                "Methods: " + new Gson().toJson(component.get("methods")) + "\n\n" +
                "Please optimize for performance and best practices.";
        
        // Get AI response
        String aiSuggestion = aiClient.generateCode(prompt);
        
        return aiSuggestion;
    }
}
```

### Example: CLI + AI Performance Analysis

```php
// In tapped-cli.php
$application->register('ai:analyze')
    ->setDescription('Analyze application with AI')
    ->setHelp('This command uses AI to analyze your application and provide optimization suggestions')
    ->addOption(
        'api-url',
        'u',
        InputOption::VALUE_REQUIRED,
        'The Tapped API URL',
        $defaultApiUrl
    )
    ->addOption(
        'auth-token',
        't',
        InputOption::VALUE_REQUIRED,
        'Authentication token',
        $defaultAuthToken
    )
    ->addOption(
        'openai-key',
        'k',
        InputOption::VALUE_REQUIRED,
        'OpenAI API key',
    )
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $io = new SymfonyStyle($input, $output);
        $apiUrl = $input->getOption('api-url');
        $authToken = $input->getOption('auth-token');
        $openaiKey = $input->getOption('openai-key');
        
        if (!$openaiKey) {
            $io->error('OpenAI API key is required');
            return Command::FAILURE;
        }
        
        $io->title('Tapped AI Analysis');
        $io->text('Collecting debug data...');
        
        try {
            // Collect all necessary data
            $client = new Client();
            $debugData = $client->get($apiUrl . '/debug-data', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $authToken,
                ]
            ])->getBody();
            
            $queriesData = $client->get($apiUrl . '/debug-data/queries?n1_detection=true', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $authToken,
                ]
            ])->getBody();
            
            // Prepare OpenAI request
            $openai = new Client([
                'base_uri' => 'https://api.openai.com/v1/',
                'headers' => [
                    'Authorization' => 'Bearer ' . $openaiKey,
                    'Content-Type' => 'application/json',
                ]
            ]);
            
            $io->text('Analyzing with AI...');
            
            // Send to OpenAI
            $response = $openai->post('chat/completions', [
                'json' => [
                    'model' => 'gpt-4',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are an expert Laravel and Livewire developer. Analyze the debug data and provide specific optimization suggestions.'
                        ],
                        [
                            'role' => 'user',
                            'content' => "Here's debug data from my Laravel Livewire application. Please analyze it and provide specific optimization suggestions:\n\n" .
                                         "Debug Data: " . $debugData . "\n\n" .
                                         "Query Data with N+1 Detection: " . $queriesData
                        ]
                    ],
                    'temperature' => 0.2
                ]
            ]);
            
            $aiResult = json_decode($response->getBody(), true);
            $analysis = $aiResult['choices'][0]['message']['content'];
            
            $io->success('AI Analysis Complete');
            $io->writeln($analysis);
            
            return Command::SUCCESS;
        } catch (GuzzleException $e) {
            $io->error('API Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    });
```
