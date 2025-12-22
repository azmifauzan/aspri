# Security Guidelines - ASPRI

## Environment Variables & Secrets

### CRITICAL: Never Commit Secrets

**JANGAN PERNAH** commit file berikut ke git:
- `.env`
- `.env.local`
- `.env.*.local`
- File apapun yang mengandung credentials atau API keys

File-file ini sudah ada di `.gitignore`. Pastikan tidak pernah di-force add.

### JWT Secret

JWT_SECRET adalah komponen **PALING PENTING** untuk keamanan aplikasi.

#### Requirements:
- **Minimum 32 characters**
- **Random dan unpredictable**
- **Unik per environment** (dev/staging/production harus berbeda)
- **Tidak boleh hardcoded** di source code

#### Generate Strong JWT Secret:

**Windows PowerShell:**
```powershell
-join ((48..57) + (65..90) + (97..122) | Get-Random -Count 32 | ForEach-Object {[char]$_})
```

**Linux/Mac:**
```bash
openssl rand -base64 32
```

**Node.js:**
```javascript
require('crypto').randomBytes(32).toString('base64')
```

#### Setting JWT Secret:

1. **Docker Compose**: Set di file `.env`
   ```bash
   JWT_SECRET=your_generated_secret_here
   ```

2. **Local Development**: Set environment variable
   ```bash
   # Windows
   set JWT_SECRET=your_generated_secret_here
   
   # Linux/Mac
   export JWT_SECRET=your_generated_secret_here
   ```

3. **Production**: Use secret management service
   - AWS Secrets Manager
   - Azure Key Vault
   - Google Cloud Secret Manager
   - HashiCorp Vault

### Database Credentials

#### Default Credentials (DEVELOPMENT ONLY):
- Username: `postgres`
- Password: `postgres`

#### Production:
- **WAJIB** ubah default credentials
- Gunakan strong password (minimum 16 characters, mixed case, numbers, symbols)
- Jangan gunakan username `postgres` di production
- Enable SSL connection
- Restrict network access

### API Keys & External Services

Untuk integrasi dengan layanan eksternal (LLM, Telegram, WhatsApp):

1. **NEVER hardcode API keys** di source code
2. **Always use environment variables**
3. **Rotate keys regularly**
4. **Use different keys per environment**

Example `.env`:
```bash
# Spring AI - OpenAI
SPRING_AI_OPENAI_API_KEY=sk-...

# Telegram Bot
TELEGRAM_BOT_TOKEN=123456:ABC-...
```

## Git Security

### Pre-commit Checks

Sebelum commit, pastikan:
1. No `.env` files
2. No hardcoded passwords
3. No API keys in code
4. No private keys or certificates

### Git History Cleanup

Jika accidentally commit secrets:

```bash
# Remove file from git history
git filter-branch --force --index-filter \
  "git rm --cached --ignore-unmatch path/to/file" \
  --prune-empty --tag-name-filter cat -- --all

# Force push (WARNING: this rewrites history)
git push origin --force --all
```

**Important:** Setelah remove secret dari git:
1. **IMMEDIATELY rotate** the exposed secret
2. Generate new secret/key/password
3. Update di semua environments

## GitGuardian Alerts

Jika GitGuardian mendeteksi secret:

1. **DO NOT IGNORE** the alert
2. **Verify** apakah secret valid atau false positive
3. Jika valid:
   - Rotate secret immediately
   - Remove dari git history
   - Update `.gitignore` jika perlu
4. Jika false positive:
   - Mark sebagai false positive di GitGuardian
   - Add comment explaining why

## Best Practices

### ✅ DO:
- Use environment variables untuk semua secrets
- Use strong, random secrets
- Rotate secrets regularly
- Use different secrets per environment
- Use secret management services di production
- Review `.gitignore` regularly
- Use `.env.example` dengan placeholder values

### ❌ DON'T:
- Hardcode secrets di source code
- Commit `.env` files
- Use weak or predictable secrets
- Share secrets via email/chat
- Reuse secrets across environments
- Store secrets di comments atau documentation
- Use default credentials di production

## Incident Response

Jika secret terexpose:

1. **Immediately**:
   - Rotate secret
   - Revoke exposed credentials
   - Check access logs untuk unauthorized access

2. **Within 24 hours**:
   - Remove secret dari git history
   - Update semua environments dengan new secret
   - Document incident

3. **Within 1 week**:
   - Review security practices
   - Update documentation
   - Train team jika perlu

## Contact

Jika menemukan security vulnerability, laporkan ke:
- Security team
- Repository maintainer
- [Buat private security advisory di GitHub]

**DO NOT** open public issue untuk security vulnerabilities.
