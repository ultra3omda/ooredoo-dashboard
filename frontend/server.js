const http = require('http');
const httpProxy = require('http-proxy');

const proxy = httpProxy.createProxyServer({
  target: 'http://127.0.0.1:8002',
  changeOrigin: true,
  timeout: 120000,
  proxyTimeout: 120000,
});

proxy.on('error', (err, req, res) => {
  console.error('Proxy error:', err.message);
  if (!res.headersSent) {
    res.writeHead(502, { 'Content-Type': 'text/html' });
    res.end('<h1>Loading... Please wait for PHP server to start.</h1>');
  }
});

const server = http.createServer((req, res) => {
  proxy.web(req, res);
});

const PORT = process.env.PORT || 3000;
const HOST = process.env.HOST || '0.0.0.0';

server.listen(PORT, HOST, () => {
  console.log(`Frontend proxy running on ${HOST}:${PORT} -> PHP:8002`);
});
