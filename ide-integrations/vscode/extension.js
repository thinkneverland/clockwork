const vscode = require('vscode');
const axios = require('axios');
const WebSocket = require('websocket').w3cwebsocket;

let statusBarItem;
let webSocketClient = null;
let connectionStatus = 'disconnected';
let componentsProvider, queriesProvider, eventsProvider, requestsProvider;
let currentPanelView = null;

/**
 * Activate the extension
 * @param {vscode.ExtensionContext} context
 */
function activate(context) {
    console.log('Tapped extension is now active');

    // Create status bar item
    statusBarItem = vscode.window.createStatusBarItem(vscode.StatusBarAlignment.Right, 100);
    statusBarItem.text = '$(circle-slash) Tapped: Disconnected';
    statusBarItem.command = 'tapped.connect';
    statusBarItem.show();
    context.subscriptions.push(statusBarItem);

    // Initialize tree view providers
    initializeTreeViews(context);
    
    // Register commands
    registerCommands(context);
}

/**
 * Initialize tree view providers
 * @param {vscode.ExtensionContext} context
 */
function initializeTreeViews(context) {
    // Components tree view
    componentsProvider = new ComponentsProvider();
    vscode.window.registerTreeDataProvider('tapped-components', componentsProvider);
    
    // Queries tree view
    queriesProvider = new QueriesProvider();
    vscode.window.registerTreeDataProvider('tapped-queries', queriesProvider);
    
    // Events tree view
    eventsProvider = new EventsProvider();
    vscode.window.registerTreeDataProvider('tapped-events', eventsProvider);
    
    // Requests tree view
    requestsProvider = new RequestsProvider();
    vscode.window.registerTreeDataProvider('tapped-requests', requestsProvider);
}

/**
 * Register all extension commands
 * @param {vscode.ExtensionContext} context
 */
function registerCommands(context) {
    // Connect to application
    context.subscriptions.push(
        vscode.commands.registerCommand('tapped.connect', async () => {
            const apiUrl = vscode.workspace.getConfiguration('tapped').get('apiUrl');
            
            if (!apiUrl) {
                vscode.window.showInputBox({
                    prompt: 'Enter the Tapped API URL',
                    value: 'http://localhost:8000/tapped/api'
                }).then(url => {
                    if (url) {
                        vscode.workspace.getConfiguration('tapped').update('apiUrl', url, true);
                        connectToApplication(url);
                    }
                });
                return;
            }
            
            connectToApplication(apiUrl);
        })
    );
    
    // Show components
    context.subscriptions.push(
        vscode.commands.registerCommand('tapped.showComponents', () => {
            if (connectionStatus !== 'connected') {
                vscode.window.showErrorMessage('Please connect to a Tapped application first.');
                return;
            }
            
            fetchComponents();
        })
    );
    
    // Show queries
    context.subscriptions.push(
        vscode.commands.registerCommand('tapped.showQueries', () => {
            if (connectionStatus !== 'connected') {
                vscode.window.showErrorMessage('Please connect to a Tapped application first.');
                return;
            }
            
            fetchQueries();
        })
    );
    
    // Inspect component
    context.subscriptions.push(
        vscode.commands.registerCommand('tapped.inspectComponent', (componentId) => {
            if (!componentId) {
                vscode.window.showInputBox({
                    prompt: 'Enter the component ID to inspect'
                }).then(id => {
                    if (id) {
                        showComponentDetails(id);
                    }
                });
                return;
            }
            
            showComponentDetails(componentId);
        })
    );
    
    // Take screenshot
    context.subscriptions.push(
        vscode.commands.registerCommand('tapped.takeScreenshot', () => {
            if (connectionStatus !== 'connected') {
                vscode.window.showErrorMessage('Please connect to a Tapped application first.');
                return;
            }
            
            takeScreenshot();
        })
    );
    
    // Analyze N+1 queries
    context.subscriptions.push(
        vscode.commands.registerCommand('tapped.analyzeN1Queries', () => {
            if (connectionStatus !== 'connected') {
                vscode.window.showErrorMessage('Please connect to a Tapped application first.');
                return;
            }
            
            analyzeN1Queries();
        })
    );
}

