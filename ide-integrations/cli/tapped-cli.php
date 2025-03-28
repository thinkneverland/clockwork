#!/usr/bin/env php
<?php

/**
 * Tapped CLI - Command Line Interface for Tapped API
 * 
 * This CLI tool provides access to Tapped debugging features from the command line,
 * making it easy to integrate with various IDEs and development workflows.
 */

// Bootstrap
require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;

// Configuration
$configFile = getenv('HOME') . '/.tapped-cli.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];

$defaultApiUrl = $config['api_url'] ?? 'http://localhost:8000/tapped/api';
$defaultAuthToken = $config['auth_token'] ?? '';

// Create application
$application = new Application('Tapped CLI', '1.0.0');

// Configure command
$application->register('components')
    ->setDescription('List Livewire components')
    ->setHelp('This command lists all Livewire components detected by Tapped')
    ->addOption(
        'api-url',
        'u',
        InputOption::VALUE_REQUIRED,
        'The Tapped API URL',
        $defaultApiUrl
    )
    ->addOption(
        'auth-token',
        't',
        InputOption::VALUE_REQUIRED,
        'Authentication token',
        $defaultAuthToken
    )
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $io = new SymfonyStyle($input, $output);
        $apiUrl = $input->getOption('api-url');
        $authToken = $input->getOption('auth-token');
        
        $io->title('Tapped Livewire Components');
        
        try {
            $client = new Client();
            $response = $client->get($apiUrl . '/debug-data/livewire', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $authToken,
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (!isset($data['success']) || !$data['success']) {
                $io->error('API returned an error: ' . ($data['message'] ?? 'Unknown error'));
                return Command::FAILURE;
            }
            
            $components = $data['data'] ?? [];
            
            if (empty($components)) {
                $io->warning('No Livewire components found');
                return Command::SUCCESS;
            }
            
            $table = new Table($output);
            $table->setHeaders(['ID', 'Name', 'Class']);
            
            foreach ($components as $component) {
                $table->addRow([
                    $component['id'] ?? 'N/A',
                    $component['name'] ?? 'N/A',
                    $component['class'] ?? 'N/A',
                ]);
            }
            
            $table->render();
            return Command::SUCCESS;
        } catch (GuzzleException $e) {
            $io->error('Failed to connect to API: ' . $e->getMessage());
            return Command::FAILURE;
        }
    });

$application->register('queries')
    ->setDescription('List database queries')
    ->setHelp('This command lists database queries captured by Tapped')
    ->addOption(
        'api-url',
        'u',
        InputOption::VALUE_REQUIRED,
        'The Tapped API URL',
        $defaultApiUrl
    )
    ->addOption(
        'auth-token',
        't',
        InputOption::VALUE_REQUIRED,
        'Authentication token',
        $defaultAuthToken
    )
    ->addOption(
        'n1-detection',
        'n',
        InputOption::VALUE_NONE,
        'Enable N+1 query detection'
    )
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $io = new SymfonyStyle($input, $output);
        $apiUrl = $input->getOption('api-url');
        $authToken = $input->getOption('auth-token');
        $n1Detection = $input->getOption('n1-detection');
        
        $io->title('Tapped Database Queries');
        
        try {
            $client = new Client();
            $url = $apiUrl . '/debug-data/queries';
            if ($n1Detection) {
                $url .= '?n1_detection=true';
            }
            
            $response = $client->get($url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $authToken,
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (!isset($data['success']) || !$data['success']) {
                $io->error('API returned an error: ' . ($data['message'] ?? 'Unknown error'));
                return Command::FAILURE;
            }
            
            if ($n1Detection) {
                $queries = $data['data']['queries'] ?? [];
                $n1Issues = $data['data']['n1_issues'] ?? [];
            } else {
                $queries = $data['data'] ?? [];
            }
            
            if (empty($queries)) {
                $io->warning('No database queries found');
                return Command::SUCCESS;
            }
            
            $table = new Table($output);
            $table->setHeaders(['ID', 'Query', 'Time (ms)', 'Connection']);
            
            foreach ($queries as $query) {
                // Truncate query to keep table readable
                $queryText = $query['query'] ?? 'N/A';
                if (strlen($queryText) > 80) {
                    $queryText = substr($queryText, 0, 77) . '...';
                }
                
                $table->addRow([
                    $query['id'] ?? 'N/A',
                    $queryText,
                    $query['time'] ?? 'N/A',
                    $query['connectionName'] ?? 'N/A',
                ]);
            }
            
            $table->render();
            
            // Show N+1 issues if detection was enabled
            if ($n1Detection && !empty($n1Issues)) {
                $io->section('N+1 Query Issues');
                
                $n1Table = new Table($output);
                $n1Table->setHeaders(['Pattern', 'Count', 'Component', 'Suggested Fix']);
                
                foreach ($n1Issues as $issue) {
                    $n1Table->addRow([
                        $issue['pattern'] ?? 'N/A',
                        $issue['count'] ?? 0,
                        $issue['component'] ?? 'N/A',
                        $issue['suggestedFix'] ?? 'N/A',
                    ]);
                }
                
                $n1Table->render();
            }
            
            return Command::SUCCESS;
        } catch (GuzzleException $e) {
            $io->error('Failed to connect to API: ' . $e->getMessage());
            return Command::FAILURE;
        }
    });

