<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interactive Browser</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <style> body { font-family: sans-serif; } #loading-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(255, 255, 255, 0.8); z-index: 9999; display: flex; justify-content: center; align-items: center; flex-direction: column; gap: 1rem; } .loader { border: 8px solid #f3f3f3; border-radius: 50%; border-top: 8px solid #3498db; width: 60px; height: 60px; animation: spin 2s linear infinite; } @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } } </style>
</head>
<body class="bg-gray-100">
    <?php
        require_once 'auth/session_config.php';
        if (isset($_SESSION['loggedin'])) {
            echo "<script> const userSession = { isLoggedIn: true, accountLevel: '" . htmlspecialchars($_SESSION['account_level'], ENT_QUOTES) . "', sessionId: '" . session_id() . "' }; </script>";
        } else {
            echo "<script> const userSession = { isLoggedIn: false }; </script>";
        }
    ?>
    <div id="app-container" class="container mx-auto p-4" style="display: none;">
        <div class="bg-white rounded-lg shadow-md p-4 mb-4">
            <div class="flex justify-between items-center mb-4">
                <h1 class="text-2xl font-bold text-gray-800">Interactive Server-Side Browser</h1>
                <div>
                    <a href="dashboard.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg mr-2">My Dashboard</a>
                    <a id="admin-link" href="admin.html" class="hidden bg-purple-500 hover:bg-purple-600 text-white font-bold py-2 px-4 rounded-lg mr-2">Admin Panel</a>
                    <a href="auth/logout.php" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg">Logout</a>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-2">
                <input type="text" id="url-input" class="flex-grow p-2 border border-gray-300 rounded-lg" placeholder="https://example.com">
                <button id="go-button" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg">이동</button>
            </div>
        </div>
        <div id="browser-view-container" class="relative bg-white rounded-lg shadow-md" style="height: 80vh;">
            <div id="loading-overlay" class="hidden">
                <div class="loader"></div>
                <p class="text-gray-600">Loading page...</p>
            </div>
            <iframe id="browser-view" class="w-full h-full border-0 rounded-lg"></iframe>
        </div>
    </div>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        if (typeof userSession !== 'undefined' && userSession.isLoggedIn) {
            document.getElementById('app-container').style.display = 'block';
            connectWebSocket();
            if (userSession.accountLevel === 'admin') {
                document.getElementById('admin-link').classList.remove('hidden');
            }
        } else {
            window.location.href = 'login.html';
        }

        const urlInput = document.getElementById('url-input');
        const goButton = document.getElementById('go-button');
        const browserView = document.getElementById('browser-view');
        const loadingOverlay = document.getElementById('loading-overlay');
        let socket;

        function connectWebSocket() {
            const wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
            const wsUrl = `${wsProtocol}//${window.location.host}?sessionId=${userSession.sessionId}`;
            socket = new WebSocket(wsUrl);

            socket.onopen = () => console.log("[open] WebSocket established.");
            socket.onmessage = (event) => {
                loadingOverlay.classList.add('hidden');
                try {
                    const data = JSON.parse(event.data);
                    if (data.error) { Toastify({ text: "Error: " + data.error, duration: 5000, style: { background: "red" }}).showToast(); return; }
                    if (data.html) { browserView.srcdoc = data.html; if (data.url && document.activeElement !== urlInput) urlInput.value = data.url; }
                } catch (e) { console.error("Invalid JSON:", event.data); }
            };
            socket.onclose = (event) => {
                console.log(`[close] Connection died. Code: ${event.code}, Reason: ${event.reason}`);
                if (event.code === 4000) {
                    Toastify({ text: "Bandwidth Limit Exceeded: You have used your 50MB daily limit.", duration: -1, close: true, gravity: "top", position: "center", style: { background: "linear-gradient(to right, #ff5f6d, #ffc371)" } }).showToast();
                    document.getElementById('url-input').disabled = true;
                    document.getElementById('go-button').disabled = true;
                } else if (event.code !== 1000) { setTimeout(connectWebSocket, 3000); }
            };
            socket.onerror = (error) => console.log(`[error] ${error.message}`);
        }

        function sendMessage(data) { if (socket && socket.readyState === WebSocket.OPEN) socket.send(JSON.stringify(data)); }
        function navigateToUrl(url) { if (!url) return; url = (!url.startsWith('http://') && !url.startsWith('https://')) ? 'https://' + url : url; urlInput.value = url; loadingOverlay.classList.remove('hidden'); sendMessage({ type: 'navigate', url: url }); }
        goButton.addEventListener('click', () => navigateToUrl(urlInput.value.trim()));
        urlInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') navigateToUrl(urlInput.value.trim()); });
        window.addEventListener('message', (event) => { const data = event.data; if (!data || !data.type) return; if (['mouse', 'click', 'keyboard', 'scroll'].includes(data.type)) { sendMessage(data); if(data.type === 'click') loadingOverlay.classList.remove('hidden'); } });
    </script>
</body>
</html>
