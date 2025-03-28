<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\DataCollectors;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseQueryCollector extends AbstractDataCollector
{
    /**
     * @var array<int, array<string, mixed>> Collected queries
     */
    protected array $queries = [];

    /**
     * @var array<string, array<int, int>> Track duplicate queries for N+1 detection
     */
    protected array $queryHashes = [];

    /**
     * @var array<string, array<string, mixed>> Detected N+1 query issues
     */
    protected array $nPlusOneIssues = [];

    /**
     * Start collecting database queries.
     */
    public function startCollecting(): void
    {
        DB::listen(function (QueryExecuted $query) {
            $this->recordQuery($query);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        $this->detectNPlusOneIssues();

        return [
            'queries' => $this->queries,
            'total_queries' => count($this->queries),
            'total_time' => array_sum(array_column($this->queries, 'time')),
            'duplicate_queries' => $this->countDuplicateQueries(),
            'n_plus_one_issues' => $this->nPlusOneIssues,
        ];
    }

    /**
     * Record a database query.
     */
    protected function recordQuery(QueryExecuted $event): void
    {
        $sql = $event->sql;
        $bindings = $event->bindings;
        $time = $event->time;
        $connection = $event->connectionName;

        // Format SQL query with bindings
        $query = $this->formatSql($sql, $bindings);
        
        // Compute backtrace to determine query origin
        $backtrace = $this->getBacktrace();

        // Generate a hash for similar query detection (without bindings)
        $queryHash = md5($sql);

        // Store the trace hash to group related queries
        $traceHash = md5(json_encode(array_column($backtrace, 'file') . array_column($backtrace, 'line')));

        // Track for N+1 detection
        if (!isset($this->queryHashes[$traceHash])) {
            $this->queryHashes[$traceHash] = [];
        }
        
        if (!isset($this->queryHashes[$traceHash][$queryHash])) {
            $this->queryHashes[$traceHash][$queryHash] = 0;
        }
        
        $this->queryHashes[$traceHash][$queryHash]++;

        // Store the query details
        $this->queries[] = [
            'query' => $query,
            'raw_sql' => $sql,
            'bindings' => $bindings,
            'time' => $time,
            'connection' => $connection,
            'file' => $backtrace[0]['file'] ?? null,
            'line' => $backtrace[0]['line'] ?? null,
            'trace_hash' => $traceHash,
            'query_hash' => $queryHash,
        ];
    }

    /**
     * Format SQL with bindings.
     *
     * @param string $sql
     * @param array<int, mixed> $bindings
     * @return string
     */
    protected function formatSql(string $sql, array $bindings): string
    {
        $sql = str_replace(['%', '?'], ['%%', '%s'], $sql);
        
        $formattedBindings = array_map(function ($binding) {
            if (is_null($binding)) {
                return 'NULL';
            }
            
            if (is_bool($binding)) {
                return $binding ? 'TRUE' : 'FALSE';
            }
            
            if (is_string($binding)) {
                return "'" . addslashes($binding) . "'";
            }
            
            return $binding;
        }, $bindings);
        
        return vsprintf($sql, $formattedBindings);
    }

    /**
     * Get a backtrace that excludes vendor and framework code.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getBacktrace(): array
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 50);
        
        // Remove Database and internal framework code from the trace
        $backtrace = array_filter($backtrace, function ($trace) {
            if (!isset($trace['file'])) {
                return false;
            }
            
            $file = $trace['file'];
            
            // Exclude framework files
            if (Str::contains($file, [
                '/vendor/laravel/framework/',
                '/vendor/illuminate/',
                '/Database/Connection.php',
                '/vendor/thinkneverland/tapped/',
            ])) {
                return false;
            }
            
            return true;
        });
        
        return array_values($backtrace);
    }

    /**
     * Count the number of duplicate queries.
     */
    protected function countDuplicateQueries(): int
    {
        $duplicates = 0;
        $queryHashes = [];
        
        foreach ($this->queries as $query) {
            $hash = $query['query_hash'];
            
            if (!isset($queryHashes[$hash])) {
                $queryHashes[$hash] = 0;
            }
            
            $queryHashes[$hash]++;
            
            if ($queryHashes[$hash] > 1) {
                $duplicates++;
            }
        }
        
        return $duplicates;
    }

    /**
     * Detect potential N+1 query issues.
     */
    protected function detectNPlusOneIssues(): void
    {
        if (!Config::get('tapped.detect_n_plus_1_queries', true)) {
            return;
        }
        
        // Threshold for N+1 detection - can be configured
        $threshold = Config::get('tapped.n_plus_1_threshold', 5);
        
        foreach ($this->queryHashes as $traceHash => $queries) {
            foreach ($queries as $queryHash => $count) {
                // If we have many of the same query from the same trace, it's likely an N+1 issue
                if ($count >= $threshold) {
                    // Collect all relevant queries for deeper analysis
                    $relevantQueries = [];
                    $totalTime = 0;
                    $file = null;
                    $line = null;
                    $queryPattern = null;
                    $tables = [];
                    
                    // Find all instances of this query pattern
                    foreach ($this->queries as $queryIndex => $query) {
                        if ($query['query_hash'] === $queryHash && $query['trace_hash'] === $traceHash) {
                            $relevantQueries[] = $queryIndex;
                            $totalTime += $query['time'];
                            
                            // Store the file and line of the first occurrence
                            if ($file === null) {
                                $queryPattern = $query['raw_sql'];
                                $file = $query['file'];
                                $line = $query['line'];
                                
                                // Extract table names from the query
                                $tables = $this->extractTablesFromQuery($query['raw_sql']);
                            }
                        }
                    }
                    
                    // Generate optimization recommendations
                    $recommendations = $this->generateOptimizationRecommendations($queryPattern, $count, $tables);
                    
                    // Add the issue to our list
                    $this->nPlusOneIssues[] = [
                        'query_pattern' => $queryPattern,
                        'example' => $this->queries[$relevantQueries[0]]['query'],
                        'count' => $count,
                        'total_time' => $totalTime,
                        'file' => $file,
                        'line' => $line,
                        'tables' => $tables,
                        'query_indices' => $relevantQueries,
                        'severity' => $this->calculateSeverity($count, $totalTime),
                        'recommendations' => $recommendations,
                    ];
                }
            }
        }
        
        // Sort issues by severity
        usort($this->nPlusOneIssues, function ($a, $b) {
            return $b['severity'] <=> $a['severity'];
        });
    }
    
    /**
     * Extract table names from a SQL query.
     *
     * @param string $sql
     * @return array<int, string>
     */
    protected function extractTablesFromQuery(string $sql): array
    {
        $tables = [];
        
        // Look for FROM clause
        if (preg_match('/FROM\s+`?([\w\d_]+)`?/i', $sql, $matches)) {
            $tables[] = $matches[1];
        }
        
        // Look for JOIN clauses
        if (preg_match_all('/JOIN\s+`?([\w\d_]+)`?/i', $sql, $matches)) {
            $tables = array_merge($tables, $matches[1]);
        }
        
        return array_unique($tables);
    }
    
    /**
     * Calculate severity of the N+1 issue.
     *
     * @param int $count
     * @param float $totalTime
     * @return int 1-10 severity rating
     */
    protected function calculateSeverity(int $count, float $totalTime): int
    {
        // Base severity on query count
        $countSeverity = min(10, max(1, (int) ($count / 10)));
        
        // Time factor (milliseconds)
        $timeSeverity = min(10, max(1, (int) ($totalTime / 100)));
        
        // Combined severity weighted more towards count
        return min(10, max(1, (int) (($countSeverity * 0.7) + ($timeSeverity * 0.3))));
    }
    
    /**
     * Generate optimization recommendations for N+1 issues.
     *
     * @param string|null $queryPattern
     * @param int $count
     * @param array<int, string> $tables
     * @return array<int, string>
     */
    protected function generateOptimizationRecommendations(?string $queryPattern, int $count, array $tables): array
    {
        $recommendations = [];
        
        // If it looks like a select query
        if ($queryPattern && stripos($queryPattern, 'SELECT') === 0) {
            $recommendations[] = 'Use eager loading with the "with()" method to load relationships in a single query';
            
            if (count($tables) > 0) {
                $tablesStr = implode(', ', $tables);
                $recommendations[] = "Consider adding indexes on foreign keys in the {$tablesStr} table(s)";
            }
            
            $recommendations[] = 'Use a chunked query approach for processing large dataset iterations';
        }
        
        $recommendations[] = "This query was executed {$count} times in a loop - consider refactoring to use a single query";
        
        return $recommendations;
    }
}
