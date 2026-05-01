(function () {
    'use strict';

    const fab        = document.getElementById('chatFab');
    const chatWindow = document.getElementById('chatWindow');
    const closeBtn   = document.getElementById('chatClose');
    const form       = document.getElementById('chatForm');
    const input      = document.getElementById('chatInput');
    const sendBtn    = document.getElementById('chatSend');
    const messages   = document.getElementById('chatMessages');

    function openChat() {
        chatWindow.classList.remove('chat-hidden');
        input.focus();
        fab.setAttribute('aria-expanded', 'true');
    }

    function closeChat() {
        chatWindow.classList.add('chat-hidden');
        fab.setAttribute('aria-expanded', 'false');
    }

    fab.addEventListener('click', function () {
        chatWindow.classList.contains('chat-hidden') ? openChat() : closeChat();
    });

    closeBtn.addEventListener('click', closeChat);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !chatWindow.classList.contains('chat-hidden')) {
            closeChat();
        }
    });

    function appendBubble(text, type) {
        var div = document.createElement('div');
        div.className = 'chat-bubble chat-bubble--' + type;
        div.textContent = text;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
        return div;
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        var userText = input.value.trim();
        if (!userText) return;

        appendBubble(userText, 'user');
        input.value = '';
        input.disabled = true;
        sendBtn.disabled = true;

        var typingBubble = appendBubble('Thinking…', 'typing');

        try {
            var res = await fetch(BASE_PATH + '/api/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ message: userText }),
            });

            typingBubble.remove();

            if (!res.ok) {
                var errMsg = 'Sorry, something went wrong. Please try again.';
                try {
                    var errData = await res.json();
                    if (errData.error) errMsg = errData.error;
                } catch (_) {}
                appendBubble(errMsg, 'error');
                return;
            }

            var data = await res.json();
            if (data.reply) {
                appendBubble(data.reply, 'assistant');
            } else if (data.error) {
                appendBubble(data.error, 'error');
            }

        } catch (networkErr) {
            typingBubble.remove();
            appendBubble('Network error — please check your connection and try again.', 'error');
        } finally {
            input.disabled = false;
            sendBtn.disabled = false;
            input.focus();
        }
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

})();
