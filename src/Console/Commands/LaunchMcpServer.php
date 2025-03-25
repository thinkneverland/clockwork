<?php

namespace ThinkNeverland\Tapped\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use ThinkNeverland\Tapped\WebSocket\McpServer;
use ThinkNeverland\Tapped\Services\LivewireStateManager;
use ThinkNeverland\Tapped\Services\EventLogger;
use React\EventLoop\Loop;

class LaunchMcpServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tapped:serve
                            {--port=8080 : Port to run the server on}
                            {--host=0.0.0.0 : Host to bind the server to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the Tapped debugging server';

    public function __construct(
        protected LivewireStateManager $stateManager,
        protected EventLogger $eventLogger
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $host = $this->option('host');
        $port = (int) $this->option('port');

        // Create WebSocket server
        $server = IoServer::factory(
            new HttpServer(
                new WsServer(
                    new McpServer($this->stateManager, $this->eventLogger)
                )
            ),
            $port,
            $host
        );

        $this->info('Tapped server started!');
        $this->info("Listening on {$host}:{$port}");
        $this->info('Access the debugger at: ' . url('__tapped'));
        $this->comment('Press Ctrl+C to stop the server');

        // Run the server
        $server->run();
    }

    protected function detectEnvironment(): string
    {
        if (file_exists('/.dockerenv')) {
            return 'docker';
        }

        if (file_exists('/Applications/Herd.app')) {
            return 'herd';
        }

        return 'local';
    }

    protected function getHostForEnvironment(string $env): string
    {
        return match ($env) {
            'docker' => '0.0.0.0',
            'herd' => $this->getHerdIp(),
            default => Config::get('tapped.mcp_server.host', '127.0.0.1'),
        };
    }

    protected function getHerdIp(): string
    {
        // Herd uses 127.0.0.1 for local development
        // We need to bind to 0.0.0.0 to allow connections from the browser
        return '0.0.0.0';
    }
}
