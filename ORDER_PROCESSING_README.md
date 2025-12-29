# ShopLuxe Order Processing System

This is a complete e-commerce order processing system with frontend and backend integration.

## Files Overview

- `Account.php` - Main order processing API endpoint
- `Check out.html` - Frontend checkout page with integrated API calls
- `test_order_api.php` - Test script for the order processing API

## Features

### Backend (process_order.php)
- ✅ **Input Validation**: Validates all incoming data from frontend
- ✅ **Product Validation**: Checks product IDs and quantities against server catalog
- ✅ **Server-side Calculations**: Never trusts frontend totals - calculates everything server-side
- ✅ **Payment Processing**: Ready for Paystack/Stripe integration (currently simulated)
- ✅ **JSON Response**: Returns structured JSON responses
- ✅ **CORS Headers**: Proper headers for cross-origin requests
- ✅ **Error Handling**: Comprehensive error handling with meaningful messages

### Frontend (Check out.html)
- ✅ **AJAX Integration**: Sends order data to PHP backend via fetch API
- ✅ **Form Validation**: Client-side validation before sending to server
- ✅ **Loading States**: Shows processing state during order submission
- ✅ **Error Handling**: Displays server errors to user
- ✅ **Success Modal**: Shows order confirmation with tracking number

## API Endpoint: Account.php

### Request Format
```json
{
  "cart": [
    {
      "id": "1",
      "quantity": 2,
      "price": 89.99
    }
  ],
  "shipping": {
    "firstName": "John",
    "lastName": "Doe",
    "email": "john@example.com",
    "address": "123 Main St",
    "city": "New York",
    "zipCode": "10001",
    "country": "US",
    "state": "NY"
  },
  "payment": {
    "method": "card",
    "cardNumber": "4111111111111111",
    "expiryDate": "12/25",
    "cardName": "John Doe",
    "cvv": "123"
  }
}
```

### Success Response
```json
{
  "success": true,
  "order_id": "ORD_20251226_1234",
  "tracking_number": "SLX_20251226_567",
  "status": "confirmed",
  "message": "Order placed successfully!",
  "order_details": {
    "order_id": "ORD_20251226_1234",
    "tracking_number": "SLX_20251226_567",
    "status": "confirmed",
    "order_date": "2025-12-26 14:30:00",
    "customer": {
      "name": "John Doe",
      "email": "john@example.com"
    },
    "items": [...],
    "totals": {
      "subtotal": 179.98,
      "shipping": 0,
      "tax": 14.40,
      "total": 194.38
    }
  },
  "estimated_delivery": "3-5 business days"
}
```

### Error Response
```json
{
  "success": false,
  "error": "Invalid email address",
  "timestamp": "2025-12-26 14:30:00"
}
```

## Setup Instructions

### 1. Start PHP Server
```bash
cd /path/to/your/project
php -S localhost:8000
```

### 2. Test the API
```bash
php test_order_api.php
```

### 3. Manual Testing with curl
```bash
curl -X POST http://localhost:8000/Account.php \
  -H "Content-Type: application/json" \
  -d '{
    "cart": [{"id": "1", "quantity": 1, "price": 89.99}],
    "shipping": {
      "firstName": "John",
      "lastName": "Doe",
      "email": "john@example.com",
      "address": "123 Main St",
      "city": "NYC",
      "zipCode": "10001",
      "country": "US",
      "state": "NY"
    },
    "payment": {
      "method": "card",
      "cardNumber": "4111111111111111",
      "expiryDate": "12/25",
      "cardName": "John Doe",
      "cvv": "123"
    }
  }'
```

## Security Features

- **Server-side Price Validation**: Prevents price manipulation attacks
- **Input Sanitization**: All inputs are validated and sanitized
- **CORS Protection**: Proper CORS headers configured
- **Error Handling**: No sensitive information leaked in errors
- **Payment Simulation**: Ready for real payment gateway integration

## Payment Integration

### Paystack Integration (Production)
```php
// In process_order.php, replace the processPayment method:

private function processPayment($payment_data, $amount) {
    $paystack_secret = 'sk_your_paystack_secret_key';

    $data = [
        'email' => $this->request_data['shipping']['email'],
        'amount' => $amount * 100, // Convert to kobo
        'callback_url' => 'https://yourdomain.com/payment/callback'
    ];

    $ch = curl_init('https://api.paystack.co/transaction/initialize');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $paystack_secret,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($result['status']) {
        return [
            'success' => true,
            'transaction_id' => $result['data']['reference'],
            'payment_url' => $result['data']['authorization_url']
        ];
    } else {
        return [
            'success' => false,
            'error' => $result['message']
        ];
    }
}
```

### Stripe Integration (Production)
```php
// Install Stripe PHP SDK: composer require stripe/stripe-php

require_once('vendor/autoload.php');

private function processPayment($payment_data, $amount) {
    \Stripe\Stripe::setApiKey('sk_your_stripe_secret_key');

    try {
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $amount * 100, // Convert to cents
            'currency' => 'usd',
            'payment_method_types' => ['card'],
            'receipt_email' => $this->request_data['shipping']['email'],
        ]);

        return [
            'success' => true,
            'transaction_id' => $paymentIntent->id,
            'client_secret' => $paymentIntent->client_secret
        ];
    } catch (\Stripe\Exception\ApiErrorException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
```

## Database Integration

For production, replace the `saveOrder` method with actual database storage:

```php
private function saveOrder($order_details) {
    // Example with PDO and MySQL
    $pdo = new PDO('mysql:host=localhost;dbname=shopluxe', 'username', 'password');

    $stmt = $pdo->prepare("
        INSERT INTO orders (order_id, tracking_number, customer_email, total_amount, status, order_data, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $order_details['order_id'],
        $order_details['tracking_number'],
        $order_details['customer']['email'],
        $order_details['totals']['total'],
        $order_details['status'],
        json_encode($order_details)
    ]);

    return $order_details['order_id'];
}
```

## Testing

Run the comprehensive test suite:
```bash
php test_order_api.php
```

This will test various scenarios including:
- Valid orders
- Empty carts
- Invalid products
- Invalid quantities
- Invalid emails
- Different payment methods

## Production Deployment

1. **Enable HTTPS** for secure payment processing
2. **Configure Database** for order storage
3. **Set up Payment Gateway** (Paystack/Stripe)
4. **Add Logging** for order tracking
5. **Configure Email Notifications** for order confirmations
6. **Set up Webhooks** for payment status updates

## Support

For issues or questions about this order processing system, please check:
1. The test results from `test_order_api.php`
2. PHP error logs
3. Network requests in browser developer tools