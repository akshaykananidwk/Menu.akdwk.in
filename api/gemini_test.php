<?php
/**
 * Admin-only Gemini test endpoint.
 * Lets the Super Admin verify the configured Gemini API key/model live and
 * discover which models the key supports.
 * Actions: test (send a prompt, show reply), models (list usable models).
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isSuperAdmin()) { jsonError('Unauthorized.', 403); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrfCheck(); }

$action = $_GET['action'] ?? 'test';

switch ($action) {
    case 'test':
        $prompt = trim($_POST['prompt'] ?? '');
        $res = geminiTestPrompt($prompt);
        if ($res['ok']) {
            jsonSuccess('Gemini responded successfully.', [
                'reply' => $res['text'],
                'model' => $res['model'],
            ]);
        }
        jsonError($res['error'] ?: 'Gemini test failed.');
        break;

    case 'models':
        $res = geminiListModels();
        if ($res['ok']) { jsonSuccess('Fetched model list.', ['models' => $res['models']]); }
        jsonError($res['error'] ?: 'Could not list models.');
        break;

    default:
        jsonError('Unknown action.');
}
