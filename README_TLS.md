# TLS / HTTPS Setup for KNBTS System (XAMPP / Apache on Windows)

This document describes how to enable TLS (HTTPS) for local development with XAMPP and notes for production.

1) Quick summary (development)
- Generate a self-signed certificate and private key with OpenSSL.
- Configure Apache to use the certificate (httpd-ssl.conf / VirtualHost on port 443).
- Ensure `httpd.conf` includes the SSL module and the SSL extra config.
- Restart Apache via XAMPP Control Panel.

2) Generate a self-signed cert (OpenSSL included with XAMPP)
Open a command prompt and run (adjust CN/subject as needed):

```powershell
cd "C:\xampp\apache"
mkdir certs
cd certs
"..\bin\openssl.exe" req -x509 -nodes -days 365 -newkey rsa:2048 -keyout server.key -out server.crt -subj "/C=US/ST=State/L=City/O=Company/OU=IT/CN=localhost"
```

3) Configure Apache
- Open `apache\conf\extra\httpd-ssl.conf` (or create a new include). Update these lines:

  - `SSLCertificateFile "C:/xampp/apache/certs/server.crt"`
  - `SSLCertificateKeyFile "C:/xampp/apache/certs/server.key"`
  - Set `DocumentRoot` and `ServerName` (e.g. `ServerName localhost:443`).

- Make sure `httpd.conf` contains:

```text
LoadModule ssl_module modules/mod_ssl.so
Include conf/extra/httpd-ssl.conf
Listen 443
```

4) Trusting the certificate (browser)
- For local dev, import `server.crt` into Windows Trusted Root Certification Authorities so browsers will accept the self-signed cert without warnings.

5) Production notes
- Use a real CA-signed certificate (Let's Encrypt or commercial CA). Do not use self-signed certs in production.
- If deploying behind a load balancer or reverse proxy that terminates TLS, ensure the application trusts `X-Forwarded-Proto` and variables are set correctly.

6) What we changed in this repo
- Added `.htaccess` to redirect HTTP -> HTTPS.
- Added `BASE_URL`, `FORCE_HTTPS`, and `SITE_PROTOCOL` constants in `config/constants.php` to help generate secure links.
- Included an example Apache SSL VirtualHost file at `config/apache-ssl.conf`.

7) Next steps for you
- Generate certs and update Apache configs as above.
- Restart Apache and visit `https://localhost/knbts_secure_system/`.
- For production, obtain a CA-signed cert and update paths accordingly.
