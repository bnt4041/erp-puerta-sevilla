<?php
/* Copyright (C) 2026 DocSig Module
 *
 * CSS styles for DocSig module
 */

// Load Dolibarr environment
if (!defined('NOREQUIREUSER')) {
    define('NOREQUIREUSER', '1');
}
if (!defined('NOREQUIREDB')) {
    define('NOREQUIREDB', '1');
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

session_cache_limiter('public');

header('Content-type: text/css; charset=UTF-8');
header('Cache-Control: max-age=3600');
?>
/* DocSig Module Styles */

/* Signature icon in lists */
.docsig-icon {
    cursor: pointer;
    margin-left: 5px;
    opacity: 0.7;
    transition: opacity 0.2s;
}

/* When icon is injected into documents list, it includes data-file-path.
   Hide by default unless it's a PDF. */
.docsig-icon[data-file-path] {
    display: none;
}

.docsig-icon[data-file-path$=".pdf"],
.docsig-icon[data-file-path$=".PDF"] {
    display: inline-block;
}

.docsig-icon:hover {
    opacity: 1;
}

.docsig-icon.signed {
    color: #28a745;
}

.docsig-icon.pending {
    color: #ffc107;
}

.docsig-icon.expired {
    color: #dc3545;
}

/* Modal styles */
.docsig-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
}

.docsig-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 900px;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.docsig-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #ddd;
    padding-bottom: 15px;
    margin-bottom: 20px;
}

.docsig-modal-header h3 {
    margin: 0;
    color: #333;
}

.docsig-modal-close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    line-height: 1;
}

.docsig-modal-close:hover {
    color: #000;
}

.docsig-modal-body {
    max-height: 60vh;
    overflow-y: auto;
}

.docsig-modal-footer {
    border-top: 1px solid #ddd;
    padding-top: 15px;
    margin-top: 20px;
    text-align: right;
}

/* Tabs in modal */
.docsig-tabs {
    display: flex;
    border-bottom: 2px solid #ddd;
    margin-bottom: 20px;
}

.docsig-tab {
    padding: 10px 20px;
    cursor: pointer;
    border: none;
    background: none;
    font-size: 14px;
    color: #666;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
}

.docsig-tab:hover {
    color: #333;
}

.docsig-tab.active {
    color: #0066cc;
    border-bottom-color: #0066cc;
}

.docsig-tab-content {
    display: none;
}

.docsig-tab-content.active {
    display: block;
}

/* Signer list */
.docsig-signers-list {
    border: 1px solid #ddd;
    border-radius: 4px;
    margin: 15px 0;
}

.docsig-signer-item {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    border-bottom: 1px solid #eee;
}

.docsig-signer-item:last-child {
    border-bottom: none;
}

.docsig-signer-info {
    flex: 1;
}

.docsig-signer-name {
    font-weight: 600;
    color: #333;
}

.docsig-signer-email {
    font-size: 12px;
    color: #666;
}

.docsig-signer-status {
    margin-left: 15px;
}

.docsig-signer-actions {
    margin-left: 15px;
}

/* Status badges */
.docsig-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.docsig-badge-pending {
    background-color: #fff3cd;
    color: #856404;
}

.docsig-badge-sent {
    background-color: #cce5ff;
    color: #004085;
}

.docsig-badge-signed {
    background-color: #d4edda;
    color: #155724;
}

.docsig-badge-rejected {
    background-color: #f8d7da;
    color: #721c24;
}

.docsig-badge-expired {
    background-color: #e2e3e5;
    color: #383d41;
}

/* Form elements */
.docsig-form-group {
    margin-bottom: 15px;
}

.docsig-form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}

.docsig-form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.docsig-form-control:focus {
    border-color: #0066cc;
    outline: none;
    box-shadow: 0 0 0 2px rgba(0,102,204,0.1);
}

/* Add signer form */
.docsig-add-signer {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    margin-top: 15px;
}

.docsig-add-signer-row {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}

.docsig-add-signer-row .docsig-form-group {
    flex: 1;
    margin-bottom: 0;
}

