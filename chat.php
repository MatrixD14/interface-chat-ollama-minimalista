<?php
/**
 * ============================================================================
 * SINGLE-FILE AI CHAT — ULTRA MINIMAL
 * ============================================================================
 */

// CONFIG
define('ACCESS_PASSWORD', 'local');
$apiKey  = 'ollama';
$baseUrl = 'http://127.0.0.1:11434/v1';
$model   = 'llama3.2:1b';
$allowedModels = ['llama3.2:1b'];
$systemPrompt = 'You are a helpful AI assistant. Be concise and helpful.';
$maxTokens = 4096;

// AUTH
session_start();
$authenticated = false;

if (isset($_GET['pwd']) && hash_equals(ACCESS_PASSWORD, $_GET['pwd'])) {
    $_SESSION['chat_authenticated'] = true;
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}
if (!empty($_SESSION['chat_authenticated'])) $authenticated = true;
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// SSE
if (isset($_GET['stream']) && $authenticated) {
    session_write_close();
    while (ob_get_level()) ob_end_clean();
    ob_implicit_flush(true);

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['messages']) || !is_array($data['messages'])) {
        echo "data: " . json_encode(['error' => 'Invalid request']) . "\n\n";
        exit;
    }

    if (!empty($data['model']) && in_array($data['model'], $allowedModels, true)) {
        $model = $data['model'];
    }

    $messages = $data['messages'];
    if (!empty($systemPrompt)) {
        array_unshift($messages, ['role' => 'system', 'content' => $systemPrompt]);
    }

    $ch = curl_init();
    $buffer = '';
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/chat/completions',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'stream' => true
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'Accept: text/event-stream'
        ],
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$buffer) {
            $buffer .= $data;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                if ($line === '' || strpos($line, 'data: ') !== 0) continue;

                $jsonStr = substr($line, 6);
                if ($jsonStr === '[DONE]') { echo "data: [DONE]\n\n"; flush(); continue; }

                $json = json_decode($jsonStr, true);
                if (!$json) continue;

                if (isset($json['choices'][0]['delta']['content'])) {
                    echo "data: " . json_encode(['content' => $json['choices'][0]['delta']['content']]) . "\n\n";
                    flush();
                } elseif (isset($json['error']['message'])) {
                    echo "data: " . json_encode(['error' => $json['error']['message']]) . "\n\n";
                    flush();
                }
            }
            return strlen($data);
        }
    ]);
    curl_exec($ch);
    if (curl_errno($ch)) {
        echo "data: " . json_encode(['error' => 'Connection error: ' . curl_error($ch)]) . "\n\n";
        flush();
    }
    curl_close($ch);
    exit;
}