/**
 * Connect to Tapped application
 * @param {string} apiUrl
 */
async function connectToApplication(apiUrl) {
    try {
        statusBarItem.text = '$(sync~spin) Tapped: Connecting...';
        
        // Check API connectivity
        const response = await axios.get(`${apiUrl}/status`);
        
        if (response.data.success) {
            connectionStatus = 'connected';
            statusBarItem.text = '$(check) Tapped: Connected';
            vscode.window.showInformationMessage('Connected to Tapped application successfully.');
            
            // Connect WebSocket if available
            const wsUrl = response.data.data?.websocket_url;
            if (wsUrl) {
                connectWebSocket(wsUrl);
            }
            
            // Refresh all data views
            refreshAllViews();
        } else {
            connectionStatus = 'error';
            statusBarItem.text = '$(error) Tapped: Error';
            vscode.window.showErrorMessage(`Failed to connect: ${response.data.message}`);
        }
    } catch (error) {
        connectionStatus = 'error';
        statusBarItem.text = '$(error) Tapped: Error';
        vscode.window.showErrorMessage(`Connection error: ${error.message}`);
    }
}

/**
 * Connect to WebSocket for real-time updates
 * @param {string} wsUrl
 */
function connectWebSocket(wsUrl) {
    if (webSocketClient) {
        webSocketClient.close();
    }
    
    webSocketClient = new WebSocket(wsUrl);
    
    webSocketClient.onopen = () => {
        console.log('WebSocket connection established');
    };
    
    webSocketClient.onmessage = (message) => {
        try {
            const data = JSON.parse(message.data);
            handleWebSocketMessage(data);
        } catch (error) {
            console.error('Error parsing WebSocket message:', error);
        }
    };
    
    webSocketClient.onerror = (error) => {
        console.error('WebSocket error:', error);
    };
    
    webSocketClient.onclose = () => {
        console.log('WebSocket connection closed');
    };
}

/**
 * Handle incoming WebSocket messages
 * @param {any} data
 */
function handleWebSocketMessage(data) {
    const eventType = data.type;
    
    switch (eventType) {
        case 'livewire.update':
            componentsProvider.refresh(data.data);
            break;
        case 'database.query':
            queriesProvider.refresh(data.data);
            break;
        case 'event.fired':
            eventsProvider.refresh(data.data);
            break;
        case 'http.request':
            requestsProvider.refresh(data.data);
            break;
        default:
            console.log('Unknown event type:', eventType);
    }
}

/**
 * Refresh all data views
 */
async function refreshAllViews() {
    try {
        await Promise.all([
            fetchComponents(),
            fetchQueries(),
            fetchEvents(),
            fetchRequests()
        ]);
    } catch (error) {
        console.error('Error refreshing views:', error);
    }
}

/**
 * Fetch Livewire components
 */
async function fetchComponents() {
    const apiUrl = vscode.workspace.getConfiguration('tapped').get('apiUrl');
    
    try {
        const response = await axios.get(`${apiUrl}/debug-data/livewire`);
        if (response.data.success) {
            componentsProvider.refresh(response.data.data);
        }
    } catch (error) {
        console.error('Error fetching components:', error);
    }
}

/**
 * Fetch database queries
 */
async function fetchQueries() {
    const apiUrl = vscode.workspace.getConfiguration('tapped').get('apiUrl');
    
    try {
        const response = await axios.get(`${apiUrl}/debug-data/queries`);
        if (response.data.success) {
            queriesProvider.refresh(response.data.data);
        }
    } catch (error) {
        console.error('Error fetching queries:', error);
    }
}

/**
 * Fetch events
 */
async function fetchEvents() {
    const apiUrl = vscode.workspace.getConfiguration('tapped').get('apiUrl');
    
    try {
        const response = await axios.get(`${apiUrl}/debug-data/events`);
        if (response.data.success) {
            eventsProvider.refresh(response.data.data);
        }
    } catch (error) {
        console.error('Error fetching events:', error);
    }
}

