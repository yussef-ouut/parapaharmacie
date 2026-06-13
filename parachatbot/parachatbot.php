<?php
/**
 * 2026 Youssef Aotarid
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author    Youssef Aotarid <youssef.aotarid@bts-dwfs.fr>
 * @copyright 2026 Youssef Aotarid
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

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

class ParaChatbot extends Module
{
    public function __construct()
    {
        $this->name = 'parachatbot';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Youssef Aotarid';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('ParaChatbot - Assistant IA');
        $this->description = $this->l('Un assistant virtuel de parapharmacie pour conseiller vos clients et recommander des produits avec l\'IA.');

        $this->confirmUninstall = $this->l('Êtes-vous sûr de vouloir désinstaller ParaChatbot ?');

        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);
    }

    /**
     * Installation du module et greffe sur les Hooks de Prestashop.
     * On utilise 'header' pour charger les fichiers CSS/JS et 'displayFooter' pour le HTML.
     */
    public function install()
    {
        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('actionFrontControllerSetMedia') &&
            $this->registerHook('displayFooter');
    }

    /**
     * Désinstallation du module.
     */
    public function uninstall()
    {
        return parent::uninstall();
    }

    /**
     * Enregistrement des assets CSS et JS sur le Front Controller.
     */
    public function hookActionFrontControllerSetMedia($params)
    {
        if (isset($this->context->controller)) {
            // CSS
            $this->context->controller->registerStylesheet(
                'modules-parachatbot-css',
                'modules/' . $this->name . '/views/css/chatbot.css',
                [
                    'media' => 'all',
                    'priority' => 150
                ]
            );

            // JS
            $this->context->controller->registerJavascript(
                'modules-parachatbot-js',
                'modules/' . $this->name . '/views/js/chatbot_v2.js',
                [
                    'position' => 'bottom',
                    'priority' => 150
                ]
            );
        }
    }

    /**
     * Hook Header : s'exécute dans le <head> de toutes les pages.
     */
    public function hookHeader()
    {
        // Construction robuste de l'URL AJAX du Front Controller pour éviter les blocages de routeur
        $ajaxUrl = $this->context->link->getModuleLink('parachatbot', 'chat');
        if (empty($ajaxUrl)) {
            $ajaxUrl = $this->context->shop->getBaseURL(true) . 'index.php?fc=module&module=parachatbot&controller=chat';
        }
        
        Media::addJsDef(array(
            'paraChatbotAjaxUrl' => $ajaxUrl
        ));
    }

    /**
     * Hook DisplayFooter : s'exécute tout en bas de la page, avant la fermeture de </body>.
     * C'est ici que l'on injecte le code HTML de notre widget de chat.
     */
    public function hookDisplayFooter()
    {
        return $this->display(__FILE__, 'views/templates/hook/chat.tpl');
    }

    /**
     * Page de configuration du module dans le Back Office
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitParaChatbotModule')) {
            $apiKey = (string)Tools::getValue('PARACHATBOT_GEMINI_API_KEY');
            $model = (string)Tools::getValue('PARACHATBOT_GEMINI_MODEL');
            Configuration::updateValue('PARACHATBOT_GEMINI_API_KEY', $apiKey);
            Configuration::updateValue('PARACHATBOT_GEMINI_MODEL', $model);
            $output .= $this->displayConfirmation($this->l('Paramètres mis à jour avec succès.'));
        }

        // Récupérer les statistiques
        $db = Db::getInstance();
        $totalMessages = (int)$db->getValue("SELECT COUNT(*) FROM `" . _DB_PREFIX_ . "parachatbot_conversation`");
        $totalSessions = (int)$db->getValue("SELECT COUNT(DISTINCT session_id) FROM `" . _DB_PREFIX_ . "parachatbot_conversation`");
        
        $historyHtml = '<div class="panel">
            <h3><i class="icon-bar-chart"></i> Statistiques du Chatbot IA</h3>
            <div class="row">
                <div class="col-md-6">
                    <div class="alert alert-info">
                        <strong>' . $totalSessions . '</strong> conversations uniques<br>
                        <strong>' . $totalMessages . '</strong> messages échangés au total
                    </div>
                </div>
            </div>
            <h3><i class="icon-comments"></i> 10 Derniers Messages</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Session</th>
                        <th>Rôle</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>';

        $lastMessages = $db->executeS("SELECT * FROM `" . _DB_PREFIX_ . "parachatbot_conversation` ORDER BY id_message DESC LIMIT 10");
        if ($lastMessages) {
            foreach ($lastMessages as $msg) {
                $roleBadge = $msg['role'] == 'user' ? '<span class="label label-primary">Client</span>' : '<span class="label label-success">IA Kingphar</span>';
                $historyHtml .= '<tr>
                    <td>' . $msg['created_at'] . '</td>
                    <td>' . htmlspecialchars($msg['session_id']) . '</td>
                    <td>' . $roleBadge . '</td>
                    <td>' . nl2br(htmlspecialchars($msg['content'])) . '</td>
                </tr>';
            }
        } else {
            $historyHtml .= '<tr><td colspan="4" class="text-center">Aucune conversation pour le moment.</td></tr>';
        }

        $historyHtml .= '</tbody></table></div>';

        return $output . $this->renderForm() . $historyHtml;
    }

    /**
     * Génère le formulaire de configuration
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->name;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = 'id_module';
        $helper->submit_action = 'submitParaChatbotModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => array(
                'PARACHATBOT_GEMINI_API_KEY' => Configuration::get('PARACHATBOT_GEMINI_API_KEY'),
                'PARACHATBOT_GEMINI_MODEL' => Configuration::get('PARACHATBOT_GEMINI_MODEL', 'gemini-2.5-flash'),
            ),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Configuration de l\'Intelligence Artificielle (Google Gemini)'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Clé API Google Gemini'),
                        'name' => 'PARACHATBOT_GEMINI_API_KEY',
                        'desc' => $this->l('Entrez votre clé API Google Gemini (AQ.Ab...).'),
                        'size' => 60,
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Modèle d\'IA Gemini'),
                        'name' => 'PARACHATBOT_GEMINI_MODEL',
                        'desc' => $this->l('Entrez l\'identifiant du modèle (ex: gemini-1.5-flash ou gemini-1.5-pro).'),
                        'size' => 60,
                        'required' => true
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Enregistrer'),
                ),
            ),
        );

        return $helper->generateForm(array($fields_form));
    }
}
