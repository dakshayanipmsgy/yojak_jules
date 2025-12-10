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
$provider = trim($_POST['provider'] ?? '');

if (empty($prompt)) {
    echo json_encode(['status' => 'error', 'message' => 'Empty prompt']);
    exit;
}

// Fetch API Keys
$configPath = 'system/global_config.json';
$config = readJSON($configPath);
$keys = $config['api_keys'] ?? [];

$apiKey = '';
if ($provider === 'openai') {
    $apiKey = $keys['openai'] ?? '';
} elseif ($provider === 'gemini') {
    $apiKey = $keys['gemini'] ?? '';
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid provider selected']);
    exit;
}

if (empty($apiKey)) {
    echo json_encode(['status' => 'error', 'message' => 'Provider not configured.']);
    exit;
}

$systemPrompt = "You are a government drafting assistant. Output strictly in HTML format (use <p>, <br>, <b>). Do not use Markdown backticks.";

if ($provider === 'openai') {
    // OpenAI Implementation
    $url = 'https://api.openai.com/v1/chat/completions';

    $data = [
        'model' => 'gpt-3.5-turbo',
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
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $apiKey;

    // Gemini requires a different structure
    // We prepend the system instructions to the user prompt since Gemini Pro (standard) via generateContent
    // often handles system instructions better as part of the prompt or using the new system_instruction field (beta).
    // However, the prompt specifies: "contents": [{"parts": [{"text": "You are a government drafting assistant... " + $user_prompt}]}]

    $combinedPrompt = $systemPrompt . "\n\nUser Request: " . $prompt;

    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $combinedPrompt]
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
    // Parse response: candidates[0].content.parts[0].text
    $generatedText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
}

if (empty($generatedText)) {
    echo json_encode(['status' => 'error', 'message' => 'Empty response from AI']);
    exit;
}

// Clean up Markdown backticks if the model ignores instructions
// Remove ```html ... ``` or just ``` ... ```
$generatedText = preg_replace('/^```html\s*/i', '', $generatedText);
$generatedText = preg_replace('/^```\s*/i', '', $generatedText);
$generatedText = preg_replace('/\s*```$/', '', $generatedText);


// Return Success
echo json_encode(['status' => 'success', 'data' => $generatedText]);
