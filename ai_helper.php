<?php
require_once 'auth_check.php';
require_once 'functions.php';

header('Content-Type: application/json');

// Ensure only authenticated users can access
$deptId = $_SESSION['dept_id'] ?? null;
if (!$deptId) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit;
}

$prompt = trim($_POST['prompt'] ?? '');
if (empty($prompt)) {
    echo json_encode(['status' => 'error', 'message' => 'Empty prompt']);
    exit;
}

// Fetch API Key
$configPath = 'system/global_config.json';
$config = readJSON($configPath);
$apiKey = $config['ai_api_key'] ?? '';

if (empty($apiKey)) {
    echo json_encode(['status' => 'error', 'message' => 'AI API Key not configured. Contact Superadmin.']);
    exit;
}

// Prepare Request
$url = 'https://api.openai.com/v1/chat/completions';
$systemPrompt = "You are a helpful government assistant. Draft a professional, formal letter based on the user's request. Use HTML formatting (<p>, <b>, <br>). Do not include markdown code blocks. The output should be just the body of the letter, suitable for insertion into a document editor.";

$data = [
    'model' => 'gpt-3.5-turbo', // Or 'gpt-4' if available/preferred
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $prompt]
    ],
    'temperature' => 0.7
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(['status' => 'error', 'message' => 'Curl Error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['status' => 'error', 'message' => 'API Error (HTTP ' . $httpCode . '): ' . $response]);
    exit;
}

$result = json_decode($response, true);
$generatedText = $result['choices'][0]['message']['content'] ?? '';

if (empty($generatedText)) {
    echo json_encode(['status' => 'error', 'message' => 'Empty response from AI']);
    exit;
}

// Return Success
echo json_encode(['status' => 'success', 'data' => $generatedText]);
