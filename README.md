# Anthropic API Proxy (PHP)

ملف PHP واحد يعمل كأنه Anthropic API بالضبط (يدعم streaming وكل الهيدرات).

## التركيب
1. ارفع `index.php` و `.htaccess` و `.env` لجذر الدومين (PHP 8 + curl + HTTPS).
2. انسخ `.env.example` إلى `.env` وضع مفتاحك ومفاتيح عملائك.

## للعميل
```bash
export ANTHROPIC_BASE_URL=https://api.yoursite.com
export ANTHROPIC_API_KEY=sk-client-xxxx
```