// LOGIN
if (!$authenticated):
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; background: #343541; color: #ececf1;
            min-height: 100dvh; display: grid; place-items: center; padding: 16px;
        }
        form {
            display: flex; flex-direction: column; gap: 2px;
            width: min(300px, 90vw);
        }
        input, button { padding: 10px; font-size: 16px; }
        input { background: #2a2b32; color: #ececf1; border: none; }
    </style>
</head>
<body>
    <form method="GET">
        <input type="password" name="pwd" placeholder="Senha" required autofocus>
        <button>Entrar</button>
    </form>
</body>
</html>
<?php
exit;
endif;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="theme-color" content="#343541">
    <title>AI Chat</title>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100dvh; overflow: hidden; }
        body { background: #343541; color: #ececf1; line-height: 1.6; -webkit-tap-highlight-color: transparent; }

        .app { display: flex; height: 100dvh; width: 100%; overflow: hidden; }

        /* SIDEBAR */
        .sidebar {
            width: 200px; background: #202123; padding: 8px;
            display: flex; flex-direction: column; flex-shrink: 0;
        }
        .new-btn {
            width: 100%; padding: 10px; background: #2a2b32; border: none;
            color: #ececf1; font-size: 13px; cursor: pointer; text-align: left;
        }
        .sidebar-scroll { flex: 1; overflow-y: auto; padding: 8px 0; }
        .chat-item {
            display: flex; align-items: center; gap: 6px;
            padding: 8px 4px; font-size: 13px; cursor: pointer;
        }
        .chat-item.active { background: #2a2b32; }
        .chat-item-title { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .chat-del {
            background: transparent; border: none; color: #8e8ea0;
            cursor: pointer; padding: 2px 5px; font-size: 16px; line-height: 1; flex-shrink: 0;
        }
        .logout-btn { display: block; padding: 10px 4px; color: #ececf1; font-size: 13px; text-decoration: none; }

        /* MAIN */
        .main { flex: 1; display: flex; flex-direction: column; min-width: 0; overflow: hidden; }
        .header {
            display: flex; align-items: center; padding: 8px 10px;
            padding-top: calc(8px + env(safe-area-inset-top)); flex-shrink: 0;
        }
        .model-wrap { position: relative; }
        .model-btn {
            display: flex; align-items: center; gap: 4px;
            padding: 7px 10px; background: transparent; border: none;
            color: #ececf1; font-size: 13px; font-weight: 600; cursor: pointer;
            max-width: 220px;
        }
        .model-btn span:first-child { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .fs-btn {
            margin-left: auto; background: transparent; border: none;
            color: #ececf1; padding: 7px 10px; cursor: pointer; font-size: 16px; line-height: 1;
        }
        .model-menu {
            display: none; position: absolute; top: 100%; left: 0; margin-top: 4px;
            background: #2a2b32; padding: 4px; min-width: 150px; z-index: 50;
        }
        .model-menu.show { display: block; }
        .model-opt { padding: 9px 12px; cursor: pointer; font-size: 13px; }
        .model-opt.active { color: #10a37f; }

        /* CHAT */
        .chat-scroll { flex: 1; overflow-y: auto; overflow-x: hidden; -webkit-overflow-scrolling: touch; }
        .chat-messages { max-width: 768px; margin: 0 auto; padding: 16px; }
        .empty {
            min-height: 55vh; display: flex; align-items: center; justify-content: center;
            padding: 20px; text-align: center;
        }
        .empty h2 { font-size: 17px; font-weight: 400; color: #8e8ea0; }

        .msg { display: flex; gap: 10px; padding: 10px 0; }
        .msg-avatar {
            width: 28px; height: 28px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 600;
        }
        .msg.user .msg-avatar { background: #5436da; color: #fff; }
        .msg.assistant .msg-avatar { background: #10a37f; color: #fff; }
        .msg-content { flex: 1; min-width: 0; overflow-wrap: break-word; word-break: break-word; }
        .msg-content p { margin-bottom: 10px; }
        .msg-content p:last-child { margin-bottom: 0; }
        .msg-content ul, .msg-content ol { margin: 10px 0; padding-left: 22px; }
        .msg-content li { margin-bottom: 4px; }
        .msg-content h1, .msg-content h2, .msg-content h3 { margin: 14px 0 10px; font-weight: 600; }
        .msg-content h1 { font-size: 1.3em; }
        .msg-content h2 { font-size: 1.15em; }
        .msg-content h3 { font-size: 1.05em; }
        .msg-content a { color: #7ab7ff; text-decoration: none; }
        .msg-content strong { font-weight: 600; }
        .msg-content em { font-style: italic; }
        .msg-content code {
            font-family: monospace; font-size: 13px;
            background: rgba(255,255,255,0.1); padding: 1px 5px;
        }
        .code-wrap { position: relative; margin: 10px 0; background: #1e1e1e; }
        .code-wrap pre { margin: 0; padding: 12px; overflow-x: auto; }
        .code-wrap code {
            background: transparent !important; padding: 0;
            font-size: 12.5px; line-height: 1.5; display: block;
        }
        .copy-btn {
            position: absolute; top: 5px; right: 5px;
            background: #40414f; border: none; color: #b4b4b4;
            font-size: 11px; padding: 3px 8px; cursor: pointer;
        }
        .cursor {
            display: inline-block; width: 2px; height: 1em;
            background: #ececf1; vertical-align: text-bottom;
        }
        .error { background: rgba(255,77,77,0.1); color: #ff6b6b; padding: 10px 14px; margin: 10px 0; font-size: 13px; }

        /* INPUT */
        .input-area {
            flex-shrink: 0; padding: 10px 14px;
            padding-bottom: calc(10px + env(safe-area-inset-bottom));
        }
        .input-wrap { max-width: 768px; margin: 0 auto; }
        .input-box { display: flex; align-items: flex-end; gap: 8px; background: #2a2b32; padding: 8px 10px; }
        .input-box textarea {
            flex: 1; background: transparent; border: none; outline: none;
            color: #ececf1; font-size: 16px; line-height: 1.5; resize: none;
            max-height: 140px; min-height: 22px; font-family: inherit;
        }
        .input-box textarea::placeholder { color: #8e8ea0; }
        .send-btn, .stop-btn {
            width: 34px; height: 34px; border: none; cursor: pointer; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 17px; font-weight: 700; font-family: inherit;
        }
        .send-btn { background: #10a37f; color: #fff; }
        .send-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .stop-btn { background: #565869; color: #fff; display: none; }
        .is-streaming .send-btn { display: none; }
        .is-streaming .stop-btn { display: flex; }

        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); }

        @media (max-width: 768px) {
            .sidebar { width: 120px; padding: 6px; }
            .new-btn { padding: 8px 4px; font-size: 11px; }
            .chat-item { padding: 6px 4px; font-size: 11px; gap: 4px; }
            .chat-del { padding: 2px 4px; font-size: 14px; }
            .logout-btn { padding: 8px 4px; font-size: 11px; }
            .chat-messages { padding: 10px; }
            .msg { gap: 8px; padding: 8px 0; }
            .msg-avatar { width: 24px; height: 24px; font-size: 11px; }
            .empty h2 { font-size: 15px; }
            .input-area { padding: 8px 10px; padding-bottom: calc(8px + env(safe-area-inset-bottom)); }
        }
    </style>
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <button class="new-btn" id="newChatBtn">New</button>
            <div class="sidebar-scroll"><div id="chatHistory"></div></div>
            <a href="?logout=1" class="logout-btn">Sair</a>
        </aside>

        <main class="main">
            <header class="header">
                <div class="model-wrap">
                    <button class="model-btn" id="modelBtn">
                        <span id="currentModel"><?php echo htmlspecialchars($model); ?></span>
                        <span style="font-size:10px">▾</span>
                    </button>
                    <div class="model-menu" id="modelMenu">
                        <?php foreach ($allowedModels as $m): ?>
                        <div class="model-opt" data-model="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button class="fs-btn" id="fullscreenBtn" title="Tela cheia">⛶</button>
            </header>

            <div class="chat-scroll" id="chatScroll">
                <div class="chat-messages" id="chatMessages">
                    <div class="empty" id="emptyState"><h2>Como posso ajudar?</h2></div>
                </div>
            </div>

            <div class="input-area">
                <div class="input-wrap">
                    <div class="input-box" id="inputBox">
                        <textarea id="messageInput" placeholder="Mensagem..." rows="1" autofocus></textarea>
                        <button class="send-btn" id="sendBtn" disabled>→</button>
                        <button class="stop-btn" id="stopBtn">■</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    (function() {
        'use strict';

        let messages = [];
        let isStreaming = false;
        let currentModel = <?php echo json_encode($model); ?>;
        let chatHistories = JSON.parse(localStorage.getItem('chatHistories') || '[]');
        let currentChatId = null;
        let abortController = null;

        const $ = s => document.querySelector(s);
        const chatMessages = $('#chatMessages');
        const chatScroll = $('#chatScroll');
        const messageInput = $('#messageInput');
        const sendBtn = $('#sendBtn');
        const stopBtn = $('#stopBtn');
        const inputBox = $('#inputBox');
        const emptyState = $('#emptyState');
        const modelMenu = $('#modelMenu');
        const currentModelEl = $('#currentModel');
        const modelOpts = document.querySelectorAll('.model-opt');

        function escapeHtml(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function renderMarkdown(text) {
            if (!text) return '';
            const parts = text.split(/(```[\s\S]*?```)/g);
            let html = '';
            for (let i = 0; i < parts.length; i++) {
                if (i % 2 === 1) {
                    let body = parts[i].slice(3, -3);
                    const nl = body.indexOf('\n');
                    if (nl > 0 && nl < 20 && /^[a-zA-Z0-9+#-]+$/.test(body.slice(0, nl).trim())) {
                        body = body.slice(nl + 1);
                    }
                    html += '<div class="code-wrap"><button class="copy-btn" onclick="copyCode(this)">Copiar</button><pre><code>'
                          + escapeHtml(body.replace(/^\n+|\n+$/g, '')) + '</code></pre></div>';
                } else {
                    let t = escapeHtml(parts[i]);
                    t = t.replace(/`([^`\n]+)`/g, '<code>$1</code>');
                    t = t.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
                    t = t.replace(/\*([^*\n]+)\*/g, '<em>$1</em>');
                    t = t.replace(/\[([^\]]+)\]\(([^)]+)\)/g, (m, txt, url) =>
                        /^(https?:\/\/|mailto:)/i.test(url)
                            ? '<a href="' + url + '" target="_blank" rel="noopener">' + txt + '</a>'
                            : m
                    );
                    t = t.replace(/\n/g, '<br>');
                    html += t;
                }
            }
            return html;
        }

        function scrollToBottom() { chatScroll.scrollTop = chatScroll.scrollHeight; }
        function autoResize() {
            messageInput.style.height = 'auto';
            messageInput.style.height = Math.min(messageInput.scrollHeight, 140) + 'px';
        }
        function updateSendBtn() { sendBtn.disabled = !messageInput.value.trim() || isStreaming; }
        function setStreaming(s) {
            isStreaming = s;
            inputBox.classList.toggle('is-streaming', s);
            updateSendBtn();
        }

        function appendMessage(role, content, streaming) {
            emptyState.style.display = 'none';
            const id = 'm_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6);
            const avatar = role === 'user' ? 'U' : 'A';
            const rendered = role === 'user'
                ? '<p>' + escapeHtml(content) + '</p>'
                : (streaming ? renderMarkdown(content) + '<span class="cursor"></span>' : renderMarkdown(content));
            const div = document.createElement('div');
            div.className = 'msg ' + role;
            div.id = id;
            div.innerHTML = '<div class="msg-avatar">' + avatar + '</div>'
                          + '<div class="msg-content">' + rendered + '</div>';
            chatMessages.appendChild(div);
            scrollToBottom();
            return id;
        }

        function updateMessage(id, content, isFinal) {
            const el = document.querySelector('#' + id + ' .msg-content');
            if (!el) return;
            el.innerHTML = isFinal
                ? renderMarkdown(content)
                : renderMarkdown(content) + '<span class="cursor"></span>';
            scrollToBottom();
        }

        function showError(msg) {
            const div = document.createElement('div');
            div.className = 'error';
            div.textContent = msg;
            chatMessages.appendChild(div);
            scrollToBottom();
        }

        function genId() { return 'c_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8); }

        function saveHistory() {
            if (!messages.length) return;
            const title = messages[0].content.slice(0, 45) + (messages[0].content.length > 45 ? '…' : '');
            if (!currentChatId) currentChatId = genId();
            const data = { id: currentChatId, title, messages, model: currentModel, updatedAt: Date.now() };
            const idx = chatHistories.findIndex(c => c.id === currentChatId);
            if (idx >= 0) chatHistories[idx] = data;
            else chatHistories.unshift(data);
            chatHistories = chatHistories.slice(0, 50);
            localStorage.setItem('chatHistories', JSON.stringify(chatHistories));
            renderHistory();
        }

        function renderHistory() {
            const container = $('#chatHistory');
            container.innerHTML = '';
            chatHistories.forEach(chat => {
                const div = document.createElement('div');
                div.className = 'chat-item' + (chat.id === currentChatId ? ' active' : '');
                div.dataset.id = chat.id;
                div.innerHTML = '<span class="chat-item-title">' + escapeHtml(chat.title) + '</span>'
                              + '<button class="chat-del">×</button>';
                container.appendChild(div);
            });
        }

        function setActiveModel(m) {
            currentModelEl.textContent = m;
            modelOpts.forEach(el => el.classList.toggle('active', el.dataset.model === m));
        }

        function loadChat(id) {
            const chat = chatHistories.find(c => c.id === id);
            if (!chat) return;
            currentChatId = chat.id;
            messages = chat.messages.slice();
            if (chat.model) { currentModel = chat.model; setActiveModel(currentModel); }
            chatMessages.innerHTML = '';
            if (!messages.length) {
                chatMessages.appendChild(emptyState);
                emptyState.style.display = '';
            } else {
                emptyState.style.display = 'none';
                messages.forEach(m => appendMessage(m.role, m.content));
            }
            renderHistory();
        }

        function startNewChat() {
            currentChatId = null;
            messages = [];
            chatMessages.innerHTML = '';
            chatMessages.appendChild(emptyState);
            emptyState.style.display = '';
            renderHistory();
            messageInput.focus();
        }

        async function sendMessage(text) {
            messages.push({ role: 'user', content: text });
            appendMessage('user', text);
            saveHistory();

            messageInput.value = '';
            autoResize();
            updateSendBtn();
            setStreaming(true);

            const msgId = appendMessage('assistant', '', true);
            let acc = '';
            let rafId = null;
            abortController = new AbortController();

            try {
                const res = await fetch('?stream=1', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ messages, model: currentModel }),
                    signal: abortController.signal
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);

                const reader = res.body.getReader();
                const decoder = new TextDecoder();
                let buf = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    buf += decoder.decode(value, { stream: true });
                    const lines = buf.split('\n');
                    buf = lines.pop() || '';
                    for (const line of lines) {
                        if (!line.startsWith('data: ')) continue;
                        const raw = line.slice(6);
                        if (raw === '[DONE]') continue;
                        let p;
                        try { p = JSON.parse(raw); } catch (e) { continue; }
                        if (p.error) throw new Error(p.error);
                        if (p.content) {
                            acc += p.content;
                            if (!rafId) {
                                rafId = requestAnimationFrame(() => {
                                    rafId = null;
                                    updateMessage(msgId, acc);
                                });
                            }
                        }
                    }
                }

                if (rafId) { cancelAnimationFrame(rafId); rafId = null; }

                if (acc) {
                    messages.push({ role: 'assistant', content: acc });
                    updateMessage(msgId, acc, true);
                    saveHistory();
                } else {
                    document.getElementById(msgId)?.remove();
                }
            } catch (err) {
                if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
                if (err.name === 'AbortError') {
                    if (acc) {
                        messages.push({ role: 'assistant', content: acc });
                        updateMessage(msgId, acc, true);
                        saveHistory();
                    } else {
                        document.getElementById(msgId)?.remove();
                    }
                } else {
                    document.getElementById(msgId)?.remove();
                    showError('Erro: ' + err.message);
                }
            } finally {
                abortController = null;
                setStreaming(false);
            }
        }

        sendBtn.addEventListener('click', () => {
            const text = messageInput.value.trim();
            if (text && !isStreaming) sendMessage(text);
        });
        messageInput.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                const text = messageInput.value.trim();
                if (text && !isStreaming) sendMessage(text);
            }
        });
        messageInput.addEventListener('input', () => { autoResize(); updateSendBtn(); });
        stopBtn.addEventListener('click', () => { if (abortController) abortController.abort(); });
        $('#newChatBtn').addEventListener('click', startNewChat);

        $('#chatHistory').addEventListener('click', e => {
            const item = e.target.closest('.chat-item');
            if (!item) return;
            if (e.target.classList.contains('chat-del')) {
                e.stopPropagation();
                if (confirm('Deletar esta conversa?')) {
                    const id = item.dataset.id;
                    chatHistories = chatHistories.filter(c => c.id !== id);
                    localStorage.setItem('chatHistories', JSON.stringify(chatHistories));
                    if (currentChatId === id) startNewChat();
                    else renderHistory();
                }
                return;
            }
            loadChat(item.dataset.id);
        });

        $('#modelBtn').addEventListener('click', e => {
            e.stopPropagation();
            modelMenu.classList.toggle('show');
        });
        modelOpts.forEach(el => {
            el.addEventListener('click', () => {
                currentModel = el.dataset.model;
                setActiveModel(currentModel);
                modelMenu.classList.remove('show');
            });
        });
        document.addEventListener('click', () => modelMenu.classList.remove('show'));

        $('#fullscreenBtn').addEventListener('click', async () => {
            try {
                if (!document.fullscreenElement) await document.documentElement.requestFullscreen();
                else await document.exitFullscreen();
            } catch (e) { alert('Tela cheia não suportada.'); }
        });

        renderHistory();
        updateSendBtn();
        setActiveModel(currentModel);
        messageInput.focus();
    })();

    function copyCode(btn) {
        const code = btn.closest('.code-wrap').querySelector('code').textContent;
        const done = () => { const t = btn.textContent; btn.textContent = 'Copiado!'; setTimeout(() => btn.textContent = t, 1500); };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(code).then(done).catch(() => fallbackCopy(code, done));
        } else fallbackCopy(code, done);
    }
    function fallbackCopy(text, cb) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;opacity:0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); cb(); } catch (e) {}
        document.body.removeChild(ta);
    }
    </script>
</body>
</html>
