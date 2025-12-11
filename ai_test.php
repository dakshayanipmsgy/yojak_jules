<?php
require_once 'auth_check.php';
require_once 'functions.php';

header('Content-Type: application/json');

// Ensure Superadmin
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] !== 'superadmin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Superadmin access required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit;
}

$provider = trim($_POST['provider'] ?? '');
$inputKey = trim($_POST['key'] ?? '');
$model = trim($_POST['model'] ?? '');

if (empty($provider)) {
    echo json_encode(['status' => 'error', 'message' => 'Provider is required']);
    exit;
}

// Fallback to stored config if key is not provided (user testing existing setup)
if (empty($inputKey)) {
    $configPath = 'system/global_config.json';
    $config = readJSON($configPath);
    $aiConfig = $config['ai_config'] ?? [];

    if ($provider === 'openai') {
        $inputKey = $aiConfig['openai']['key'] ?? '';
    } elseif ($provider === 'gemini') {
        $inputKey = $aiConfig['gemini']['key'] ?? '';
    }
}

if (empty($inputKey)) {
    echo json_encode(['status' => 'error', 'message' => 'API Key is missing (Input empty and no stored key found).']);
    exit;
}

// Defaults if model is empty
if (empty($model)) {
    if ($provider === 'openai') $model = 'gpt-4o';
    if ($provider === 'gemini') $model = 'gemini-1.5-flash';
}

$prompt = "Say Hi";
$responseMessage = "";

if ($provider === 'openai') {
    $url = 'https://api.openai.com/v1/chat/completions';
    $data = [
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.7,
        'max_tokens' => 10
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $inputKey
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
        $errorMsg = 'OpenAI API Error (HTTP ' . $httpCode . ')';
        $json = json_decode($response, true);
        if (isset($json['error']['message'])) {
            $errorMsg .= ': ' . $json['error']['message'];
        }
        echo json_encode(['status' => 'error', 'message' => $errorMsg]);
        exit;
    }

    $result = json_decode($response, true);
    $responseMessage = $result['choices'][0]['message']['content'] ?? 'No content received';

} elseif ($provider === 'gemini') {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $inputKey;
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
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
        $errorMsg = 'Gemini API Error (HTTP ' . $httpCode . ')';
        $json = json_decode($response, true);
        if (isset($json['error']['message'])) {
            $errorMsg .= ': ' . $json['error']['message'];
        }
        echo json_encode(['status' => 'error', 'message' => $errorMsg]);
        exit;
    }

    $result = json_decode($response, true);
    $responseMessage = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'No content received';
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid provider']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'message' => "Connection Verified! Response: " . trim($responseMessage)
]);