/* Link copy box */
.docsig-link-box {
    background: #f0f8ff;
    border: 1px solid #b3d9ff;
    border-radius: 4px;
    padding: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.docsig-link-box input {
    flex: 1;
    border: none;
    background: transparent;
    font-family: monospace;
    font-size: 12px;
}

.docsig-link-box button {
    flex-shrink: 0;
}

/* Public signing page styles */
.docsig-public-container {
    max-width: 600px;
    margin: 40px auto;
    padding: 30px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.docsig-public-header {
    text-align: center;
    margin-bottom: 30px;
}

.docsig-public-header h1 {
    color: #333;
    font-size: 24px;
    margin-bottom: 10px;
}

.docsig-public-header p {
    color: #666;
}

.docsig-step {
    display: none;
}

.docsig-step.active {
    display: block;
}

/* Signature canvas */
.docsig-signature-wrapper {
    border: 2px dashed #ddd;
    border-radius: 8px;
    padding: 10px;
    margin: 20px 0;
    background: #fafafa;
}

.docsig-signature-canvas {
    width: 100%;
    height: 200px;
    border: 1px solid #ccc;
    border-radius: 4px;
    background: #fff;
    cursor: crosshair;
}

.docsig-signature-actions {
    text-align: center;
    margin-top: 10px;
}

/* OTP input */
.docsig-otp-input {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin: 20px 0;
}

.docsig-otp-digit {
    width: 50px;
    height: 60px;
    text-align: center;
    font-size: 24px;
    font-weight: bold;
    border: 2px solid #ddd;
    border-radius: 8px;
}

.docsig-otp-digit:focus {
    border-color: #0066cc;
    outline: none;
}

/* Success message */
.docsig-success {
    text-align: center;
    padding: 40px;
}

.docsig-success-icon {
    font-size: 64px;
    color: #28a745;
    margin-bottom: 20px;
}

.docsig-success h2 {
    color: #28a745;
    margin-bottom: 15px;
}

/* Error message */
.docsig-error {
    background: #f8d7da;
    color: #721c24;
    padding: 15px;
    border-radius: 4px;
    margin: 15px 0;
}

/* Loading spinner */
.docsig-loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #0066cc;
    border-radius: 50%;
    animation: docsig-spin 1s linear infinite;
}

@keyframes docsig-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* ============================================================
   NEW MODAL STYLES - Enhanced signer selection
   ============================================================ */

/* Modal layout */
.docsig-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
    justify-content: center;
    align-items: flex-start;
    padding: 30px 15px;
}

.docsig-modal-content {
    background-color: #fff;
    width: 100%;
    max-width: 750px;
    border-radius: 8px;
    box-shadow: 0 4px 25px rgba(0,0,0,0.3);
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}

.docsig-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #dee2e6;
    background: #f8f9fa;
    border-radius: 8px 8px 0 0;
}

.docsig-modal-header h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
}

.docsig-modal-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
}

/* Sections */
.docsig-section {
    margin-bottom: 25px;
}

.docsig-section h4 {
    font-size: 14px;
    font-weight: 600;
    color: #495057;
    margin: 0 0 12px 0;
    padding-bottom: 8px;
    border-bottom: 1px solid #e9ecef;
}

.docsig-section h4 .fa {
    margin-right: 8px;
    color: #6c757d;
}

/* PDF info display */
.docsig-pdf-info {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
}

.docsig-pdf-details {
    flex: 1;
}

/* Signers list - enhanced */
.docsig-signers-list {
    border: 1px solid #dee2e6;
    border-radius: 6px;
    background: #fff;
    max-height: 300px;
    overflow-y: auto;
    margin-bottom: 15px;
}

.docsig-signer-item {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    border-bottom: 1px solid #e9ecef;
    gap: 12px;
    position: relative;
}

.docsig-signer-item:last-child {
    border-bottom: none;
}

.docsig-signer-item:hover {
    background: #f8f9fa;
}

.docsig-signer-checkbox {
    flex-shrink: 0;
}

.docsig-signer-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.docsig-signer-info {
    flex: 1;
    min-width: 150px;
}

.docsig-signer-info label {
    cursor: pointer;
    display: block;
    margin: 0;
}

.docsig-signer-editable {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.docsig-signer-editable .docsig-field {
    flex: 1;
    min-width: 140px;
}

.docsig-signer-editable .docsig-field label {
    display: block;
    font-size: 11px;
    color: #6c757d;
    margin-bottom: 3px;
}

.docsig-signer-editable .docsig-field input {
    width: 100%;
    padding: 5px 8px;
    font-size: 13px;
    border: 1px solid #ced4da;
    border-radius: 4px;
}

.docsig-signer-editable .docsig-field input:focus {
    border-color: #80bdff;
    outline: none;
    box-shadow: 0 0 0 2px rgba(0,123,255,0.1);
}

/* Remove signer button */
.docsig-remove-signer {
    position: absolute;
    right: 8px;
    top: 8px;
    background: none;
    border: none;
    color: #dc3545;
    cursor: pointer;
    padding: 4px 8px;
    font-size: 14px;
    opacity: 0.6;
}

.docsig-remove-signer:hover {
    opacity: 1;
}

/* Badge styles */
.badge {
    display: inline-block;
    padding: 2px 8px;
    font-size: 10px;
    font-weight: 600;
    border-radius: 10px;
    text-transform: uppercase;
    margin-left: 5px;
}

.badge-secondary {
    background: #6c757d;
    color: #fff;
}

.badge-info {
    background: #17a2b8;
    color: #fff;
}

/* Search section */
.docsig-search-section {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px dashed #dee2e6;
}

.docsig-search-section label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #495057;
    margin-bottom: 8px;
}

