<?php
/**
 * Configuration file for PuertaSevilla Rental Contract PDF Template
 * 
 * This file contains configuration constants that can be modified
 * to customize the appearance and content of the generated PDF
 * 
 * @file        pdf_puerta_sevilla_config.php
 * @category    Contracts
 * @package     PuertaSevilla
 * @version     1.0.0
 */

// ============================================================================
// FONT CONFIGURATION
// ============================================================================

// Main title font size (relative to default)
define('PS_PDF_TITLE_FONT_SIZE_OFFSET', 3);

// Section header font size (relative to default)
define('PS_PDF_SECTION_FONT_SIZE_OFFSET', 0);

// Body text font size (relative to default)  
define('PS_PDF_BODY_FONT_SIZE_OFFSET', -1);

// Small text font size (relative to default)
define('PS_PDF_SMALL_FONT_SIZE_OFFSET', -2);

// ============================================================================
// SPACING CONFIGURATION
// ============================================================================

// Space between sections (in mm)
define('PS_PDF_SECTION_SPACING', 5);

// Space between lines in a section (in mm)
define('PS_PDF_LINE_SPACING', 3);

// Space between paragraphs (in mm)
define('PS_PDF_PARAGRAPH_SPACING', 4);

// ============================================================================
// CONTENT CONFIGURATION
// ============================================================================

// Show property description
define('PS_PDF_SHOW_PROPERTY_DESC', true);

// Show duration section
define('PS_PDF_SHOW_DURATION', true);

// Show rent section
define('PS_PDF_SHOW_RENT', true);

// Show general conditions
define('PS_PDF_SHOW_CONDITIONS', true);

// Show signature boxes
define('PS_PDF_SHOW_SIGNATURES', true);

// Show notes section
define('PS_PDF_SHOW_NOTES', true);

// ============================================================================
// SIGNATURE CONFIGURATION
// ============================================================================

// Height of signature boxes (in mm)
define('PS_PDF_SIGNATURE_HEIGHT', 20);

// Width of signature boxes (in mm)
define('PS_PDF_SIGNATURE_WIDTH_RATIO', 0.45); // 45% of available width

// Label for landlord signature
define('PS_PDF_LANDLORD_SIGNATURE_LABEL', 'ARRENDADOR');

// Label for tenant signature  
define('PS_PDF_TENANT_SIGNATURE_LABEL', 'ARRENDATARIO');

// ============================================================================
// COLOR CONFIGURATION
// ============================================================================

// Title text color (RGB)
define('PS_PDF_TITLE_COLOR_R', 0);
define('PS_PDF_TITLE_COLOR_G', 0);
define('PS_PDF_TITLE_COLOR_B', 60);

// Section header color (RGB)
define('PS_PDF_SECTION_COLOR_R', 0);
define('PS_PDF_SECTION_COLOR_G', 0);
define('PS_PDF_SECTION_COLOR_B', 60);

// Regular text color (RGB)
define('PS_PDF_TEXT_COLOR_R', 0);
define('PS_PDF_TEXT_COLOR_G', 0);
define('PS_PDF_TEXT_COLOR_B', 0);

// Border/Line color (RGB)
define('PS_PDF_BORDER_COLOR_R', 128);
define('PS_PDF_BORDER_COLOR_G', 128);
define('PS_PDF_BORDER_COLOR_B', 128);

// ============================================================================
// LAYOUT CONFIGURATION
// ============================================================================

// Left margin for content (in mm)
define('PS_PDF_CONTENT_LEFT_MARGIN', 5);

// Right margin for content (in mm)
define('PS_PDF_CONTENT_RIGHT_MARGIN', 5);

// Width of left column for two-column layouts (ratio 0-1)
define('PS_PDF_TWO_COL_LEFT_WIDTH', 0.48);

// ============================================================================
// CONDITIONAL SECTIONS
// ============================================================================

// Show company logo
define('PS_PDF_SHOW_LOGO', true);

// Show customer code
define('PS_PDF_SHOW_CUSTOMER_CODE', true);

// Show address section
define('PS_PDF_SHOW_ADDRESS', true);

// Show contact details
define('PS_PDF_SHOW_CONTACT_DETAILS', true);

// ============================================================================
// REQUIRED FIELDS
// ============================================================================

// These fields must be present for PDF generation
$PS_PDF_REQUIRED_FIELDS = array(
    'contract' => array(
        'ref',              // Contract reference
        'date_contrat',     // Contract date
    ),
    'lines' => array(
        // At least one line is required
    ),
    'thirdparty' => array(
        'name',             // Tenant name
    ),
    'company' => array(
        'name',             // Company/Landlord name
    )
);

// ============================================================================
// CUSTOM TEXT
// ============================================================================

// Text to display for unspecified values
define('PS_PDF_UNKNOWN_TEXT', 'Unknown');

// Default property description if not specified
define('PS_PDF_DEFAULT_PROPERTY_DESC', 'Residential property for rent');

// Default currency symbol
define('PS_PDF_CURRENCY_SYMBOL', '€');

// Date format for PDFs (use Dolibarr format codes)
define('PS_PDF_DATE_FORMAT', 'day');

// ============================================================================
// ADVANCED CONFIGURATION
// ============================================================================

// Enable debug mode (logs to dolibarr.log)
define('PS_PDF_DEBUG_MODE', false);

// Auto-page break on content overflow
define('PS_PDF_AUTO_PAGEBREAK', true);

// Include page numbers
define('PS_PDF_SHOW_PAGE_NUMBERS', true);

// Include footer text
define('PS_PDF_SHOW_FOOTER', true);

// Include header on each page
define('PS_PDF_REPEAT_HEADER', true);

// Maximum height for notes before page break (in mm)
define('PS_PDF_MAX_NOTES_HEIGHT', 40);
