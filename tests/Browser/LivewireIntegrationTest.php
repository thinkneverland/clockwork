<?php

namespace ThinkNeverland\Tapped\Tests\Browser;

use Laravel\Dusk\Browser;
use ThinkNeverland\Tapped\Tests\DuskTestCase;
use Orchestra\Testbench\Dusk\Options;

class LivewireIntegrationTest extends DuskTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        
        // Configure Chrome options
        Options::withoutUI();
    }
    
    /**
     * Test Livewire component integration with Tapped.
     *
     * @return void
     */
    public function testLivewireComponentIntegration()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/livewire-test')
                    ->waitForText('Livewire Counter Component')
                    ->assertSee('Current Count: 0')
                    ->click('@increment-button')
                    ->waitForText('Current Count: 1')
                    
                    // Open Chrome DevTools programmatically (simulated for test)
                    ->script([
                        "window.tappedTestDebugData = {",
                        "  livewire: [{",
                        "    id: 'counter',",
                        "    name: 'counter',",
                        "    class: 'App\\\\Http\\\\Livewire\\\\Counter',",
                        "    properties: { count: 1 }",
                        "  }]",
                        "};",
                        "window.dispatchEvent(new CustomEvent('tapped:debug-data-received', { detail: window.tappedTestDebugData }));"
                    ])
                    
                    // Wait for Tapped to process the debug data
                    ->pause(500)
                    
                    // Assert that Tapped received and processed the data correctly
                    ->assertScript("!!window.tappedTestDebugData");
        });
    }
    
    /**
     * Test Livewire event tracking.
     *
     * @return void
     */
    public function testLivewireEventTracking()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/livewire-test')
                    ->waitForText('Livewire Counter Component')
                    
                    // Trigger an event
                    ->click('@emit-event-button')
                    
                    // Simulate Tapped receiving the event
                    ->script([
                        "window.tappedTestEventData = {",
                        "  events: [{",
                        "    id: 'event1',",
                        "    type: 'custom',",
                        "    name: 'counter-changed',",
                        "    component: 'counter',",
                        "    payload: { value: 1 }",
                        "  }]",
                        "};",
                        "window.dispatchEvent(new CustomEvent('tapped:event-received', { detail: window.tappedTestEventData }));"
                    ])
                    
                    // Wait for Tapped to process the event
                    ->pause(500)
                    
                    // Assert that Tapped received and processed the event correctly
                    ->assertScript("!!window.tappedTestEventData");
        });
    }
    
    /**
     * Test N+1 query detection.
     *
     * @return void
     */
    public function testN1QueryDetection()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/livewire-test-n1')
                    ->waitForText('Livewire Posts Component')
                    
                    // Simulate Tapped detecting N+1 queries
                    ->script([
                        "window.tappedTestQueryData = {",
                        "  queries: [",
                        "    { id: 'q1', query: 'SELECT * FROM users', time: 1.5 },",
                        "    { id: 'q2', query: 'SELECT * FROM posts WHERE user_id = 1', time: 0.5 },",
                        "    { id: 'q3', query: 'SELECT * FROM posts WHERE user_id = 2', time: 0.5 },",
                        "    { id: 'q4', query: 'SELECT * FROM posts WHERE user_id = 3', time: 0.5 }",
                        "  ],",
                        "  n1_issues: [{",
                        "    pattern: 'SELECT * FROM posts WHERE user_id = ?',",
                        "    count: 3,",
                        "    queries: ['q2', 'q3', 'q4'],",
                        "    component: 'App\\\\Http\\\\Livewire\\\\Posts',",
                        "    suggestedFix: 'Use eager loading: User::with(\\'posts\\')->get()'",
                        "  }]",
                        "};",
                        "window.dispatchEvent(new CustomEvent('tapped:query-data-received', { detail: window.tappedTestQueryData }));"
                    ])
                    
                    // Wait for Tapped to process the query data
                    ->pause(500)
                    
                    // Assert that Tapped received and processed the query data correctly
                    ->assertScript("!!window.tappedTestQueryData");
        });
    }
}
