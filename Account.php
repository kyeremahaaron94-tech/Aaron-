<?php
// Order Processing API
// Handles checkout requests from frontend

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.'
    ]);
    exit();
}

// Product catalog with prices (in a real app, this would come from database)
$products = [
    '1' => ['name' => 'Wireless Headphones', 'price' => 89.99, 'max_quantity' => 10],
    '2' => ['name' => 'Smart Watch', 'price' => 199.99, 'max_quantity' => 5],
    '3' => ['name' => 'Bluetooth Speaker', 'price' => 49.99, 'max_quantity' => 15],
    '4' => ['name' => 'Laptop Stand', 'price' => 29.99, 'max_quantity' => 20],
    '5' => ['name' => 'USB-C Cable', 'price' => 14.99, 'max_quantity' => 50],
    '6' => ['name' => 'Phone Case', 'price' => 24.99, 'max_quantity' => 30],
    '7' => ['name' => 'Wireless Mouse', 'price' => 34.99, 'max_quantity' => 25],
    '8' => ['name' => 'Keyboard', 'price' => 79.99, 'max_quantity' => 12],
    '9' => ['name' => 'Monitor', 'price' => 249.99, 'max_quantity' => 8],
    '10' => ['name' => 'Webcam', 'price' => 69.99, 'max_quantity' => 18]
];

class OrderProcessor {
    private $products;
    private $tax_rate = 0.08; // 8% tax
    private $shipping_threshold = 50.00; // Free shipping over $50
    private $shipping_cost = 10.00;

    public function __construct($products) {
        $this->products = $products;
    }

    /**
     * Process the order from frontend data
     */
    public function processOrder($request_data) {
        try {
            // 1. Validate input data
            $validation_result = $this->validateInput($request_data);
            if (!$validation_result['valid']) {
                return $this->errorResponse($validation_result['error'], 400);
            }

            // 2. Validate cart items
            $cart_validation = $this->validateCart($request_data['cart']);
            if (!$cart_validation['valid']) {
                return $this->errorResponse($cart_validation['error'], 400);
            }

            // 3. Calculate totals server-side
            $calculations = $this->calculateTotals($request_data['cart']);

            // 4. Validate shipping information
            $shipping_validation = $this->validateShipping($request_data['shipping']);
            if (!$shipping_validation['valid']) {
                return $this->errorResponse($shipping_validation['error'], 400);
            }

            // 5. Validate payment information
            $payment_validation = $this->validatePayment($request_data['payment']);
            if (!$payment_validation['valid']) {
                return $this->errorResponse($payment_validation['error'], 400);
            }

            // 6. Generate order details
            $order_details = $this->generateOrder($request_data, $calculations);

            // 7. Process payment (simulation)
            $payment_result = $this->processPayment($request_data['payment'], $calculations['total']);

            if (!$payment_result['success']) {
                return $this->errorResponse('Payment failed: ' . $payment_result['error'], 402);
            }

            // 8. Save order (in a real app, save to database)
            $order_id = $this->saveOrder($order_details);

            // 9. Return success response
            return $this->successResponse($order_details, $order_id);

        } catch (Exception $e) {
            error_log('Order processing error: ' . $e->getMessage());
            return $this->errorResponse('Internal server error', 500);
        }
    }

    /**
     * Validate basic input structure
     */
    private function validateInput($data) {
        if (!is_array($data)) {
            return ['valid' => false, 'error' => 'Invalid request data format'];
        }

        $required_fields = ['cart', 'shipping', 'payment'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || !is_array($data[$field])) {
                return ['valid' => false, 'error' => "Missing or invalid {$field} data"];
            }
        }

        if (empty($data['cart'])) {
            return ['valid' => false, 'error' => 'Cart is empty'];
        }

