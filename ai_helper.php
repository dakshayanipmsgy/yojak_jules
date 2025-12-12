<?php
require_once 'auth_check.php';
require_once 'functions.php';

enforceFeature('ai_writer');

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
$provider = trim($_POST['provider'] ?? '');

if (empty($prompt)) {
    echo json_encode(['status' => 'error', 'message' => 'Empty prompt']);
    exit;
}

// Fetch API Keys
$configPath = 'system/global_config.json';
$config = readJSON($configPath);
$aiConfig = $config['ai_config'] ?? [];

$apiKey = '';
$model = '';

if ($provider === 'openai') {
    $apiKey = $aiConfig['openai']['key'] ?? '';
    $model = $aiConfig['openai']['model'] ?? 'gpt-4o';
} elseif ($provider === 'gemini') {
    $apiKey = $aiConfig['gemini']['key'] ?? '';
    $model = $aiConfig['gemini']['model'] ?? 'gemini-1.5-flash';
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid provider selected']);
    exit;
}

if (empty($apiKey)) {
    echo json_encode(['status' => 'error', 'message' => 'Provider not configured (Missing API Key).']);
    exit;
}

// Strict System Instruction
$systemInstruction = "You are a professional government drafting assistant. Your goal is to write strict, precise, and formal documents. Do not be conversational. Do not use filler words. Output strictly in HTML format (using <p>, <br>, <b> lists). Do not use Markdown backticks.";

if ($provider === 'openai') {
    // OpenAI Implementation
    $url = 'https://api.openai.com/v1/chat/completions';

    $data = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $systemInstruction],
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
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        echo json_encode(['status' => 'error', 'message' => 'Curl Error: ' . $err]);
        exit;
    }

    if ($httpCode !== 200) {
        echo json_encode(['status' => 'error', 'message' => 'OpenAI API Error (HTTP ' . $httpCode . '): ' . $response]);
        exit;
    }

    $result = json_decode($response, true);
    $generatedText = $result['choices'][0]['message']['content'] ?? '';

} elseif ($provider === 'gemini') {
    // Gemini Implementation
    // Endpoint: https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={key}
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $apiKey;

    // Payload
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $systemInstruction . "\n\nTask: " . $prompt]
                ]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        echo json_encode(['status' => 'error', 'message' => 'Curl Error: ' . $err]);
        exit;
    }

    if ($httpCode !== 200) {
         echo json_encode(['status' => 'error', 'message' => 'Gemini API Error (HTTP ' . $httpCode . '): ' . $response]);
         exit;
    }

    $result = json_decode($response, true);
    // Parse response
    $generatedText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
}

if (empty($generatedText)) {
    echo json_encode(['status' => 'error', 'message' => 'Empty response from AI']);
    exit;
}

// Clean up Markdown backticks if the model ignores instructions
$generatedText = preg_replace('/^```html\s*/i', '', $generatedText);
$generatedText = preg_replace('/^```\s*/i', '', $generatedText);
$generatedText = preg_replace('/\s*```$/', '', $generatedText);

// Return Success
echo json_encode(['status' => 'success', 'data' => $generatedText]);
