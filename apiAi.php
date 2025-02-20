<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// API anahtarını güvenli bir şekilde saklayın
const ANTHROPIC_API_KEY = 'apiKey';

// POST isteği kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// JSON verisini al
$json = file_get_contents('php://input');
$data = json_decode($json);

// Adres kontrolü
if (!isset($data->address) || empty($data->address)) {
    http_response_code(400);
    echo json_encode(['error' => 'Address is required']);
    exit;
}

// cURL ile Anthropic API'ye istek
$ch = curl_init('https://api.anthropic.com/v1/messages');

$requestData = [
	'model' => 'claude-3-haiku-20240307',
    //'model' => 'claude-3-opus-20240229',
    'max_tokens' => 1024,
    'messages' => [
        [
            'role' => 'user',
            'content' => sprintf(
                'You are an address validation system. Analyze the following address and extract building number and apartment/flat number information. Address: "%s"

                Return ONLY a JSON object with this exact structure, no other text:
                {
                    "hasBuildingNumber": boolean,
                    "buildingNumber": string or null,
                    "hasFlatNumber": boolean,
                    "flatNumber": string or null
                }

                Rules:
                - hasBuildingNumber: true if any building number is found
                - buildingNumber: the actual number if found, null if not found
                - hasFlatNumber: true if any apartment/flat number is found
                - flatNumber: the actual number if found, null if not found
                - Numbers can be in formats like: No:12, No: 12, Numara:12, No.12, #12, or just 12
                - For apartment/flat numbers look for: Daire:12, D:12, Apt:12, Daire No:12 or similar variations
                - Return only the JSON, no other text',
                $data->address
            )
        ]
    ]
];

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($requestData),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Hata kontrolü
if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo json_encode(['error' => 'API request failed']);
    exit;
}

// API yanıtını işle ve gönder
$anthropicResponse = json_decode($response);
if (isset($anthropicResponse->content[0]->text)) {
    $text = $anthropicResponse->content[0]->text;
    
    // JSON yanıtını temizle ve doğrula
    $text = trim($text);
    
    // JSON decode et
    $jsonResponse = json_decode($text);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        // Yanıt geçerli JSON ise, tekrar encode edip gönder
        echo json_encode($jsonResponse);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Invalid JSON response from AI']);
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid API response']);
}