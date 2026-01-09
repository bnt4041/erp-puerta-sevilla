/**
 * WhatsApp Chat Module - JavaScript
 * Enhanced version with file attachments and improved UI
 */

$(document).ready(function () {
    if (typeof waContactPhone === 'undefined') return;

    // Initialize
    initChat();
    loadChatHistory();
    setupEventHandlers();
    setupAutoRefresh();
});

var lastChatHtml = '';
var chatInterval = null;
var selectedFile = null;
var isUploading = false;

/**
 * Initialize chat UI
 */
function initChat() {
    // Auto-resize textarea
    $('#wa-message').on('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
}

/**
 * Setup all event handlers
 */
function setupEventHandlers() {
    // Send message on Enter (without Shift)
    $('#wa-message').on('keydown', function (e) {
        if (e.which == 13 && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Send button click
    $('#wa-send-btn').on('click', function () {
        sendMessage();
    });

    // Attachment button click
    $('#wa-attach-btn').on('click', function () {
        $('#wa-file-input').click();
    });

    // File selection
    $('#wa-file-input').on('change', function (e) {
        handleFileSelect(e.target.files[0]);
    });

    // Remove attachment
    $(document).on('click', '.wa-attachment-remove', function () {
        clearAttachment();
    });

    // Image modal
    $(document).on('click', '.wa-msg-image', function () {
        var src = $(this).attr('src');
        $('#wa-image-modal img').attr('src', src);
        $('#wa-image-modal').addClass('active');
    });

    $('#wa-image-modal, .wa-modal-close').on('click', function () {
        $('#wa-image-modal').removeClass('active');
    });

    // Document download
    $(document).on('click', '.wa-doc-download', function (e) {
        e.stopPropagation();
        var url = $(this).data('url');
        if (url) {
            window.open(url, '_blank');
        }
    });

    // Drag and drop
    var chatContainer = $('.whatsapp-chat-container');
    
    chatContainer.on('dragover', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('wa-dragover');
    });

    chatContainer.on('dragleave', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('wa-dragover');
    });

    chatContainer.on('drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('wa-dragover');
        
        var files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            handleFileSelect(files[0]);
        }
    });
}

/**
 * Setup auto-refresh for chat
 */
function setupAutoRefresh() {
    chatInterval = setInterval(loadChatHistory, 5000);

    $(window).on('blur', function () { 
        clearInterval(chatInterval); 
    });
    
    $(window).on('focus', function () {
        clearInterval(chatInterval);
        chatInterval = setInterval(loadChatHistory, 5000);
        loadChatHistory();
    });
}

/**
 * Handle file selection
 */
function handleFileSelect(file) {
    if (!file) return;

    // Validate file size (max 16MB for WhatsApp)
    var maxSize = 16 * 1024 * 1024;
    if (file.size > maxSize) {
        alert('El archivo es demasiado grande. Máximo 16MB.');
        return;
    }

    // Validate file type
    var allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'video/mp4', 'video/3gpp',
        'audio/mpeg', 'audio/ogg', 'audio/amr', 'audio/opus',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain'
    ];

    if (!allowedTypes.includes(file.type) && !file.type.startsWith('image/')) {
        alert('Tipo de archivo no permitido.');
        return;
    }

    selectedFile = file;
    showAttachmentPreview(file);
}

/**
 * Show attachment preview
 */
function showAttachmentPreview(file) {
    var preview = $('.wa-attachment-preview');
    var thumb = preview.find('.wa-attachment-thumb');
    var name = preview.find('.wa-attachment-name');
    var size = preview.find('.wa-attachment-size');

    name.text(file.name);
    size.text(formatFileSize(file.size));

    // Set thumbnail
    thumb.empty();
    if (file.type.startsWith('image/')) {
        var reader = new FileReader();
        reader.onload = function (e) {
            thumb.html('<img src="' + e.target.result + '" alt="preview">');
        };
        reader.readAsDataURL(file);
    } else {
        thumb.html('<i class="fas ' + getFileIcon(file.type) + '"></i>');
    }

    preview.addClass('active');
}

/**
 * Clear attachment
 */
function clearAttachment() {
    selectedFile = null;
    $('#wa-file-input').val('');
    $('.wa-attachment-preview').removeClass('active');
}

/**
 * Send message (text or with attachment)
 */
