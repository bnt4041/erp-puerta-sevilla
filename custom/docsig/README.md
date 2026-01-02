# Docsig Module - Document Signature

## Overview

Docsig is a comprehensive document signature module for Dolibarr ERP/CRM that provides:

- **Multi-signer envelopes** (parallel or sequential signing)
- **Double authentication** (DNI/ID + Email OTP)
- **Handwritten signature capture** via canvas
- **PAdES-compliant PDF signing**
- **TSA RFC3161 timestamp** support
- **Immutable audit trail** (blockchain-like)
- **Compliance certificate generation**
- **Notification tracking** per contact
- **Rate limiting** and security features

## Installation

### 1. Copy Module Files

```bash
# Copy module to Dolibarr custom directory
cp -r docsig /path/to/dolibarr/htdocs/custom/

# Set proper permissions
chown -R www-data:www-data /path/to/dolibarr/htdocs/custom/docsig
chmod -R 755 /path/to/dolibarr/htdocs/custom/docsig
```

### 2. Enable Module

1. Log in to Dolibarr as administrator
2. Go to **Home → Setup → Modules/Applications**
3. Search for "Docsig"
4. Click **Activate**

The module will automatically:
- Create database tables
- Generate system RSA certificate
- Add DNI extrafield to contacts
- Configure directories

### 3. Configure Module

Go to **Docsig → Setup** and configure:

#### General Settings
- **Signature Mode**: parallel (default) or ordered
- **Expiration Days**: 30 (default)
- **OTP Expiry Minutes**: 10 (default)
- **OTP Max Attempts**: 5 (default)

#### TSA (Time Stamp Authority) Settings
- **Enable TSA**: Yes/No
- **TSA URL**: e.g., `http://timestamp.digicert.com`
- **TSA User**: (if required)
- **TSA Password**: (if required)
- **TSA Policy OID**: (optional)

Popular free TSA servers:
- DigiCert: `http://timestamp.digicert.com`
- Sectigo: `http://timestamp.sectigo.com`
- FreeTSA: `https://freetsa.org/tsr`

#### Signature Display
- **Enable Visible Signature**: Yes/No
- **Default Position**: bottom-left, bottom-right, top-left, top-right, center

### 4. System Requirements

**PHP Extensions Required:**
```bash
- openssl (for certificate generation and PDF signing)
- gd or imagick (for signature image processing)
- curl (for TSA requests)
```

**PHP Configuration:**
```ini
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 50M
post_max_size = 50M
```

**Database:**
- MariaDB 10.3+ or MySQL 5.7+

### 5. Verify Installation

Check that:
1. All SQL tables are created (check Database Tools)
2. System certificate is generated (Setup → System Certificate)
3. Permissions are set correctly (Permissions page)
4. Hooks are working (test by viewing an invoice)

## Usage

### Creating a Signature Request

#### From Document List
1. Open any document list (invoices, orders, contracts, etc.)
2. Click the **signature icon** (🖊️) next to the document
3. Modal opens with two options:
   - **Create new envelope** (if none exists)
   - **View existing envelope** (if already requested)

#### From Document Card
1. Open a document (invoice, order, contract, etc.)
2. Find the **Signatures** section
3. Click "**Request signature**"

#### Configuration Options
- **Document**: Select PDF to sign
- **Signature Mode**:
  - **Parallel**: All signers can sign simultaneously
  - **Ordered**: Sequential signing (1st must sign before 2nd, etc.)
- **Expiration**: Days until request expires (default 30)
- **Custom Message**: Optional message to signers
- **Signers**: Add one or more signers
  - Search existing contacts
  - Create new contact inline (AJAX)
  - Specify DNI (optional but recommended)

### Signer Experience (Public Page)

#### Step 1: Identity Verification
1. Signer receives email with link
2. Opens link (no login required)
3. Enters:
   - **DNI/Document ID** (must match if configured)
   - **Email** (must match registered email)
4. System validates and sends OTP code

