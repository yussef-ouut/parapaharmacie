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

class ParaChatbotAIService
{
    private $context;
    private $apiKey;
    private $model;

    public function __construct($context = null)
    {
        $this->context = $context ? $context : Context::getContext();
        $this->apiKey = Configuration::get('PARACHATBOT_GEMINI_API_KEY');
        $this->model = Configuration::get('PARACHATBOT_GEMINI_MODEL');
        if (empty($this->model)) {
            $this->model = 'gemini-2.5-flash';
        }
    }

    /**
     * Génère la réponse de l'IA en utilisant l'API Google Gemini
     *
     * @param string $message Le message de l'utilisateur
     * @param array $history L'historique des conversations
     * @param array $products La liste des produits pertinents
     * @return string La réponse textuelle de l'IA
     */
    public function generateResponse($message, array $history = array(), array $products = array())
    {
        if (empty($this->apiKey)) {
            return "Veuillez configurer votre clé API Google Gemini dans l'administration du module.";
        }

        // 1. Définition du Prompt Système (Instructions strictes)
        $systemInstruction = "Tu es l'IA Conseiller officiel de 'Kingphar.ma', une prestigieuse parapharmacie marocaine (Avenue Moulay Abdellah, Marrakech).\n";
        $systemInstruction .= "Ton rôle est de comprendre les besoins/symptômes du client, et de lui recommander EXCLUSIVEMENT les produits disponibles dans le contexte fourni.\n";
        $systemInstruction .= "RÈGLES STRICTES :\n";
        $systemInstruction .= "- Tu ne remplaces JAMAIS un médecin. Ne pose aucun diagnostic médical.\n";
        $systemInstruction .= "- Tu réponds dans la langue du client (Français, Arabe, ou Darija).\n";
        $systemInstruction .= "- Tu ne dois JAMAIS inventer de produits. Utilise SEULEMENT les produits listés ci-dessous dans la section [PRODUITS DISPONIBLES].\n";
        $systemInstruction .= "- N'inclus PAS de liens bruts dans ton texte. Le système affichera automatiquement des cartes produits graphiques sous ton message.\n";
        $systemInstruction .= "- Fais des réponses concises, claires, avec des puces si tu listes plusieurs produits.\n\n";

        // 2. Injection du contexte des produits (RAG)
        $systemInstruction .= "[PRODUITS DISPONIBLES]\n";
        if (empty($products)) {
            $systemInstruction .= "Aucun produit spécifique trouvé pour cette demande.\n";
        } else {
            foreach ($products as $p) {
                $systemInstruction .= "- ID: " . $p['id_product'] . " | Nom: " . $p['name'] . " | Catégorie: " . $p['category'] . " | Prix: " . $p['price'] . " | Stock: " . $p['stock'] . " | Description: " . $p['description'] . "\n";
            }
        }

        // 3. Construction des messages au format Gemini (user / model)
        $contents = array();
        
        foreach ($history as $msg) {
            $role = $msg['role'] == 'user' ? 'user' : 'model';
            $contents[] = array(
                "role" => $role,
                "parts" => array(
                    array("text" => $msg['content'])
                )
            );
        }

        $contents[] = array(
            "role" => "user",
            "parts" => array(
                array("text" => $message)
            )
        );

        $payloadData = array(
            "systemInstruction" => array(
                "parts" => array(
                    array("text" => $systemInstruction)
                )
            ),
            "contents" => $contents,
            "generationConfig" => array(
                "temperature" => 0.7
            )
        );

        // 4. Appel à l'API Gemini
        return $this->callGemini($payloadData);
    }

