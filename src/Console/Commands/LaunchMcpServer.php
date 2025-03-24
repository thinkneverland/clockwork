<?php

namespace ThinkNeverland\Tapped\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use ThinkNeverland\Tapped\WebSocket\McpServer;

class LaunchMcpServer extends Command
{
    protected $signature = 'tapped:mcp-server';
    protected $description = 'Launch the Tapped MCP protocol server';

    public function handle(): int
    {
        $host = Config::get('tapped.mcp_server.host', '127.0.0.1');
        $port = Config::get('tapped.mcp_server.port', 8888);

        $this->info("Starting Tapped MCP server on {$host}:{$port}...");

        $server = IoServer::factory(
            new HttpServer(
                new WsServer(
                    new McpServer()
                )
            ),
            $port,
            $host
        );

        $this->info('MCP server started! Press Ctrl+C to stop.');

        $server->run();

        return Command::SUCCESS;
    }
}