/**
 * Fetch HTTP requests
 */
async function fetchRequests() {
    const apiUrl = vscode.workspace.getConfiguration('tapped').get('apiUrl');
    
    try {
        const response = await axios.get(`${apiUrl}/debug-data/requests`);
        if (response.data.success) {
            requestsProvider.refresh(response.data.data);
        }
    } catch (error) {
        console.error('Error fetching requests:', error);
    }
}

/**
 * Show component details
 * @param {string} componentId
 */
async function showComponentDetails(componentId) {
    const apiUrl = vscode.workspace.getConfiguration('tapped').get('apiUrl');
    
    try {
        const response = await axios.get(`${apiUrl}/debug-data/livewire?component_id=${componentId}`);
        
        if (response.data.success) {
            const component = response.data.data;
            
            if (!component) {
                vscode.window.showErrorMessage(`Component with ID ${componentId} not found.`);
                return;
            }
            
            showComponentPanel(component);
        } else {
            vscode.window.showErrorMessage(`Error: ${response.data.message}`);
        }
    } catch (error) {
        vscode.window.showErrorMessage(`Error: ${error.message}`);
    }
}

/**
 * Show component inspector panel
 * @param {any} component
 */
function showComponentPanel(component) {
    const panel = vscode.window.createWebviewPanel(
        'tappedComponentPanel',
        `Component: ${component.name}`,
        vscode.ViewColumn.Beside,
        {
            enableScripts: true
        }
    );
    
    panel.webview.html = getComponentPanelHtml(component);
    
    panel.webview.onDidReceiveMessage(
        message => {
            switch (message.command) {
                case 'updateProperty':
                    updateComponentProperty(component.id, message.property, message.value);
                    return;
            }
        },
        undefined,
        []
    );
}

/**
 * Update a component property
 * @param {string} componentId
 * @param {string} property
 * @param {string} value
 */
async function updateComponentProperty(componentId, property, value) {
    const apiUrl = vscode.workspace.getConfiguration('tapped').get('apiUrl');
    
    try {
        const response = await axios.post(`${apiUrl}/debug-data/livewire/update-property`, {
            component_id: componentId,
            property: property,
            value: value
        });
        
        if (response.data.success) {
            vscode.window.showInformationMessage(`Property ${property} updated successfully.`);
        } else {
            vscode.window.showErrorMessage(`Error: ${response.data.message}`);
        }
    } catch (error) {
        vscode.window.showErrorMessage(`Error: ${error.message}`);
    }
}

/**
 * Generate HTML for component panel
 * @param {any} component
 * @returns {string}
 */
function getComponentPanelHtml(component) {
    return `
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Tapped Component Viewer</title>
            <style>
                body { font-family: var(--vscode-font-family); color: var(--vscode-foreground); }
                h2 { color: var(--vscode-editor-foreground); }
                .property { margin-bottom: 8px; }
                .property-name { font-weight: bold; }
                .property-value { font-family: monospace; }
                .editable { cursor: pointer; padding: 2px; }
                .editable:hover { background-color: var(--vscode-editor-hoverHighlightBackground); }
                .tabs { display: flex; margin-bottom: 10px; }
                .tab { padding: 8px 12px; cursor: pointer; border: 1px solid var(--vscode-panel-border); }
                .tab.active { background-color: var(--vscode-tab-activeBackground); }
                .tab-content { display: none; }
                .tab-content.active { display: block; }
            </style>
        </head>
        <body>
            <h2>${component.name} (${component.id})</h2>
            
            <div class="tabs">
                <div class="tab active" data-tab="properties">Properties</div>
                <div class="tab" data-tab="methods">Methods</div>
                <div class="tab" data-tab="events">Events</div>
            </div>
            
            <div id="properties" class="tab-content active">
                <h3>Properties</h3>
                ${generatePropertiesHtml(component.properties)}
            </div>
            
            <div id="methods" class="tab-content">
                <h3>Methods</h3>
                ${generateMethodsHtml(component.methods)}
            </div>
            
            <div id="events" class="tab-content">
                <h3>Events</h3>
                <p>Events will appear here when they are emitted.</p>
            </div>
            
            <script>
                (function() {
                    const vscode = acquireVsCodeApi();
                    
                    // Tab switching
                    document.querySelectorAll('.tab').forEach(tab => {
                        tab.addEventListener('click', () => {
                            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                            
                            tab.classList.add('active');
                            const tabName = tab.getAttribute('data-tab');
                            document.getElementById(tabName).classList.add('active');
                        });
                    });
                    
                    // Property editing
                    document.querySelectorAll('.editable').forEach(element => {
                        element.addEventListener('click', () => {
                            const property = element.getAttribute('data-property');
                            const value = element.textContent;
                            
                            const newValue = prompt('Enter new value for ' + property, value);
                            
                            if (newValue !== null && newValue !== value) {
                                element.textContent = newValue;
                                
                                vscode.postMessage({
                                    command: 'updateProperty',
                                    property: property,
                                    value: newValue
                                });
                            }
                        });
                    });
                })();
            </script>
        </body>
        </html>
    `;
}

