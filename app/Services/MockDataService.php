<?php

namespace App\Services;

use App\Models\{
    Booking,
    BookingHandymanMapping,
    BookingServiceAddonMapping,
    BookingRating,
    Category,
    Service,
    User,
    Payment,
    PostJobRequest,
    PostJobServiceMapping,
    Coupon,
    CouponServiceMapping,
};
use Carbon\Carbon;

class MockDataService
{
    protected $faker;
    const DASHBOARD_CACHE_KEY = 'mock_dashboard_data';
    const EDO_LOCATIONS = [
        'Benin City',
        'Ekpoma',
        'Auchi',
        'Uromi',
        'Ubiaja',
        'Irrua',
        'Igarra',
        'Sabongida-Ora',
        'Fugar',
        'Agbor',
    ];
    
    const MOCK_ADMIN_ID = 99999;
    const MOCK_ADMIN_EMAIL = 'demo_admin@mockdata.test';
    
    public function __construct()
    {
        $this->faker = $this->buildDataGenerator();
    }

    /**
     * Build a data generator instance.
     * Uses Faker when available, otherwise uses an internal fallback.
     */
    protected function buildDataGenerator()
    {
        if (class_exists('Faker\\Factory')) {
            return \Faker\Factory::create('en_NG');
        }

        return new class {
            protected $first = ['Chinedu', 'Adebayo', 'Ifeoma', 'Ngozi', 'Tunde', 'Sade', 'Emeka', 'Chioma', 'Bola', 'Kemi', 'Uche', 'Folake'];
            protected $last = ['Okafor', 'Adeyemi', 'Nwankwo', 'Balogun', 'Eze', 'Ogunleye', 'Ibrahim', 'Ojo', 'Onyeka', 'Afolabi'];
            protected $words = ['repair', 'service', 'booking', 'support', 'request', 'urgent', 'scheduled', 'confirmed'];

            // Match Faker behavior where formatters are often accessed as properties.
            public function __get($name)
            {
                if (method_exists($this, $name)) {
                    return $this->{$name}();
                }

                return null;
            }

            public function userName()
            {
                return strtolower($this->firstName()) . rand(10, 9999);
            }

            public function firstName()
            {
                return $this->first[array_rand($this->first)];
            }

            public function lastName()
            {
                return $this->last[array_rand($this->last)];
            }

            public function name()
            {
                return $this->firstName() . ' ' . $this->lastName();
            }

            public function email()
            {
                return strtolower($this->firstName()) . rand(1, 999) . '@example.com';
            }

            public function phoneNumber()
            {
                return '+1-' . rand(200, 999) . '-' . rand(100, 999) . '-' . rand(1000, 9999);
            }

            public function randomFloat($decimals = 2, $min = 0, $max = 1)
            {
                $value = $min + mt_rand() / mt_getrandmax() * ($max - $min);
                return round($value, (int) $decimals);
            }

            public function sentence()
            {
                return ucfirst($this->word()) . ' ' . $this->word() . ' ' . $this->word() . '.';
            }

            public function paragraph()
            {
                return $this->sentence() . ' ' . $this->sentence() . ' ' . $this->sentence();
            }

            public function word()
            {
                return $this->words[array_rand($this->words)];
            }

            public function address()
            {
                return rand(100, 9999) . ' Main Street';
            }

            public function latitude()
            {
                return (string) round(-90 + mt_rand() / mt_getrandmax() * 180, 6);
            }

            public function longitude()
            {
                return (string) round(-180 + mt_rand() / mt_getrandmax() * 360, 6);
            }

            public function randomElement(array $items)
            {
                return $items[array_rand($items)];
            }

            public function boolean()
            {
                return (bool) rand(0, 1);
            }
        };
    }
    
