package com.thinkneverland.tapped.api;

import com.google.gson.Gson;
import com.google.gson.JsonObject;
import com.google.gson.reflect.TypeToken;
import com.thinkneverland.tapped.settings.TappedSettings;
import okhttp3.*;

import java.io.IOException;
import java.lang.reflect.Type;
import java.util.List;
import java.util.Map;
import java.util.concurrent.TimeUnit;

/**
 * API Client for communicating with the Tapped API
 */
public class TappedApiClient {
    private static final MediaType JSON = MediaType.get("application/json; charset=utf-8");
    private final OkHttpClient client;
    private final Gson gson;
    private String baseUrl;
    private String authToken;

    /**
     * Constructor
     */
    public TappedApiClient() {
        this.client = new OkHttpClient.Builder()
                .connectTimeout(5, TimeUnit.SECONDS)
                .readTimeout(10, TimeUnit.SECONDS)
                .writeTimeout(10, TimeUnit.SECONDS)
                .build();
        this.gson = new Gson();
        
        // Get settings
        TappedSettings settings = TappedSettings.getInstance();
        this.baseUrl = settings.getApiUrl();
        this.authToken = settings.getAuthToken();
    }

    /**
     * Update the base URL and token
     *
     * @param baseUrl   API base URL
     * @param authToken Authentication token
     */
    public void updateConnection(String baseUrl, String authToken) {
        this.baseUrl = baseUrl;
        this.authToken = authToken;
    }

    /**
     * Check connection to the Tapped API
     *
     * @return Connection status
     * @throws IOException if connection fails
     */
    public ApiResponse<JsonObject> checkConnection() throws IOException {
        Request request = new Request.Builder()
                .url(this.baseUrl + "/status")
                .addHeader("Accept", "application/json")
                .build();

        try (Response response = client.newCall(request).execute()) {
            String responseBody = response.body().string();
            return parseResponse(responseBody, JsonObject.class);
        }
    }

    /**
     * Get Livewire components
     *
     * @return List of components
     * @throws IOException if request fails
     */
    public ApiResponse<List<Map<String, Object>>> getLivewireComponents() throws IOException {
        Request request = new Request.Builder()
                .url(this.baseUrl + "/debug-data/livewire")
                .addHeader("Accept", "application/json")
                .addHeader("Authorization", "Bearer " + this.authToken)
                .build();

        try (Response response = client.newCall(request).execute()) {
            String responseBody = response.body().string();
            Type type = new TypeToken<ApiResponse<List<Map<String, Object>>>>() {}.getType();
            return gson.fromJson(responseBody, type);
        }
    }

    /**
     * Get component details
     *
     * @param componentId Component ID
     * @return Component details
     * @throws IOException if request fails
     */
    public ApiResponse<Map<String, Object>> getComponentDetails(String componentId) throws IOException {
        Request request = new Request.Builder()
                .url(this.baseUrl + "/debug-data/livewire?component_id=" + componentId)
                .addHeader("Accept", "application/json")
                .addHeader("Authorization", "Bearer " + this.authToken)
                .build();

        try (Response response = client.newCall(request).execute()) {
            String responseBody = response.body().string();
            Type type = new TypeToken<ApiResponse<Map<String, Object>>>() {}.getType();
            return gson.fromJson(responseBody, type);
        }
    }

    /**
     * Get database queries
     *
     * @param detectN1 Whether to detect N+1 queries
     * @return Query data
     * @throws IOException if request fails
     */
    public ApiResponse<Map<String, Object>> getDatabaseQueries(boolean detectN1) throws IOException {
        String url = this.baseUrl + "/debug-data/queries";
        if (detectN1) {
            url += "?n1_detection=true";
        }

        Request request = new Request.Builder()
                .url(url)
                .addHeader("Accept", "application/json")
                .addHeader("Authorization", "Bearer " + this.authToken)
                .build();

        try (Response response = client.newCall(request).execute()) {
            String responseBody = response.body().string();
            Type type = new TypeToken<ApiResponse<Map<String, Object>>>() {}.getType();
            return gson.fromJson(responseBody, type);
        }
    }