    /**
     * Effectue la requête cURL vers Gemini API
     */
    private function callGemini($payloadData, $customModel = null)
    {
        $modelToUse = $customModel ? $customModel : $this->model;
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelToUse . ':generateContent?key=' . $this->apiKey;
        $payload = json_encode($payloadData);

        $maxRetries = 4;
        $retryDelay = 2; // 2 seconds base for backoff

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            if ($attempt > 0) {
                sleep($retryDelay * $attempt);
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json'
            ));

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                if ($attempt < $maxRetries) {
                    $this->logError('ParaChatbot Gemini cURL Error (attempt ' . $attempt . '): ' . $error . ', retrying...', 2);
                    continue;
                }
                $this->logError('ParaChatbot Gemini cURL Error: ' . $error, 3);
                return "Je rencontre actuellement une petite difficulté de réseau (Erreur cURL : " . $error . "). Merci de patienter ou de réessayer dans un instant.";
            }

            if ($httpCode === 429 || $httpCode === 503 || $httpCode === 500) {
                if ($attempt < $maxRetries) {
                    $this->logError('ParaChatbot Gemini API transient error HTTP ' . $httpCode . ' hit on attempt ' . $attempt . ', retrying...', 2);
                    continue;
                }
                $this->logError('ParaChatbot Gemini API Error HTTP ' . $httpCode . ' (Exhausted after retries): ' . $result, 3);
                if ($httpCode === 429) {
                    return "Notre assistant est actuellement très sollicité. Veuillez réessayer dans une minute.";
                }
                if ($httpCode === 503) {
                    return "Notre assistant est temporairement surchargé. Veuillez réessayer dans un instant.";
                }
                return "Une petite erreur technique s'est produite. Merci de réessayer plus tard.";
            }

            if ($httpCode !== 200) {
                $this->logError('ParaChatbot Gemini API Error HTTP ' . $httpCode . ': ' . $result, 3);
                return "Une erreur technique (" . $httpCode . ") s'est produite lors de la connexion à l'IA : " . $result;
            }

            break;
        }

        $json = json_decode($result, true);
        
        if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
            return $json['candidates'][0]['content']['parts'][0]['text'];
        }

        return "Je n'ai pas pu formuler de réponse à cause d'une erreur inattendue de l'API Gemini.";
    }

    public function isConfigured()
    {
        return !empty($this->apiKey);
    }

    /**
     * Extrait sémantiquement des mots-clés de recherche en français à partir du message utilisateur
     */
    public function extractKeywords($message, array $history = array())
    {
        if (empty($this->apiKey)) {
            return "";
        }

        $systemInstruction = "Tu es un expert médical en parapharmacie marocaine. Traduis l'intention du client (darija/arabe) en mots-clés de recherche français pour le catalogue.\n";
        $systemInstruction .= "RÈGLES STRICTES :\n";
        $systemInstruction .= "- Retourne UNIQUEMENT un tableau JSON de 1 à 3 mots-clés.\n";
        $systemInstruction .= "- Si le client parle de chaleur, soleil ou été (sehd, skhoun, chmch), retourne [\"solaire\", \"soleil\", \"hydratant\"].\n";
        $systemInstruction .= "- Exemple pour 'fia lhboub' : [\"acne\", \"visage\"]\n";
        $systemInstruction .= "- Exemple pour 'cheveux kitiho' : [\"chute\", \"cheveux\"]\n";
        $systemInstruction .= "- Si tu ne comprends pas, retourne les mots principaux de la phrase.\n";

        $contents = array();
        $contents[] = array(
            "role" => "user",
            "parts" => array(
                array("text" => "Message : " . $message)
            )
        );

        $payloadData = array(
            "systemInstruction" => array(
                "parts" => array(
                    array("text" => $systemInstruction)
                )
            ),
            "contents" => $contents,
            "generationConfig" => array(
                "temperature" => 0.1,
                "responseMimeType" => "application/json"
            )
        );

        $response = $this->callGemini($payloadData);
        
        if (empty($response) || strpos($response, 'erreur') !== false) {
            return ""; 
        }
        
        $jsonArray = json_decode($response, true);
        if (is_array($jsonArray)) {
            return implode(" ", $jsonArray);
        }
        
        preg_match_all('/"([^"]+)"/', $response, $matches);
        if (!empty($matches[1])) {
            return implode(" ", $matches[1]);
        }
        
        return $message;
    }

    /**
     * Méthode de journalisation sécurisée
     */
    private function logError($message, $severity = 3)
    {
        try {
            PrestaShopLogger::addLog($message, $severity);
        } catch (\Throwable $e) {
            error_log("[ParaChatbot] " . $message);
        }
    }
}
