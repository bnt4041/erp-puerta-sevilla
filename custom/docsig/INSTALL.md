# Docsig - Quick Installation Guide

## Prerequisites

```bash
# Verify PHP extensions
php -m | grep -E "openssl|curl|gd"

# Should show:
# - openssl
# - curl
# - gd (or imagick)
```

## Installation Steps

### 1. Upload Module

```bash
cd /path/to/dolibarr/htdocs/custom/
# Module already in place: docsig/
```

### 2. Set Permissions

```bash
chown -R www-data:www-data docsig
chmod -R 755 docsig
```

### 3. Activate Module

1. Login to Dolibarr as admin
2. **Home → Setup → Modules/Applications**
3. Search: "docsig"
4. Click **Activate**

Module automatically:
- ✓ Creates 7 database tables
- ✓ Generates RSA-2048 system certificate
- ✓ Uses native tva_intra field from contacts for DNI/NIF/CIF
- ✓ Sets up directories in `/documents/docsig/`

### 4. Configure TSA (Optional but Recommended)

**Docsig → Setup → TSA Settings**

Free TSA servers:
```
DigiCert:    http://timestamp.digicert.com
Sectigo:     http://timestamp.sectigo.com
FreeTSA:     https://freetsa.org/tsr
```

Enable TSA: ✓ ON
TSA URL: `http://timestamp.digicert.com`

### 5. Test

1. Go to any invoice
2. Click signature icon (🖊️)
3. Create signature request
4. Add yourself as signer
5. Check email for link
6. Complete signature flow

## Directory Structure

```
docsig/
├── core/
│   └── modules/
│       └── modDocsig.class.php     # Module descriptor
├── class/
│   ├── docsigenvelope.class.php    # Envelope management
│   ├── docsignature.class.php      # Signature handling
│   ├── docsigaudittrail.class.php  # Audit trail
│   ├── docsignotification.class.php # Notifications
│   ├── docsigpdfsigner.class.php   # PDF signing engine
│   └── actions_docsig.class.php    # Hooks
├── sql/
│   ├── llx_docsig_envelope.sql
│   ├── llx_docsig_signature.sql
│   ├── llx_docsig_audit_trail.sql
│   ├── llx_docsig_notification.sql
│   ├── llx_docsig_certificate.sql
│   ├── llx_docsig_key.sql
│   └── llx_docsig_rate_limit.sql
├── ajax/
│   └── envelope.php                # AJAX endpoints
├── public/
│   └── sign.php                    # Public signature page
├── admin/
│   ├── setup.php                   # Configuration
│   └── about.php                   # About
├── js/
│   └── docsig.js                   # Frontend JS
├── css/
│   ├── docsig.css                  # Styles
│   └── sign.css                    # Public page styles
├── lib/
│   └── docsig.lib.php              # Helper functions
└── README.md                       # Full documentation
```

## Database Tables

| Table | Purpose |
|-------|---------|
| `llx_docsig_envelope` | Signature envelopes |
| `llx_docsig_signature` | Individual signatures |
| `llx_docsig_audit_trail` | Immutable audit log |
| `llx_docsig_notification` | Email notifications |
| `llx_docsig_certificate` | Compliance certificates |
| `llx_docsig_key` | System certificates/keys |
| `llx_docsig_rate_limit` | Rate limiting records |

## Configuration Options

### Setup → General
- Signature Mode: `parallel` (default) or `ordered`
- Expiration Days: `30` (default)
- OTP Expiry Minutes: `10` (default)
- OTP Max Attempts: `5` (default)

### Setup → TSA
- Enable TSA: ON/OFF
- TSA URL: RFC3161 timestamp server
- TSA User: (if required)
- TSA Password: (if required)

### Setup → Display
- Visible Signature: ON/OFF
- Signature Position: bottom-left, bottom-right, etc.

## Usage Flow

### 1. Request Signature
- From any document list or card
- Click signature icon (🖊️)
- Select PDF document
- Add signers (contacts - ensure tva_intra field is filled for DNI/NIF/CIF verification)
- Configure options
- Send request

### 2. Signer Receives Email
- Email contains signature link
- Link format: `/custom/docsig/public/sign.php?token=xxxxx`

### 3. Signer Authentication
- Step 1: Enter NIF/CIF/NIE (from tva_intra field) + Email
- Step 2: Enter OTP code (received by email)
- Step 3: Draw signature on canvas
- Step 4: Submit signature

### 4. Document Signed
- PDF signed with PAdES format
- TSA timestamp applied (if enabled)
- Compliance certificate generated
- All parties notified

## Security Features

✓ **Token Security**: 64-char random tokens, hashed in DB
✓ **Rate Limiting**: Max 10 OTP per hour per email/IP
✓ **Double Authentication**: DNI + Email OTP
✓ **Audit Trail**: Immutable, blockchain-like hash chain
✓ **PDF Integrity**: SHA-256 hashes
✓ **Key Encryption**: Private keys encrypted with AES-256-GCM
✓ **Input Sanitization**: All inputs validated/escaped

## Troubleshooting

### Module won't activate
```sql
-- Check database permissions
SHOW GRANTS FOR CURRENT_USER;
-- Need: CREATE, INSERT, UPDATE, SELECT
```

### Emails not sending
```php
// Check in Dolibarr
Setup → Emails → SMTP configuration
Setup → Company/Organization → Email
```

### TSA fails
```bash
# Test TSA URL
curl -X POST http://timestamp.digicert.com \
  -H "Content-Type: application/timestamp-query" \
  --data-binary @/dev/null
# Should return 200
```

### Signature links don't work
```apache
# Check .htaccess or Apache config
# Ensure /custom/docsig/public/ is accessible
<Directory /path/to/dolibarr/htdocs/custom/docsig/public>
    Require all granted
</Directory>
```

## Support

**Documentation**: [README.md](README.md)
**Version**: 1.0.0
**License**: GPL v3+
**PHP**: 8.1+
**Dolibarr**: 15.0+

## Post-Installation Checklist

- [ ] Module activated successfully
- [ ] System certificate generated (check Setup → System Certificate)
- [ ] TSA configured (if using timestamps)
- [ ] Email working (test with Tools → Email test)
- [ ] Permissions assigned to users (Setup → Users → Permissions → Docsig)
- [ ] Test signature flow end-to-end
- [ ] Review audit trail for test signatures
- [ ] Check signed PDFs are valid
- [ ] Verify certificates generate correctly

---

**Ready to use!** 🎉

Request your first signature from any document.