    /**
     * Generate comprehensive mock data for dashboard
     */
    public function generateMockDashboardData()
    {
        return [
            'total_booking' => rand(250, 500),
            'total_service' => rand(50, 120),
            'total_provider' => rand(30, 80),
            'total_revenue' => rand(50000, 250000),
            'monthly_revenue' => rand(10000, 50000),
            'provider' => $this->generateMockProviders(5),
            'user' => $this->generateMockCustomers(5),
            'upcomming_booking' => $this->generateMockBookings(5),
            'post_job_requests' => $this->generateMockPostJobRequests(5),
            'recent_messages' => $this->generateMockMessages(8),
            'notification_unread_count' => rand(0, 15),
            'generated_at' => Carbon::now()->toDateTimeString(),
            'status' => true
        ];
    }

    /**
     * Return stable mock snapshot persisted in cache.
     * Background scheduler can regenerate this periodically.
     */
    public function getPersistedMockDashboardData($forceRegenerate = false, $autoRefreshAfterMinutes = 60)
    {
        if ($forceRegenerate || !cache()->has(self::DASHBOARD_CACHE_KEY)) {
            $mockData = $this->generateMockDashboardData();
            cache()->put(self::DASHBOARD_CACHE_KEY, $mockData, now()->addDays(7));
            return $mockData;
        }

        $cachedData = cache()->get(self::DASHBOARD_CACHE_KEY);

        // Fallback: if scheduler is down, refresh once data gets stale.
        if ($autoRefreshAfterMinutes > 0) {
            $generatedAt = isset($cachedData['generated_at']) ? Carbon::parse($cachedData['generated_at']) : null;

            if (is_null($generatedAt) || $generatedAt->lte(Carbon::now()->subMinutes($autoRefreshAfterMinutes))) {
                $mockData = $this->generateMockDashboardData();
                cache()->put(self::DASHBOARD_CACHE_KEY, $mockData, now()->addDays(7));
                return $mockData;
            }
        }

        return $cachedData;
    }

    /**
     * Force delete old snapshot and create a fresh one.
     */
    public function resetPersistedMockDashboardData()
    {
        cache()->forget(self::DASHBOARD_CACHE_KEY);
        return $this->getPersistedMockDashboardData(true);
    }

    /**
     * Build a mock user list compatible with admin users datatable.
     */
    public function getMockAdminUsersList($listStatus = null, $count = 40)
    {
        $items = [];

        for ($i = 1; $i <= $count; $i++) {
            $typePool = ['user', 'provider', 'handyman'];
            $userType = $typePool[array_rand($typePool)];
            $first = $this->faker->firstName();
            $last = $this->faker->lastName();

            $items[] = [
                'id' => 7000 + $i,
                'first_name' => $first,
                'last_name' => $last,
                'display_name' => trim($first . ' ' . $last),
                'email' => strtolower($first) . '.' . strtolower($last) . rand(1, 999) . '@example.ng',
                'contact_number' => '+234' . rand(7000000000, 9099999999),
                'address' => $this->generateEdoAddress(),
                'status' => rand(0, 1),
                'user_type' => $userType,
                'is_email_verified' => rand(0, 1),
                'created_at' => Carbon::now()->subDays(rand(1, 180))->subHours(rand(0, 23))->toDateTimeString(),
            ];
        }

        $filtered = collect($items);

        if ($listStatus === 'unverified') {
            $filtered = $filtered->where('is_email_verified', 0)->values();
        } elseif ($listStatus === 'all') {
            $filtered = $filtered->values();
        } else {
            $filtered = $filtered->where('user_type', 'user')->values();
        }

        return $filtered;
    }

