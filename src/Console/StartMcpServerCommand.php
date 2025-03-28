<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use ThinkNeverland\Tapped\MCP\McpServer;

class StartMcpServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tapped:mcp-server 
                            {--host=0.0.0.0 : The host address to bind to}
                            {--port=8090 : The port to listen on}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the Tapped MCP WebSocket server for real-time debugging';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $host = $this->option('host');
        $port = (int) $this->option('port');

        $this->info("Starting Tapped MCP server on {$host}:{$port}...");

        // Create the MCP server instance from the container
        $mcpServer = $this->laravel->make(McpServer::class);

        try {
            // Create and configure the WebSocket server
            $server = IoServer::factory(
                new HttpServer(
                    new WsServer($mcpServer)
                ),
                $port,
                $host
            );

            $this->info('MCP server started successfully.');
            $this->info('Press Ctrl+C to stop the server.');

            // Start the server (this is a blocking call)
            $server->run();

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to start MCP server: {$e->getMessage()}");
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
