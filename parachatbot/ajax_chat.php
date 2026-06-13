<?php
/**
 * Endpoint AJAX ultra-rapide pour le chatbot.
 * Contourne le framework lourd de PrestaShop pour des réponses instantanées.
 * 
 * 2026 Youssef Aotarid
 */

// Charger uniquement le minimum nécessaire de PrestaShop
define('_PS_ADMIN_DIR_', dirname(__FILE__) . '/../../admin5757oyqhmph4mgouv9d');
require_once dirname(__FILE__) . '/../../config/config.inc.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Lire le JSON envoyé
$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput, true);

if (!isset($input['message']) || empty(trim($input['message']))) {
    http_response_code(400);
    echo json_encode(array("error" => "Message requis."));
    exit;
}

// Charger uniquement nos services
require_once dirname(__FILE__) . '/classes/ProductService.php';
require_once dirname(__FILE__) . '/classes/AIService.php';
require_once dirname(__FILE__) . '/classes/ChatService.php';

$context = Context::getContext();
$productService = new ParaChatbotProductService($context);
$aiService = new ParaChatbotAIService($context);
$chatService = new ParaChatbotChatService($productService, $aiService, $context);

$sessionId = isset($input['session_id']) ? $input['session_id'] : null;

try {
    $result = $chatService->processMessage($input['message'], $sessionId);
    echo json_encode(array(
        "response" => $result['response'],
        "products" => $result['products']
    ));
} catch (Throwable $e) {
    try {
        PrestaShopLogger::addLog('ParaChatbot ajax_chat Exception: ' . $e->getMessage(), 3);
    } catch (Throwable $logEx) {
        error_log('ParaChatbot ajax_chat Exception: ' . $e->getMessage());
    }
    http_response_code(500);
    echo json_encode(array(
        "response" => "Je rencontre actuellement une petite perturbation technique. Veuillez réessayer dans un instant.",
        "products" => array(),
        "error" => $e->getMessage()
    ));
}
exit;