    public function getMockProvidersList($listStatus = null, $count = 40)
    {
        $providerTypes = ['Company', 'Individual', 'Agency'];
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $first = $this->faker->firstName();
            $last = $this->faker->lastName();
            $status = rand(0, 1);
            $isSubscribe = rand(0, 1);

            $rows[] = [
                'id' => 8000 + $i,
                'first_name' => $first,
                'last_name' => $last,
                'display_name' => trim($first . ' ' . $last),
                'email' => strtolower($first) . '.' . strtolower($last) . rand(1, 999) . '@example.ng',
                'contact_number' => '+234' . rand(7000000000, 9099999999),
                'address' => $this->generateEdoAddress(),
                'providertype_id' => $providerTypes[array_rand($providerTypes)],
                'status' => $status,
                'is_subscribe' => $isSubscribe,
                'created_at' => Carbon::now()->subDays(rand(1, 180))->subHours(rand(0, 23))->toDateTimeString(),
            ];
        }

        $collection = collect($rows);

        if ($listStatus === 'pending') {
            $collection = $collection->where('status', 0)->values();
        } elseif ($listStatus === 'subscribe') {
            $collection = $collection->where('status', 1)->where('is_subscribe', 1)->values();
        } else {
            $collection = $collection->where('status', 1)->values();
        }

