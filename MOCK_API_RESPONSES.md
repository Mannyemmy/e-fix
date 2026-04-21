# Mock API Responses - Examples

## Admin Dashboard API Response

### Endpoint
```
GET /api/admin-dashboard
```

### Response (Mock Mode)
```json
{
  "status": true,
  "total_booking": 387,
  "total_service": 94,
  "total_provider": 58,
  "total_revenue": 156234.50,
  "monthly_revenue": 34567.89,
  "notification_unread_count": 12,
  "provider": [
    {
      "id": 1001,
      "username": "john_plumber",
      "first_name": "John",
      "last_name": "Smith",
      "email": "john@example.com",
      "user_type": "provider",
      "contact_number": "+1-234-567-8901",
      "city_id": 5,
      "status": 1,
      "is_featured": 1,
      "rating": 4.8,
      "service_type": "Plumbing",
      "total_bookings": 156,
      "completion_rate": 98,
      "profile_image": "https://i.pravatar.cc/150?img=15",
      "media": {
        "original_url": "https://i.pravatar.cc/150?img=15"
      }
    },
    {
      "id": 1002,
      "username": "electrical_pro",
      "first_name": "Mike",
      "last_name": "Johnson",
      "email": "mike@example.com",
      "user_type": "provider",
      "contact_number": "+1-345-678-9012",
      "city_id": 3,
      "status": 1,
      "is_featured": 0,
      "rating": 4.6,
      "service_type": "Electrical",
      "total_bookings": 203,
      "completion_rate": 96,
      "profile_image": "https://i.pravatar.cc/150?img=32",
      "media": {
        "original_url": "https://i.pravatar.cc/150?img=32"
      }
    }
  ],
  "user": [
    {
      "id": 2001,
      "username": "sarah_customer",
      "first_name": "Sarah",
      "last_name": "Williams",
      "email": "sarah@example.com",
      "user_type": "user",
      "contact_number": "+1-456-789-0123",
      "city_id": 7,
      "status": 1,
      "total_bookings": 12,
      "rating": 4.9,
      "is_email_verified": 1,
      "profile_image": "https://i.pravatar.cc/150?img=85",
      "media": {
        "original_url": "https://i.pravatar.cc/150?img=85"
      }
    }
  ],
  "upcomming_booking": [
    {
      "id": 3001,
      "booking_id": "BK202412000001",
      "customer_id": 2001,
      "provider_id": 1001,
      "service_id": 101,
      "category_id": 2,
      "status": "pending",
      "total_amount": "249.99",
      "advance_payment": "50.00",
      "booking_date": "2024-12-15 10:30:00",
      "service_date": "2024-12-18 14:00:00",
      "service_name": "Plumbing Repair",
      "customer_name": "Sarah Williams",
      "provider_name": "John Smith",
      "rating": 5,
      "payment_status": "pending",
      "notes": "Fix kitchen sink leak",
      "location": "123 Main St, City, State 12345",
      "latitude": "40.7128",
      "longitude": "-74.0060",
      "category_service": {
        "id": 101,
        "name": "Plumbing Repair",
        "category_id": 2,
        "image": "https://via.placeholder.com/150?text=Service"
      },
      "customer": {
        "id": 2001,
        "first_name": "Sarah",
        "last_name": "Williams",
        "email": "sarah@example.com",
        "contact_number": "+1-456-789-0123"
      },
      "provider": {
        "id": 1001,
        "first_name": "John",
        "last_name": "Smith",
        "email": "john@example.com",
        "contact_number": "+1-234-567-8901",
        "rating": 4.8
      }
    }
  ]
}
```

## Mock Data Structure Details

### Provider Object
```json
{
  "id": 1001,
  "username": "provider_username",
  "first_name": "John",
  "last_name": "Smith",
  "email": "john@email.com",
  "user_type": "provider",
  "contact_number": "+1-234-567-8901",
  "city_id": 5,
  "status": 1,
  "is_featured": 1,
  "rating": 4.8,
  "service_type": "Plumbing",
  "total_bookings": 156,
  "completion_rate": 98,
  "profile_image": "https://i.pravatar.cc/150?img=15",
  "is_email_verified": 1,
  "media": {
    "original_url": "https://i.pravatar.cc/150?img=15"
  }
}
```

### Customer Object
```json
{
  "id": 2001,
  "username": "customer_username",
  "first_name": "Sarah",
  "last_name": "Williams",
  "email": "sarah@email.com",
  "user_type": "user",
  "contact_number": "+1-456-789-0123",
  "city_id": 7,
  "status": 1,
  "total_bookings": 12,
  "rating": 4.9,
  "is_email_verified": 1,
  "profile_image": "https://i.pravatar.cc/150?img=85",
  "media": {
    "original_url": "https://i.pravatar.cc/150?img=85"
  }
}
```