$application->register('events')
    ->setDescription('List events')
    ->setHelp('This command lists events captured by Tapped')
    ->addOption(
        'api-url',
        'u',
        InputOption::VALUE_REQUIRED,
        'The Tapped API URL',
        $defaultApiUrl
    )
    ->addOption(
        'auth-token',
        't',
        InputOption::VALUE_REQUIRED,
        'Authentication token',
        $defaultAuthToken
    )
    ->addOption(
        'type',
        'y',
        InputOption::VALUE_REQUIRED,
        'Filter by event type'
    )
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $io = new SymfonyStyle($input, $output);
        $apiUrl = $input->getOption('api-url');
        $authToken = $input->getOption('auth-token');
        $type = $input->getOption('type');
        
        $io->title('Tapped Events');
        
        try {
            $client = new Client();
            $url = $apiUrl . '/debug-data/events';
            if ($type) {
                $url .= '?type=' . urlencode($type);
            }
            
            $response = $client->get($url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $authToken,
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (!isset($data['success']) || !$data['success']) {
                $io->error('API returned an error: ' . ($data['message'] ?? 'Unknown error'));
                return Command::FAILURE;
            }
            
            $events = $data['data'] ?? [];
            
            if (empty($events)) {
                $io->warning('No events found');
                return Command::SUCCESS;
            }
            
            $table = new Table($output);
            $table->setHeaders(['ID', 'Type', 'Name', 'Component', 'Timestamp']);
            
            foreach ($events as $event) {
                $table->addRow([
                    $event['id'] ?? 'N/A',
                    $event['type'] ?? 'N/A',
                    $event['name'] ?? 'N/A',
                    $event['component'] ?? 'N/A',
                    $event['timestamp'] ?? 'N/A',
                ]);
            }
            
            $table->render();
            return Command::SUCCESS;
        } catch (GuzzleException $e) {
            $io->error('Failed to connect to API: ' . $e->getMessage());
            return Command::FAILURE;
        }
    });

$application->register('screenshot')
    ->setDescription('Capture a screenshot')
    ->setHelp('This command captures a screenshot of the current page')
    ->addOption(
        'api-url',
        'u',
        InputOption::VALUE_REQUIRED,
        'The Tapped API URL',
        $defaultApiUrl
    )
    ->addOption(
        'auth-token',
        't',
        InputOption::VALUE_REQUIRED,
        'Authentication token',
        $defaultAuthToken
    )
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $io = new SymfonyStyle($input, $output);
        $apiUrl = $input->getOption('api-url');
        $authToken = $input->getOption('auth-token');
        
        $io->title('Tapped Screenshot Capture');
        
        try {
            $client = new Client();
            $response = $client->post($apiUrl . '/screenshot/capture', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $authToken,
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (!isset($data['success']) || !$data['success']) {
                $io->error('API returned an error: ' . ($data['message'] ?? 'Unknown error'));
                return Command::FAILURE;
            }
            
            $screenshot = $data['data'] ?? [];
            
            $io->success('Screenshot captured successfully');
            $io->table(
                ['Property', 'Value'],
                [
                    ['ID', $screenshot['id'] ?? 'N/A'],
                    ['Filename', $screenshot['filename'] ?? 'N/A'],
                    ['Path', $screenshot['path'] ?? 'N/A'],
                    ['URL', $screenshot['url'] ?? 'N/A'],
                ]
            );
            
            return Command::SUCCESS;
        } catch (GuzzleException $e) {
            $io->error('Failed to connect to API: ' . $e->getMessage());
            return Command::FAILURE;
        }
    });

$application->register('config')
    ->setDescription('Configure the CLI tool')
    ->setHelp('This command configures the CLI tool with API URL and authentication token')
    ->addOption(
        'api-url',
        'u',
        InputOption::VALUE_REQUIRED,
        'The Tapped API URL'
    )
    ->addOption(
        'auth-token',
        't',
        InputOption::VALUE_REQUIRED,
        'Authentication token'
    )
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($configFile, $config) {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Tapped CLI Configuration');
        
        $apiUrl = $input->getOption('api-url');
        $authToken = $input->getOption('auth-token');
        
        if (!$apiUrl && !$authToken) {
            $apiUrl = $io->ask('Enter the Tapped API URL', $config['api_url'] ?? 'http://localhost:8000/tapped/api');
            $authToken = $io->ask('Enter the authentication token', $config['auth_token'] ?? '');
        }
        
        $config['api_url'] = $apiUrl ?: $config['api_url'] ?? '';
        $config['auth_token'] = $authToken ?: $config['auth_token'] ?? '';
        
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
        
        $io->success('Configuration saved to ' . $configFile);
        return Command::SUCCESS;
    });

// Run the application
$application->run();
