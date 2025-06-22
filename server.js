// server.js
const http = require('http');
const express = require('express');
const { WebSocketServer } = require('ws');
const puppeteer = require('puppeteer');
const path = require('path');
const fs = require('fs');
const { execFile } = require('child_process');
const url = require('url');
const mysql = require('mysql2/promise');
const fetch = require('node-fetch');

const app = express();
const server = http.createServer(app);
const wss = new WebSocketServer({ server });

// These values would ideally come from an environment file shared with PHP
const PORT = 8080; 
const FREE_USER_BANDWIDTH_LIMIT_BYTES = 50 * 1024 * 1024;
const IDLE_TIMEOUT_MS = 10 * 60 * 1000;

const dbPool = mysql.createPool({
    host: 'localhost', // Replace with DB_HOST from config
    user: 'your_username', // Replace with DB_USER from config
    password: 'your_password', // Replace with DB_PASS from config
    database: 'your_database', // Replace with DB_NAME from config
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
});

app.use(express.static(__dirname));

const phpCgi = (req, res, next) => {
    const parsedUrl = url.parse(req.originalUrl);
    const phpFile = path.join(__dirname, parsedUrl.pathname);
    if (!phpFile.endsWith('.php')) return next();
    const env = { REDIRECT_STATUS: 200, REQUEST_METHOD: req.method, SCRIPT_FILENAME: phpFile, QUERY_STRING: parsedUrl.query || '', CONTENT_TYPE: req.headers['content-type'] || '', CONTENT_LENGTH: req.headers['content-length'] || 0, SERVER_PROTOCOL: 'HTTP/1.1', GATEWAY_INTERFACE: 'CGI/1.1', HTTP_COOKIE: req.headers.cookie || '' };
    const php = execFile('php-cgi', [], { env }, (error, stdout) => { if (error) { console.error(error); return res.status(500).send('Error processing PHP file.'); } const [rawHeaders, body] = stdout.split('\r\n\r\n', 2); (rawHeaders.split('\r\n')).forEach(header => { if (header) { const [name, value] = header.split(': '); if (name && value) res.setHeader(name, value); } }); res.send(body); });
    if (req.method === 'POST') req.pipe(php.stdin);
};
app.use(phpCgi);

wss.on('connection', async (ws, req) => {
    let userData;
    try {
        const queryParams = new url.URLSearchParams(req.url.slice(1));
        const sessionId = queryParams.get('sessionId');
        if (!sessionId) throw new Error('Session ID not provided.');

        const validationUrl = `http://localhost:${PORT}/auth/validate_session.php?sessionId=${sessionId}`;
        const response = await fetch(validationUrl);
        userData = await response.json();
        if (!response.ok || userData.status !== 'authenticated') throw new Error(userData.error || 'Session validation failed.');
    } catch (err) {
        console.error('Authentication failed:', err.message);
        return ws.close(1008, 'Authentication failed.');
    }

    ws.userId = userData.userId;
    ws.accountLevel = userData.accountLevel;
    console.log(`Client connected: User ID ${ws.userId}, Level: ${ws.accountLevel}`);

    let userBrowser, page;
    try {
        const userSessionPath = path.join(__dirname, 'user_sessions', ws.userId.toString());
        fs.mkdirSync(userSessionPath, { recursive: true });
        userBrowser = await puppeteer.launch({ headless: true, userDataDir: userSessionPath, args: ['--no-sandbox', '--disable-setuid-sandbox'] });
        page = await userBrowser.newPage();
    } catch(err) {
        console.error(`Browser launch failed for User ${ws.userId}:`, err);
        return ws.close(1011, 'Browser launch failed.');
    }
    
    let idleTimeout = setTimeout(() => ws.close(), IDLE_TIMEOUT_MS);
    const resetIdleTimeout = () => { clearTimeout(idleTimeout); idleTimeout = setTimeout(() => ws.close(), IDLE_TIMEOUT_MS); };

    const sendPageContent = async () => {
        if (!page || page.isClosed()) return;
        try {
            const content = await page.content();
            
            if (ws.accountLevel === 'free_user') {
                const dataSize = Buffer.byteLength(content, 'utf8');
                const today = new Date().toISOString().slice(0, 10);
                const [rows] = await dbPool.execute('SELECT usage_bytes FROM bandwidth_usage WHERE user_id = ? AND date = ?', [ws.userId, today]);
                const currentUsage = rows[0]?.usage_bytes || 0;
                if (currentUsage + dataSize > FREE_USER_BANDWIDTH_LIMIT_BYTES) {
                    return ws.close(4000, 'Daily bandwidth limit exceeded.');
                }
                await dbPool.execute('INSERT INTO bandwidth_usage (user_id, date, usage_bytes) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE usage_bytes = usage_bytes + ?', [ws.userId, today, dataSize, dataSize]);
            }

            const pageUrl = page.url();
            const baseTag = `<base href="${pageUrl}">`;
            const interactionScript = `<script>document.addEventListener('contextmenu',e=>e.preventDefault());let lastMove=0;document.addEventListener('mousemove',e=>{if(Date.now()-lastMove<20)return;lastMove=Date.now();window.parent.postMessage({type:'mouse',x:e.clientX,y:e.clientY},'*')});document.addEventListener('click',e=>{e.preventDefault();window.parent.postMessage({type:'click',x:e.clientX,y:e.clientY},'*')},!0);document.addEventListener('keydown',e=>{e.preventDefault();window.parent.postMessage({type:'keyboard',key:e.key,code:e.code},'*')},!0);let lastScroll=0;window.addEventListener('scroll',()=>{if(Date.now()-lastScroll<100)return;lastScroll=Date.now();window.parent.postMessage({type:'scroll',x:window.scrollX,y:window.scrollY},'*')})<\/script>`;
            const finalContent = content.replace('</head>', `${baseTag}${interactionScript}</head>`);
            ws.send(JSON.stringify({ html: finalContent, url: pageUrl }));
        } catch (error) {
            if (ws.readyState !== ws.CLOSED) console.error('Failed to send page content:', error.message);
        }
    };

    ws.on('message', async (message) => {
        resetIdleTimeout();
        let data;
        try { data = JSON.parse(message); } catch (e) { return; }
        try {
            switch (data.type) {
                case 'navigate': await page.goto(data.url, { waitUntil: 'networkidle2', timeout: 30000 }); await sendPageContent(); break;
                case 'mouse': await page.mouse.move(data.x, data.y); break;
                case 'click': await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 5000 }).catch(() => {}), page.mouse.click(data.x, data.y)]); await sendPageContent(); break;
                case 'keyboard': await page.keyboard.press(data.key); break;
                case 'scroll': await page.evaluate((x, y) => { window.scrollTo(x, y); }, data.x, data.y); break;
            }
        } catch (error) { console.error(`Error processing message:`, error.message); }
    });

    ws.on('close', async () => {
        clearTimeout(idleTimeout);
        if (userBrowser) {
            console.log(`Closing browser for User ID ${ws.userId}.`);
            await userBrowser.close();
        }
    });
});

server.listen(PORT, () => console.log(`Server running on http://your_vps_ip:${PORT}`));
