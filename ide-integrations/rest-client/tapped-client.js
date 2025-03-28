/**
 * Tapped REST Client
 * 
 * A generic REST client for interacting with the Tapped API from any IDE or tool
 * that can make HTTP requests. This can be integrated with any AI assistant or tool
 * that needs access to Laravel Livewire debugging data.
 */

const axios = require('axios');

class TappedClient {
    /**
     * Constructor
     * 
     * @param {Object} options Client options
     * @param {string} options.baseUrl Base URL for the Tapped API
     * @param {string} options.authToken Authentication token (optional)
     * @param {number} options.timeout Request timeout in milliseconds (default: 5000)
     */
    constructor(options = {}) {
        this.baseUrl = options.baseUrl || 'http://localhost:8000/tapped/api';
        this.authToken = options.authToken || '';
        this.timeout = options.timeout || 5000;
        
        // Initialize axios instance
        this.axios = axios.create({
            baseURL: this.baseUrl,
            timeout: this.timeout,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            }
        });
        
        // Add auth token if provided
        if (this.authToken) {
            this.axios.defaults.headers.common['Authorization'] = `Bearer ${this.authToken}`;
        }
    }
    
    /**
     * Set authentication token
     * 
     * @param {string} token Auth token
     */
    setAuthToken(token) {
        this.authToken = token;
        this.axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    
    /**
     * Check connection to Tapped API
     * 
     * @returns {Promise<Object>} Status response
     */
    async checkConnection() {
        try {
            const response = await this.axios.get('/status');
            return response.data;
        } catch (error) {
            this._handleError(error);
        }
    }
    
    /**
     * Get all debug data
     * 
     * @param {string} format Data format ('json' or 'binary')
     * @returns {Promise<Object>} Debug data
     */
    async getDebugData(format = 'json') {
        try {
            const response = await this.axios.get(`/debug-data?format=${format}`);
            return response.data;
        } catch (error) {
            this._handleError(error);
        }
    }
    
    /**
     * Get Livewire component data
     * 
     * @param {string} componentId Optional component ID to filter
     * @returns {Promise<Object>} Component data
     */
    async getLivewireData(componentId = null) {
        try {
            const url = componentId ? `/debug-data/livewire?component_id=${componentId}` : '/debug-data/livewire';
            const response = await this.axios.get(url);
            return response.data;
        } catch (error) {
            this._handleError(error);
        }
    }
    
    /**
     * Get database query data
     * 
     * @param {boolean} n1Detection Enable N+1 query detection
     * @returns {Promise<Object>} Query data
     */
    async getQueryData(n1Detection = false) {
        try {
            const url = n1Detection ? '/debug-data/queries?n1_detection=true' : '/debug-data/queries';
            const response = await this.axios.get(url);
            return response.data;
        } catch (error) {
            this._handleError(error);
        }
    }
    
    /**
     * Get HTTP request data
     * 
     * @param {string} requestId Optional request ID to filter
     * @returns {Promise<Object>} Request data
     */
    async getRequestData(requestId = null) {
        try {
            const url = requestId ? `/debug-data/requests?request_id=${requestId}` : '/debug-data/requests';
            const response = await this.axios.get(url);
            return response.data;
        } catch (error) {
            this._handleError(error);
        }
    }
    
    /**
     * Get event data
     * 
     * @param {string} eventType Optional event type to filter
     * @returns {Promise<Object>} Event data
     */
    async getEventData(eventType = null) {
        try {
            const url = eventType ? `/debug-data/events?type=${eventType}` : '/debug-data/events';
            const response = await this.axios.get(url);
            return response.data;
        } catch (error) {
            this._handleError(error);
        }
    }
    
    /**
     * Capture a debug snapshot
     * 
     * @param {string} label Optional label for the snapshot
     * @returns {Promise<Object>} Snapshot data
     */
    async captureSnapshot(label = null) {
        try {
            const data = label ? { label } : {};
            const response = await this.axios.post('/debug-data/snapshots', data);
            return response.data;
        } catch (error) {
            this._handleError(error);
        }
    }
    
    /**
     * Get list of snapshots
     * 
     * @returns {Promise<Object>} List of snapshots
     */
    async getSnapshots() {
        try {
            const response = await this.axios.get('/debug-data/snapshots');
            return response.data;
        } catch (error) {
            this._handleError(error);
        }
    }
    
    /**
     * Get a specific snapshot
     * 
     * @param {string} id Snapshot ID
     * @returns {Promise<Object>} Snapshot data
     */
    async getSnapshot(id) {
        try {
            const response = await this.axios.get(`/debug-data/snapshots/${id}`);
            return response.data;
        } catch (error) {
            this._handleError(error);
        }
    }
    
    /**
     * Delete a snapshot
     * 
     * @param {string} id Snapshot ID
     * @returns {Promise<Object>} Deletion result
     */
    async deleteSnapshot(id) {
        try {
            const response = await this.axios.delete(`/debug-data/snapshots/${id}`);
            return response.data;
        } catch (error) {
            this._handleError(error);
        }
    }
    
    /**
     * Capture a screenshot
     * 
     * @param {Object} options Screenshot options
     * @param {boolean} options.fullPage Whether to capture the full page
     * @param {string} options.selector Element selector for element screenshots
     * @returns {Promise<Object>} Screenshot data
     */
    async captureScreenshot(options = {}) {
        try {
            const response = await this.axios.post('/screenshot/capture', options);
            return response.data;
        } catch (error) {
            this._handleError(error);
        }
    }
    
    /**
     * Get list of screenshots
     * 
     * @param {string} type Filter by screenshot type (page, element)
     * @returns {Promise<Object>} List of screenshots
     */
    async getScreenshots(type = null) {
        try {
            const url = type ? `/screenshot?type=${type}` : '/screenshot';
            const response = await this.axios.get(url);
            return response.data;
        } catch (error) {
            this._handleError(error);
        }
    }
    
    /**
     * Start a recording session
     * 
     * @param {Object} metadata Optional metadata
     * @returns {Promise<Object>} Recording session data
     */
    async startRecording(metadata = {}) {
        try {
            const response = await this.axios.post('/screenshot/recording/start', { metadata });
            return response.data;
        } catch (error) {
            this._handleError(error);
        }
    }
    
    /**
     * Complete a recording session
     * 
     * @param {string} recordingId Recording session ID
     * @param {Object} metadata Optional metadata
     * @returns {Promise<Object>} Updated recording data
     */
    async completeRecording(recordingId, metadata = {}) {
        try {
            const response = await this.axios.post(`/screenshot/recording/${recordingId}/complete`, { metadata });
            return response.data;
        } catch (error) {
            this._handleError(error);
        }
    }
    
    /**
     * Get list of recordings
     * 
     * @returns {Promise<Object>} List of recordings
     */
    async getRecordings() {
        try {
            const response = await this.axios.get('/screenshot/recordings');
            return response.data;
        } catch (error) {
            this._handleError(error);
        }
    }
    
    /**
     * Handle request errors
     * 
     * @param {Error} error Axios error
     * @throws {Error} Throws enhanced error
     * @private
     */
    _handleError(error) {
        if (error.response) {
            // Server responded with an error status
            const message = error.response.data?.message || error.response.statusText;
            const enhancedError = new Error(`API Error (${error.response.status}): ${message}`);
            enhancedError.status = error.response.status;
            enhancedError.data = error.response.data;
            throw enhancedError;
        } else if (error.request) {
            // Request was made but no response received
            throw new Error('No response received from API server');
        } else {
            // Error in setting up the request
            throw error;
        }
    }
}

// Example usage:
// 
// const client = new TappedClient({
//     baseUrl: 'http://localhost:8000/tapped/api',
//     authToken: 'your-auth-token-here'
// });
// 
// async function getLivewireComponents() {
//     try {
//         const response = await client.getLivewireData();
//         console.log(response.data);
//     } catch (error) {
//         console.error('Error:', error.message);
//     }
// }

module.exports = TappedClient;