        return ['valid' => true];
    }

    /**
     * Validate cart items, quantities, and product IDs
     */
    private function validateCart($cart) {
        if (!is_array($cart)) {
            return ['valid' => false, 'error' => 'Invalid cart format'];
        }

        foreach ($cart as $item) {
            // Check required fields
            if (!isset($item['id']) || !isset($item['quantity']) || !isset($item['price'])) {
                return ['valid' => false, 'error' => 'Invalid cart item structure'];
            }

            $product_id = (string)$item['id'];
            $quantity = (int)$item['quantity'];

            // Validate product exists
            if (!isset($this->products[$product_id])) {
                return ['valid' => false, 'error' => "Invalid product ID: {$product_id}"];
            }

            // Validate quantity
            if ($quantity <= 0) {
                return ['valid' => false, 'error' => 'Quantity must be greater than 0'];
            }

            if ($quantity > $this->products[$product_id]['max_quantity']) {
                return ['valid' => false, 'error' => "Insufficient stock for product {$product_id}"];
            }

            // Validate price matches server price (prevent price manipulation)
            $server_price = $this->products[$product_id]['price'];
            $client_price = (float)$item['price'];

            if (abs($server_price - $client_price) > 0.01) {
                return ['valid' => false, 'error' => "Price mismatch for product {$product_id}"];
            }
        }

        return ['valid' => true];
    }

    /**
     * Calculate totals server-side (don't trust frontend)
     */
    private function calculateTotals($cart) {
        $subtotal = 0;

        foreach ($cart as $item) {
            $product_id = (string)$item['id'];
            $quantity = (int)$item['quantity'];
            $server_price = $this->products[$product_id]['price'];

            $subtotal += $server_price * $quantity;
        }

        $shipping = $subtotal >= $this->shipping_threshold ? 0 : $this->shipping_cost;
        $tax = $subtotal * $this->tax_rate;
        $total = $subtotal + $shipping + $tax;

        return [
            'subtotal' => round($subtotal, 2),
            'shipping' => round($shipping, 2),
            'tax' => round($tax, 2),
            'total' => round($total, 2)
        ];
    }

    /**
     * Validate shipping information
     */
    private function validateShipping($shipping) {
        $required_fields = ['firstName', 'lastName', 'email', 'address', 'city', 'zipCode', 'country'];

        foreach ($required_fields as $field) {
            if (!isset($shipping[$field]) || empty(trim($shipping[$field]))) {
                return ['valid' => false, 'error' => "Missing required shipping field: {$field}"];
            }
        }

        // Email validation
        if (!filter_var($shipping['email'], FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => 'Invalid email address'];
        }

        // ZIP code validation (basic)
        if (!preg_match('/^\d{5}(-\d{4})?$/', $shipping['zipCode'])) {
            return ['valid' => false, 'error' => 'Invalid ZIP code format'];
        }

        return ['valid' => true];
    }

    /**
     * Validate payment information
     */
    private function validatePayment($payment) {
        if (!isset($payment['method'])) {
            return ['valid' => false, 'error' => 'Payment method not specified'];
        }

        $method = $payment['method'];

        if ($method === 'card') {
            $card_fields = ['cardNumber', 'expiryDate', 'cardName', 'cvv'];
            foreach ($card_fields as $field) {
                if (!isset($payment[$field]) || empty(trim($payment[$field]))) {
                    return ['valid' => false, 'error' => "Missing card field: {$field}"];
                }
            }

            // Basic card number validation (remove spaces and check length)
            $card_number = preg_replace('/\s+/', '', $payment['cardNumber']);
            if (!preg_match('/^\d{13,19}$/', $card_number)) {
                return ['valid' => false, 'error' => 'Invalid card number'];
            }

            // Expiry date validation (MM/YY format)
            if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $payment['expiryDate'])) {
                return ['valid' => false, 'error' => 'Invalid expiry date format (MM/YY)'];
            }

            // CVV validation
            if (!preg_match('/^\d{3,4}$/', $payment['cvv'])) {
                return ['valid' => false, 'error' => 'Invalid CVV'];
            }

        } elseif ($method === 'paypal') {
            // PayPal validation would go here
            // For now, just check if PayPal email is provided
            if (!isset($payment['paypalEmail']) || !filter_var($payment['paypalEmail'], FILTER_VALIDATE_EMAIL)) {
                return ['valid' => false, 'error' => 'Valid PayPal email required'];
            }
        } else {
            return ['valid' => false, 'error' => 'Unsupported payment method'];
        }

        return ['valid' => true];
    }

    /**
     * Process payment (simulation - integrate with Paystack/Stripe in production)
     */
    private function processPayment($payment_data, $amount) {
        // Simulate payment processing
        // In production, integrate with Paystack or Stripe APIs

        // Simulate random success/failure for testing
        $success = rand(1, 10) > 1; // 90% success rate

        if ($success) {
            return [
                'success' => true,
                'transaction_id' => 'TXN_' . time() . '_' . rand(1000, 9999),
                'message' => 'Payment processed successfully'
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Payment declined by issuer'
            ];
        }
    }

    /**
     * Generate order details
     */
    private function generateOrder($request_data, $calculations) {
        $order_id = 'ORD_' . date('Ymd') . '_' . rand(1000, 9999);
        $tracking_number = 'SLX_' . date('Ymd') . '_' . rand(100, 999);

        return [
            'order_id' => $order_id,
            'tracking_number' => $tracking_number,
            'status' => 'confirmed',
            'order_date' => date('Y-m-d H:i:s'),
            'customer' => [
                'name' => $request_data['shipping']['firstName'] . ' ' . $request_data['shipping']['lastName'],
                'email' => $request_data['shipping']['email']
            ],
            'shipping_address' => $request_data['shipping'],
            'items' => array_map(function($item) {
                $product_id = (string)$item['id'];
                return [
                    'id' => $product_id,
                    'name' => $this->products[$product_id]['name'],
                    'price' => $this->products[$product_id]['price'],
                    'quantity' => (int)$item['quantity'],
                    'total' => round($this->products[$product_id]['price'] * (int)$item['quantity'], 2)
                ];
            }, $request_data['cart']),
            'totals' => $calculations,
            'payment_method' => $request_data['payment']['method']
        ];
    }

    /**
     * Save order (simulation - in a real app, save to database)
     */
    private function saveOrder($order_details) {
        // In a real application, save to database
        // For now, just return the order ID

        // You could save to a file or database here
        // file_put_contents('orders/' . $order_details['order_id'] . '.json', json_encode($order_details));

        return $order_details['order_id'];
    }

    /**
     * Success response
     */
    private function successResponse($order_details, $order_id) {
        http_response_code(200);
        return [
            'success' => true,
            'order_id' => $order_id,
            'tracking_number' => $order_details['tracking_number'],
            'status' => 'confirmed',
            'message' => 'Order placed successfully!',
            'order_details' => $order_details,
            'estimated_delivery' => '3-5 business days'
        ];
    }

    /**
     * Error response
     */
    private function errorResponse($message, $code = 400) {
        http_response_code($code);
        return [
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

// Main execution
try {
    // Get JSON input from frontend
    $input = file_get_contents('php://input');
    $request_data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON input',
            'details' => json_last_error_msg()
        ]);
        exit();
    }

    // Process the order
    $processor = new OrderProcessor($products);
    $response = $processor->processOrder($request_data);

    echo json_encode($response);

} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>