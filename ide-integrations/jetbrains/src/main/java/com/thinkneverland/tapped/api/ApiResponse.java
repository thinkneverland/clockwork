package com.thinkneverland.tapped.api;

/**
 * Generic API response wrapper
 * @param <T> Type of data in the response
 */
public class ApiResponse<T> {
    private final boolean success;
    private final String message;
    private final T data;

    /**
     * Constructor
     * @param success Whether the request was successful
     * @param message Response message
     * @param data Response data
     */
    public ApiResponse(boolean success, String message, T data) {
        this.success = success;
        this.message = message;
        this.data = data;
    }

    /**
     * Get success status
     * @return Whether the request was successful
     */
    public boolean isSuccess() {
        return success;
    }

    /**
     * Get response message
     * @return Response message
     */
    public String getMessage() {
        return message;
    }

    /**
     * Get response data
     * @return Response data
     */
    public T getData() {
        return data;
    }
}