.docsig-contact-search-wrapper {
    position: relative;
}

.docsig-search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #ced4da;
    border-top: none;
    border-radius: 0 0 6px 6px;
    max-height: 250px;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.docsig-search-item {
    padding: 10px 15px;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
}

.docsig-search-item:last-child {
    border-bottom: none;
}

.docsig-search-item:hover {
    background: #f8f9fa;
}

.docsig-search-item.docsig-no-results {
    color: #6c757d;
    font-style: italic;
    cursor: default;
}

.docsig-search-item.docsig-no-results:hover {
    background: transparent;
}

.docsig-search-item-name {
    font-size: 14px;
}

.docsig-search-item-email {
    color: #6c757d;
}

/* New contact section */
.docsig-new-contact-section {
    margin-top: 10px;
}

.docsig-new-contact-section > a {
    color: #007bff;
    text-decoration: none;
}

.docsig-new-contact-section > a:hover {
    text-decoration: underline;
}

.docsig-new-contact-form {
    margin-top: 15px;
    padding: 15px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
}

.docsig-form-grid {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.docsig-form-row {
    display: flex;
    gap: 10px;
}

.docsig-form-row input,
.docsig-form-row select {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
}

.docsig-form-actions {
    margin-top: 12px;
    display: flex;
    gap: 10px;
}

/* Modal actions footer */
.docsig-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #dee2e6;
}

.docsig-modal-actions .button {
    padding: 10px 20px;
    font-size: 14px;
}

.docsig-modal-actions .button-primary {
    background: #007bff;
    color: #fff;
    border: none;
}

.docsig-modal-actions .button-primary:hover {
    background: #0056b3;
}

.docsig-modal-actions .button-primary:disabled {
    background: #6c757d;
    cursor: not-allowed;
}

.docsig-modal-actions .button-cancel {
    background: #f8f9fa;
    border: 1px solid #ced4da;
}

/* Responsive */
@media (max-width: 768px) {
    .docsig-modal-content {
        width: 95%;
        margin: 10px auto;
    }

    .docsig-signer-item {
        flex-wrap: wrap;
    }

    .docsig-signer-editable {
        width: 100%;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed #e9ecef;
    }

    .docsig-form-row {
        flex-direction: column;
    }

    .docsig-add-signer-row {
        flex-direction: column;
    }

    .docsig-public-container {
        margin: 20px;
        padding: 20px;
    }
}

/* ============================================================
   ENHANCED SIGNER CARD STYLES
   ============================================================ */

/* Signers group headers */
.docsig-signers-group {
    margin-bottom: 0;
}

.docsig-signers-group:not(:last-child) {
    border-bottom: 2px solid #e9ecef;
}

