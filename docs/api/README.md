# Tapped API Documentation

This document provides detailed API documentation for Tapped, the Laravel Livewire debugger.

## Table of Contents

1. [Authentication](#authentication)
2. [RESTful API Endpoints](#restful-api-endpoints)
3. [GraphQL API](#graphql-api)
4. [Response Format](#response-format)
5. [Examples](#examples)
6. [Error Handling](#error-handling)
7. [Rate Limiting](#rate-limiting)
8. [Webhooks](#webhooks)

## Authentication

Tapped API uses token-based authentication. Include your API token in the `Authorization` header:

```http
GET /tapped/api/debug-data
Authorization: Bearer your-api-token-here
```

To configure your API token, set the `TAPPED_API_TOKEN` environment variable in your `.env` file:

```
TAPPED_API_TOKEN=your-secure-token-here
```

## RESTful API Endpoints

### Status Endpoint

Check the API connection status.

```
GET /tapped/api/status
```

#### Response

```json
{
  "success": true,
  "message": "Tapped API is running",
  "data": {
    "version": "1.0.0",
    "php_version": "8.1.0",
    "laravel_version": "9.0.0",
    "livewire_version": "2.10.0",
    "websocket_url": "ws://127.0.0.1:8084"
  }
}
```

### Debug Data Endpoints

#### Get All Debug Data

Get all debug data, including Livewire components, queries, events, and requests.

```
GET /tapped/api/debug-data
```

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| format | string | Data format: `json` (default) or `binary` |

#### Response

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "livewire": [...],
    "queries": [...],
    "requests": [...],
    "events": [...]
  }
}
```

#### Get Livewire Component Data

Get Livewire component data.

```
GET /tapped/api/debug-data/livewire
```

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| component_id | string | Filter by component ID |

#### Response

```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": "abc123",
      "name": "counter",
      "class": "App\\Http\\Livewire\\Counter",
      "properties": [
        {
          "name": "count",
          "value": "5",
          "type": "integer",
          "isPublic": true,
          "isPersistent": true
        }
      ],
      "methods": [
        {
          "name": "increment",
          "parameters": [],
          "hasParameters": false,
          "isPublic": true
        }
      ]
    }
  ]
}
```

#### Get Database Query Data

Get database query data.

```
GET /tapped/api/debug-data/queries
```

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| n1_detection | boolean | Enable N+1 query detection |

#### Response

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "queries": [
      {
        "id": "query123",
        "query": "SELECT * FROM users WHERE id = ?",
        "bindings": ["1"],
        "time": 5.67,
        "connectionName": "mysql",
        "timestamp": "2023-05-15T12:34:56+00:00",
        "caller": "App\\Http\\Controllers\\UserController@show:25",
        "isN1Candidate": false
      }
    ],
    "n1_issues": [
      {
        "pattern": "SELECT * FROM posts WHERE user_id = ?",
        "count": 5,
        "queries": ["query124", "query125", "query126", "query127", "query128"],
        "component": "App\\Http\\Livewire\\UserPosts",
        "suggestedFix": "Use eager loading: User::with('posts')->find($id)"
      }
    ]
  }
}
```

#### Get Event Data

Get event data.

```
GET /tapped/api/debug-data/events
```

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| type | string | Filter by event type |

#### Response

```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": "event123",
      "type": "lifecycle",
      "name": "mount",
      "payload": "{\"user_id\":5}",
      "component": "App\\Http\\Livewire\\UserProfile",
      "timestamp": "2023-05-15T12:34:56+00:00"
    }
  ]
}
```

#### Get HTTP Request Data

Get HTTP request data.

```
GET /tapped/api/debug-data/requests
```

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| request_id | string | Filter by request ID |

#### Response

```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": "req123",
      "method": "GET",
      "uri": "/users/5",
      "url": "https://example.com/users/5",
      "path": "/users/5",
      "responseStatus": 200,
      "responseTime": 125.67,
      "timestamp": "2023-05-15T12:34:56+00:00",
      "headers": [
        {
          "name": "Content-Type",
          "value": "application/json"
        }
      ],
      "query": [
        {
          "name": "include",
          "value": "posts"
        }
      ],
      "isAjax": false,
      "isLivewire": false
    }
  ]
}
```

### Snapshot Endpoints

#### Create a Snapshot

Create a new debug state snapshot.

```
POST /tapped/api/debug-data/snapshots
```

#### Request Body

```json
{
  "label": "My Snapshot"
}
```

#### Response

```json
{
  "success": true,
  "message": "Snapshot created successfully",
  "data": {
    "snapshot_id": "snap123",
    "label": "My Snapshot",
    "timestamp": 1652624096
  }
}
```

#### Get Snapshots

Get a list of all snapshots.

```
GET /tapped/api/debug-data/snapshots
```

#### Response

```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": "snap123",
      "label": "My Snapshot",
      "timestamp": 1652624096,
      "filename": "snap123.json"
    }
  ]
}
```

#### Get a Specific Snapshot

Get a specific snapshot by ID.

```
GET /tapped/api/debug-data/snapshots/{id}
```

#### Response

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "id": "snap123",
    "label": "My Snapshot",
    "timestamp": 1652624096,
    "data": {
      "livewire": [...],
      "queries": [...],
      "requests": [...],
      "events": [...]
    }
  }
}
```

#### Delete a Snapshot

Delete a snapshot.

```
DELETE /tapped/api/debug-data/snapshots/{id}
```

#### Response

```json
{
  "success": true,
  "message": "Snapshot deleted successfully"
}
```

### Screenshot Endpoints

#### Capture a Screenshot

Capture a screenshot of the current page.

```
POST /tapped/api/screenshot/capture
```

#### Request Body

```json
{
  "fullPage": true,
  "selector": "#element-id"
}
```

#### Response

```json
{
  "success": true,
  "message": "Screenshot captured successfully",
  "data": {
    "id": "ss123",
    "filename": "ss123.png",
    "path": "tapped/screenshots/ss123.png",
    "url": "https://example.com/storage/tapped/screenshots/ss123.png",
    "metadata": {
      "id": "ss123",
      "type": "page",
      "timestamp": 1652624096,
      "date": "2023-05-15 12:34:56"
    }
  }
}
```

#### Get Screenshots

Get a list of screenshots.

```
GET /tapped/api/screenshot
```

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| type | string | Filter by screenshot type (page, element) |

#### Response

```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": "ss123",
      "filename": "ss123.png",
      "path": "tapped/screenshots/ss123.png",
      "url": "https://example.com/storage/tapped/screenshots/ss123.png",
      "metadata": {
        "id": "ss123",
        "type": "page",
        "timestamp": 1652624096,
        "date": "2023-05-15 12:34:56"
      }
    }
  ]
}
```

### Recording Endpoints

#### Start a Recording

Start a recording session.

```
POST /tapped/api/screenshot/recording/start
```

#### Request Body

```json
{
  "metadata": {
    "description": "Homepage navigation flow"
  }
}
```

#### Response

```json
{
  "success": true,
  "message": "Recording started successfully",
  "data": {
    "id": "rec123",
    "status": "started",
    "started_at": 1652624096,
    "frame_count": 0,
    "metadata": {
      "date": "2023-05-15 12:34:56",
      "description": "Homepage navigation flow"
    }
  }
}
```

#### Complete a Recording

Complete a recording session.

```
POST /tapped/api/screenshot/recording/{id}/complete
```

#### Request Body

```json
{
  "metadata": {
    "notes": "Completed successfully"
  }
}
```

#### Response

```json
{
  "success": true,
  "message": "Recording completed successfully",
  "data": {
    "id": "rec123",
    "status": "completed",
    "started_at": 1652624096,
    "completed_at": 1652624196,
    "frame_count": 10,
    "duration": 100,
    "metadata": {
      "date": "2023-05-15 12:34:56",
      "description": "Homepage navigation flow",
      "notes": "Completed successfully"
    }
  }
}
```

#### Get Recordings

Get a list of recordings.

```
GET /tapped/api/screenshot/recordings
```

#### Response

```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": "rec123",
      "status": "completed",
      "started_at": 1652624096,
      "completed_at": 1652624196,
      "frame_count": 10,
      "duration": 100,
      "actual_frame_count": 10,
      "frames": [
        {
          "filename": "rec123_frame_0.png",
          "path": "tapped/screenshots/rec123_frame_0.png",
          "url": "https://example.com/storage/tapped/screenshots/rec123_frame_0.png"
        }
      ],
      "metadata": {
        "date": "2023-05-15 12:34:56",
        "description": "Homepage navigation flow",
        "notes": "Completed successfully"
      }
    }
  ]
}
```

## GraphQL API

Tapped provides a GraphQL API at `/tapped/graphql` that allows for complex queries and fine-grained data access.

### Endpoint

```
POST /tapped/graphql
```

### Authentication

Include your API token in the `Authorization` header:

```http
POST /tapped/graphql
Authorization: Bearer your-api-token-here
```

### Example Queries

#### Get Livewire Components

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
      isPublic
      isPersistent
    }
    methods {
      name
      parameters {
        name
        type
        isOptional
        defaultValue
      }
      hasParameters
      isPublic
    }
  }
}
```

