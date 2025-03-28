<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\Console\Commands;

use Illuminate\Console\Command;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\Socket\SecureServer;
use React\Socket\Server;
use ThinkNeverland\Tapped\MCP\McpServer;
use Throwable;

class McpServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tapped:mcp-server
                            {--host=127.0.0.1 : The host address to bind to}
                            {--port=8888 : The port to listen on}
                            {--secure : Enable secure WebSocket connection (WSS)}
                            {--cert= : Path to SSL certificate file (required for secure mode)}
                            {--key= : Path to SSL private key file (required for secure mode)}
                            {--passphrase= : Optional passphrase for the SSL key file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the Tapped MCP WebSocket server for real-time debugging';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $host = $this->option('host') ?? config('tapped.mcp_server.host', '127.0.0.1');
        $port = (int) ($this->option('port') ?? config('tapped.mcp_server.port', 8888));
        $secure = $this->option('secure') || config('tapped.mcp_server.secure', false);

        // Create MCP server instance
        try {
            $mcpServer = new McpServer($this->laravel);
            
            // Configure WebSocket server
            $wsServer = new WsServer($mcpServer);
            $httpServer = new HttpServer($wsServer);
            
            // Protocol info for display
            $protocol = $secure ? 'WSS (Secure WebSocket)' : 'WS (WebSocket)';
            $this->info('Starting Tapped MCP ' . $protocol . ' server...');
            
            if ($secure) {
                // Check required SSL parameters
                $certPath = $this->option('cert') ?? config('tapped.mcp_server.ssl_cert');
                $keyPath = $this->option('key') ?? config('tapped.mcp_server.ssl_key');
                $passphrase = $this->option('passphrase') ?? config('tapped.mcp_server.ssl_passphrase');
                
                if (empty($certPath) || empty($keyPath)) {
                    $this->error('SSL certificate and key paths are required for secure mode.');
                    $this->info('Provide --cert and --key options or configure them in your tapped.php config file.');
                    return Command::FAILURE;
                }
                
                if (!file_exists($certPath) || !is_readable($certPath)) {
                    $this->error("SSL certificate file not found or not readable: {$certPath}");
                    return Command::FAILURE;
                }
                
                if (!file_exists($keyPath) || !is_readable($keyPath)) {
                    $this->error("SSL key file not found or not readable: {$keyPath}");
                    return Command::FAILURE;
                }
                
                $this->info("Using SSL certificate: {$certPath}");
                
                // Create secure WebSocket server
                $socketServer = new Server("tcp://{$host}:{$port}");
                $secureServer = new SecureServer($socketServer, null, [
                    'local_cert' => $certPath,
                    'local_pk' => $keyPath,
                    'passphrase' => $passphrase,
                    'allow_self_signed' => true,
                    'verify_peer' => false,
                ]);
                
                // Create server with secure socket
                $server = new IoServer($httpServer, $secureServer);
                $this->info("Secure server listening on wss://{$host}:{$port}");
            } else {
                // Standard non-secure WebSocket server
                $server = IoServer::factory($httpServer, $port, $host);
                $this->info("Server listening on ws://{$host}:{$port}");
            }
            
            $this->info("Server started successfully!");
            $this->info("Press Ctrl+C to stop the server");
            
            // Run the server
            $server->run();
            
            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Failed to start server: {$e->getMessage()}");
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
