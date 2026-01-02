/* Docsig Module JavaScript */

(function() {
    'use strict';

    // Modal handling
    let docsigModal = null;
    let docsigCurrentSocid = 0;

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        initializeDocsig();
    });

    function initializeDocsig() {
        // Create modal
        createModal();

        // Attach event listeners to signature request buttons
        document.querySelectorAll('.docsig-request-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const elementType = this.dataset.elementType;
                const elementId = this.dataset.elementId;

                // Optional: preselect the document from the row in documents list
                let prefillDocumentPath = '';
                const modulepart = this.dataset.modulepart || '';
                const fileEncoded = this.dataset.document || '';
                if (modulepart && fileEncoded) {
                    try {
                        prefillDocumentPath = modulepart.replace(/\/+$/, '') + '/' + decodeURIComponent(fileEncoded);
                    } catch (err) {
                        prefillDocumentPath = modulepart.replace(/\/+$/, '') + '/' + fileEncoded;
                    }
                }

                openSignatureModal(elementType, elementId, prefillDocumentPath);
            });
        });
    }

    function createModal() {
        if (document.getElementById('docsig-modal')) return;

        const modal = document.createElement('div');
        modal.id = 'docsig-modal';
        modal.className = 'docsig-modal';
        modal.innerHTML = `
            <div class="docsig-modal-content">
                <span class="docsig-modal-close">&times;</span>
                <div id="docsig-modal-body"></div>
            </div>
        `;
        document.body.appendChild(modal);

        docsigModal = modal;

        // Close handlers
        modal.querySelector('.docsig-modal-close').addEventListener('click', closeModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });
    }

    function openSignatureModal(elementType, elementId, prefillDocumentPath) {
        if (!docsigModal) return;

        // Check if envelope exists
        fetch(docsigAjaxUrl + 'envelope.php?action=get_envelope_status&element_type=' + encodeURIComponent(elementType) + '&element_id=' + encodeURIComponent(elementId) + '&token=' + encodeURIComponent(docsigToken))
            .then(safeJson)
            .then(data => {
                if (data.success) {
                    showEnvelopeStatus(data.envelope, data.signatures, elementType, elementId);
                } else {
                    showCreateEnvelopeForm(elementType, elementId, prefillDocumentPath);
                }
            })
            .catch(() => {
                showCreateEnvelopeForm(elementType, elementId, prefillDocumentPath);
            });

        docsigModal.style.display = 'block';
    }

    function closeModal() {
        if (docsigModal) {
            docsigModal.style.display = 'none';
        }
    }

    function showCreateEnvelopeForm(elementType, elementId, prefillDocumentPath) {
        const body = document.getElementById('docsig-modal-body');
        body.innerHTML = `
            <h2><i class="fa fa-file-signature"></i> Request Signature</h2>
            <form id="docsig-create-form">
                <div class="docsig-form-group">
                    <label>Document *</label>
                    <select id="document-select" class="form-control" required>
                        <option value="">Loading documents...</option>
                    </select>
                </div>

                <div class="docsig-form-group">
                    <label>Buscar contacto</label>
                    <input type="text" id="docsig-contact-search" class="form-control" placeholder="Nombre, email, teléfono o DNI">
                    <div id="docsig-contact-results" class="docsig-search-results"></div>
                </div>

                <div class="docsig-form-group">
                    <label>Signature Mode *</label>
                    <select id="signature-mode" class="form-control">
                        <option value="parallel">Parallel (all can sign at same time)</option>
                        <option value="ordered">Ordered (sequential signing)</option>
                    </select>
                </div>

                <div class="docsig-form-group">
                    <label>Expiration (days) *</label>
                    <input type="number" id="expiration-days" class="form-control" value="30" min="1" max="365">
                </div>

                <div class="docsig-form-group">
                    <label>Custom Message</label>
                    <textarea id="custom-message" class="form-control" rows="3" 
                        placeholder="Optional message to signers"></textarea>
                </div>

                <h3>Signers</h3>
                <div id="signers-list"></div>
                <button type="button" id="add-signer-btn" class="btn btn-secondary">
                    <i class="fa fa-plus"></i> Add Signer
                </button>

                <hr style="margin: 30px 0;">
                <button type="submit" class="btn btn-primary">Send Signature Request</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </form>
        `;

        // Load documents
        loadDocuments(elementType, elementId, prefillDocumentPath);

        // Load defaults (thirdparty + linked contacts)
        loadInitContext(elementType, elementId);

        // Form submit
        document.getElementById('docsig-create-form').addEventListener('submit', function(e) {
            e.preventDefault();
            submitCreateEnvelope(elementType, elementId);
        });

        // Add signer button
        document.getElementById('add-signer-btn').addEventListener('click', function() {
            addSignerRow();
        });
    }

    function loadDocuments(elementType, elementId, prefillDocumentPath) {
        const select = document.getElementById('document-select');

        if (prefillDocumentPath) {
            const parts = prefillDocumentPath.split('/');
            const filename = parts[parts.length - 1] || prefillDocumentPath;
            select.innerHTML = `<option value="${escapeHtml(prefillDocumentPath)}">${escapeHtml(filename)}</option>`;
            return;
        }

        // Fallback: no document preselected
        select.innerHTML = `<option value="">Seleccione un documento desde la lista</option>`;
    }

    function safeJson(response) {
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            return response.text().then(text => {
                throw new Error(text || ('Unexpected response (HTTP ' + response.status + ')'));
            });
        }
        return response.json();
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function loadInitContext(elementType, elementId) {
        const url = docsigAjaxUrl + 'envelope.php?action=init_context&element_type=' + encodeURIComponent(elementType) + '&element_id=' + encodeURIComponent(elementId) + '&token=' + encodeURIComponent(docsigToken);
        fetch(url).then(safeJson).then(data => {
            if (data.success) {
                docsigCurrentSocid = data.socid || 0;
                // Prefill default signers (linked contacts)
                const defaults = data.default_signers || [];
                if (defaults.length) {
                    defaults.forEach(c => addSignerRow(c));
                } else {
                    addSignerRow();
                }
                // Setup search/autocomplete
                setupContactSearch();
                setupCreateContact();
            } else {
                addSignerRow();
                setupContactSearch();
                setupCreateContact();
            }
        }).catch(() => { addSignerRow(); setupContactSearch(); });
    }

    function setupContactSearch() {
        const input = document.getElementById('docsig-contact-search');
        const results = document.getElementById('docsig-contact-results');
        if (!input || !results) return;

        let timer = null;
        input.addEventListener('input', function() {
            const q = this.value.trim();
            clearTimeout(timer);
            if (q.length < 2) { results.innerHTML = ''; return; }
            timer = setTimeout(() => doSearchContacts(q, results), 250);
        });
    }

    function doSearchContacts(q, resultsContainer) {
        const url = docsigAjaxUrl + 'envelope.php?action=search_contacts&q=' + encodeURIComponent(q) + '&limit=20&token=' + encodeURIComponent(docsigToken);
        fetch(url).then(safeJson).then(data => {
            if (!data.success) { resultsContainer.innerHTML = ''; return; }
            const items = data.results || [];
            if (!items.length) { resultsContainer.innerHTML = '<div class="docsig-no-results">Sin resultados</div>'; return; }
            resultsContainer.innerHTML = items.map(c => (
                '<div class="docsig-result-item" data-id="'+c.id+'" data-name="'+escapeHtml(c.name)+'" data-email="'+escapeHtml(c.email||'')+'" data-dni="'+escapeHtml(c.dni||'')+'">'
                + '<strong>'+escapeHtml(c.name)+'</strong>'
                + (c.email ? ' — '+escapeHtml(c.email) : '')
                + (c.phone ? ' · '+escapeHtml(c.phone) : '')
                + (c.dni ? ' · DNI: '+escapeHtml(c.dni) : '')
                + '</div>'
            )).join('');
            Array.from(resultsContainer.querySelectorAll('.docsig-result-item')).forEach(el => {
                el.addEventListener('click', function() {
                    const contact = { id: parseInt(this.dataset.id,10)||0, name: this.dataset.name, email: this.dataset.email, dni: this.dataset.dni };
                    addSignerRow(contact);
                });
            });
        }).catch(() => { resultsContainer.innerHTML = ''; });
    }

    function setupCreateContact() {
        const results = document.getElementById('docsig-contact-results');
        if (!results) return;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-secondary';
        btn.textContent = 'Crear nuevo contacto';
        btn.style.marginTop = '6px';
        results.parentNode.appendChild(btn);
        btn.addEventListener('click', () => {
            const name = prompt('Apellidos (obligatorio):');
            if (!name) return;
            const firstname = prompt('Nombre:') || '';
            const email = prompt('Email:') || '';
            const dni = prompt('DNI:') || '';
            const payload = new URLSearchParams();
            payload.set('action', 'create_contact');
            payload.set('name', name);
            payload.set('firstname', firstname);
            payload.set('email', email);
            payload.set('dni', dni);
            payload.set('socid', String(docsigCurrentSocid || 0));
            payload.set('token', docsigToken);
            fetch(docsigAjaxUrl + 'envelope.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: payload.toString() })
                .then(safeJson)
                .then(res => {
                    if (res.success && res.contact) {
                        addSignerRow({ id: res.contact.id, name: res.contact.name, email: res.contact.email, dni: res.contact.dni });
                        alert('Contacto creado');
                    } else {
                        alert('Error creando contacto: ' + (res.error || ''));
                    }
                })
                .catch(err => alert('Error creando contacto: ' + err));
        });
    }

    function addSignerRow(contact) {
        const list = document.getElementById('signers-list');
        const index = list.children.length;
        
        const div = document.createElement('div');
        div.className = 'docsig-signer-item';
        div.innerHTML = `
            <div class="docsig-signer-info">
                <input type="hidden" class="signer-id" value="${contact ? contact.id : ''}">
                <div class="docsig-form-group">
                    <label>Name *</label>
                    <input type="text" class="signer-name form-control" value="${contact ? contact.name : ''}" required>
                </div>
                <div class="docsig-form-group">
                    <label>Email *</label>
                    <input type="email" class="signer-email form-control" value="${contact ? contact.email : ''}" required>
                </div>
                <div class="docsig-form-group">
                    <label>DNI</label>
                    <input type="text" class="signer-dni form-control" value="${contact ? contact.dni : ''}" 
                        placeholder="Optional">
                </div>
            </div>
            <button type="button" class="btn btn-danger btn-sm remove-signer" 
                onclick="this.closest('.docsig-signer-item').remove()">
                <i class="fa fa-trash"></i>
            </button>
        `;
        list.appendChild(div);
    }

    function submitCreateEnvelope(elementType, elementId) {
        const form = document.getElementById('docsig-create-form');
        const signers = [];

        form.querySelectorAll('.docsig-signer-item').forEach(function(item) {
            signers.push({
                id: item.querySelector('.signer-id').value || 0,
                name: item.querySelector('.signer-name').value,
                email: item.querySelector('.signer-email').value,
                dni: item.querySelector('.signer-dni').value,
            });
        });

        if (signers.length === 0) {
            alert('Please add at least one signer');
            return;
        }

        const data = {
            element_type: elementType,
            element_id: elementId,
            document_path: form.querySelector('#document-select').value,
            document_name: form.querySelector('#document-select option:checked').text,
            signature_mode: form.querySelector('#signature-mode').value,
            expiration_days: form.querySelector('#expiration-days').value,
            custom_message: form.querySelector('#custom-message').value,
            signers: signers,
        };

        // Show loading
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="docsig-loading"></span> Sending...';

        fetch(docsigAjaxUrl + 'envelope.php?action=create_envelope&token=' + encodeURIComponent(docsigToken), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': docsigToken,
            },
            body: JSON.stringify(data)
        })
        .then(safeJson)
        .then(result => {
            if (result.success) {
                alert(result.message);
                closeModal();
                location.reload();
            } else {
                alert('Error: ' + result.error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Send Signature Request';
            }
        })
        .catch(error => {
            alert('Error: ' + error);
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Send Signature Request';
        });
    }

    function showEnvelopeStatus(envelope, signatures, elementType, elementId) {
        const body = document.getElementById('docsig-modal-body');
        
        let html = `
            <h2><i class="fa fa-file-signature"></i> Signature Envelope: ${envelope.ref}</h2>
            <p><strong>Document:</strong> ${envelope.document_name}</p>
            <p><strong>Status:</strong> ${envelope.status_label}</p>
            <p><strong>Signers:</strong> ${envelope.nb_signed} / ${envelope.nb_signers}</p>

            <h3>Signatures</h3>
            <div class="docsig-signers-list">
        `;

        signatures.forEach(function(sig) {
            const signUrl = window.location.origin + '/custom/docsig/public/sign.php?token=' + sig.token;
            html += `
                <div class="docsig-signer-item">
                    <div class="docsig-signer-info">
                        <strong>${sig.name}</strong><br>
                        ${sig.email}<br>
                        <span class="docsig-signer-status status-${getStatusClass(sig.status)}">${sig.status_label}</span>
                        ${sig.signed_date ? '<br>Signed: ' + new Date(sig.signed_date * 1000).toLocaleString() : ''}
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-secondary" 
                            onclick="navigator.clipboard.writeText('${signUrl}').then(() => alert('Link copied!'))">
                            <i class="fa fa-copy"></i> Copy Link
                        </button>
                    </div>
                </div>
            `;
        });

        html += '</div>';

        if (envelope.signed_document_path) {
            html += `
                <hr>
                <a href="/document.php?modulepart=docsig&file=${encodeURIComponent(envelope.signed_document_path)}" 
                    target="_blank" class="btn btn-success">
                    <i class="fa fa-download"></i> Download Signed Document
                </a>
            `;
        }

        if (envelope.certificate_path) {
            html += `
                <a href="/document.php?modulepart=docsig&file=${encodeURIComponent(envelope.certificate_path)}" 
                    target="_blank" class="btn btn-primary">
                    <i class="fa fa-certificate"></i> Download Certificate
                </a>
            `;
        }

        if (envelope.status < 3) { // Not completed
            html += `
                <hr>
                <button type="button" class="btn btn-danger" onclick="cancelEnvelope(${envelope.id})">
                    <i class="fa fa-times"></i> Cancel Envelope
                </button>
            `;
        }

        body.innerHTML = html;
    }

    function getStatusClass(status) {
        const map = {0: 'pending', 1: 'opened', 2: 'authenticated', 3: 'signed', 4: 'failed', 5: 'cancelled'};
        return map[status] || 'pending';
    }

    window.cancelEnvelope = function(envelopeId) {
        const reason = prompt('Cancellation reason:');
        if (!reason) return;

        fetch(docsigAjaxUrl + 'envelope.php?action=cancel_envelope&envelope_id=' + envelopeId + '&reason=' + encodeURIComponent(reason) + '&token=' + encodeURIComponent(docsigToken))
            .then(safeJson)
            .then(result => {
                if (result.success) {
                    alert(result.message);
                    closeModal();
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            });
    };

    window.closeModal = closeModal;

})();
