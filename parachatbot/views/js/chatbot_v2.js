/**
 * ParaChatbot - Moteur JavaScript Applicatif Professionnel (Style Support Client / Claude)
 * Gère l'ouverture/fermeture, les bulles de messages et les recommandations produits en flux
 */

document.addEventListener('DOMContentLoaded', () => {
    const trigger = document.getElementById('parabotTrigger');
    const terminal = document.getElementById('parabotTerminal');
    const closeBtn = document.getElementById('parabotCloseBtn');
    const expandBtn = document.getElementById('parabotExpandBtn');
    const chatForm = document.getElementById('parabotChatForm');
    const userInput = document.getElementById('parabotUserInput');
    const chatHistory = document.getElementById('parabotChatHistory');

    if (!trigger || !terminal || !chatForm) return;

    // Gestion Ouverture / Fermeture
    const toggleTerminal = () => {
        terminal.classList.toggle('hidden');
        if (!terminal.classList.contains('hidden')) {
            userInput.focus();
            scrollToBottom();
        }
    };

    trigger.addEventListener('click', toggleTerminal);
    closeBtn.addEventListener('click', toggleTerminal);

    // Gestion Agrandir / Réduire (Agrandir la fenêtre)
    if (expandBtn) {
        expandBtn.addEventListener('click', () => {
            terminal.classList.toggle('maximized');
            const icon = expandBtn.querySelector('i');
            if (terminal.classList.contains('maximized')) {
                icon.className = 'fa-solid fa-compress';
                expandBtn.setAttribute('aria-label', 'Réduire la discussion');
            } else {
                icon.className = 'fa-solid fa-expand';
                expandBtn.setAttribute('aria-label', 'Agrandir la discussion');
            }
            scrollToBottom();
        });
    }

    // Défilement automatique vers le bas
    const scrollToBottom = () => {
        chatHistory.scrollTop = chatHistory.scrollHeight;
    };

    // Effet d'écriture pour les réponses du bot
    const typeWriterHTML = (element, htmlText, speed = 8, callback) => {
        let i = 0;
        element.innerHTML = "";
        const processedHTML = htmlText.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        
        // Scroll une seule fois au début de l'écriture (vers le message, pas les produits)
        const parentMsg = element.closest('.chat-message');
        if (parentMsg) parentMsg.scrollIntoView({ behavior: 'smooth', block: 'start' });

        const timer = setInterval(() => {
            if (processedHTML[i] === '<') {
                let closingIndex = processedHTML.indexOf('>', i);
                if (closingIndex !== -1) {
                    element.innerHTML += processedHTML.substring(i, closingIndex + 1);
                    i = closingIndex + 1;
                } else {
                    element.innerHTML += processedHTML[i];
                    i++;
                }
            } else {
                element.innerHTML += processedHTML[i];
                i++;
            }
            
            if (i >= processedHTML.length) {
                clearInterval(timer);
                if (callback) callback();
            }
        }, speed);
    };

    let conversationHistory = [];

    // Récupérer ou initialiser l'identifiant de session dans localStorage pour persister les données après rechargement (F5)
    let pageSessionId = localStorage.getItem('parachatbot_session_id');
    if (!pageSessionId) {
        pageSessionId = 'sess_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
        localStorage.setItem('parachatbot_session_id', pageSessionId);
    }

    // Sauvegarde l'historique dans localStorage
    const saveHistoryToLocalStorage = () => {
        localStorage.setItem('parachatbot_history_' + pageSessionId, JSON.stringify(conversationHistory));
    };

    // Ajouter une bulle de message dans la discussion
    const appendMessage = (sender, text, isTyping = false) => {
        const card = document.createElement('div');
        card.classList.add('chat-message', sender === 'user' ? 'user' : 'bot');
        if (isTyping) card.id = 'parabotTypingIndicator';

        const avatar = document.createElement('div');
        avatar.classList.add('message-avatar');
        avatar.innerHTML = sender === 'user' ? '<i class="fa-solid fa-user"></i>' : '<i class="fa-solid fa-leaf"></i>';

        const content = document.createElement('div');
        content.classList.add('message-content');

        if (isTyping) {
            content.innerHTML = `
                <div class="typing-dots">
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                </div>
            `;
        } else {
            const bodyDiv = document.createElement('div');
            content.appendChild(bodyDiv);
            if (sender === 'bot') {
                typeWriterHTML(bodyDiv, text);
            } else {
                bodyDiv.innerHTML = `<p>${text}</p>`;
            }

            const time = document.createElement('span');
            time.classList.add('message-time');
            const now = new Date();
            const timeStr = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
            time.textContent = timeStr;
            content.appendChild(time);

            // Enregistrer dans l'historique local
            conversationHistory.push({
                sender: sender,
                text: text,
                time: timeStr
            });
            saveHistoryToLocalStorage();
        }

        card.appendChild(avatar);
        card.appendChild(content);
        chatHistory.appendChild(card);
        scrollToBottom();
    };

    // Rendre un message historique sans effet typewriter
    const renderHistoricalMessage = (sender, text, timeStr) => {
        const card = document.createElement('div');
        card.classList.add('chat-message', sender === 'user' ? 'user' : 'bot');

        const avatar = document.createElement('div');
        avatar.classList.add('message-avatar');
        avatar.innerHTML = sender === 'user' ? '<i class="fa-solid fa-user"></i>' : '<i class="fa-solid fa-leaf"></i>';

        const content = document.createElement('div');
        content.classList.add('message-content');

        const bodyDiv = document.createElement('div');
        content.appendChild(bodyDiv);
        const processedHTML = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        bodyDiv.innerHTML = processedHTML;

        const time = document.createElement('span');
        time.classList.add('message-time');
        time.textContent = timeStr;
        content.appendChild(time);

        card.appendChild(avatar);
        card.appendChild(content);
        chatHistory.appendChild(card);
        scrollToBottom();
    };

    // Injecter des cartes de produits avec apparition progressive (sans scroll automatique)
    const appendProducts = (products = []) => {
        if (!products || products.length === 0) return;

        // Ajouter un petit titre de section
        const sectionLabel = document.createElement('div');
        sectionLabel.classList.add('products-section-label');
        sectionLabel.innerHTML = `<i class="fa-solid fa-bag-shopping"></i> Produits recommandés`;
        chatHistory.appendChild(sectionLabel);

        products.forEach((prod, index) => {
            const card = document.createElement('div');
            card.classList.add('product-card');
            card.style.animationDelay = `${index * 120}ms`; // Apparition progressive
            
            const icon = prod.name.toLowerCase().includes('arnica') ? 'fa-spa' : 'fa-prescription-bottle-medical';
            const link = prod.link || '#';

            const imageHtml = prod.image 
                ? `<img src="${prod.image}" alt="${prod.name}" class="product-card-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">` 
                : '';
            const fallbackIconHtml = `<div class="product-card-avatar-fallback" style="${prod.image ? 'display:none;' : 'display:flex;'}"><i class="fa-solid ${icon}"></i></div>`;

            card.innerHTML = `
                <div class="product-card-avatar">
                    ${imageHtml}
                    ${fallbackIconHtml}
                </div>
                <div class="product-card-details">
                    <h5>${prod.name}</h5>
                    <p>${prod.price}</p>
                </div>
                <a href="${link}" class="product-card-link" target="_blank">Commander</a>
            `;
            chatHistory.appendChild(card);
        });

        // Enregistrer les produits recommandés dans l'historique local
        conversationHistory.push({
            sender: 'products',
            products: products
        });
        saveHistoryToLocalStorage();
    };

    // Rendre des produits historiques sans animation de délai
    const renderHistoricalProducts = (products = []) => {
        if (!products || products.length === 0) return;

        const sectionLabel = document.createElement('div');
        sectionLabel.classList.add('products-section-label');
        sectionLabel.innerHTML = `<i class="fa-solid fa-bag-shopping"></i> Produits recommandés`;
        chatHistory.appendChild(sectionLabel);

        products.forEach((prod) => {
            const card = document.createElement('div');
            card.classList.add('product-card');
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
            card.style.animation = 'none';
            
            const icon = prod.name.toLowerCase().includes('arnica') ? 'fa-spa' : 'fa-prescription-bottle-medical';
            const link = prod.link || '#';

            const imageHtml = prod.image 
                ? `<img src="${prod.image}" alt="${prod.name}" class="product-card-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">` 
                : '';
            const fallbackIconHtml = `<div class="product-card-avatar-fallback" style="${prod.image ? 'display:none;' : 'display:flex;'}"><i class="fa-solid ${icon}"></i></div>`;

            card.innerHTML = `
                <div class="product-card-avatar">
                    ${imageHtml}
                    ${fallbackIconHtml}
                </div>
                <div class="product-card-details">
                    <h5>${prod.name}</h5>
                    <p>${prod.price}</p>
                </div>
                <a href="${link}" class="product-card-link" target="_blank">Commander</a>
            `;
            chatHistory.appendChild(card);
        });
        scrollToBottom();
    };

    // Charger l'historique sauvegardé dans localStorage
    const loadSavedHistory = () => {
        const saved = localStorage.getItem('parachatbot_history_' + pageSessionId);
        if (saved) {
            try {
                conversationHistory = JSON.parse(saved);
                conversationHistory.forEach(item => {
                    if (item.sender === 'user' || item.sender === 'bot') {
                        renderHistoricalMessage(item.sender, item.text, item.time);
                    } else if (item.sender === 'products') {
                        renderHistoricalProducts(item.products);
                    }
                });
            } catch (e) {
                console.error("Erreur de décodage de l'historique", e);
                conversationHistory = [];
            }
        }
    };

    // Charger l'historique au démarrage
    loadSavedHistory();

    // Gestion du bouton de réinitialisation (reset)
    const resetBtn = document.getElementById('parabotResetBtn');
    if (resetBtn) {
        resetBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (confirm("Voulez-vous réinitialiser la discussion ?")) {
                localStorage.removeItem('parachatbot_history_' + pageSessionId);
                
                // Générer un nouvel ID de session
                pageSessionId = 'sess_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
                localStorage.setItem('parachatbot_session_id', pageSessionId);
                conversationHistory = [];

                // Réinitialiser l'affichage
                chatHistory.innerHTML = `
                    <div class="chat-message bot">
                        <div class="message-avatar">
                            <i class="fa-solid fa-robot"></i>
                        </div>
                        <div class="message-content">
                            <p>Marhaban ! Bienvenue chez <strong>Kingphar.ma</strong> (Avenue Moulay Abdellah, Marrakech). 🌿 <br><br>Je suis votre docteur et pharmacologue IA. Je peux vous guider à travers notre catalogue et vous suggérer les produits d'excellence adaptés à vos besoins. Décrivez-moi vos symptômes ou vos objectifs santé.</p>
                            <span class="message-time">À l'instant</span>
                        </div>
                    </div>
                `;
                scrollToBottom();
            }
        });
    }

    let isChatbotLoading = false;

    // Fonction de transmission de requête au serveur (Fetch API)
    const transmitQuery = async (queryText) => {
        if (!queryText || isChatbotLoading) return;

        isChatbotLoading = true;
        userInput.disabled = true;

        // Ajouter le message de l'utilisateur
        appendMessage('user', queryText);

        // Afficher l'indicateur d'écriture pour le bot
        appendMessage('bot', '', true);

        // Endpoint AJAX ultra-rapide (contourne le framework lourd de PrestaShop)
        const baseUrl = typeof prestashop !== 'undefined' && prestashop.urls ? prestashop.urls.base_url : '/prestashop/';
        const fastAjaxUrl = baseUrl + 'modules/parachatbot/ajax_chat.php';
        const fallbackUrl = typeof paraChatbotAjaxUrl !== 'undefined' ? paraChatbotAjaxUrl : 'chat.php';
        const ajaxUrl = fastAjaxUrl;

        try {
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    message: queryText,
                    session_id: pageSessionId
                })
            });

            if (!response.ok) throw new Error("Erreur de réponse");

            const data = await response.json();

            // Retirer l'indicateur d'écriture
            const indicator = document.getElementById('parabotTypingIndicator');
            if (indicator) indicator.remove();

            // Ajouter le message de réponse du bot
            appendMessage('bot', data.response);

            // Ajouter les cartes de produits si présentes
            if (data.products && data.products.length > 0) {
                setTimeout(() => {
                    appendProducts(data.products);
                }, 400); // Petit délai après l'écriture du texte
            }

        } catch (error) {
            console.error(error);
            const indicator = document.getElementById('parabotTypingIndicator');
            if (indicator) indicator.remove();
            
            appendMessage('bot', "Désolé, le conseiller rencontre une perturbation réseau. Veuillez réessayer.");
        } finally {
            isChatbotLoading = false;
            userInput.disabled = false;
            userInput.focus();
        }
    };

    // Écouteur d'envoi du formulaire
    chatForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const text = userInput.value.trim();
        userInput.value = '';
        transmitQuery(text);
    });

    // Écouteur des suggestions rapides (pills)
    document.addEventListener('click', (e) => {
        const suggestion = e.target.closest('.suggestion-pill');
        if (suggestion) {
            const text = suggestion.getAttribute('data-text');
            transmitQuery(text);
        }
    });
});
