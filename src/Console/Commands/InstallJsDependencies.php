<?php

namespace ThinkNeverland\Tapped\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ThinkNeverland\Tapped\TappedServiceProvider;

class InstallJsDependencies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tapped:install-deps
                        {--node-only : Only include the core dependencies needed for Node.js integration}
                        {--npm : Use npm instead of yarn}
                        {--path= : Custom installation path}
                        {--no-scripts : Do not add development scripts to package.json}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install JavaScript dependencies required by Tapped for development';

    /**
     * Common development scripts to include
     * 
     * @var array
     */
    protected $devScripts = [
        'tapped:dev' => 'concurrently "vite" "nodemon --watch src"',
        'tapped:watch' => 'vite build --watch',
        'tapped:build' => 'vite build',
        'tapped:serve' => 'browser-sync start --server "public" --files "public/**/*"',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🛠️  Setting up Tapped development dependencies');
        $this->comment('This command will install all packages needed for Tapped in development.');

        // Get dependencies from the service provider
        /** @var TappedServiceProvider $provider */
        $provider = app()->make(TappedServiceProvider::class);

        // Since we're in dev mode, always include all dependencies
        $allDependencies = $provider->getAllDependencies();
        $dependencies = $this->option('node-only')
            ? $this->filterEssentialDependencies($allDependencies['dependencies'])
            : $allDependencies['dependencies'];
        $devDependencies = $this->option('node-only')
            ? []
            : $allDependencies['devDependencies'];

        // Determine installation path
        $path = $this->option('path') ?? base_path();

        // Check if package.json exists
        $packageJsonPath = $path . '/package.json';
        if (!File::exists($packageJsonPath)) {
            $this->warn("No package.json found in $path");

            if ($this->confirm('Create a new package.json file?', true)) {
                $packageJson = $this->createDefaultPackageJson();

                File::put(
                    $packageJsonPath,
                    json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );

                $this->info('✅ Created package.json file');
            } else {
                return 1;
            }
        }

        // Read existing package.json
        $packageJson = json_decode(File::get($packageJsonPath), true);

        // Add dependencies
        if (!isset($packageJson['dependencies'])) {
            $packageJson['dependencies'] = [];
        }

        foreach ($dependencies as $name => $version) {
            $packageJson['dependencies'][$name] = $version;
        }

        // Add dev dependencies
        if (!empty($devDependencies)) {
            if (!isset($packageJson['devDependencies'])) {
                $packageJson['devDependencies'] = [];
            }

            foreach ($devDependencies as $name => $version) {
                $packageJson['devDependencies'][$name] = $version;
            }
        }

        // Add development scripts
        if (!$this->option('no-scripts')) {
            if (!isset($packageJson['scripts'])) {
                $packageJson['scripts'] = [];
            }

            foreach ($this->devScripts as $name => $script) {
                if (!isset($packageJson['scripts'][$name])) {
                    $packageJson['scripts'][$name] = $script;
                }
            }

            $this->info('✅ Added development scripts (tapped:dev, tapped:watch, etc.)');
        }

        // Write updated package.json
        File::put(
            $packageJsonPath,
            json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->info('✅ Updated package.json with Tapped dependencies');

        // Install dependencies
        if ($this->confirm('Install the dependencies now?', true)) {
            $useNpm = $this->option('npm');
            $command = $useNpm ? 'npm install' : 'yarn install';

            $this->line('');
            $this->info("⚙️  Running: $command");
            $this->line('');

            $process = proc_open($command, [
                0 => STDIN,
                1 => STDOUT,
                2 => STDERR,
            ], $pipes, $path);

            $exitCode = proc_close($process);

            if ($exitCode === 0) {
                $this->newLine();
                $this->info('✅ Dependencies installed successfully');

                $this->line('');
                $this->info('🚀 You can now use these commands:');
                $this->line('   - yarn tapped:dev       Run Tapped in development mode');
                $this->line('   - yarn tapped:watch     Continuously build Tapped');
                $this->line('   - yarn tapped:build     Build Tapped for distribution');
                $this->line('   - yarn tapped:serve     Serve Tapped with browser sync');
                $this->line('');
            } else {
                $this->error('❌ Failed to install dependencies');
                return 1;
            }
        }

        return 0;
    }

    /**
     * Create a default package.json configuration
     * 
     * @return array
     */
    protected function createDefaultPackageJson(): array
    {
        return [
            'name' => basename(base_path()),
            'private' => true,
            'version' => '0.1.0',
            'description' => 'Laravel application with Tapped development tools',
            'engines' => [
                'node' => '>=16.0.0',
                'npm' => '>=8.0.0'
            ],
            'dependencies' => new \stdClass(),
            'devDependencies' => new \stdClass(),
            'scripts' => []
        ];
    }

    /**
     * Filter to only include essential dependencies for basic integration
     * 
     * @param array $dependencies
     * @return array
     */
    protected function filterEssentialDependencies(array $dependencies): array
    {
        $essentialPackages = [
            'punycode',
            'urijs',
            'vue',
            'lodash',
            'date-fns'
        ];

        return array_intersect_key($dependencies, array_flip($essentialPackages));
    }
}