function sendMessage() {
    if (isUploading) return;

    var msg = $('#wa-message').val().trim();
    var phone = $('#wa-phone').val();
    var socid = $('#wa-socid').val();
    var contactid = $('#wa-contactid').val();

    if (!msg && !selectedFile) return;

    var $btn = $('#wa-send-btn');
    $btn.prop('disabled', true);
    isUploading = true;

    if (selectedFile) {
        // Send with file
        sendMediaMessage(phone, msg, selectedFile, socid, contactid, function () {
            $btn.prop('disabled', false);
            isUploading = false;
        });
    } else {
        // Send text only
        sendTextMessage(phone, msg, socid, contactid, function () {
            $btn.prop('disabled', false);
            isUploading = false;
        });
    }
}

/**
 * Send text message
 */
function sendTextMessage(phone, msg, socid, contactid, callback) {
    $.ajax({
        url: dolibarr_uri_base + '/custom/whatsapp/public/ajax_send.php',
        type: 'POST',
        data: {
            token: dolibarrToken,
            phone: phone,
            msg: msg,
            socid: socid,
            contactid: contactid
        },
        dataType: 'json',
        success: function (data) {
            if (data.error) {
                alert('Error: ' + (data.message || 'Error desconocido'));
            } else {
                $('#wa-message').val('').css('height', 'auto');
                loadChatHistory();
            }
            if (callback) callback();
        },
        error: function () {
            alert('Error de conexión / Sesión expirada');
            if (callback) callback();
        }
    });
}

/**
 * Send media message with file attachment
 */
function sendMediaMessage(phone, caption, file, socid, contactid, callback) {
    var formData = new FormData();
    formData.append('token', dolibarrToken);
    formData.append('phone', phone);
    formData.append('caption', caption);
    formData.append('socid', socid);
    formData.append('contactid', contactid);
    formData.append('file', file);

    $.ajax({
        url: dolibarr_uri_base + '/custom/whatsapp/public/ajax_send_media.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        xhr: function () {
            var xhr = new window.XMLHttpRequest();
            xhr.upload.addEventListener('progress', function (evt) {
                if (evt.lengthComputable) {
                    var percent = Math.round((evt.loaded / evt.total) * 100);
                    // Could show progress here
                }
            }, false);
            return xhr;
        },
        success: function (data) {
            if (data.error) {
                alert('Error: ' + (data.message || 'Error al enviar archivo'));
            } else {
                $('#wa-message').val('').css('height', 'auto');
                clearAttachment();
                loadChatHistory();
            }
            if (callback) callback();
        },
        error: function () {
            alert('Error de conexión al subir archivo');
            if (callback) callback();
        }
    });
}

/**
 * Load chat history
 */
function loadChatHistory() {
    $.ajax({
        url: dolibarr_uri_base + '/custom/whatsapp/public/ajax_history.php',
        type: 'GET',
        data: {
            phone: waContactPhone,
            objectid: waObjectId,
            objecttype: waObjectType,
            limit: 40
        },
        dataType: 'json',
        success: function (data) {
            renderMessages(data);
        },
        error: function () {
            console.error('Error loading chat history');
        }
    });
}

/**
 * Render messages in chat
 */