#### Step 2: OTP Verification
1. Signer receives 6-digit code by email
2. Enters code (expires in 10 min, 5 attempts max)
3. System validates code

#### Step 3: Sign Document
1. Signer draws signature on canvas
2. Reviews document information
3. Checks agreement checkbox
4. Submits signature

#### Step 4: Complete
1. Confirmation message shown
2. If all signers complete: download signed document
3. If pending others: notification sent when complete

### Managing Envelopes

#### View Envelope Status
- Click signature icon on document
- View modal showing:
  - Envelope reference and status
  - List of signers with individual status
  - Signature links (copiable)
  - Download options (when available)

#### Cancel Envelope
1. Open envelope modal
2. Click "**Cancel Envelope**"
3. Enter cancellation reason
4. All signature links are invalidated

#### Download Documents
- **Signed PDF**: Available when all (or configured subset) sign
- **Compliance Certificate**: Auto-generated on completion
- Documents stored in `/documents/docsig/`

### Audit Trail

Access via **Docsig → Audit Trail**

View immutable log of all events:
- Envelope created/sent/cancelled
- Link opened
- OTP sent/validated/failed
- Signature completed
- Document signed
- Certificate generated

Each event includes:
- Timestamp
- IP address
- User agent
- Event data (JSON)
- Cryptographic hash (blockchain-like integrity)

### Notifications

All notifications are logged in database and visible on contact cards.

#### Notification Types
- **Request**: Signature request sent
- **OTP**: Verification code sent
- **Reminder**: Reminder sent (manual)
- **Completed**: All signatures collected
- **Cancelled**: Envelope cancelled

#### View Contact Notifications
1. Open contact card
2. Go to **Signatures** tab
3. View all notifications sent to this contact
   - Subject and body visible
   - Sent date/time
   - Associated envelope

## Technical Architecture

### Database Tables

- `llx_docsig_envelope`: Signature envelopes
- `llx_docsig_signature`: Individual signatures
- `llx_docsig_audit_trail`: Immutable audit log
- `llx_docsig_notification`: Email notifications sent
- `llx_docsig_certificate`: Compliance certificates
- `llx_docsig_key`: System certificates/keys
- `llx_docsig_rate_limit`: Rate limiting records

### Classes

- **DocsigEnvelope**: Main envelope management
- **DocsigSignature**: Individual signature handling
- **DocsigAuditTrail**: Audit trail logging
- **DocsigNotification**: Notification management
- **DocsigPDFSigner**: PDF signing engine (PAdES + TSA)
- **DocsigCertificate**: Compliance certificate generation

### Hooks

Module integrates via hooks:
- `printFieldListOption`: Add column header in lists
- `printFieldListValue`: Add signature button to rows
- `formObjectOptions`: Add signature section to cards
- `printTabsHead`: Add signatures tab to contacts
- `addHtmlHeader`: Include CSS and JS

Contexts supported:
- invoicelist, invoicecard
- orderlist, ordercard
- contractlist, contractcard
- propallist, propalcard
- supplierinvoicelist, supplierinvoicecard
- contactlist, contactcard

### Security Features

#### Token Security
- 64+ character random tokens (configurable)
- Hashed in database (SHA-256)
- Single-use or expirable
- Not exposed in logs

#### Rate Limiting
- OTP: Max 10 requests per hour per email/IP
- Signature attempts: Configurable
- Auto-blocking on threshold

#### Authentication
- Double factor: DNI + Email
- OTP with time expiration
- Attempt limiting
- Session validation

#### PDF Integrity
- SHA-256 hash of original
- SHA-256 hash of signed version
- Any modification invalidates signature
- TSA timestamp binds date

#### Audit Trail Integrity
- Blockchain-like hash chain
- Each event hashes previous event
- Immutable (append-only table)
- Verifiable integrity check

#### Data Protection
- Private keys encrypted (AES-256-GCM)
- Stored with IV and authentication tag
- Encryption key derived from Dolibarr instance ID
- Sensitive data sanitized on input

