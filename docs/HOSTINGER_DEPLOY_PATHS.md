# Hostinger — Angular + PHP file paths (`callmenow.in`)

## Folder layout (Angular / .NET / PHP alag)

| Stack | Project folder | Build / run |
|--------|----------------|-------------|
| **Angular** | `qrcode-app-ui\` | `npm run build` → `qrcode-app-ui\dist\qrcode-app-ui\browser\` |
| **.NET API** | `QRCodeApp.API\` | `dotnet run` (Angular ab yahan auto-copy **nahi** hota) |
| **PHP API** | `qrcode-app-php\` | Hostinger par alag deploy |

`.NET` ke saath Angular ek hi server se chahiye ho to: `scripts\copy-angular-to-dotnet-wwwroot.ps1` (pehle `npm run build`).

Repo root par short list: `FOLDER_LAYOUT.txt`

---

## 1) Tumhare PC par (project folder)

| Kya upload karna hai | Local path (Windows) |
|----------------------|----------------------|
| Angular production build (browser files) | `qrcode-app-ui\dist\qrcode-app-ui\browser\` |
| PHP API (poora project) | `qrcode-app-php\` |

**Build Angular:**

```text
cd qrcode-app-ui
npm run build
```

Build output: `qrcode-app-ui\dist\qrcode-app-ui\browser\` (`angular.json` → `outputPath`).

---

## 2) Hostinger File Manager — `public_html` (main site)

**Domain:** `https://callmenow.in`  
**Server folder:** `public_html/` (File Manager → *Access files of callmenow.in*)

| Server path | Dalna kya |
|-------------|-----------|
| `public_html/index.html` | `dist\qrcode-app-ui\browser\index.html` |
| `public_html/*.js` | `browser\` ke saare `.js` |
| `public_html/*.css` | `browser\` ke saare `.css` |
| `public_html/.htaccess` | `qrcode-app-ui\public\.htaccess` (SPA routing) |
| `public_html/assets/` (agar ho) | `browser\assets\` |

---

## 3) PHP API — recommended: subdomain `api.callmenow.in`

**Server par ek folder** (example name):

| Server path | Local source |
|-------------|--------------|
| `public_html/callmenow-api/composer.json` | `qrcode-app-php\composer.json` |
| `public_html/callmenow-api/vendor/` | `qrcode-app-php\vendor\` (pehle `composer install`) |
| `public_html/callmenow-api/src/` | `qrcode-app-php\src\` |
| `public_html/callmenow-api/public/index.php` | `qrcode-app-php\public\index.php` |
| `public_html/callmenow-api/public/router.php` | `qrcode-app-php\public\router.php` |
| `public_html/callmenow-api/public/.htaccess` | `qrcode-app-php\public\.htaccess` |

**hPanel → Domains → Subdomains:** `api.callmenow.in`  
**Document root** set karo: `public_html/callmenow-api/public`  
(jahan `index.php` hai).

**API URL:** `https://api.callmenow.in/api/...`

---

## 4) Environment (PHP + MySQL)

Hostinger par (panel / `.env` / jo bhi host deta hai) ye values set karo:

| Variable | Example |
|----------|---------|
| `MYSQL_HOST` | `localhost` (ya panel mein jo host likha ho) |
| `MYSQL_DATABASE` | tumhara DB naam |
| `MYSQL_USER` | tumhara MySQL user |
| `MYSQL_PASSWORD` | tumhara password |
| `CALLMENOW_PUBLIC_BASE_URL` | `https://callmenow.in` |
| `CORS_ORIGIN` | `https://callmenow.in` |

---

## 5) Angular → API URL (production)

Abhi services `/api` use karti hain. Subdomain use karte ho to production mein base URL  
`https://api.callmenow.in` hona chahiye (interceptors / environment — alag se set karna hoga).

---

## Quick copy checklist

- [ ] `qrcode-app-ui\dist\qrcode-app-ui\browser\*` → `public_html\`
- [ ] `qrcode-app-ui\public\.htaccess` → `public_html\.htaccess`
- [ ] Poora `qrcode-app-php\` (vendor ke saath) → `public_html\callmenow-api\`
- [ ] `qrcode-app-php\public\.htaccess` server par `public\.htaccess`
- [ ] Subdomain document root → `callmenow-api\public`
- [ ] MySQL + env vars + CORS
