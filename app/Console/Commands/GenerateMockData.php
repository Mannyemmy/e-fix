<?php

namespace App\Console\Commands;

use App\Services\MockDataService;
use Illuminate\Console\Command;

class GenerateMockData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mock-data:generate {--clear : Clear existing mock data} {--regenerate : Regenerate mock data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate or manage mock data for demo admin panel';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if ($this->option('clear')) {
            $this->clearMockData();
            $this->info('Mock data cleared.');
            return 0;
        }

        if ($this->option('regenerate')) {
            $this->regenerateMockData();
            return 0;
        }

        $this->generateMockData();
        return 0;
    }

    /**
     * Generate mock data
     */
    protected function generateMockData()
    {
        $this->info('Generating mock data for demo admin panel...');

        $mockService = new MockDataService();
        $mockData = $mockService->getPersistedMockDashboardData(true);

        $this->info('Mock dashboard data generated successfully!');
        $this->info('Total bookings: ' . $mockData['total_booking']);
        $this->info('Total services: ' . $mockData['total_service']);
        $this->info('Total providers: ' . $mockData['total_provider']);
        $this->info('Total revenue: $' . number_format($mockData['total_revenue'], 2));
    }

    /**
     * Regenerate mock data with new random values
     */
    protected function regenerateMockData()
    {
        $this->info('Regenerating mock data...');
        
        $mockService = new MockDataService();
        $mockData = $mockService->getPersistedMockDashboardData(true);

        $this->info('Mock data regenerated successfully!');
    }

    /**
     * Clear mock data from cache
     */
    protected function clearMockData()
    {
        cache()->forget(MockDataService::DASHBOARD_CACHE_KEY);
        $this->info('Mock data cache cleared.');
    }
}
