<?php
define('_PS_ADMIN_DIR_', dirname(__FILE__) . '/../../admin5757oyqhmph4mgouv9d');
require_once dirname(__FILE__) . '/../../config/config.inc.php';

$key = 'VOTRE_CLE_API_GEMINI_ICI';
$model = 'gemini-1.5-flash';

Configuration::updateValue('PARACHATBOT_GEMINI_API_KEY', $key);
Configuration::updateValue('PARACHATBOT_GEMINI_MODEL', $model);

echo "Google Gemini API Key and Model updated successfully in database!\n";
echo "Key: " . Configuration::get('PARACHATBOT_GEMINI_API_KEY') . "\n";
echo "Model: " . Configuration::get('PARACHATBOT_GEMINI_MODEL') . "\n";
