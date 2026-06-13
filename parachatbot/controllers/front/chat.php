<?php
/**
 * 2026 Youssef Aotarid
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 *
 * @author    Youssef Aotarid <youssef.aotarid@bts-dwfs.fr>
 * @copyright 2026 Youssef Aotarid
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

// Autoloading ciblé des services du module
spl_autoload_register(function ($class) {
    if (strpos($class, 'ParaChatbot') === 0) {
        $className = str_replace('ParaChatbot', '', $class);
        $file = _PS_MODULE_DIR_ . 'parachatbot/classes/' . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

class ParaChatbotChatModuleFrontController extends ModuleFrontController
{
    /**
     * Cette méthode gère le traitement de la requête AJAX.
     * Elle délègue le traitement métier à la couche de services.
     */
    public function initContent()
    {
        // 1. Appel du parent pour initialiser le contexte de Prestashop
        parent::initContent();

        // 2. Définir le type de réponse en JSON
        header('Content-Type: application/json; charset=UTF-8');

        // 3. Lire le JSON envoyé via Fetch API
        $rawInput = file_get_contents("php://input");
        $input = json_decode($rawInput, true);

        if (!isset($input['message']) || empty(trim($input['message']))) {
            http_response_code(400);
            echo json_encode(array("error" => "Message requis."));
            exit;
        }

        // 4. Instanciation de la couche de services
        $productService = new ParaChatbotProductService($this->context);
        $aiService = new ParaChatbotAIService($this->context);
        $chatService = new ParaChatbotChatService($productService, $aiService, $this->context);

        // 5. Délégation du traitement du message au ChatService
        $result = $chatService->processMessage($input['message']);

        // 6. Retourner la réponse en JSON
        echo json_encode(array(
            "response" => $result['response'],
            "products" => $result['products']
        ));
        exit;
    }
}