#### Get Database Queries with N+1 Detection

```graphql
query {
  databaseQueries(detectN1: true) {
    queries {
      id
      query
      bindings
      time
      connectionName
      timestamp
      isN1Candidate
    }
    n1Issues {
      pattern
      count
      queries
      component
      suggestedFix
    }
  }
}
```

#### Get Events by Type

```graphql
query {
  events(type: "lifecycle") {
    id
    type
    name
    payload
    component
    timestamp
  }
}
```

### Example Mutations

#### Capture a Snapshot

```graphql
mutation {
  captureSnapshot(label: "My Snapshot") {
    success
    message
    snapshot {
      id
      label
      timestamp
    }
  }
}
```

#### Update a Component Property

```graphql
mutation {
  updateComponentProperty(
    componentId: "abc123", 
    property: "count", 
    value: "10"
  ) {
    success
    message
    component {
      id
      name
      properties {
        name
        value
      }
    }
  }
}
```

## Response Format

All RESTful API responses follow a consistent format:

```json
{
  "success": true|false,
  "message": "Human-readable message",
  "data": { /* Response data */ }
}
```

- `success`: Boolean indicating if the request was successful
- `message`: Human-readable message about the result
- `data`: The actual response data (may be null for error responses)

For error responses, additional information may be included:

```json
{
  "success": false,
  "message": "Validation failed",
  "data": {
    "errors": {
      "label": ["The label field is required"]
    }
  }
}
```

## Examples

### Using cURL

```bash
# Get all Livewire components
curl -X GET "http://localhost:8000/tapped/api/debug-data/livewire" \
  -H "Authorization: Bearer your-api-token-here" \
  -H "Accept: application/json"

# Create a snapshot
curl -X POST "http://localhost:8000/tapped/api/debug-data/snapshots" \
  -H "Authorization: Bearer your-api-token-here" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"label":"My Snapshot"}'

# Analyze queries with N+1 detection
curl -X GET "http://localhost:8000/tapped/api/debug-data/queries?n1_detection=true" \
  -H "Authorization: Bearer your-api-token-here" \
  -H "Accept: application/json"
```

### Using JavaScript

```javascript
// Using the TappedClient
const TappedClient = require('./tapped-client.js');

const client = new TappedClient({
  baseUrl: 'http://localhost:8000/tapped/api',
  authToken: 'your-api-token-here'
});

// Get Livewire components
client.getLivewireData()
  .then(response => {
    if (response.success) {
      console.log(response.data);
    } else {
      console.error(response.message);
    }
  })
  .catch(error => {
    console.error('Error:', error.message);
  });

// Capture a screenshot
client.captureScreenshot({ fullPage: true })
  .then(response => {
    if (response.success) {
      console.log('Screenshot captured:', response.data.url);
    } else {
      console.error(response.message);
    }
  })
  .catch(error => {
    console.error('Error:', error.message);
  });
```

## Error Handling

Tapped API uses standard HTTP status codes:

| Status Code | Description |
|-------------|-------------|
| 200 | OK - The request was successful |
| 400 | Bad Request - The request could not be understood or was missing required parameters |
| 401 | Unauthorized - Authentication failed or user does not have permissions |
| 404 | Not Found - The requested resource was not found |
| 422 | Unprocessable Entity - Validation failed |
| 429 | Too Many Requests - Rate limit exceeded |
| 500 | Internal Server Error - An error occurred on the server |

## Rate Limiting

To protect the API from abuse, rate limiting is enabled by default. The default limit is 60 requests per minute per API token.

Rate limit headers are included in the response:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1652624156
```

When the rate limit is exceeded, a 429 status code is returned with a message indicating when the limit will reset.

## Webhooks

Tapped can send webhook notifications for various events. Configure webhooks in the Tapped configuration file:

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

### Webhook Payloads

Each webhook payload follows this format:

```json
{
  "event": "component.updated",
  "timestamp": 1652624096,
  "payload": {
    // Event-specific data
  }
}
```

### Webhook Signature Verification

To verify that a webhook came from Tapped, a signature is included in the `X-Tapped-Signature` header:

```
X-Tapped-Signature: sha256=hash
```

The signature is generated using HMAC-SHA256 with your webhook secret as the key and the JSON payload as the message.

### Example Webhook Verification (PHP)

```php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_TAPPED_SIGNATURE'];
$secret = 'your-webhook-secret';

$expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (hash_equals($expectedSignature, $signature)) {
    // Webhook is verified
    $data = json_decode($payload, true);
    // Process the webhook
} else {
    // Invalid signature
    http_response_code(401);
    echo 'Invalid signature';
}
```

For detailed information on integrating with Tapped's API, refer to the [developer documentation](https://github.com/thinkneverland/tapped).
