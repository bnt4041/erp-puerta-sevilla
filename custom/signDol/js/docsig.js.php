<?php
/* Copyright (C) 2026 DocSig Module
 *
 * JavaScript for DocSig module
 */

// Load Dolibarr environment
if (!defined('NOREQUIREUSER')) {
    define('NOREQUIREUSER', '1');
}
if (!defined('NOREQUIRESOC')) {
    define('NOREQUIRESOC', '1');
}
if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOLOGIN')) {
    define('NOLOGIN', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}

// Include Dolibarr main
$res = 0;
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) {
    $res = @include __DIR__.'/../../../main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../../../../main.inc.php')) {
    $res = @include __DIR__.'/../../../../main.inc.php';
}
if (!$res) {
    header('Content-type: application/javascript; charset=UTF-8');
    print "/* DocSig: failed to load Dolibarr environment */\n";
    exit;
}

session_cache_limiter('public');

header('Content-type: application/javascript; charset=UTF-8');
header('Cache-Control: max-age=3600');
?>
/**
 * DocSig Module JavaScript
 * Gestión de firma digital de documentos
 */

var DocSig = DocSig || {};

(function() {
    'use strict';

    // Configuration
    DocSig.config = {
        ajaxUrl: '<?php echo dol_buildpath('/signDol/ajax/', 1); ?>',
        csrfToken: '',
        addedSigners: {} // Track dynamically added signers
    };

    /**
     * Initialize module
     */
    DocSig.init = function() {
        // Get CSRF token from Dolibarr
        var tokenInput = document.querySelector('input[name="token"]');
        if (tokenInput) {
            DocSig.config.csrfToken = tokenInput.value;
        }

        // Initialize signature icons
        DocSig.initSignatureIcons();

        // Initialize modal if exists
        DocSig.initModal();
    };

    /**
     * Initialize signature icons in document lists
     */
    DocSig.initSignatureIcons = function() {
        var icons = document.querySelectorAll('.docsig-icon');
        icons.forEach(function(icon) {
            icon.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var element = this.dataset.element;
                var objectId = this.dataset.objectId;
                var filePath = this.dataset.filePath;
                DocSig.openModal(element, objectId, filePath);
            });
        });
    };

    /**
     * Initialize modal functionality
     */
    DocSig.initModal = function() {
        var modal = document.getElementById('docsig-modal');
        if (modal) {
            // Close modal on background click
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    DocSig.closeModal();
                }
            });

            // Close button
            var closeBtn = modal.querySelector('.docsig-modal-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    DocSig.closeModal();
                });
            }
        }
    };

    /**
     * Open signature modal
     */
    DocSig.openModal = function(element, objectId, filePath) {
        var modal = document.getElementById('docsig-modal');
        if (!modal) {
            DocSig.createModal();
            modal = document.getElementById('docsig-modal');
        }

        // Reset added signers tracker
        DocSig.config.addedSigners = {};

        // Load modal content via AJAX
        DocSig.loadModalContent(element, objectId, filePath);

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    /**
     * Close modal
     */
    DocSig.closeModal = function() {
        var modal = document.getElementById('docsig-modal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    /**
     * Create modal HTML if not exists
     */
    DocSig.createModal = function() {
        var modalHtml = 
            '<div id="docsig-modal" class="docsig-modal">' +
                '<div class="docsig-modal-content">' +
                    '<div class="docsig-modal-header">' +
                        '<h3><span class="fa fa-file-signature"></span> Firma de documento</h3>' +
                        '<span class="docsig-modal-close">&times;</span>' +
                    '</div>' +
                    '<div class="docsig-modal-body" id="docsig-modal-body">' +
                        '<div class="docsig-loading"></div> Cargando...' +
                    '</div>' +
                '</div>' +
            '</div>';
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        DocSig.initModal();
    };

    /**
     * Load modal content via AJAX
     */
    DocSig.loadModalContent = function(element, objectId, filePath) {
        var body = document.getElementById('docsig-modal-body');
        body.innerHTML = '<div class="docsig-loading"></div> Cargando...';

        var xhr = new XMLHttpRequest();
        xhr.open('POST', DocSig.config.ajaxUrl + 'modal.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            body.innerHTML = response.html;
                            DocSig.initModalHandlers();
                        } else {
                            body.innerHTML = '<div class="docsig-error"><span class="fa fa-exclamation-triangle"></span> ' + response.error + '</div>';
                        }
                    } catch(e) {
                        body.innerHTML = xhr.responseText;
                        DocSig.initModalHandlers();
                    }
                } else {
                    body.innerHTML = '<div class="docsig-error"><span class="fa fa-exclamation-triangle"></span> Error al cargar el contenido</div>';
                }
            }
        };

        var params = 'action=getmodal&element=' + encodeURIComponent(element) +
                     '&id=' + encodeURIComponent(objectId) +
                     '&filepath=' + encodeURIComponent(filePath || '') +
                     '&token=' + encodeURIComponent(DocSig.config.csrfToken);
        xhr.send(params);
    };

    /**
     * Initialize modal event handlers after content load
     */
    DocSig.initModalHandlers = function() {
        // Create envelope form
        var createForm = document.getElementById('docsig-create-form');
        if (createForm) {
            createForm.addEventListener('submit', function(e) {
                e.preventDefault();
                DocSig.createEnvelope(new FormData(createForm));
            });
        }

        // Contact search with debounce
        var searchInput = document.getElementById('docsig-contact-search');
        if (searchInput) {
            searchInput.addEventListener('input', DocSig.debounce(function(e) {
                DocSig.searchContacts(e.target.value);
            }, 300));
            
            // Hide results on blur (with delay to allow click)
            searchInput.addEventListener('blur', function() {
                setTimeout(function() {
                    var results = document.getElementById('docsig-search-results');
                    if (results) results.style.display = 'none';
                }, 200);
            });
        }

        // Toggle new contact form
        var toggleNewContact = document.getElementById('docsig-toggle-new-contact');
        if (toggleNewContact) {
            toggleNewContact.addEventListener('click', function(e) {
                e.preventDefault();
                var form = document.getElementById('docsig-new-contact-form');
                if (form) {
                    form.style.display = form.style.display === 'none' ? 'block' : 'none';
                }
            });
        }

        // Add new contact button
        var addNewContactBtn = document.getElementById('docsig-add-new-contact');
        if (addNewContactBtn) {
            addNewContactBtn.addEventListener('click', DocSig.addNewContact);
        }

        // Cancel new contact button
        var cancelNewContactBtn = document.getElementById('docsig-cancel-new-contact');
        if (cancelNewContactBtn) {
            cancelNewContactBtn.addEventListener('click', function() {
                var form = document.getElementById('docsig-new-contact-form');
                if (form) {
                    form.style.display = 'none';
                    DocSig.clearNewContactForm();
                }
            });
        }

        // Cancel existing envelope button
        var cancelEnvelopeBtn = document.getElementById('docsig-cancel-envelope');
        if (cancelEnvelopeBtn) {
            cancelEnvelopeBtn.addEventListener('click', function() {
                var envId = this.dataset.id;
                DocSig.cancelEnvelope(envId);
            });
        }

        // Resend buttons
        var resendBtns = document.querySelectorAll('.docsig-resend-btn');
        resendBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                DocSig.resendRequest(this.dataset.signerId);
            });
        });

        // Update submit button state based on checkboxes
        DocSig.updateSubmitButtonState();
        var checkboxes = document.querySelectorAll('.docsig-signer-item input[type="checkbox"]');
        checkboxes.forEach(function(cb) {
            cb.addEventListener('change', DocSig.updateSubmitButtonState);
        });
    };

    /**
     * Search contacts via AJAX
     */
    DocSig.searchContacts = function(query) {
        if (query.length < 2) {
            var results = document.getElementById('docsig-search-results');
            if (results) results.style.display = 'none';
            return;
        }

        // Get currently added signer IDs to exclude
        var excludeIds = DocSig.getAddedSignerIds().join(',');

        var xhr = new XMLHttpRequest();
        xhr.open('POST', DocSig.config.ajaxUrl + 'search_contacts.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    DocSig.showSearchResults(response.results || []);
                } catch(e) {
                    console.error('Error parsing search results:', e);
                }
            }
        };

        xhr.send('q=' + encodeURIComponent(query) + 
                 '&exclude=' + encodeURIComponent(excludeIds) +
                 '&token=' + encodeURIComponent(DocSig.config.csrfToken));
    };

    /**
     * Get IDs of signers already in the list
     */
    DocSig.getAddedSignerIds = function() {
        var ids = [];
        var items = document.querySelectorAll('.docsig-signer-item');
        items.forEach(function(item) {
            ids.push(item.dataset.id);
        });
        return ids;
    };

    /**
     * Show search results dropdown
     */
    DocSig.showSearchResults = function(contacts) {
        var dropdown = document.getElementById('docsig-search-results');
        if (!dropdown) return;

        dropdown.innerHTML = '';

        if (contacts.length === 0) {
            dropdown.innerHTML = '<div class="docsig-search-item docsig-no-results">No se encontraron resultados</div>';
        } else {
            contacts.forEach(function(contact) {
                var item = document.createElement('div');
                item.className = 'docsig-search-item';
                item.innerHTML = 
                    '<div class="docsig-search-item-name">' +
                        '<strong>' + DocSig.escapeHtml(contact.fullname || contact.name) + '</strong>' +
                        (contact.socname ? ' <small class="opacitymedium">(' + DocSig.escapeHtml(contact.socname) + ')</small>' : '') +
                    '</div>' +
                    '<div class="docsig-search-item-email">' +
                        '<small>' + DocSig.escapeHtml(contact.email) + '</small>' +
                    '</div>';
                
                item.addEventListener('click', function() {
                    DocSig.addContactFromSearch(contact);
                    dropdown.style.display = 'none';
                    document.getElementById('docsig-contact-search').value = '';
                });

                dropdown.appendChild(item);
            });
        }

        dropdown.style.display = 'block';
    };

    /**
     * Add contact from search results to signers list
     */
    DocSig.addContactFromSearch = function(contact) {
        var uniqueId = 'contact_' + contact.id;
        
        // Check if already exists
        if (document.querySelector('.docsig-signer-item[data-id="' + uniqueId + '"]')) {
            alert('Este contacto ya está en la lista');
            return;
        }

        var html = DocSig.renderSignerItem(
            uniqueId,
            contact.fullname || contact.name,
            contact.email,
            contact.phone || '',
            contact.poste || '',
            'contact',
            contact.id,
            true // Checked by default when adding from search
        );

        var list = document.getElementById('docsig-signers-list');
        if (list) {
            list.insertAdjacentHTML('beforeend', html);
            
            // Add change listener to new checkbox
            var newCheckbox = list.querySelector('.docsig-signer-item[data-id="' + uniqueId + '"] input[type="checkbox"]');
            if (newCheckbox) {
                newCheckbox.addEventListener('change', DocSig.updateSubmitButtonState);
            }
            
            DocSig.updateSubmitButtonState();
        }
    };

    /**
     * Add new contact (without thirdparty)
     */
    DocSig.addNewContact = function() {
        var firstname = document.getElementById('new-contact-firstname').value.trim();
        var lastname = document.getElementById('new-contact-lastname').value.trim();
        var email = document.getElementById('new-contact-email').value.trim();
        var phone = document.getElementById('new-contact-phone').value.trim();
        var dni = document.getElementById('new-contact-dni').value.trim();
        var saveContact = document.getElementById('new-contact-save').value;

        if (!email) {
            alert('El email es obligatorio');
            return;
        }

        if (!DocSig.validateEmail(email)) {
            alert('El email no es válido');
            return;
        }

        var name = (firstname + ' ' + lastname).trim() || email;
        var uniqueId = 'new_' + Date.now();

        var html = DocSig.renderSignerItem(
            uniqueId,
            name,
            email,
            phone,
            '',
            'new',
            0,
            true
        );

        // Add extra hidden fields before closing div
        var extraFields = '<input type="hidden" name="signers[' + uniqueId + '][save_contact]" value="' + saveContact + '">';
        if (dni) {
            extraFields += '<input type="hidden" name="signers[' + uniqueId + '][dni]" value="' + DocSig.escapeHtml(dni) + '">';
        }
        // Insert before the last closing div
        html = html.slice(0, -6) + extraFields + '</div>';

        var list = document.getElementById('docsig-signers-list');
        if (list) {
            list.insertAdjacentHTML('beforeend', html);
            
            // Add change listener
            var newCheckbox = list.querySelector('.docsig-signer-item[data-id="' + uniqueId + '"] input[type="checkbox"]');
            if (newCheckbox) {
                newCheckbox.addEventListener('change', DocSig.updateSubmitButtonState);
            }
            
            DocSig.updateSubmitButtonState();
        }

        // Clear and hide form
        DocSig.clearNewContactForm();
        document.getElementById('docsig-new-contact-form').style.display = 'none';
    };

    /**
     * Clear new contact form
     */
    DocSig.clearNewContactForm = function() {
        document.getElementById('new-contact-firstname').value = '';
        document.getElementById('new-contact-lastname').value = '';
        document.getElementById('new-contact-email').value = '';
        document.getElementById('new-contact-phone').value = '';
        document.getElementById('new-contact-dni').value = '';
        document.getElementById('new-contact-save').value = '0';
    };

    /**
     * Render signer item HTML - Enhanced version matching PHP render
     */
    DocSig.renderSignerItem = function(uniqueId, name, email, phone, poste, type, contactId, checked) {
        var checkedAttr = checked ? ' checked' : '';
        var typeConfig = {
            'thirdparty': { label: 'Tercero', badge: 'badge-primary', icon: 'fa-building' },
            'object_contact': { label: 'Vinculado', badge: 'badge-success', icon: 'fa-link' },
            'internal_user': { label: 'Interno', badge: 'badge-warning', icon: 'fa-user' },
            'thirdparty_contact': { label: 'Contacto', badge: 'badge-secondary', icon: 'fa-user' },
            'contact': { label: 'Contacto', badge: 'badge-secondary', icon: 'fa-user' },
            'new': { label: 'Nuevo', badge: 'badge-info', icon: 'fa-plus' }
        };
        var config = typeConfig[type] || typeConfig['contact'];
        var removeBtn = type === 'new' ? 
            '<button type="button" class="docsig-remove-signer" onclick="this.parentElement.remove(); DocSig.updateSubmitButtonState();"><span class="fa fa-times"></span></button>' : '';

        return '<div class="docsig-signer-item" data-id="' + DocSig.escapeHtml(uniqueId) + '">' +
            // Checkbox
            '<div class="docsig-signer-select">' +
                '<input type="checkbox" name="signers_selected[]" value="' + DocSig.escapeHtml(uniqueId) + '" id="signer-' + DocSig.escapeHtml(uniqueId) + '"' + checkedAttr + '>' +
            '</div>' +
            // Main info
            '<div class="docsig-signer-main">' +
                '<label for="signer-' + DocSig.escapeHtml(uniqueId) + '" class="docsig-signer-label">' +
                    '<span class="fa ' + config.icon + ' docsig-signer-icon"></span>' +
                    '<span class="docsig-signer-name">' + DocSig.escapeHtml(name) + '</span>' +
                    (poste ? '<span class="docsig-signer-role">' + DocSig.escapeHtml(poste) + '</span>' : '') +
                '</label>' +
                '<span class="badge ' + config.badge + '">' + DocSig.escapeHtml(config.label) + '</span>' +
            '</div>' +
            // Editable fields
            '<div class="docsig-signer-fields">' +
                '<div class="docsig-field-group">' +
                    '<span class="fa fa-envelope docsig-field-icon"></span>' +
                    '<input type="email" name="signers[' + uniqueId + '][email]" value="' + DocSig.escapeHtml(email) + '" class="docsig-input" placeholder="Email">' +
                '</div>' +
                '<div class="docsig-field-group">' +
                    '<span class="fa fa-phone docsig-field-icon"></span>' +
                    '<input type="text" name="signers[' + uniqueId + '][phone]" value="' + DocSig.escapeHtml(phone) + '" class="docsig-input" placeholder="Teléfono">' +
                '</div>' +
            '</div>' +
            // Hidden fields
            '<input type="hidden" name="signers[' + uniqueId + '][name]" value="' + DocSig.escapeHtml(name) + '">' +
            '<input type="hidden" name="signers[' + uniqueId + '][type]" value="' + DocSig.escapeHtml(type) + '">' +
            '<input type="hidden" name="signers[' + uniqueId + '][contact_id]" value="' + DocSig.escapeHtml(contactId) + '">' +
            removeBtn +
        '</div>';
    };

    /**
     * Update submit button state based on selected signers
     */
    DocSig.updateSubmitButtonState = function() {
        var submitBtn = document.getElementById('docsig-submit-btn');
        if (!submitBtn) return;

        var checkedSigners = document.querySelectorAll('.docsig-signer-item input[type="checkbox"]:checked');
        var hasPdf = document.querySelector('input[name="pdf_file"]');
        
        submitBtn.disabled = checkedSigners.length === 0 || !hasPdf;
    };

    /**
     * Create envelope via AJAX
     */
    DocSig.createEnvelope = function(formData) {
        var submitBtn = document.getElementById('docsig-submit-btn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="fa fa-spinner fa-spin"></span> Enviando...';
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', DocSig.config.ajaxUrl + 'envelope_create.php', true);
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            alert('Solicitud de firma creada correctamente');
                            DocSig.closeModal();
                            location.reload();
                        } else {
                            alert('Error: ' + response.error);
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = '<span class="fa fa-paper-plane"></span> Enviar solicitud de firma';
                            }
                        }
                    } catch(e) {
                        alert('Error al procesar la respuesta');
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<span class="fa fa-paper-plane"></span> Enviar solicitud de firma';
                        }
                    }
                } else {
                    alert('Error al crear la solicitud');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<span class="fa fa-paper-plane"></span> Enviar solicitud de firma';
                    }
                }
            }
        };

        formData.append('token', DocSig.config.csrfToken);
        xhr.send(formData);
    };

    /**
     * Cancel envelope
     */
    DocSig.cancelEnvelope = function(envelopeId) {
        if (!confirm('¿Está seguro de cancelar esta solicitud de firma?')) {
            return;
        }

        var reason = prompt('Motivo de cancelación (opcional):');

        var xhr = new XMLHttpRequest();
        xhr.open('POST', DocSig.config.ajaxUrl + 'envelope_cancel.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert('Solicitud cancelada');
                        DocSig.closeModal();
                        location.reload();
                    } else {
                        alert('Error: ' + response.error);
                    }
                } catch(e) {
                    alert('Error al procesar la respuesta');
                }
            }
        };

        xhr.send('envelope_id=' + encodeURIComponent(envelopeId) + 
                 '&reason=' + encodeURIComponent(reason || '') + 
                 '&token=' + encodeURIComponent(DocSig.config.csrfToken));
    };

    /**
     * Resend request to signer
     */
    DocSig.resendRequest = function(signerId) {
        if (!confirm('¿Reenviar solicitud de firma?')) {
            return;
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', DocSig.config.ajaxUrl + 'resend_request.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert('Solicitud reenviada correctamente');
                    } else {
                        alert('Error: ' + response.error);
                    }
                } catch(e) {
                    alert('Error al procesar la respuesta');
                }
            }
        };

        xhr.send('signer_id=' + encodeURIComponent(signerId) + 
                 '&token=' + encodeURIComponent(DocSig.config.csrfToken));
    };

    /**
     * Validate email format
     */
    DocSig.validateEmail = function(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    };

    /**
     * Escape HTML special characters
     */
    DocSig.escapeHtml = function(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

    /**
     * Debounce function
     */
    DocSig.debounce = function(func, wait) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    };

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', DocSig.init);
    } else {
        DocSig.init();
    }

})();
