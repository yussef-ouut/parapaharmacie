<?php
/**
 * 2026 Youssef Aotarid
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class ParaChatbotChatService
{
    private $context;
    private $productService;
    private $aiService;

    public function __construct(ParaChatbotProductService $productService, ParaChatbotAIService $aiService, $context = null)
    {
        $this->context = $context ? $context : Context::getContext();
        $this->productService = $productService;
        $this->aiService = $aiService;
    }

    /**
     * Traite le message utilisateur, gère l'historique, recherche les produits et sollicite OpenAI/Gemini.
     */
    public function processMessage($message, $clientSessionId = null)
    {
        $userMessage = trim($message);
        
        // 1. Identifier la session
        if ($clientSessionId) {
            $sessionId = $clientSessionId; // Session unique par onglet/chargement de page depuis le JS
        } else {
            // Fallback (ancien comportement)
            $sessionId = 'guest_' . $this->context->cookie->id_guest;
            if ($this->context->customer->isLogged()) {
                $sessionId = 'customer_' . $this->context->customer->id;
            }
        }

        // 2. Vérifier si c'est une salutation simple ou un message trop court (à ne pas mettre en cache)
        $normalizedMsg = strtolower(trim(preg_replace('/[^a-zA-Z\s]/', '', $userMessage)));
        $normalizedMsg = preg_replace('/\s+/', ' ', $normalizedMsg);
        
        $commonGreetings = array(
            'salam', 'bonjour', 'salut', 'merci', 'chokran', 'oui', 'non', 'ok', 'daccord', 'hello', 'coucou',
            'salam alaykum', 'salam alaikoum', 'salamo alaykom', 'salamou alaykoum', 'labas', 'sbah lkhir', 'sbah el khir',
            'msa lkhir', 'msa el khir', 'choukran', 'shokran', 'merci beaucoup', 'bonsoir', 'hi', 'hola', 'hey', 'ahlan'
        );
        
        $skipExtraction = false;
        if (strlen($normalizedMsg) < 4 || in_array($normalizedMsg, $commonGreetings)) {
            $skipExtraction = true;
        }

        // 3. Économie d'API radicale : Tenter de récupérer une réponse déjà formulée en cache local (uniquement pour les vraies questions !)
        if (!$skipExtraction) {
            $cachedResponse = $this->getCachedResponse($userMessage);
            if ($cachedResponse !== null) {
                // Sauvegarder le message de l'utilisateur dans l'historique
                $this->saveMessage($sessionId, 'user', $userMessage);

                // Effectuer la recherche locale de produits (Gratuit & Instantané)
                $searchQuery = $userMessage;
                $localTranslation = $this->translateDarijaLocally($userMessage);
                if ($localTranslation) {
                    $searchQuery = $localTranslation;
                }
                $recommendedProducts = $this->productService->searchProducts($searchQuery, 10);

                // Sauvegarder la réponse de l'assistant dans l'historique
                $this->saveMessage($sessionId, 'assistant', $cachedResponse);

                return array(
                    "response" => $cachedResponse,
                    "products" => $recommendedProducts
                );
            }
        }

        // 4. Sauvegarder le message de l'utilisateur dans l'historique (Flux normal)
        $this->saveMessage($sessionId, 'user', $userMessage);

        // 5. Récupérer l'historique de la conversation (Les 6 derniers messages = contexte court)
        $history = $this->getHistory($sessionId, 6);

        // 6. Extraction Sémantique et Recherche de Produits
        $searchQuery = $userMessage;
        $recommendedProducts = array();

        if ($skipExtraction) {
            // Pas besoin de chercher des produits pour une simple salutation
            $recommendedProducts = array();
        } else {
            // Étape A: Traduction Darija locale (Gratuit, instantané, évite un appel API)
            $localTranslation = $this->translateDarijaLocally($userMessage);
            if ($localTranslation) {
                $searchQuery = $localTranslation;
                $recommendedProducts = $this->productService->searchProducts($searchQuery, 10);
            } else {
                // Étape B: Tenter une recherche directe si c'est du Français (ex: "acne", "creme solaire")
                $recommendedProducts = $this->productService->searchProducts($userMessage, 10);
                
                // Étape C: Si recherche infructueuse et IA configurée, appel à Gemini (2ème recours uniquement)
                if (empty($recommendedProducts) && $this->aiService->isConfigured()) {
                    $extractedKeywords = $this->aiService->extractKeywords($userMessage, $history);
                    if (!empty($extractedKeywords)) {
                        $searchQuery = $extractedKeywords;
                        $recommendedProducts = $this->productService->searchProducts($searchQuery, 10);
                    }
                }
            }
        }

        // Si l'IA n'est pas configurée, on utilise un Fallback local
        if (!$this->aiService->isConfigured()) {
            return $this->processLocalFallback($userMessage, $recommendedProducts);
        }

        // 7. Générer la réponse via l'IA
        $aiResponse = $this->aiService->generateResponse($userMessage, $history, $recommendedProducts);

        // 8. Sauvegarder la réponse de l'IA dans l'historique
        $this->saveMessage($sessionId, 'assistant', $aiResponse);

        return array(
            "response" => $aiResponse,
            "products" => $recommendedProducts
        );
    }

    /**
     * Sauvegarde un message dans la base de données
     */
    private function saveMessage($sessionId, $role, $content)
    {
        $db = Db::getInstance();
        $sql = "INSERT INTO `" . _DB_PREFIX_ . "parachatbot_conversation` (`session_id`, `role`, `content`, `created_at`) 
                VALUES ('" . pSQL($sessionId) . "', '" . pSQL($role) . "', '" . pSQL($content) . "', NOW())";
        $db->execute($sql);
    }

    /**
     * Récupère l'historique d'une session
     */
    private function getHistory($sessionId, $limit = 6)
    {
        $db = Db::getInstance();
        // On récupère les derniers messages, puis on inverse pour avoir l'ordre chronologique
        $sql = "SELECT `role`, `content` 
                FROM `" . _DB_PREFIX_ . "parachatbot_conversation` 
                WHERE `session_id` = '" . pSQL($sessionId) . "'
                ORDER BY `id_message` DESC 
                LIMIT " . (int)$limit;
                
        $results = $db->executeS($sql);
        
        if ($results) {
            return array_reverse($results);
        }
        return array();
    }

    /**
     * Fallback local utilisé si la clé API OpenAI n'est pas configurée (ou en cas d'erreur bloquante)
     */
    private function processLocalFallback($userMessage, $recommendedProducts)
    {
        $userMessage = strtolower($userMessage);
        $responseMessage = "";

        if (strpos($userMessage, 'bonjour') !== false || strpos($userMessage, 'salut') !== false) {
            $responseMessage = "Bonjour ! Je suis le conseiller virtuel Kingphar (Mode Local). Comment puis-je vous aider aujourd'hui ?";
        } else {
            if (!empty($recommendedProducts)) {
                $responseMessage = "Voici quelques produits de notre catalogue qui pourraient correspondre à votre besoin :";
            } else {
                $responseMessage = "Je n'ai pas trouvé de produits correspondant exactement à votre demande. Pourriez-vous préciser vos symptômes ou le type de soin recherché ?";
            }
        }

        return array(
            "response" => $responseMessage . "<br><br><small><i>(L'IA OpenAI n'est pas encore connectée. Veuillez configurer votre clé API dans l'administration).</i></small>",
            "products" => $recommendedProducts
        );
    }

    /**
     * Traduit localement les termes courants du darija/arabe marocain vers le français
     * afin d'éviter d'appeler l'API Gemini pour la traduction de mots-clés.
     */
    private function translateDarijaLocally($message)
    {
        $message = mb_strtolower($message, 'UTF-8');
        
        $dictionary = array(
            'hboub' => 'acne bouton visage',
            'lhboub' => 'acne bouton visage',
            'haboub' => 'acne bouton visage',
            'sehd' => 'solaire soleil protection',
            'skhoun' => 'solaire soleil protection',
            'skhn' => 'solaire soleil protection',
            'chmch' => 'solaire soleil protection',
            'chams' => 'solaire soleil protection',
            'chaar' => 'chute cheveux',
            'cha3r' => 'chute cheveux',
            'cheveux' => 'chute cheveux',
            'kityh' => 'chute anti-chute',
            'kitiho' => 'chute anti-chute',
            'tassakot' => 'chute anti-chute',
            'fatigue' => 'fatigue energie',
            't3b' => 'fatigue energie',
            '3ya' => 'fatigue energie',
            'sac' => 'hydratant sec peau',
            'nchaf' => 'hydratant sec peau',
            'cernes' => 'fatigue cerne',
            'halalat' => 'fatigue cerne'
        );

        $matchedKeywords = array();
        foreach ($dictionary as $darija => $french) {
            if (preg_match('/\b' . preg_quote($darija, '/') . '\b/u', $message) || strpos($message, $darija) !== false) {
                $matchedKeywords[] = $french;
            }
        }

        if (!empty($matchedKeywords)) {
            return implode(' ', array_unique($matchedKeywords));
        }

        return null;
    }

    /**
     * Tente de récupérer une réponse déjà formulée pour la même question exacte
     * afin d'éviter d'appeler l'API Gemini (Gain de temps et économie de quota).
     */
    private function getCachedResponse($userMessage)
    {
        $db = Db::getInstance();
        $cleanUserMessage = trim($userMessage);
        
        // Trouver le dernier message utilisateur identique
        $sqlUser = "SELECT `id_message`, `session_id` 
                    FROM `" . _DB_PREFIX_ . "parachatbot_conversation` 
                    WHERE `role` = 'user' AND `content` = '" . pSQL($cleanUserMessage) . "' 
                    ORDER BY `id_message` DESC 
                    LIMIT 1";
        $userMsg = $db->getRow($sqlUser);
        
        if ($userMsg) {
            $userId = (int)$userMsg['id_message'];
            $userSession = $userMsg['session_id'];
            
            // Trouver la réponse de l'assistant qui a suivi ce message dans la même session
            $sqlAssistant = "SELECT `content` 
                             FROM `" . _DB_PREFIX_ . "parachatbot_conversation` 
                             WHERE `role` = 'assistant' 
                               AND `session_id` = '" . pSQL($userSession) . "' 
                               AND `id_message` > " . $userId . " 
                             ORDER BY `id_message` ASC 
                             LIMIT 1";
            $assistantContent = $db->getValue($sqlAssistant);
            
            // Vérifier qu'on n'a pas récupéré un message d'erreur
            if ($assistantContent && strpos($assistantContent, 'très sollicité') === false && strpos($assistantContent, 'erreur') === false) {
                return $assistantContent;
            }
        }
        
        return null;
    }
}
