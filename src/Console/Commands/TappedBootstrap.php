<?php

namespace ThinkNeverland\Tapped\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use ThinkNeverland\Tapped\Facades\Tapped;

class TappedBootstrap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tapped:bootstrap
                            {--serve : Immediately start the Tapped server after setup}
                            {--cdn : Use CDN for dependencies instead of local installation}
                            {--no-assets : Skip publishing assets}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-step bootstrap for Tapped debugger';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->components->info('Bootstrapping Tapped - Laravel Debugger');

        // 1. Create config if needed
        if (!file_exists(config_path('tapped.php'))) {
            $this->callSilently('vendor:publish', [
                '--tag' => 'tapped-config',
            ]);

            $this->components->task('Publishing configuration', fn() => true);
        }

        // 2. Handle assets
        if (!$this->option('no-assets')) {
            $this->handleAssets();
        }

        // 3. Set up dependencies
        if ($this->option('cdn')) {
            $this->setupCdnMode();
        } else {
            $this->setupLocalDependencies();
        }

        // 4. Provide quick setup info
        $this->outputSetupInfo();

        // 5. Start server if requested
        if ($this->option('serve')) {
            $this->startServer();
        }

        return 0;
    }

    /**
     * Handle publishing assets
     */
    protected function handleAssets(): void
    {
        $assetsPath = public_path('vendor/tapped');

        if (!File::exists($assetsPath)) {
            $this->callSilently('vendor:publish', [
                '--tag' => 'tapped-assets',
            ]);

            $this->components->task('Publishing assets', fn() => File::exists($assetsPath));
        } else {
            $this->components->task('Verifying assets', fn() => true);
        }
    }

    /**
     * Set up CDN mode
     */
    protected function setupCdnMode(): void
    {
        // Create a CDN configuration file
        $cdnConfigPath = storage_path('app/tapped/cdn-config.json');

        if (!File::exists(dirname($cdnConfigPath))) {
            File::makeDirectory(dirname($cdnConfigPath), 0755, true);
        }

        // Get dependencies
        $dependencies = Tapped::getJsDependencies();

        // Create CDN configuration
        $cdnConfig = [
            'enabled' => true,
            'libraries' => $this->prepareCdnLibraries($dependencies),
            'timestamp' => now()->timestamp,
        ];

        File::put(
            $cdnConfigPath,
            json_encode($cdnConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->components->task('Setting up CDN mode', fn() => true);
    }

    /**
     * Prepare CDN library mappings
     */
    protected function prepareCdnLibraries(array $dependencies): array
    {
        // Key dependencies to provide via CDN
        $cdnMappings = [
            'vue' => 'https://cdn.jsdelivr.net/npm/vue@3.2.47/dist/vue.global.prod.js',
            'urijs' => 'https://cdn.jsdelivr.net/npm/urijs@1.19.11/src/URI.min.js',
            'punycode' => 'https://cdn.jsdelivr.net/npm/punycode@2.3.1/punycode.min.js',
            'lodash' => 'https://cdn.jsdelivr.net/npm/lodash@4.17.21/lodash.min.js',
            'date-fns' => 'https://cdn.jsdelivr.net/npm/date-fns@2.30.0/index.min.js',
            'feather-icons' => 'https://cdn.jsdelivr.net/npm/feather-icons@4.29.1/dist/feather.min.js',
            'prismjs' => 'https://cdn.jsdelivr.net/npm/prismjs@1.29.0/prism.min.js',
        ];

        $libraries = [];

        foreach ($dependencies as $name => $version) {
            if (isset($cdnMappings[$name])) {
                $libraries[$name] = [
                    'url' => $cdnMappings[$name],
                    'version' => str_replace('^', '', $version),
                    'global' => $this->getGlobalVariableName($name),
                ];
            }
        }

        return $libraries;
    }

    /**
     * Get global variable name for a library
     */
    protected function getGlobalVariableName(string $libraryName): string
    {
        return match ($libraryName) {
            'vue' => 'Vue',
            'urijs' => 'URI',
            'date-fns' => 'dateFns',
            'feather-icons' => 'feather',
            'lodash' => '_',
            default => $libraryName,
        };
    }

    /**
     * Setup local dependencies
     */
    protected function setupLocalDependencies(): void
    {
        // Check if we can find node/npm
        $hasNode = $this->checkNodeAvailable();

        if (!$hasNode) {
            $this->components->warn('Node.js not found. Switching to CDN mode...');
            $this->setupCdnMode();
            return;
        }

        // Get package.json path
        $packageJsonPath = base_path('package.json');

        // Create minimal dependency setup
        $tappedVendorDir = base_path('vendor/tapped');
        if (!File::isDirectory($tappedVendorDir)) {
            File::makeDirectory($tappedVendorDir, 0755, true);
        }

        // Get minimal dependencies
        $minimalDeps = Tapped::getJsDependencies(false);
        $essentialDeps = array_filter($minimalDeps, function ($key) {
            return in_array($key, ['punycode', 'urijs', 'vue', 'lodash']);
        }, ARRAY_FILTER_USE_KEY);

        // Add to existing package.json or create new
        if (File::exists($packageJsonPath)) {
            $this->addToExistingPackage($packageJsonPath, $essentialDeps);
        } else {
            $this->createMinimalPackage($packageJsonPath, $essentialDeps);
        }

        // Install dependencies
        $this->installDependencies();
    }

    /**
     * Add to existing package.json
     */
    protected function addToExistingPackage(string $packageJsonPath, array $essentialDeps): void
    {
        $packageJson = json_decode(File::get($packageJsonPath), true);

        if (!isset($packageJson['dependencies'])) {
            $packageJson['dependencies'] = [];
        }

        // Add only the essential deps that don't exist
        foreach ($essentialDeps as $name => $version) {
            if (!isset($packageJson['dependencies'][$name])) {
                $packageJson['dependencies'][$name] = $version;
            }
        }

        File::put(
            $packageJsonPath,
            json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->components->task('Adding dependencies to package.json', fn() => true);
    }

    /**
     * Create minimal package.json
     */
    protected function createMinimalPackage(string $packageJsonPath, array $essentialDeps): void
    {
        $packageJson = [
            'name' => basename(base_path()),
            'private' => true,
            'dependencies' => $essentialDeps,
        ];

        File::put(
            $packageJsonPath,
            json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->components->task('Creating package.json', fn() => true);
    }

    /**
     * Install dependencies
     */
    protected function installDependencies(): void
    {
        $this->components->info('Installing minimal dependencies...');

        $command = 'npm install punycode urijs';
        $this->components->task('Running npm install', function () use ($command) {
            $process = Process::run($command);
            return $process->successful();
        });
    }

    /**
     * Check if Node.js is available
     */
    protected function checkNodeAvailable(): bool
    {
        try {
            $process = Process::run('node -v');
            return $process->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Start the Tapped server
     */
    protected function startServer(): void
    {
        $this->components->info('Starting Tapped server...');
        $this->components->info('Access the debugger at: ' . url('__tapped'));

        $this->call('tapped:serve');
    }

    /**
     * Output setup information
     */
    protected function outputSetupInfo(): void
    {
        $this->components->info('Tapped is ready to use!');
        $this->newLine();
        $this->components->bulletList([
            'Access the debugger at: <options=bold>' . url('__tapped') . '</>',
            'Debug Livewire with: <options=bold>Tapped::getStateManager()->track($component)</>',
            'Log custom events: <options=bold>Tapped::getEventLogger()->log($event, $data)</>',
        ]);
        $this->newLine();
    }
}
