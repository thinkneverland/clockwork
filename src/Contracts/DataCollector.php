<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\Contracts;

interface DataCollector
{
    /**
     * Collect data for the current request.
     *
     * @return array<string, mixed>
     */
    public function collect(): array;

    /**
     * Get the unique name for this collector.
     */
    public function getName(): string;

    /**
     * Determine if this collector should be executed.
     */
    public function shouldCollect(): bool;

    /**
     * Start data collection if this collector has starting logic.
     */
    public function startCollecting(): void;

    /**
     * Stop data collection if this collector has stopping logic.
     */
    public function stopCollecting(): void;
}
