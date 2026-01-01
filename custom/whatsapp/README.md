# WhatsApp Module for Dolibarr

Integration of WhatsApp messaging into Dolibarr ERP using goWA.

## Features

1. **goWA Integration** - Uses go-whatsapp-web-multidevice for WhatsApp connectivity
2. **Hooks & Triggers** - Other modules can use the WhatsApp sending functionality
3. **Send WhatsApp Button** - Added to invoices, proposals, orders, and contact cards
4. **ActionComm Integration** - WhatsApp messages are logged in the agenda

## Installation

1. Copy the `whatsapp` folder to your Dolibarr `custom` directory
2. Enable the module in Dolibarr: Home > Setup > Modules
3. Go to module setup and click on "Local Service" tab
4. Click "Install goWA" - the system will automatically download the correct binary for your architecture
5. Start the service
6. Go to "Link WhatsApp (QR)" tab and scan the QR code with your phone

## Requirements

- Dolibarr 14.0 or higher
- PHP 7.0 or higher with ZipArchive extension
- Internet connection (for downloading goWA binary)
- Write permissions on the module directory

## Usage by Other Modules

```php
// Include the library
require_once dol_buildpath('/whatsapp/lib/whatsapp.lib.php');

// Send a message
$result = whatsapp_send('+34600123456', 'Hello from Dolibarr!');
if ($result['error'] == 0) {
    echo "Message sent!";
}
```

## Triggers

The module fires the `WHATSAPP_SENT` trigger when a message is sent. Other modules can listen to this event.

## API Endpoints

The module exposes REST API endpoints in Dolibarr's Swagger:
- `POST /whatsapp/send` - Send a WhatsApp message
- `GET /whatsapp/status` - Get connection status

## Support

For issues with goWA, visit: https://github.com/aldinokemal/go-whatsapp-web-multidevice