        return $collection;
    }

    public function getMockHandymenList($listStatus = null, $count = 40)
    {
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $first = $this->faker->firstName();
            $last = $this->faker->lastName();
            $status = rand(0, 1);
            $providerName = $this->faker->name();

            $rows[] = [
                'id' => 9000 + $i,
                'first_name' => $first,
                'last_name' => $last,
                'display_name' => trim($first . ' ' . $last),
                'email' => strtolower($first) . '.' . strtolower($last) . rand(1, 999) . '@example.ng',
                'contact_number' => '+234' . rand(7000000000, 9099999999),
                'address' => $this->generateEdoAddress(),
                'provider_name' => $providerName,
                'status' => $status,
                'created_at' => Carbon::now()->subDays(rand(1, 180))->subHours(rand(0, 23))->toDateTimeString(),
            ];
        }

        $collection = collect($rows);

        if ($listStatus === 'pending' || $listStatus === 'request') {
            $collection = $collection->where('status', 0)->values();
        } elseif ($listStatus === 'unassigned') {
            $collection = $collection->where('status', 1)->values();
        } else {
            $collection = $collection->where('status', 1)->values();
        }

        return $collection;
    }

    public function getMockBookingsList($count = 60)
    {
        $serviceNames = ['Home Plumbing', 'Electrical Fix', 'Deep Cleaning', 'Painting Service', 'Carpentry Work'];
        $paymentStatuses = ['pending', 'paid', 'approved_by_admin'];
        $bookingStatuses = ['pending', 'accept', 'completed', 'cancel'];
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $serviceName = $serviceNames[array_rand($serviceNames)];
            $customerName = $this->faker->name();
            $providerName = $this->faker->name();
            $createdAt = Carbon::now()->subDays(rand(0, 45))->subHours(rand(0, 20));
            $amount = rand(15000, 450000);

            $rows[] = [
                'id' => 10000 + $i,
                'service_name' => $serviceName,
                'date' => $createdAt->toDateTimeString(),
                'customer_name' => $customerName,
                'provider_name' => $providerName,
                'status' => $bookingStatuses[array_rand($bookingStatuses)],
                'total_amount' => $amount,
                'payment_status' => $paymentStatuses[array_rand($paymentStatuses)],
                'updated_at' => $createdAt->toDateTimeString(),
            ];
        }

        return collect($rows);
    }

    public function getMockPaymentsList($count = 60)
    {
        $paymentTypes = ['cash', 'wallet', 'card', 'flutterwave'];
        $paymentStatuses = ['pending', 'paid', 'approved_by_admin', 'pending_by_admin'];
        $serviceNames = ['Home Plumbing', 'Electrical Fix', 'Deep Cleaning', 'Painting Service', 'Carpentry Work'];
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $when = Carbon::now()->subDays(rand(0, 60))->subHours(rand(0, 23));
            $rows[] = [
                'id' => 11000 + $i,
                'booking_name' => $serviceNames[array_rand($serviceNames)],
                'customer_name' => $this->faker->name(),
                'payment_type' => $paymentTypes[array_rand($paymentTypes)],
                'payment_status' => $paymentStatuses[array_rand($paymentStatuses)],
                'datetime' => $when->toDateTimeString(),
                'total_amount' => rand(10000, 600000),
            ];
        }

        return collect($rows);
    }

    public function getMockPostJobRequestsList($count = 50)
    {
        $titles = ['Urgent Plumbing Work', 'House Painting Request', 'Electrical Fault Repair', 'Carpentry Installation', 'Cleaning Support'];
        $statuses = ['requested', 'assigned', 'completed'];
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'id' => 12000 + $i,
                'title' => $titles[array_rand($titles)],
                'provider_name' => $this->faker->name(),
                'customer_name' => $this->faker->name(),
                'status' => $statuses[array_rand($statuses)],
                'price' => rand(15000, 300000),
            ];
        }

        return collect($rows);
    }

    public function getMockWalletsList($count = 50)
    {
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'id' => 13000 + $i,
                'title' => 'Wallet ' . (13000 + $i),
                'user_name' => $this->faker->name(),
                'amount' => rand(5000, 450000),
                'status' => rand(0, 1),
            ];
        }

        return collect($rows);
    }

    protected function generateEdoAddress()
    {
        $city = self::EDO_LOCATIONS[array_rand(self::EDO_LOCATIONS)];
        return rand(1, 300) . ' ' . $this->faker->randomElement(['Sapele Road', 'Airport Road', 'Mission Road', 'GRA', 'Ekenwan Road']) . ', ' . $city . ', Edo State, Nigeria';
    }
    
    /**
     * Generate mock providers with realistic data
     */
    public function generateMockProviders($count = 5)
    {
        $providers = [];
        $serviceTypes = ['Plumbing', 'Electrical', 'Carpentry', 'Cleaning', 'Painting', 'HVAC', 'Landscaping'];
        
        for ($i = 1; $i <= $count; $i++) {
            $providers[] = [
                'id' => 1000 + $i,
                'username' => $this->faker->userName(),
                'first_name' => $this->faker->firstName(),
                'last_name' => $this->faker->lastName(),
                'email' => $this->faker->email(),
                'user_type' => 'provider',
                'contact_number' => '+234' . rand(7000000000, 9099999999),
                'address' => $this->generateEdoAddress(),
                'city_id' => rand(1, 10),
                'status' => 1,
                'is_featured' => rand(0, 1),
                'rating' => round($this->faker->randomFloat(1, 3.5, 5), 1),
                'profile_image' => 'https://i.pravatar.cc/150?img=' . rand(1, 70),
                'service_type' => $serviceTypes[array_rand($serviceTypes)],
                'description' => $this->faker->sentence(),
                'total_bookings' => rand(50, 500),
                'completion_rate' => rand(85, 100),
                'is_email_verified' => 1,
                'media' => [
                    'original_url' => 'https://i.pravatar.cc/150?img=' . rand(1, 70)
                ]
            ];
        }
        
        return $providers;
    }
    
    /**
     * Generate mock customers with realistic data
     */
    public function generateMockCustomers($count = 5)
    {
        $customers = [];
        
        for ($i = 1; $i <= $count; $i++) {
            $customers[] = [
                'id' => 2000 + $i,
                'username' => $this->faker->userName(),
                'first_name' => $this->faker->firstName(),
                'last_name' => $this->faker->lastName(),
                'email' => $this->faker->email(),
                'user_type' => 'user',
                'contact_number' => '+234' . rand(7000000000, 9099999999),
                'address' => $this->generateEdoAddress(),
                'city_id' => rand(1, 10),
                'status' => 1,
                'profile_image' => 'https://i.pravatar.cc/150?img=' . rand(70, 140),
                'total_bookings' => rand(5, 50),
                'rating' => round($this->faker->randomFloat(1, 4, 5), 1),
                'is_email_verified' => rand(0, 1),
                'media' => [
                    'original_url' => 'https://i.pravatar.cc/150?img=' . rand(70, 140)
                ]
            ];
        }
        
        return $customers;
    }
    
    /**
     * Generate mock bookings with realistic data
     */
    public function generateMockBookings($count = 5)
    {
        $bookings = [];
        $statuses = ['pending', 'accept', 'reject', 'completed', 'cancel'];
        $serviceNames = ['Plumbing Repair', 'Electrical Installation', 'House Cleaning', 'Painting', 'Carpentry', 'HVAC Maintenance'];
        
        for ($i = 1; $i <= $count; $i++) {
            $status = $statuses[array_rand($statuses)];
            $bookingDate = Carbon::now()->subDays(rand(0, 30))->addHours(rand(0, 23));
            
            $bookings[] = [
                'id' => 3000 + $i,
                'booking_id' => 'BK' . date('Ymd') . str_pad($i, 5, '0', STR_PAD_LEFT),
                'customer_id' => 2000 + $i,
                'provider_id' => 1000 + $i,
                'service_id' => 100 + $i,
                'category_id' => 1 + ($i % 5),
                'status' => $status,
                'total_amount' => round($this->faker->randomFloat(2, 50, 500), 2),
                'advance_payment' => round($this->faker->randomFloat(2, 0, 100), 2),
                'booking_date' => $bookingDate->toDateTimeString(),
                'service_date' => $bookingDate->addDays(rand(1, 7))->toDateTimeString(),
                'service_name' => $serviceNames[array_rand($serviceNames)],
                'customer_name' => $this->faker->name(),
                'provider_name' => $this->faker->name(),
                'rating' => rand(3, 5),
                'payment_status' => in_array($status, ['completed', 'accept']) ? 'paid' : 'pending',
                'notes' => $this->faker->sentence(),
                'location' => $this->generateEdoAddress(),
                'latitude' => $this->faker->latitude(),
                'longitude' => $this->faker->longitude(),
                'category_service' => [
                    'id' => 100 + $i,
                    'name' => $serviceNames[array_rand($serviceNames)],
                    'category_id' => 1 + ($i % 5),
                    'image' => 'https://via.placeholder.com/150?text=Service'
                ],
                'customer' => [
                    'id' => 2000 + $i,
                    'first_name' => $this->faker->firstName(),
                    'last_name' => $this->faker->lastName(),
                    'email' => $this->faker->email(),
                    'contact_number' => '+234' . rand(7000000000, 9099999999),
                ],
                'provider' => [
                    'id' => 1000 + $i,
                    'first_name' => $this->faker->firstName(),
                    'last_name' => $this->faker->lastName(),
                    'email' => $this->faker->email(),
                    'contact_number' => '+234' . rand(7000000000, 9099999999),
                    'rating' => round($this->faker->randomFloat(1, 3.5, 5), 1),
                ]
            ];
        }
        
        return $bookings;
    }
    
    /**
     * Generate mock revenue data for charts
     */
    public function generateMockRevenueData()
    {
        $months = [];
        $currentMonth = Carbon::now()->startOfMonth();
        
        for ($i = 11; $i >= 0; $i--) {
            $month = $currentMonth->copy()->subMonths($i);
            $months[] = [
                'month' => $month->format('M'),
                'year' => $month->format('Y'),
                'revenue' => rand(5000, 50000),
                'bookings' => rand(20, 200),
                'date' => $month->toDateString()
            ];
        }
        
        return $months;
    }
    
    /**
     * Generate mock post job requests
     */
    public function generateMockPostJobRequests($count = 5)
    {
        $requests = [];
        $jobTypes = ['Urgent Plumbing', 'Electrical Help Needed', 'House Painting', 'Furniture Assembly', 'Moving Help'];
        
        for ($i = 1; $i <= $count; $i++) {
            $requests[] = [
                'id' => 4000 + $i,
                'title' => $jobTypes[array_rand($jobTypes)],
                'description' => $this->faker->paragraph(),
                'customer_id' => 2000 + $i,
                'budget' => rand(50, 500),
                'status' => $this->faker->randomElement(['open', 'assigned', 'completed']),
                'created_at' => Carbon::now()->subDays(rand(0, 30))->toDateTimeString(),
                'customer_name' => $this->faker->name(),
                'customer_email' => $this->faker->email(),
                'bids_count' => rand(1, 10),
            ];
        }
        
        return $requests;
    }
    
    /**
     * Generate mock customer reviews
     */
    public function generateMockReviews($count = 10)
    {
        $reviews = [];
        
        for ($i = 1; $i <= $count; $i++) {
            $reviews[] = [
                'id' => 5000 + $i,
                'booking_id' => 3000 + rand(1, 5),
                'customer_id' => 2000 + rand(1, 5),
                'provider_id' => 1000 + rand(1, 5),
                'rating' => rand(3, 5),
                'comments' => $this->faker->sentence(),
                'created_at' => Carbon::now()->subDays(rand(0, 30))->toDateTimeString(),
                'customer' => [
                    'id' => 2000 + rand(1, 5),
                    'first_name' => $this->faker->firstName(),
                    'last_name' => $this->faker->lastName(),
                ],
                'service' => [
                    'id' => 100 + rand(1, 5),
                    'name' => $this->faker->word(),
                ]
            ];
        }
        
        return $reviews;
    }

    /**
     * Generate mock chat/activity messages.
     */
    public function generateMockMessages($count = 8)
    {
        $messages = [];

        for ($i = 1; $i <= $count; $i++) {
            $messages[] = [
                'id' => 6000 + $i,
                'sender_name' => $this->faker->name(),
                'receiver_name' => $this->faker->name(),
                'message' => $this->faker->sentence(),
                'type' => $this->faker->randomElement(['chat', 'system', 'booking_update']),
                'is_read' => $this->faker->boolean(),
                'created_at' => Carbon::now()->subMinutes(rand(5, 1440))->toDateTimeString(),
            ];
        }

        return $messages;
    }
    
    /**
     * Generate evolving mock data (changes based on time)
     * This creates different data based on the current minute to simulate real activity
     */
    public function generateTimeBasedMockData()
    {
        $seed = (int)Carbon::now()->format('YmdH'); // Changes every hour
        mt_srand($seed);
        
        return [
            'total_booking' => 250 + rand(0, 250),
            'total_service' => 50 + rand(0, 70),
            'total_provider' => 30 + rand(0, 50),
            'total_revenue' => 50000 + rand(0, 200000),
            'monthly_revenue' => 10000 + rand(0, 40000),
            'new_bookings_today' => rand(5, 25),
            'completed_bookings_today' => rand(3, 20),
            'active_providers' => rand(15, 60),
            'active_customers' => rand(50, 300),
        ];
    }
    
    /**
     * Check if current user is mock admin
     */
    public static function isMockAdmin($userId = null)
    {
        $id = $userId ?? auth()?->user()?->id;
        return $id == self::MOCK_ADMIN_ID;
    }
    
    /**
     * Check if session is mock mode
     */
    public static function isMockMode()
    {
        if (!session()->has('is_mock_mode')) {
            return false;
        }

        $flag = session()->get('is_mock_mode');

        return $flag === true || $flag === 1 || $flag === '1' || $flag === 'true';
    }
    
    /**
     * Enable mock mode in session for authenticated user flow.
     */
    public static function createMockSession()
    {
        session()->put('is_mock_mode', true);
        session()->put('mock_admin_id', self::MOCK_ADMIN_ID);
    }
    
    /**
     * Clear mock session
     */
    public static function clearMockSession()
    {
        session()->forget(['is_mock_mode', 'mock_admin_id']);
    }
}
