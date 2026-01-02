<?php
/* Copyright (C) 2026 Document Signature Module
 * Library functions
 */

/**
 * Prepare admin pages header
 *
 * @return array
 */
function docsig_admin_prepare_head()
{
	global $langs, $conf;

	$langs->load("docsig@docsig");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/docsig/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath("/docsig/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'docsig');

	return $head;
}

/**
 * Get envelope status badge
 *
 * @param int $status Status code
 * @return string HTML
 */
function docsig_get_status_badge($status)
{
	$badges = array(
		0 => '<span class="badge badge-status0">Draft</span>',
		1 => '<span class="badge badge-status4">Sent</span>',
		2 => '<span class="badge badge-status3">In Progress</span>',
		3 => '<span class="badge badge-status6">Completed</span>',
		4 => '<span class="badge badge-status9">Cancelled</span>',
		5 => '<span class="badge badge-status8">Expired</span>',
	);

	return isset($badges[$status]) ? $badges[$status] : '';
}

/**
 * Get signature status badge
 *
 * @param int $status Status code
 * @return string HTML
 */
function docsig_get_signature_status_badge($status)
{
	$badges = array(
		0 => '<span class="badge badge-status0">Pending</span>',
		1 => '<span class="badge badge-status3">Opened</span>',
		2 => '<span class="badge badge-status4">Authenticated</span>',
		3 => '<span class="badge badge-status6">Signed</span>',
		4 => '<span class="badge badge-status8">Failed</span>',
		5 => '<span class="badge badge-status9">Cancelled</span>',
		6 => '<span class="badge badge-status8">Expired</span>',
	);

	return isset($badges[$status]) ? $badges[$status] : '';
}

/**
 * Format event type for display
 *
 * @param string $eventType Event type
 * @return string
 */
function docsig_format_event_type($eventType)
{
	$map = array(
		'envelope_created' => 'Envelope Created',
		'envelope_sent' => 'Envelope Sent',
		'envelope_cancelled' => 'Envelope Cancelled',
		'link_opened' => 'Link Opened',
		'otp_requested' => 'OTP Requested',
		'otp_sent' => 'OTP Sent',
		'otp_validated' => 'OTP Validated',
		'otp_failed' => 'OTP Failed',
		'signature_completed' => 'Signature Completed',
		'document_signed' => 'Document Signed',
		'certificate_generated' => 'Certificate Generated',
		'email_sent' => 'Email Sent',
	);

	return isset($map[$eventType]) ? $map[$eventType] : $eventType;
}
