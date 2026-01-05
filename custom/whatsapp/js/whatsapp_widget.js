document.addEventListener('DOMContentLoaded', function () {
    // Create Floating Button
    var floatCmd = document.createElement('div');
    floatCmd.className = 'whatsapp-floating-btn';
    floatCmd.innerHTML = '<i class="fa fa-whatsapp"></i>';
    floatCmd.onclick = toggleWhatsAppChat;
    document.body.appendChild(floatCmd);

    // Create Chat Window (Hidden by default)
    var chatWindow = document.createElement('div');
    chatWindow.className = 'whatsapp-chat-window';
    chatWindow.style.display = 'none';
    chatWindow.innerHTML = `
        <div class="whatsapp-chat-header">
            <span>WhatsApp</span>
            <span class="close-chat" onclick="toggleWhatsAppChat()">×</span>
        </div>
        <div class="whatsapp-chat-body" id="wa-chat-body">
            <div class="wa-msg-received">Welcome! Select a contact or enter a number to start chatting.</div>
        </div>
        <div class="whatsapp-chat-footer">
            <input type="text" id="wa-input-phone" placeholder="Phone (e.g. 34600...)" style="width: 100%; margin-bottom: 5px;">
            <div style="display: flex;">
                <input type="text" id="wa-input-msg" placeholder="Type a message..." style="flex-grow: 1;">
                <button onclick="sendWhatsAppGlobal()">Wait</button>
            </div>
        </div>
    `;
    document.body.appendChild(chatWindow);

    // Add Styles
    var style = document.createElement('style');
    style.innerHTML = `
        .whatsapp-floating-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            background-color: #25d366;
            color: white;
            border-radius: 50%;
            text-align: center;
            font-size: 30px;
            line-height: 60px;
            box-shadow: 2px 2px 3px #999;
            z-index: 9999;
            cursor: pointer;
            transition: all 0.3s;
        }
        .whatsapp-floating-btn:hover {
            background-color: #128c7e;
            transform: scale(1.1);
        }
        .whatsapp-chat-window {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 300px;
            height: 400px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid #ddd;
        }
        .whatsapp-chat-header {
            background-color: #075e54;
            color: white;
            padding: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: bold;
        }
        .close-chat { cursor: pointer; font-size: 20px; }
        .whatsapp-chat-body {
            flex-grow: 1;
            padding: 10px;
            overflow-y: auto;
            background-color: #e5ddd5;
        }
        .whatsapp-chat-footer {
            padding: 10px;
            background-color: #f0f0f0;
        }
        .wa-msg-sent {
            background-color: #dcf8c6;
            padding: 5px 10px;
            border-radius: 5px;
            margin-bottom: 5px;
            align-self: flex-end;
            margin-left: 20px;
        }
        .wa-msg-received {
            background-color: white;
            padding: 5px 10px;
            border-radius: 5px;
            margin-bottom: 5px;
            align-self: flex-start;
            margin-right: 20px;
        }
    `;
    document.head.appendChild(style);
});

function toggleWhatsAppChat() {
    var win = document.querySelector('.whatsapp-chat-window');
    if (win.style.display === 'none') {
        win.style.display = 'flex';
    } else {
        win.style.display = 'none';
    }
}

function sendWhatsAppGlobal() {
    var phone = document.getElementById('wa-input-phone').value;
    var msg = document.getElementById('wa-input-msg').value;
    if (!phone || !msg) return;

    // Use formatting from Dolibarr generic functions
    // We'll call the API using fetch

    // For now, simpler implementation:
    // Create a hidden form and submit, or use fetch to the API endpoint

    // Assuming we have the API endpoint exposed
    // Or we can use the same helper as whatsapp_card.php

    // Best way: call Dolibarr API or internal script
    // Let's use a small AJAX proxy or the REST API

    var btn = event.target;
    btn.innerHTML = '...';
    btn.disabled = true;

    // We can call /whatsapp/api/send (if we exposed it as a simple script) or use Dolibarr REST API
    // Let's assume we use the standard REST API /api/index.php/whatsapp/send
    // But REST API needs API Key. Accessing from frontend might be tricky without exposing key.

    // Alternative: create a public ajax script in the module

    fetch(dolibarr_uri_base + '/custom/whatsapp/public/ajax_send.php?phone=' + encodeURIComponent(phone) + '&msg=' + encodeURIComponent(msg))
        .then(response => response.json())
        .then(data => {
            btn.innerHTML = 'Send';
            btn.disabled = false;
            if (data.error) {
                alert('Error: ' + data.message);
            } else {
                document.getElementById('wa-input-msg').value = '';
                var body = document.getElementById('wa-chat-body');
                body.innerHTML += '<div class="wa-msg-sent">' + msg + '</div>';
                body.scrollTop = body.scrollHeight;
            }
        })
        .catch(err => {
            btn.innerHTML = 'Send';
            btn.disabled = false;
            console.error(err);
        });
}