/**
 * Generate HTML for component properties
 * @param {any[]} properties
 * @returns {string}
 */
function generatePropertiesHtml(properties) {
    if (!properties || properties.length === 0) {
        return '<p>No properties available.</p>';
    }
    
    return properties.map(prop => {
        return `
            <div class="property">
                <div class="property-name">${prop.name} (${prop.type})</div>
                <div class="property-value editable" data-property="${prop.name}">${prop.value}</div>
            </div>
        `;
    }).join('');
}

/**
 * Generate HTML for component methods
 * @param {any[]} methods
 * @returns {string}
 */
function generateMethodsHtml(methods) {
    if (!methods || methods.length === 0) {
        return '<p>No methods available.</p>';
    }
    
    return methods.map(method => {
        const params = method.parameters.map(p => {
            return p.isOptional ? `${p.name}?` : p.name;
        }).join(', ');
        
        return `
            <div class="method">
                <div class="method-name">${method.name}(${params})</div>
            </div>
        `;
    }).join('');
}

/**
 * Take a screenshot of the application
 */
async function takeScreenshot() {
    const apiUrl = vscode.workspace.getConfiguration('tapped').get('apiUrl');
    
    try {
        vscode.window.showInformationMessage('Taking screenshot...');
        
        const response = await axios.post(`${apiUrl}/screenshot/capture`);
        
        if (response.data.success) {
            const screenshot = response.data.data;
            vscode.window.showInformationMessage(`Screenshot captured successfully. ID: ${screenshot.id}`);
        } else {
            vscode.window.showErrorMessage(`Error: ${response.data.message}`);
        }
    } catch (error) {
        vscode.window.showErrorMessage(`Error: ${error.message}`);
    }
}

/**
 * Analyze N+1 queries
 */
async function analyzeN1Queries() {
    const apiUrl = vscode.workspace.getConfiguration('tapped').get('apiUrl');
    
    try {
        vscode.window.showInformationMessage('Analyzing N+1 queries...');
        
        const response = await axios.get(`${apiUrl}/debug-data/queries?n1_detection=true`);
        
        if (response.data.success) {
            const n1Issues = response.data.data.n1_issues || [];
            
            if (n1Issues.length === 0) {
                vscode.window.showInformationMessage('No N+1 query issues detected.');
                return;
            }
            
            const panel = vscode.window.createWebviewPanel(
                'tappedN1Panel',
                'N+1 Query Analysis',
                vscode.ViewColumn.Active,
                {
                    enableScripts: true
                }
            );
            
            panel.webview.html = generateN1AnalysisHtml(n1Issues);
        } else {
            vscode.window.showErrorMessage(`Error: ${response.data.message}`);
        }
    } catch (error) {
        vscode.window.showErrorMessage(`Error: ${error.message}`);
    }
}

/**
 * Generate HTML for N+1 analysis
 * @param {any[]} issues
 * @returns {string}
 */
