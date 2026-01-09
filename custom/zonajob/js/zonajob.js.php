<?php
/* Copyright (C) 2025 ZonaJob Dev
 * JavaScript for ZonaJob module
 */

if (!defined('NOREQUIREUSER')) define('NOREQUIREUSER', '1');
if (!defined('NOREQUIRESOC')) define('NOREQUIRESOC', '1');
if (!defined('NOREQUIRETRAN')) define('NOREQUIRETRAN', '1');
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', 1);
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1);
if (!defined('NOLOGIN')) define('NOLOGIN', 1);
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', 1);
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', 1);
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');

session_cache_limiter('public');

header('Content-type: application/javascript');
if (empty($dolibarr_nocache)) {
    header('Cache-Control: max-age=10800, public, must-revalidate');
}
?>

/**
 * ZonaJob JavaScript Module
 */
var ZonaJob = (function() {
    'use strict';

    // Private variables
    var signatureCanvas = null;
    var signatureCtx = null;
    var isDrawing = false;
    var lastX = 0;
    var lastY = 0;

    /**
     * Initialize module
     */
    function init() {
        initSignatureCanvas();
        initGeolocation();
        initPhotoUpload();
        initFormValidation();
    }

    /**
     * Initialize signature canvas
     */
    function initSignatureCanvas() {
        signatureCanvas = document.getElementById('signature-canvas');
        if (!signatureCanvas) return;

        signatureCtx = signatureCanvas.getContext('2d');
        
        // Resize canvas to container width
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        // Set drawing style
        signatureCtx.strokeStyle = '#000';
        signatureCtx.lineWidth = 2;
        signatureCtx.lineCap = 'round';
        signatureCtx.lineJoin = 'round';

        // Mouse events
        signatureCanvas.addEventListener('mousedown', startDrawing);
        signatureCanvas.addEventListener('mousemove', draw);
        signatureCanvas.addEventListener('mouseup', stopDrawing);
        signatureCanvas.addEventListener('mouseout', stopDrawing);

        // Touch events
        signatureCanvas.addEventListener('touchstart', handleTouchStart, {passive: false});
        signatureCanvas.addEventListener('touchmove', handleTouchMove, {passive: false});
        signatureCanvas.addEventListener('touchend', stopDrawing);
    }

    /**
     * Resize canvas to container width
     */
    function resizeCanvas() {
        if (!signatureCanvas) return;
        
        var container = signatureCanvas.parentElement;
        var ratio = window.devicePixelRatio || 1;
        
        // Save current drawing
        var imageData = signatureCtx.getImageData(0, 0, signatureCanvas.width, signatureCanvas.height);
        
        // Resize
        signatureCanvas.width = container.offsetWidth - 20;
        signatureCanvas.height = 200;
        
        // Restore drawing style
        signatureCtx.strokeStyle = '#000';
        signatureCtx.lineWidth = 2;
        signatureCtx.lineCap = 'round';
        signatureCtx.lineJoin = 'round';
        
        // Try to restore drawing (may not work perfectly on resize)
        try {
            signatureCtx.putImageData(imageData, 0, 0);
        } catch(e) {}
    }

    /**
     * Start drawing
     */
    function startDrawing(e) {
        isDrawing = true;
        var rect = signatureCanvas.getBoundingClientRect();
        lastX = e.clientX - rect.left;
        lastY = e.clientY - rect.top;
    }

    /**
     * Draw on canvas
     */
    function draw(e) {
        if (!isDrawing) return;
        e.preventDefault();

        var rect = signatureCanvas.getBoundingClientRect();
        var x = e.clientX - rect.left;
        var y = e.clientY - rect.top;

        signatureCtx.beginPath();
        signatureCtx.moveTo(lastX, lastY);
        signatureCtx.lineTo(x, y);
        signatureCtx.stroke();

        lastX = x;
        lastY = y;
    }

    /**
     * Handle touch start
     */
    function handleTouchStart(e) {
        e.preventDefault();
        var touch = e.touches[0];
        var rect = signatureCanvas.getBoundingClientRect();
        isDrawing = true;
        lastX = touch.clientX - rect.left;
        lastY = touch.clientY - rect.top;
    }

    /**
     * Handle touch move
     */
    function handleTouchMove(e) {
        if (!isDrawing) return;
        e.preventDefault();

        var touch = e.touches[0];
        var rect = signatureCanvas.getBoundingClientRect();
        var x = touch.clientX - rect.left;
        var y = touch.clientY - rect.top;

        signatureCtx.beginPath();
        signatureCtx.moveTo(lastX, lastY);
        signatureCtx.lineTo(x, y);
        signatureCtx.stroke();

        lastX = x;
        lastY = y;
    }

    /**
     * Stop drawing
     */
    function stopDrawing() {
        isDrawing = false;
    }

    /**
     * Clear signature canvas
     */
    function clearSignature() {
        if (signatureCtx) {
            signatureCtx.clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
        }
    }

    /**
     * Get signature data as base64
     */
    function getSignatureData() {
        if (!signatureCanvas) return '';
        return signatureCanvas.toDataURL('image/png');
    }

    /**
     * Check if signature canvas is empty
     */
    function isSignatureEmpty() {
        if (!signatureCanvas) return true;
        
        var blank = document.createElement('canvas');
        blank.width = signatureCanvas.width;
        blank.height = signatureCanvas.height;
        
        return signatureCanvas.toDataURL() === blank.toDataURL();
    }

    /**
     * Prepare signature for form submission
     */
    function prepareSignature() {
        if (isSignatureEmpty()) {
            alert('Por favor, firme en el área designada');
            return false;
        }
        
        var sigDataInput = document.getElementById('signature_data');
        if (sigDataInput) {
            sigDataInput.value = getSignatureData();
        }
        
        return true;
    }

    /**
     * Initialize geolocation
     */
    function initGeolocation() {
        if (!navigator.geolocation) return;

        navigator.geolocation.getCurrentPosition(function(pos) {
            // Photo latitude/longitude
            var photoLat = document.getElementById('photo_latitude');
            var photoLng = document.getElementById('photo_longitude');
            if (photoLat) photoLat.value = pos.coords.latitude;
            if (photoLng) photoLng.value = pos.coords.longitude;

            // Signature latitude/longitude
            var sigLat = document.getElementById('sig_latitude');
            var sigLng = document.getElementById('sig_longitude');
            if (sigLat) sigLat.value = pos.coords.latitude;
            if (sigLng) sigLng.value = pos.coords.longitude;
        }, function(error) {
            console.log('Geolocation error:', error.message);
        });
    }

    /**
     * Initialize photo upload
     */
    function initPhotoUpload() {
        var uploadArea = document.querySelector('.upload-area');
        var photoInput = document.getElementById('photo-input');
        
        if (!uploadArea || !photoInput) return;

        // Drag and drop
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            if (e.dataTransfer.files.length > 0) {
                photoInput.files = e.dataTransfer.files;
                previewPhoto(photoInput);
            }
        });
    }

    /**
     * Preview photo before upload
     */
    function previewPhoto(input) {
        if (!input.files || !input.files[0]) return;

        var reader = new FileReader();
        reader.onload = function(e) {
            var preview = document.getElementById('photo-preview');
            var container = document.getElementById('photo-preview-container');
            
            if (preview) preview.src = e.target.result;
            if (container) container.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }

    /**
     * Initialize form validation
     */
    function initFormValidation() {
        var forms = document.querySelectorAll('form');
        
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var requiredFields = form.querySelectorAll('[required]');
                var valid = true;
                
                requiredFields.forEach(function(field) {
                    if (!field.value.trim()) {
                        valid = false;
                        field.classList.add('error');
                    } else {
                        field.classList.remove('error');
                    }
                });
                
                if (!valid) {
                    e.preventDefault();
                    alert('Por favor, complete todos los campos obligatorios');
                }
            });
        });
    }

    /**
     * Fill contact info from select
     */
    function fillContactInfo(select) {
        var option = select.options[select.selectedIndex];
        if (!option.value) return;

        var nameInput = document.getElementById('signer_name');
        var emailInput = document.getElementById('signer_email');
        var phoneInput = document.getElementById('signer_phone');

        if (nameInput && option.dataset.name) nameInput.value = option.dataset.name;
        if (emailInput && option.dataset.email) emailInput.value = option.dataset.email;
        if (phoneInput && option.dataset.phone) phoneInput.value = option.dataset.phone;
    }

    /**
     * Fill WhatsApp phone from select
     */
    function fillWhatsAppPhone(select) {
        var option = select.options[select.selectedIndex];
        var phoneInput = document.getElementById('wa_phone');
        
        if (phoneInput && option.dataset.phone) {
            phoneInput.value = option.dataset.phone;
        }
    }

    /**
     * Fill email address from select
     */
    function fillEmailAddress(select) {
        var option = select.options[select.selectedIndex];
        var emailInput = document.getElementById('email_to');
        
        if (emailInput && option.dataset.email) {
            emailInput.value = option.dataset.email;
        }
    }

    /**
     * Toggle add contact form
     */
    function toggleAddContactForm() {
        var form = document.getElementById('add-contact-form');
        if (form) {
            form.style.display = (form.style.display === 'none') ? 'block' : 'none';
        }
    }

    /**
     * Open photo modal
     */
    function openPhotoModal(src) {
        var modal = document.getElementById('photo-modal');
        var modalImg = document.getElementById('modal-image');
        
        if (modal && modalImg) {
            modalImg.src = src;
            modal.style.display = 'flex';
        }
    }

    /**
     * Close photo modal
     */
    function closePhotoModal() {
        var modal = document.getElementById('photo-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    /**
     * Toggle add line form
     */
    function toggleAddLineForm() {
        var form = document.getElementById('add-line-form');
        if (form) {
            form.style.display = (form.style.display === 'none') ? 'block' : 'none';
        }
    }

    /**
     * Edit line
     */
    function editLine(lineid) {
        var editForm = document.getElementById('edit-line-form-' + lineid);
        if (editForm) {
            editForm.style.display = (editForm.style.display === 'none') ? 'block' : 'none';
        }
    }

    /**
     * Cancel edit line
     */
    function cancelEditLine(lineid) {
        var editForm = document.getElementById('edit-line-form-' + lineid);
        if (editForm) {
            editForm.style.display = 'none';
        }
    }

    /**
     * Update product info when selected
     */
    function updateProductInfo(select) {
        var productId = select.value;
        var selectedOption = select.options[select.selectedIndex];
        
        if (productId > 0) {
            // Get product data from option attributes
            var price = selectedOption.getAttribute('data-price');
            var vat = selectedOption.getAttribute('data-vat');
            var desc = selectedOption.getAttribute('data-desc');
            
            // Update price field
            var priceField = document.getElementById('line_price');
            if (priceField && price) {
                priceField.value = price;
            }
            
            // Update VAT field
            var vatField = document.getElementById('line_vat');
            if (vatField && vat) {
                vatField.value = vat;
            }
            
            // Update description field
            var descField = document.getElementById('line_description');
            if (descField && desc) {
                descField.value = desc;
            }
        } else {
            // Free text - clear fields
            var descField = document.getElementById('line_description');
            if (descField) {
                descField.value = '';
            }
        }
    }

    /**
     * Initialize product autocomplete search
     */
    function initProductAutocomplete() {
        var searchInput = document.getElementById('product_search');
        if (!searchInput) return;

        var autocompleteDropdown = document.getElementById('product_autocomplete');
        var hiddenInput = document.getElementById('fk_product');
        var debounceTimer;

        if (!autocompleteDropdown || !hiddenInput) {
            return;
        }

        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            var query = this.value.trim();

            if (query.length < 1) {
                autocompleteDropdown.style.display = 'none';
                hiddenInput.value = 0;
                return;
            }

            // Debounce search requests
            debounceTimer = setTimeout(function() {
                searchProducts(query, searchInput, autocompleteDropdown, hiddenInput);
            }, 300);
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target !== searchInput && !autocompleteDropdown.contains(e.target)) {
                autocompleteDropdown.style.display = 'none';
            }
        });
    }

    /**
     * Search products via AJAX
     */
    function searchProducts(query, searchInput, dropdown, hiddenInput) {
        var xhr = new XMLHttpRequest();

        // Prefer server-injected absolute URL if available
        var ajaxUrl = (window.ZONAJOB_PRODUCT_SEARCH_URL && String(window.ZONAJOB_PRODUCT_SEARCH_URL)) || '';
        if (!ajaxUrl) {
            // Fallback: same directory as current page
            var baseUrl = window.location.pathname.split('/').slice(0, -1).join('/');
            ajaxUrl = baseUrl + '/ajax_product_search.php';
        }
        
        // Debug logging
        console.log('AJAX URL:', ajaxUrl);
        console.log('Search query:', query);
        
        xhr.open('GET', ajaxUrl + '?search=' + encodeURIComponent(query) + '&limit=15', true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);

                    if (data && Array.isArray(data.products)) {
                        populateAutocomplete(data.products, dropdown, searchInput, hiddenInput);
                        return;
                    }

                    dropdown.innerHTML = '<div style="padding: 1rem; text-align: center; color: #b00;">Error: respuesta inválida</div>';
                    dropdown.style.display = 'block';
                } catch (e) {
                    console.error('Error parsing autocomplete response:', e);
                    console.error('Response was:', xhr.responseText);

                    dropdown.innerHTML = '<div style="padding: 1rem; text-align: center; color: #b00;">Error al procesar respuesta</div>';
                    dropdown.style.display = 'block';
                }
            } else {
                console.error('AJAX request failed with status:', xhr.status);

                dropdown.innerHTML = '<div style="padding: 1rem; text-align: center; color: #b00;">Error (' + xhr.status + ') al buscar</div>';
                dropdown.style.display = 'block';
            }
        };
        xhr.onerror = function() {
            console.error('AJAX request error');

            dropdown.innerHTML = '<div style="padding: 1rem; text-align: center; color: #b00;">Error de red al buscar</div>';
            dropdown.style.display = 'block';
        };
        xhr.send();
    }

    /**
     * Populate autocomplete dropdown
     */
    function populateAutocomplete(products, dropdown, searchInput, hiddenInput) {
        dropdown.innerHTML = '';

        if (!Array.isArray(products) || products.length === 0) {
            dropdown.innerHTML = '<div style="padding: 1rem; text-align: center; color: #999;">No se encontraron productos</div>';
            dropdown.style.display = 'block';
            return;
        }

        products.forEach(function(product) {
            var item = document.createElement('div');
            item.style.cssText = 'padding: 0.75rem; cursor: pointer; border-bottom: 1px solid #eee; hover:background-color: #f5f5f5;';
            item.innerHTML = '<strong>' + escapeHtml(product.ref) + '</strong> - ' + escapeHtml(product.label) + '<br><small style="color: #999;">' + product.type + ' | Precio: ' + product.price + '€</small>';
            
            item.addEventListener('click', function() {
                selectProduct(product, searchInput, hiddenInput, dropdown);
            });

            item.addEventListener('mouseover', function() {
                this.style.backgroundColor = '#f5f5f5';
            });

            item.addEventListener('mouseout', function() {
                this.style.backgroundColor = 'white';
            });

            dropdown.appendChild(item);
        });

        dropdown.style.display = 'block';
    }

    /**
     * Select a product from autocomplete
     */
    function selectProduct(product, searchInput, hiddenInput, dropdown) {
        searchInput.value = product.ref + ' - ' + product.label;
        hiddenInput.value = product.id;

        // Update price and VAT fields
        var priceField = document.getElementById('line_price');
        var vatField = document.getElementById('line_vat');
        var descField = document.getElementById('line_description');

        if (priceField) priceField.value = (product.price !== undefined && product.price !== null) ? product.price : 0;
        if (vatField) {
            var vatValue = (product.tva_tx !== undefined && product.tva_tx !== null && product.tva_tx !== '') ? parseFloat(product.tva_tx) : '';
            vatField.value = (isNaN(vatValue) ? '' : String(vatValue));
        }
        if (descField) descField.value = product.description || '';

        dropdown.style.display = 'none';
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        text = String(text);
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * Make button more visible
     */
    function enhanceAddLineButton() {
        var btn = document.querySelector('.btn-add-line');
        if (btn) {
            // Ensure button is visible
            btn.style.display = 'block';
            btn.style.visibility = 'visible';
            btn.style.opacity = '1';
            
            // Add visual highlight
            btn.style.fontSize = '1rem';
            btn.style.padding = '0.75rem 1.5rem';
            btn.style.fontWeight = 'bold';
            
            // Add slight animation on hover
            btn.addEventListener('mouseover', function() {
                this.style.transform = 'scale(1.05)';
                this.style.boxShadow = '0 4px 12px rgba(40, 167, 69, 0.4)';
            });
            btn.addEventListener('mouseout', function() {
                this.style.transform = 'scale(1)';
                this.style.boxShadow = 'none';
            });
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
        document.addEventListener('DOMContentLoaded', initProductAutocomplete);
        document.addEventListener('DOMContentLoaded', enhanceAddLineButton);
    } else {
        init();
        initProductAutocomplete();
        enhanceAddLineButton();
    }

    // Public API
    return {
        clearSignature: clearSignature,
        prepareSignature: prepareSignature,
        previewPhoto: previewPhoto,
        fillContactInfo: fillContactInfo,
        fillWhatsAppPhone: fillWhatsAppPhone,
        fillEmailAddress: fillEmailAddress,
        toggleAddContactForm: toggleAddContactForm,
        openPhotoModal: openPhotoModal,
        closePhotoModal: closePhotoModal,
        toggleAddLineForm: toggleAddLineForm,
        editLine: editLine,
        cancelEditLine: cancelEditLine,
        updateProductInfo: updateProductInfo,
        initProductAutocomplete: initProductAutocomplete,
        searchProducts: searchProducts
    };
})();

// Global functions for inline event handlers
function clearSignature() { ZonaJob.clearSignature(); }
function prepareSignature() { return ZonaJob.prepareSignature(); }
function previewPhoto(input) { ZonaJob.previewPhoto(input); }
function fillContactInfo(select) { ZonaJob.fillContactInfo(select); }
function fillWhatsAppPhone(select) { ZonaJob.fillWhatsAppPhone(select); }
function fillEmailAddress(select) { ZonaJob.fillEmailAddress(select); }
function toggleAddContactForm() { ZonaJob.toggleAddContactForm(); }
function openPhotoModal(src) { ZonaJob.openPhotoModal(src); }
function closePhotoModal() { ZonaJob.closePhotoModal(); }
function toggleAddLineForm() { ZonaJob.toggleAddLineForm(); }
function editLine(lineid) { ZonaJob.editLine(lineid); }
function cancelEditLine(lineid) { ZonaJob.cancelEditLine(lineid); }
function updateProductInfo(select) { ZonaJob.updateProductInfo(select); }