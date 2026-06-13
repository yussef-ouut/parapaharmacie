{**
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
 *}

<!-- Google Fonts & FontAwesome -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Syne:wght@500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="/prestashop/modules/parachatbot/views/css/chatbot.css">

<div class="parabot-widget-container" id="parabotWidget">
    <!-- Trigger Button -->
    <button class="parabot-trigger-btn" id="parabotTrigger" aria-label="Ouvrir le conseiller virtuel">
        <div class="trigger-icon-wrapper">
            <i class="fa-solid fa-comments"></i>
        </div>
        <span class="trigger-badge">IA Conseiller Kingphar</span>
    </button>

    <!-- Chat Window -->
    <div class="parabot-chat-window hidden" id="parabotTerminal">
        <!-- Header -->
        <div class="chat-header">
            <div class="bot-info">
                <div class="bot-avatar">
                    <i class="fa-solid fa-laptop-medical"></i>
                    <span class="online-indicator"></span>
                </div>
                <div class="bot-details">
                    <h4>Conseiller Clinique Kingphar</h4>
                    <p>En ligne - Expert Parapharmacie</p>
                </div>
            </div>
            <div class="header-actions">
                <button type="button" class="reset-btn" id="parabotResetBtn" title="Réinitialiser la discussion" aria-label="Réinitialiser la discussion">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
                <button type="button" class="expand-btn" id="parabotExpandBtn" aria-label="Agrandir la discussion">
                    <i class="fa-solid fa-expand"></i>
                </button>
                <button type="button" class="close-btn" id="parabotCloseBtn" aria-label="Fermer la discussion">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <!-- Chat History -->
        <div class="chat-body" id="parabotChatHistory">
            <!-- Message initial -->
            <div class="chat-message bot">
                <div class="message-avatar">
                    <i class="fa-solid fa-robot"></i>
                </div>
                <div class="message-content">
                    <p>Marhaban ! Bienvenue chez <strong>Kingphar.ma</strong> (Avenue Moulay Abdellah, Marrakech). 🌿 <br><br>Je suis votre docteur et pharmacologue IA. Je peux vous guider à travers notre catalogue et vous suggérer les produits d'excellence adaptés à vos besoins. Décrivez-moi vos symptômes ou vos objectifs santé.</p>
                    <span class="message-time">À l'instant</span>
                </div>
            </div>
        </div>

        <!-- Quick Suggestions (Pills) -->
        <div class="quick-suggestions">
            <button type="button" class="suggestion-pill" data-text="Je cherche un soin hydratant et réparateur pour peau sèche et irritée.">✨ Peau sèche</button>
            <button type="button" class="suggestion-pill" data-text="Que me conseillez-vous pour stopper la chute des cheveux et les fortifier ?">💇 Chute Cheveux</button>
            <button type="button" class="suggestion-pill" data-text="Quels compléments alimentaires me conseillez-vous pour les ballonnements et la digestion ?">🧪 Confort Digestif</button>
            <button type="button" class="suggestion-pill" data-text="Quel gel lavant doux et crème de change me conseillez-vous pour mon bébé ?">👶 Bébé & Maman</button>
        </div>

        <!-- Input Footer -->
        <form class="chat-footer" id="parabotChatForm">
            <input type="text" id="parabotUserInput" placeholder="Posez votre question à l'IA..." autocomplete="off" required>
            <button type="submit" class="send-btn" id="parabotSubmitBtn">
                <i class="fa-solid fa-paper-plane"></i>
            </button>
        </form>
    </div>
</div>