function renderMessages(data) {
    var container = $('#wa-chat-history');
    var newHtml = '';

    if (!data || data.length === 0) {
        newHtml = '<div class="wa-empty-state">';
        newHtml += '<i class="fab fa-whatsapp"></i>';
        newHtml += '<h4>No hay mensajes</h4>';
        newHtml += '<p>Inicia una conversación enviando un mensaje</p>';
        newHtml += '</div>';
    } else {
        var lastDate = '';
        
        data.forEach(function (msg) {
            // Date separator
            var msgDate = msg.date_only || '';
            if (msgDate && msgDate !== lastDate) {
                newHtml += '<div class="wa-date-separator">' + escapeHtml(msgDate) + '</div>';
                lastDate = msgDate;
            }

            var cls = msg.type === 'sent' ? 'wa-msg-sent' : 'wa-msg-received';
            newHtml += '<div class="wa-msg ' + cls + '">';

            // Media content
            if (msg.media_type && msg.media_url) {
                if (msg.media_type === 'image') {
                    newHtml += '<div class="wa-msg-media">';
                    newHtml += '<img src="' + escapeHtml(msg.media_url) + '" class="wa-msg-image" alt="Imagen">';
                    newHtml += '</div>';
                } else if (msg.media_type === 'video') {
                    newHtml += '<div class="wa-msg-media">';
                    newHtml += '<video controls src="' + escapeHtml(msg.media_url) + '"></video>';
                    newHtml += '</div>';
                } else if (msg.media_type === 'audio') {
                    newHtml += '<div class="wa-msg-media">';
                    newHtml += '<audio controls src="' + escapeHtml(msg.media_url) + '"></audio>';
                    newHtml += '</div>';
                } else if (msg.media_type === 'document') {
                    newHtml += '<div class="wa-msg-document">';
                    newHtml += '<i class="wa-doc-icon fas ' + getFileIcon(msg.media_mime || '') + '"></i>';
                    newHtml += '<div class="wa-doc-info">';
                    newHtml += '<div class="wa-doc-name">' + escapeHtml(msg.media_filename || 'Documento') + '</div>';
                    if (msg.media_size) {
                        newHtml += '<div class="wa-doc-size">' + formatFileSize(msg.media_size) + '</div>';
                    }
                    newHtml += '</div>';
                    newHtml += '<i class="wa-doc-download fas fa-download" data-url="' + escapeHtml(msg.media_url) + '"></i>';
                    newHtml += '</div>';
                }
            }

            // Text content
            if (msg.text) {
                newHtml += '<div class="wa-msg-text">' + formatMessageText(msg.text) + '</div>';
            }

            // Meta (time and status)
            newHtml += '<div class="wa-msg-meta">';
            newHtml += '<span class="wa-msg-date">' + escapeHtml(msg.time || '') + '</span>';
            if (msg.type === 'sent') {
                var statusIcon = msg.status === 'read' ? 'fa-check-double' : 'fa-check';
                newHtml += '<i class="wa-msg-status fas ' + statusIcon + '"></i>';
            }
            newHtml += '</div>';

            newHtml += '</div>';
        });
    }

    if (newHtml !== lastChatHtml) {
        container.html(newHtml);
        container.scrollTop(container[0].scrollHeight);
        lastChatHtml = newHtml;
    }
}

/**
 * Format message text (links, line breaks, etc.)
 */
function formatMessageText(text) {
    if (!text) return '';
    
    // Escape HTML first
    text = escapeHtml(text);
    
    // Convert URLs to links
    var urlRegex = /(https?:\/\/[^\s]+)/g;
    text = text.replace(urlRegex, '<a href="$1" target="_blank" rel="noopener">$1</a>');
    
    // Convert line breaks
    text = text.replace(/\n/g, '<br>');
    
    // Bold text *text*
    text = text.replace(/\*([^\*]+)\*/g, '<strong>$1</strong>');
    
    // Italic text _text_
    text = text.replace(/\_([^\_]+)\_/g, '<em>$1</em>');
    
    // Strikethrough ~text~
    text = text.replace(/\~([^\~]+)\~/g, '<del>$1</del>');
    
    // Monospace ```text```
    text = text.replace(/\`\`\`([^\`]+)\`\`\`/g, '<code>$1</code>');
    
    return text;
}

/**
 * Escape HTML entities
 */
function escapeHtml(text) {
    if (!text) return '';
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
}

/**
 * Format file size
 */
function formatFileSize(bytes) {
    if (!bytes) return '';
    var sizes = ['Bytes', 'KB', 'MB', 'GB'];
    var i = Math.floor(Math.log(bytes) / Math.log(1024));
    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
}

/**
 * Get font awesome icon for file type
 */
function getFileIcon(mimeType) {
    if (!mimeType) return 'fa-file';
    
    if (mimeType.startsWith('image/')) return 'fa-file-image';
    if (mimeType.startsWith('video/')) return 'fa-file-video';
    if (mimeType.startsWith('audio/')) return 'fa-file-audio';
    if (mimeType.includes('pdf')) return 'fa-file-pdf';
    if (mimeType.includes('word') || mimeType.includes('document')) return 'fa-file-word';
    if (mimeType.includes('excel') || mimeType.includes('sheet')) return 'fa-file-excel';
    if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) return 'fa-file-powerpoint';
    if (mimeType.includes('zip') || mimeType.includes('rar') || mimeType.includes('compressed')) return 'fa-file-archive';
    if (mimeType.startsWith('text/')) return 'fa-file-alt';
    
    return 'fa-file';
}
