const http = require('http');
const crypto = require('crypto');
const { execSync } = require('child_process');

const PORT = 9004;
const SECRET = process.env.WEBHOOK_SECRET || 'sim-clinic-deploy-secret-2026';
const DEPLOY_SCRIPT = '/home/mebaclinic/repo/deploy.sh';

const server = http.createServer((req, res) => {
  if (req.method !== 'POST' || req.url !== '/hooks/deploy') {
    res.writeHead(404);
    res.end('Not Found');
    return;
  }

  let body = '';
  req.on('data', chunk => { body += chunk; });
  req.on('end', () => {
    // Verify HMAC signature (GitHub-style)
    const signature = req.headers['x-hub-signature-256'] || '';
    const expected = 'sha256=' + crypto.createHmac('sha256', SECRET).update(body).digest('hex');

    if (signature !== expected) {
      console.log('Webhook: invalid signature');
      res.writeHead(401);
      res.end('Unauthorized');
      return;
    }

    let payload;
    try { payload = JSON.parse(body); } catch(e) {
      res.writeHead(400); res.end('Bad Request'); return;
    }

    const ref = payload.ref || '';
    const branch = ref.replace('refs/heads/', '');

    if (branch !== 'main') {
      console.log(`Webhook: ignoring branch ${branch}`);
      res.writeHead(200);
      res.end(`Ignored branch ${branch}`);
      return;
    }

    console.log(`Webhook: deploy triggered for branch ${branch}`);
    res.writeHead(200);
    res.end('Deploying...');

    // Run deploy script in background
    try {
      execSync(`nohup bash ${DEPLOY_SCRIPT} > /tmp/sim-clinic-deploy.log 2>&1 &`);
    } catch(e) {
      console.error('Deploy failed:', e.message);
    }
  });
});

server.listen(PORT, '127.0.0.1', () => {
  console.log(`Sim Clinic webhook server listening on 127.0.0.1:${PORT}`);
});