## API / AJAX Endpoints

### envelope.php

**create_envelope**
```json
POST /custom/docsig/ajax/envelope.php?action=create_envelope
{
  "element_type": "invoice",
  "element_id": 123,
  "document_path": "facture/FA2401-0001/FA2401-0001.pdf",
  "document_name": "Invoice FA2401-0001.pdf",
  "signature_mode": "parallel",
  "expiration_days": 30,
  "custom_message": "Please sign",
  "signers": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "dni": "12345678A"
    }
  ]
}
```

**get_envelope_status**
```
GET /custom/docsig/ajax/envelope.php?action=get_envelope_status&envelope_id=1
```

**cancel_envelope**
```
POST /custom/docsig/ajax/envelope.php?action=cancel_envelope&envelope_id=1&reason=No+longer+needed
```

**create_contact**
```
POST /custom/docsig/ajax/envelope.php?action=create_contact
{
  "name": "Doe",
  "firstname": "John",
  "email": "john@example.com",
  "dni": "12345678A",
  "socid": 123
}
```

## Compliance & Legal

### Electronic Signature Standards

This module implements:
- **PAdES-BES**: PDF Advanced Electronic Signature - Basic
- **RFC 3161**: Time-Stamp Protocol (TSP)
- **eIDAS** compatible (EU regulation)

### Certificate of Compliance

Auto-generated PDF containing:
- Envelope reference
- Original document hash
- Signed document hash
- List of all signers with timestamps
- TSA timestamp details
- System signature
- Full audit trail

### Legal Validity

Electronic signatures are legally binding in:
- **EU**: eIDAS Regulation (EU) No 910/2014
- **USA**: ESIGN Act, UETA
- **International**: UNCITRAL Model Law

Requirements met:
- Identity verification (double authentication)
- Intent to sign (explicit checkbox)
- Integrity protection (hash + TSA)
- Non-repudiation (audit trail)

## Troubleshooting

### Module Won't Activate
- Check database user has CREATE TABLE permission
- Verify all SQL files are present
- Check PHP error log

### Emails Not Sending
- Verify SMTP configuration in Dolibarr
- Check `MAIN_MAIL_EMAIL_FROM` is set
- Test email from Tools → Email test

### TSA Timestamp Fails
- Verify TSA URL is accessible
- Check firewall/proxy settings
- Try alternative TSA provider
- Disable TSA temporarily for testing

### Signature Links Don't Work
- Check Apache/Nginx allows access to `/custom/docsig/public/`
- Verify no .htaccess blocking
- Check token is valid and not expired

### PDF Signing Fails
- Verify PHP openssl extension loaded
- Check memory_limit sufficient (256M+)
- Ensure documents directory writable
- Check certificate exists (Setup → System Certificate)

### Rate Limit Blocking Users
- Adjust `DOCSIG_RATE_LIMIT_MAX` higher
- Clear rate limit table manually
- Check for malicious activity

## Support & Development

### Module Information
- **Version**: 1.0.0
- **Author**: Docsig Team
- **License**: GPL v3+
- **Dolibarr**: 15.0+
- **PHP**: 8.1+

### Source Code
Located in `/htdocs/custom/docsig/`

### Logs
- Dolibarr log: Check syslog
- Apache/PHP errors: `/var/log/apache2/error.log`
- Audit trail: Database table `llx_docsig_audit_trail`

### Future Enhancements
- [ ] Biometric signature options
- [ ] SMS OTP alternative
- [ ] Bulk signature requests
- [ ] Advanced certificate (PAdES-LT with OCSP/CRL)
- [ ] Integration with external signature providers
- [ ] Mobile app support
- [ ] Signature templates
- [ ] Webhook notifications

---

**Note**: This module handles sensitive operations and legal documents. Always test thoroughly in a development environment before production use. Ensure compliance with local regulations regarding electronic signatures.