function generateN1AnalysisHtml(issues) {
    const issuesHtml = issues.map(issue => {
        return `
            <div class="issue">
                <h3>N+1 Query Pattern</h3>
                <div class="issue-pattern">${issue.pattern}</div>
                <div class="issue-count">Count: ${issue.count}</div>
                <div class="issue-component">Component: ${issue.component || 'Unknown'}</div>
                <div class="issue-suggestion">
                    <h4>Suggested Fix:</h4>
                    <pre>${issue.suggestedFix || 'No automatic suggestion available'}</pre>
                </div>
            </div>
        `;
    }).join('<hr>');
    
    return `
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>N+1 Query Analysis</title>
            <style>
                body { font-family: var(--vscode-font-family); color: var(--vscode-foreground); }
                h2, h3, h4 { color: var(--vscode-editor-foreground); }
                .issue { margin-bottom: 20px; }
                .issue-pattern { font-family: monospace; padding: 8px; background-color: var(--vscode-editor-background); }
                .issue-count { font-weight: bold; color: var(--vscode-errorForeground); }
                pre { overflow: auto; padding: 8px; background-color: var(--vscode-editor-background); }
            </style>
        </head>
        <body>
            <h2>N+1 Query Analysis</h2>
            <p>Found ${issues.length} potential N+1 query issues:</p>
            ${issuesHtml}
        </body>
        </html>
    `;
}

// Stub classes for the tree view providers
class ComponentsProvider {
    constructor() {
        this._onDidChangeTreeData = new vscode.EventEmitter();
        this.onDidChangeTreeData = this._onDidChangeTreeData.event;
        this.data = [];
    }
    
    refresh(data) {
        this.data = data;
        this._onDidChangeTreeData.fire();
    }
    
    getTreeItem(element) {
        return element;
    }
    
    getChildren(element) {
        if (!element) {
            return this.data.map(component => {
                const item = new vscode.TreeItem(component.name);
                item.description = component.id;
                item.command = {
                    command: 'tapped.inspectComponent',
                    title: 'Inspect Component',
                    arguments: [component.id]
                };
                return item;
            });
        }
        return [];
    }
}

class QueriesProvider {
    constructor() {
        this._onDidChangeTreeData = new vscode.EventEmitter();
        this.onDidChangeTreeData = this._onDidChangeTreeData.event;
        this.data = [];
    }
    
    refresh(data) {
        this.data = data;
        this._onDidChangeTreeData.fire();
    }
    
    getTreeItem(element) {
        return element;
    }
    
    getChildren(element) {
        if (!element) {
            return this.data.map(query => {
                const item = new vscode.TreeItem(`${query.time}ms: ${query.query.substring(0, 30)}...`);
                item.description = query.id;
                return item;
            });
        }
        return [];
    }
}

class EventsProvider {
    constructor() {
        this._onDidChangeTreeData = new vscode.EventEmitter();
        this.onDidChangeTreeData = this._onDidChangeTreeData.event;
        this.data = [];
    }
    
    refresh(data) {
        this.data = data;
        this._onDidChangeTreeData.fire();
    }
    
    getTreeItem(element) {
        return element;
    }
    
    getChildren(element) {
        if (!element) {
            return this.data.map(event => {
                const item = new vscode.TreeItem(event.name);
                item.description = event.type;
                return item;
            });
        }
        return [];
    }
}

class RequestsProvider {
    constructor() {
        this._onDidChangeTreeData = new vscode.EventEmitter();
        this.onDidChangeTreeData = this._onDidChangeTreeData.event;
        this.data = [];
    }
    
    refresh(data) {
        this.data = data;
        this._onDidChangeTreeData.fire();
    }
    
    getTreeItem(element) {
        return element;
    }
    
    getChildren(element) {
        if (!element) {
            return this.data.map(request => {
                const item = new vscode.TreeItem(`${request.method} ${request.uri}`);
                item.description = `${request.responseStatus}`;
                return item;
            });
        }
        return [];
    }
}

/**
 * Deactivate the extension
 */
function deactivate() {
    if (webSocketClient) {
        webSocketClient.close();
    }
}

module.exports = {
    activate,
    deactivate
};
