<?php
/* IAcreditar HTTPS bridge. Compatible with PHP 7.0+ and cURL. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond($status, $data){
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, array('ok' => false, 'error' => 'method_not_allowed'));
$sharedSecret = getenv('IACREDITAR_SHARED_SECRET');
$providedSecret = isset($_SERVER['HTTP_X_IACREDITAR_SECRET']) ? $_SERVER['HTTP_X_IACREDITAR_SECRET'] : '';
if (!$sharedSecret || !$providedSecret || !hash_equals($sharedSecret, $providedSecret)) respond(401, array('ok' => false, 'error' => 'unauthorized'));

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 12000) respond(413, array('ok' => false, 'error' => 'payload_too_large'));
$data = json_decode($raw, true);
if (!is_array($data) || empty($data['message']) || !is_string($data['message'])) respond(400, array('ok' => false, 'error' => 'invalid_request'));
$message = trim($data['message']);
if (strlen($message) > 240) respond(413, array('ok' => false, 'error' => 'message_too_long'));

$apiKey = getenv('OPENAI_API_KEY');
$model = getenv('OPENAI_MODEL');
if (!$apiKey) respond(503, array('ok' => false, 'error' => 'service_not_configured'));
if (!$model) respond(503, array('ok' => false, 'error' => 'model_not_configured')); 
$personality = isset($data['personality']) && is_string($data['personality']) ? substr($data['personality'], 0, 1800) : 'Você é IAcreditar, assistente virtual oficial da KANDRIX. Responda em português do Brasil.';
$player = isset($data['player']) ? preg_replace('/[^a-zA-Z0-9_]/', '', (string) $data['player']) : 'Jogador';
$history = isset($data['history']) && is_array($data['history']) ? $data['history'] : array();
$input = array();
$count = 0;
foreach ($history as $item) {
    if (!is_array($item) || !isset($item['role'], $item['content'])) continue;
    if (!in_array($item['role'], array('user', 'assistant'), true) || !is_string($item['content'])) continue;
    $input[] = array('role' => $item['role'], 'content' => substr($item['content'], 0, 1200));
    $count++;
    if ($count >= 16) break;
}
$input[] = array('role' => 'user', 'content' => 'Jogador ' . $player . ' diz: ' . $message);

$request = array(
    'model' => $model,
    'instructions' => $personality . "\nIdentidade: você é IAcreditar, a inteligência artificial oficial da KANDRIX. O nome do jogador atual é " . $player . ". Não invente fatos pessoais. Responda em até 3 frases curtas, apropriadas para o chat do Minecraft. Não inclua prefixos de chat nem códigos de cor.",
    'input' => $input,
    'max_output_tokens' => 220,
    'store' => false
);
$ch = curl_init('https://api.openai.com/v1/responses');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request, JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer ' . $apiKey));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
curl_setopt($ch, CURLOPT_TIMEOUT, 22);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
$responseBody = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErrno = curl_errno($ch);
$curlError = curl_error($ch);
curl_close($ch);
if ($responseBody === false) {
    // Log diagnostics on Render without exposing credentials or player messages.
    error_log('[IAcreditar] OpenAI transport failure: curl_errno=' . $curlErrno . '; http_status=' . $status . '; curl_error=' . $curlError);
    respond(502, array('ok' => false, 'error' => 'upstream_error'));
}
if ($status < 200 || $status >= 300) {
    // OpenAI error bodies contain diagnostic information, not the API key.
    $diagnosticBody = substr((string) $responseBody, 0, 2000);
    error_log('[IAcreditar] OpenAI HTTP failure: http_status=' . $status . '; response=' . $diagnosticBody);
    respond(502, array('ok' => false, 'error' => 'upstream_error'));
}
$response = json_decode($responseBody, true);
if (!is_array($response)) respond(502, array('ok' => false, 'error' => 'invalid_upstream_response'));
$text = '';
if (isset($response['output_text']) && is_string($response['output_text'])) $text = $response['output_text'];
if ($text === '' && isset($response['output']) && is_array($response['output'])) {
    foreach ($response['output'] as $output) {
        if (!isset($output['content']) || !is_array($output['content'])) continue;
        foreach ($output['content'] as $content) {
            if (isset($content['type'], $content['text']) && $content['type'] === 'output_text') $text .= $content['text'];
        }
    }
}
$text = trim($text);
if ($text === '') respond(502, array('ok' => false, 'error' => 'empty_response'));
$maxChars = isset($data['max_output_chars']) ? max(80, min(1000, (int) $data['max_output_chars'])) : 420;
if (function_exists('mb_substr')) $text = mb_substr($text, 0, $maxChars, 'UTF-8'); else $text = substr($text, 0, $maxChars);
$history[] = array('role' => 'user', 'content' => $message);
$history[] = array('role' => 'assistant', 'content' => $text);
$history = array_slice($history, -16);
respond(200, array('ok' => true, 'reply' => $text, 'history' => $history));
