<?php
// Test script for order processing API
// This file can be used to test the process_order.php endpoint

header('Content-Type: application/json');

// Sample test data
$testData = [
    'cart' => [
        [
            'id' => '1',
            'quantity' => 2,
            'price' => 89.99
        ],
        [
            'id' => '3',
            'quantity' => 1,
            'price' => 49.99
        ]
    ],
    'shipping' => [
        'firstName' => 'John',
        'lastName' => 'Doe',
        'email' => 'john.doe@example.com',
        'address' => '123 Main St',
        'city' => 'New York',
        'zipCode' => '10001',
        'country' => 'US',
        'state' => 'NY'
    ],
    'payment' => [
        'method' => 'card',
        'cardNumber' => '4111111111111111',
        'expiryDate' => '12/25',
        'cardName' => 'John Doe',
        'cvv' => '123'
    ]
];

// Test cases
$testCases = [
    'valid_order' => $testData,
    'empty_cart' => array_merge($testData, ['cart' => []]),
    'invalid_product' => array_merge($testData, [
        'cart' => [['id' => '999', 'quantity' => 1, 'price' => 10.00]]
    ]),
    'invalid_quantity' => array_merge($testData, [
        'cart' => [['id' => '1', 'quantity' => 0, 'price' => 89.99]]
    ]),
    'invalid_email' => array_merge($testData, [
        'shipping' => array_merge($testData['shipping'], ['email' => 'invalid-email'])
    ]),
    'paypal_payment' => array_merge($testData, [
        'payment' => [
            'method' => 'paypal',
            'paypalEmail' => 'john.doe@example.com'
        ]
    ])
];

echo "=== Order Processing API Test Results ===\n\n";

foreach ($testCases as $testName => $data) {
    echo "Testing: {$testName}\n";
    echo str_repeat("-", 50) . "\n";

    // Make request to Account.php
    $ch = curl_init('http://localhost/Projects/Account.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Status: {$httpCode}\n";
    echo "Response:\n";

    $decoded = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo json_encode($decoded, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo $response . "\n";
    }

    echo "\n" . str_repeat("=", 60) . "\n\n";
}

// Instructions
echo "=== How to Use This Test ===\n";
echo "1. Start a PHP server: php -S localhost:8000\n";
echo "2. Run this test: php test_order_api.php\n";
echo "3. Check the results above for each test case\n";
echo "4. Test manually by making POST requests to process_order.php\n\n";

echo "=== Manual Testing with curl ===\n";
echo "curl -X POST http://localhost/Projects/process_order.php \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -d '" . json_encode($testData) . "'\n\n";
?>