### Booking Object
```json
{
  "id": 3001,
  "booking_id": "BK202412000001",
  "customer_id": 2001,
  "provider_id": 1001,
  "service_id": 101,
  "category_id": 2,
  "status": "pending",
  "total_amount": "249.99",
  "advance_payment": "50.00",
  "booking_date": "2024-12-15 10:30:00",
  "service_date": "2024-12-18 14:00:00",
  "service_name": "Plumbing Repair",
  "customer_name": "Sarah Williams",
  "provider_name": "John Smith",
  "rating": 5,
  "payment_status": "pending",
  "notes": "Fix kitchen sink leak",
  "location": "123 Main St, City, State 12345",
  "latitude": "40.7128",
  "longitude": "-74.0060",
  "category_service": {
    "id": 101,
    "name": "Plumbing Repair",
    "category_id": 2,
    "image": "https://via.placeholder.com/150?text=Service"
  },
  "customer": {
    "id": 2001,
    "first_name": "Sarah",
    "last_name": "Williams",
    "email": "sarah@example.com",
    "contact_number": "+1-456-789-0123"
  },
  "provider": {
    "id": 1001,
    "first_name": "John",
    "last_name": "Smith",
    "email": "john@example.com",
    "contact_number": "+1-234-567-8901",
    "rating": 4.8
  }
}
```

## Mock Data Statistics

### Dashboard Metrics Ranges

| Metric | Min | Max | Type |
|--------|-----|-----|------|
| Total Bookings | 250 | 500 | Integer |
| Total Services | 50 | 120 | Integer |
| Total Providers | 30 | 80 | Integer |
| Total Revenue | $50,000 | $250,000 | Currency |
| Monthly Revenue | $10,000 | $50,000 | Currency |
| Notifications | 0 | 15 | Integer |
| Provider Rating | 3.5 | 5.0 | Float |
| Customer Rating | 4.0 | 5.0 | Float |
| Booking Amount | $50 | $500 | Currency |
| Completion Rate | 85% | 100% | Percentage |

## Revenue Data

### Monthly Revenue Chart Data
```json
{
  "months": [
    {
      "month": "Jan",
      "year": "2024",
      "revenue": 18234,
      "bookings": 145,
      "date": "2024-01-01"
    },
    {
      "month": "Feb",
      "year": "2024",
      "revenue": 21567,
      "bookings": 167,
      "date": "2024-02-01"
    }
  ]
}
```

## Mock User Session

### Session Data When Logged In
```php
Session::all() // In mock mode

[
    'is_mock_mode' => true,
    'mock_admin_id' => 99999,
    'LARAVEL_SESSION' => '...'
]
```

### Authenticated User Object
```json
{
  "id": 99999,
  "username": "demo_admin",
  "first_name": "Demo",
  "last_name": "Admin",
  "email": "demo_admin@mockdata.test",
  "user_type": "admin",
  "status": 1,
  "role": "demo_admin"
}
```

## API Usage Examples

### Check if in Mock Mode (Controller)
```php
use App\Services\MockDataService;

if (MockDataService::isMockMode()) {
    // Generate mock data
    $service = new MockDataService();
    $data = $service->generateMockDashboardData();
    
    return response()->json($data);
}

// Otherwise return real data
return response()->json($realData);
```

### Generate Fresh Mock Data (Command)
```bash
php artisan mock-data:generate --regenerate
```

### Use Mock Data in Views
```php
@if(\App\Services\MockDataService::isMockMode())
    <div class="alert alert-info">
        <strong>Demo Mode:</strong> Viewing realistic mock data
    </div>
@endif
```

## Response Codes

| Code | Status | Meaning |
|------|--------|---------|
| 200 | OK | Mock data returned successfully |
| 401 | Unauthorized | Not in mock or authenticated session |
| 403 | Forbidden | Mock mode disabled for endpoint |
| 500 | Error | Issue generating mock data |

## Data Generation Notes

1. **Faker Library**: Uses PHP Faker for realistic data
2. **ID Ranges**:
   - Mock Admin ID: 99999
   - Providers: 1000-9999
   - Customers: 2000-9999
   - Bookings: 3000-9999

3. **Timestamps**: Generated as Carbon instances, can be relative to current date

4. **Images**: Using Gravatar placeholder API and placeholder.com

5. **Phone Numbers**: Format varies based on Faker locale

## Testing with Postman

1. Login to `/login/v2` first (this creates the session)
2. Get session cookie from browser
3. In Postman, add cookie to request headers
4. Call API endpoints normally
5. Mock data should be returned

## Limitations

- Mock data is in-memory only
- Data resets on server restart
- Limited to session duration
- Cannot modify mock data through API
- No persistence between sessions

## Performance Considerations

- First request: ~50-200ms (data generation)
- Subsequent requests: <10ms (cached data)
- No database queries in mock mode
- Reduced server load during demos

---

**Note:** All data in examples is completely fictional and matches the structure expected by the admin dashboard.