    /**
     * Get events
     *
     * @param eventType Optional event type filter
     * @return Event data
     * @throws IOException if request fails
     */
    public ApiResponse<List<Map<String, Object>>> getEvents(String eventType) throws IOException {
        String url = this.baseUrl + "/debug-data/events";
        if (eventType != null && !eventType.isEmpty()) {
            url += "?type=" + eventType;
        }

        Request request = new Request.Builder()
                .url(url)
                .addHeader("Accept", "application/json")
                .addHeader("Authorization", "Bearer " + this.authToken)
                .build();

        try (Response response = client.newCall(request).execute()) {
            String responseBody = response.body().string();
            Type type = new TypeToken<ApiResponse<List<Map<String, Object>>>>() {}.getType();
            return gson.fromJson(responseBody, type);
        }
    }

    /**
     * Get HTTP requests
     *
     * @return Request data
     * @throws IOException if request fails
     */
    public ApiResponse<List<Map<String, Object>>> getHttpRequests() throws IOException {
        Request request = new Request.Builder()
                .url(this.baseUrl + "/debug-data/requests")
                .addHeader("Accept", "application/json")
                .addHeader("Authorization", "Bearer " + this.authToken)
                .build();

        try (Response response = client.newCall(request).execute()) {
            String responseBody = response.body().string();
            Type type = new TypeToken<ApiResponse<List<Map<String, Object>>>>() {}.getType();
            return gson.fromJson(responseBody, type);
        }
    }

    /**
     * Update a component property
     *
     * @param componentId Component ID
     * @param property    Property name
     * @param value       New value
     * @return Update result
     * @throws IOException if request fails
     */
    public ApiResponse<Map<String, Object>> updateComponentProperty(String componentId, String property, String value) throws IOException {
        JsonObject json = new JsonObject();
        json.addProperty("component_id", componentId);
        json.addProperty("property", property);
        json.addProperty("value", value);

        RequestBody body = RequestBody.create(gson.toJson(json), JSON);
        Request request = new Request.Builder()
                .url(this.baseUrl + "/debug-data/livewire/update-property")
                .addHeader("Accept", "application/json")
                .addHeader("Authorization", "Bearer " + this.authToken)
                .post(body)
                .build();

        try (Response response = client.newCall(request).execute()) {
            String responseBody = response.body().string();
            Type type = new TypeToken<ApiResponse<Map<String, Object>>>() {}.getType();
            return gson.fromJson(responseBody, type);
        }
    }

    /**
     * Take a snapshot of the current debug state
     *
     * @param label Optional label for the snapshot
     * @return Snapshot data
     * @throws IOException if request fails
     */
    public ApiResponse<Map<String, Object>> takeSnapshot(String label) throws IOException {
        JsonObject json = new JsonObject();
        if (label != null && !label.isEmpty()) {
            json.addProperty("label", label);
        }

        RequestBody body = RequestBody.create(gson.toJson(json), JSON);
        Request request = new Request.Builder()
                .url(this.baseUrl + "/debug-data/snapshots")
                .addHeader("Accept", "application/json")
                .addHeader("Authorization", "Bearer " + this.authToken)
                .post(body)
                .build();

        try (Response response = client.newCall(request).execute()) {
            String responseBody = response.body().string();
            Type type = new TypeToken<ApiResponse<Map<String, Object>>>() {}.getType();
            return gson.fromJson(responseBody, type);
        }
    }

    /**
     * Capture a screenshot
     *
     * @return Screenshot data
     * @throws IOException if request fails
     */
    public ApiResponse<Map<String, Object>> captureScreenshot() throws IOException {
        Request request = new Request.Builder()
                .url(this.baseUrl + "/screenshot/capture")
                .addHeader("Accept", "application/json")
                .addHeader("Authorization", "Bearer " + this.authToken)
                .post(RequestBody.create(new byte[0], null))
                .build();

        try (Response response = client.newCall(request).execute()) {
            String responseBody = response.body().string();
            Type type = new TypeToken<ApiResponse<Map<String, Object>>>() {}.getType();
            return gson.fromJson(responseBody, type);
        }
    }

    /**
     * Parse API response
     *
     * @param json     JSON response
     * @param dataType Data type
     * @param <T>      Type parameter
     * @return Parsed response
     */
    private <T> ApiResponse<T> parseResponse(String json, Class<T> dataType) {
        JsonObject responseObj = gson.fromJson(json, JsonObject.class);
        boolean success = responseObj.get("success").getAsBoolean();
        String message = responseObj.get("message").getAsString();
        T data = null;

        if (responseObj.has("data") && !responseObj.get("data").isJsonNull()) {
            data = gson.fromJson(responseObj.get("data"), dataType);
        }

        return new ApiResponse<>(success, message, data);
    }
}