.docsig-group-header {
    padding: 10px 15px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.docsig-group-header .fa {
    margin-right: 8px;
}

/* Enhanced signer item */
.docsig-signer-item {
    display: grid;
    grid-template-columns: 40px 1fr auto;
    grid-template-rows: auto auto;
    gap: 8px 12px;
    padding: 14px 16px;
    border-bottom: 1px solid #f0f0f0;
    transition: all 0.2s ease;
    position: relative;
}

.docsig-signer-item:last-child {
    border-bottom: none;
}

.docsig-signer-item:hover {
    background: linear-gradient(to right, #f8f9ff, #fff);
}

.docsig-signer-item.docsig-signer-no-email {
    opacity: 0.6;
    background: #fef3f2;
}

/* Checkbox/select area */
.docsig-signer-select {
    grid-row: 1 / 3;
    display: flex;
    align-items: center;
    justify-content: center;
}

.docsig-signer-select input[type="checkbox"] {
    width: 22px;
    height: 22px;
    cursor: pointer;
    accent-color: #667eea;
}

/* Main info area */
.docsig-signer-main {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.docsig-signer-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    margin: 0;
}

.docsig-signer-icon {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e9ecef;
    border-radius: 50%;
    font-size: 12px;
    color: #495057;
}

.docsig-signer-name {
    font-weight: 600;
    font-size: 14px;
    color: #212529;
}

.docsig-signer-role {
    font-size: 12px;
    color: #6c757d;
    background: #f8f9fa;
    padding: 2px 8px;
    border-radius: 4px;
}

/* Badge enhancements */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    font-size: 10px;
    font-weight: 600;
    border-radius: 12px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.badge-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff;
}

.badge-success {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: #fff;
}

.badge-secondary {
    background: #6c757d;
    color: #fff;
}

.badge-info {
    background: linear-gradient(135deg, #17a2b8, #007bff);
    color: #fff;
}

.badge-warning {
    background: linear-gradient(135deg, #ffc107, #fd7e14);
    color: #212529;
}

/* Editable fields area */
.docsig-signer-fields {
    grid-column: 2 / 4;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.docsig-field-group {
    display: flex;
    align-items: center;
    gap: 6px;
    flex: 1;
    min-width: 180px;
}

.docsig-field-icon {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    border-radius: 4px;
    font-size: 12px;
    color: #6c757d;
    flex-shrink: 0;
}

.docsig-input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    font-size: 13px;
    transition: all 0.2s ease;
}

.docsig-input:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}

.docsig-input::placeholder {
    color: #adb5bd;
}

/* Warning for missing email */
.docsig-signer-warning {
    grid-column: 1 / 4;
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    background: #fff3cd;
    color: #856404;
    font-size: 11px;
    border-radius: 4px;
    margin-top: 4px;
}

.docsig-signer-warning .fa {
    color: #ffc107;
}

/* No contacts message */
.docsig-no-contacts {
    padding: 30px;
    text-align: center;
    color: #6c757d;
    font-style: italic;
}

.docsig-no-contacts .fa {
    margin-right: 8px;
    color: #17a2b8;
}

/* Remove signer button (for new contacts) */
.docsig-remove-signer {
    position: absolute;
    right: 10px;
    top: 10px;
    background: #fff;
    border: 1px solid #dc3545;
    color: #dc3545;
    cursor: pointer;
    padding: 4px 8px;
    font-size: 12px;
    border-radius: 4px;
    opacity: 0;
    transition: all 0.2s ease;
}

.docsig-signer-item:hover .docsig-remove-signer {
    opacity: 1;
}

.docsig-remove-signer:hover {
    background: #dc3545;
    color: #fff;
}

/* Enhanced PDF display */
.docsig-document-section .docsig-pdf-info {
    background: linear-gradient(135deg, #fff5f5, #fff);
    border: 1px solid #f5c6cb;
    border-left: 4px solid #dc3545;
}

.docsig-pdf-info .fa-file-pdf-o {
    font-size: 32px;
}

/* Mode section styling */
.docsig-mode-section select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    font-size: 14px;
    background: #fff;
    cursor: pointer;
}

.docsig-mode-section select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}

/* Custom message textarea */
#docsig-message {
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 12px;
    font-size: 14px;
    resize: vertical;
    min-height: 80px;
}

#docsig-message:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}

/* Enhanced action buttons */
.docsig-modal-actions {
    background: #f8f9fa;
    margin: 0 -20px -20px;
    padding: 15px 20px;
    border-radius: 0 0 8px 8px;
    border-top: 1px solid #e9ecef;
}

.docsig-modal-actions .button {
    padding: 12px 24px;
    font-size: 14px;
    font-weight: 500;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.docsig-modal-actions .button-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff;
    border: none;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
}

.docsig-modal-actions .button-primary:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.docsig-modal-actions .button-primary:disabled {
    background: #adb5bd;
    box-shadow: none;
    cursor: not-allowed;
}

/* Search enhancements */
#docsig-contact-search {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    font-size: 14px;
}

#docsig-contact-search:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}

/* New contact form enhancements */
.docsig-new-contact-section > a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 0;
    color: #667eea;
    font-weight: 500;
    transition: color 0.2s;
}

.docsig-new-contact-section > a:hover {
    color: #764ba2;
}

.docsig-new-contact-form {
    background: linear-gradient(135deg, #f8f9ff, #fff);
    border: 1px solid #e0e5ff;
    animation: slideDown 0.2s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.docsig-form-row input,
.docsig-form-row select {
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    transition: all 0.2s;
}

.docsig-form-row input:focus,
.docsig-form-row select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}

.docsig-form-actions .button {
    padding: 8px 16px;
    border-radius: 6px;
}
