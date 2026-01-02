<?php
/* Copyright (C) 2026 Document Signature Module
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       class/docsigcertificate.class.php
 * \ingroup    docsig
 * \brief      Compliance certificate generation
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

/**
 * DocsigCertificate class
 */
class DocsigCertificate extends CommonObject
{
	/**
	 * @var string ID to identify managed object
	 */
	public $element = 'docsigcertificate';

	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public $table_element = 'docsig_certificate';

	/**
	 * @var int ID
	 */
	public $id;

	public $fk_envelope;
	public $certificate_path;
	public $certificate_json;
	public $tsa_url;
	public $tsa_serial;
	public $tsa_response;
	public $system_signature;
	public $date_creation;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Create certificate object into database
	 *
	 * @param  User $user      User that create
	 * @param  int  $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int             <0 if KO, Id of created object if OK
	 */
	public function create($user, $notrigger = 0)
	{
		global $conf;

		$now = dol_now();

		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
		$sql .= " fk_envelope, certificate_path, certificate_json,";
		$sql .= " tsa_url, tsa_serial, tsa_response,";
		$sql .= " system_signature, entity, date_creation";
		$sql .= ") VALUES (";
		$sql .= " ".(int)$this->fk_envelope.",";
		$sql .= " ".($this->certificate_path ? "'".$this->db->escape($this->certificate_path)."'" : "NULL").",";
		$sql .= " ".($this->certificate_json ? "'".$this->db->escape($this->certificate_json)."'" : "NULL").",";
		$sql .= " ".($this->tsa_url ? "'".$this->db->escape($this->tsa_url)."'" : "NULL").",";
		$sql .= " ".($this->tsa_serial ? "'".$this->db->escape($this->tsa_serial)."'" : "NULL").",";
		$sql .= " ".($this->tsa_response ? "'".$this->db->escape($this->tsa_response)."'" : "NULL").",";
		$sql .= " ".($this->system_signature ? "'".$this->db->escape($this->system_signature)."'" : "NULL").",";
		$sql .= " ".$conf->entity.",";
		$sql .= " '".$this->db->idate($now)."'";
		$sql .= ")";

		$result = $this->db->query($sql);
		if ($result) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
			return $this->id;
		}

		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Generate compliance certificate PDF
	 *
	 * @param DocsigEnvelope $envelope Envelope object
	 * @return int <0 if KO, >0 if OK
	 */
	public function generateCertificate($envelope)
	{
		global $conf, $langs;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php';

		// Determine output path - same directory as signed PDF
		$signedPath = DOL_DATA_ROOT.'/'.$envelope->signed_document_path;
		$certificatePath = preg_replace('/\.pdf$/i', '_certificate.pdf', $signedPath);
		$certificateRelPath = str_replace(DOL_DATA_ROOT.'/', '', $certificatePath);

		// Create PDF
		$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
		
		// Document information
		$pdf->SetCreator('Docsig - Dolibarr Document Signature Module');
		$pdf->SetAuthor('Docsig System');
		$pdf->SetTitle('Certificate of Compliance - '.$envelope->ref);
		$pdf->SetSubject('Digital Signature Compliance Certificate');

		// Remove default header/footer
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);

		// Set margins
		$pdf->SetMargins(20, 20, 20);
		$pdf->SetAutoPageBreak(true, 20);

		// Add page
		$pdf->AddPage();

		// Logo/Header
		$pdf->SetFont('helvetica', 'B', 20);
		$pdf->SetTextColor(0, 51, 102);
		$pdf->Cell(0, 15, 'CERTIFICATE OF COMPLIANCE', 0, 1, 'C');
		
		$pdf->SetFont('helvetica', '', 10);
		$pdf->SetTextColor(100, 100, 100);
		$pdf->Cell(0, 5, 'Digital Signature Evidence', 0, 1, 'C');
		$pdf->Ln(5);

		// Line
		$pdf->SetDrawColor(0, 51, 102);
		$pdf->SetLineWidth(0.5);
		$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
		$pdf->Ln(10);

		// Envelope Information
		$pdf->SetFont('helvetica', 'B', 12);
		$pdf->SetTextColor(0, 51, 102);
		$pdf->Cell(0, 8, 'Envelope Information', 0, 1, 'L');
		$pdf->Ln(2);

		$pdf->SetFont('helvetica', '', 10);
		$pdf->SetTextColor(0, 0, 0);

		$this->addRow($pdf, 'Reference:', $envelope->ref);
		$this->addRow($pdf, 'Document:', $envelope->document_name);
		$this->addRow($pdf, 'Element Type:', ucfirst($envelope->element_type));
		$this->addRow($pdf, 'Element ID:', $envelope->element_id);
		$this->addRow($pdf, 'Created:', dol_print_date($envelope->date_creation, 'dayhour'));
		$this->addRow($pdf, 'Completed:', dol_print_date($envelope->last_activity, 'dayhour'));
		$this->addRow($pdf, 'Signature Mode:', ucfirst($envelope->signature_mode));
		$pdf->Ln(5);

		// Document Hashes
		$pdf->SetFont('helvetica', 'B', 12);
		$pdf->SetTextColor(0, 51, 102);
		$pdf->Cell(0, 8, 'Document Integrity', 0, 1, 'L');
		$pdf->Ln(2);

		$pdf->SetFont('courier', '', 8);
		$pdf->SetTextColor(0, 0, 0);

		$this->addRow($pdf, 'Original Hash (SHA-256):', $envelope->document_hash);
		$this->addRow($pdf, 'Signed Hash (SHA-256):', $envelope->signed_document_hash);
		$pdf->Ln(5);

		// Signatures
		$pdf->SetFont('helvetica', 'B', 12);
		$pdf->SetTextColor(0, 51, 102);
		$pdf->Cell(0, 8, 'Signatures ('.$envelope->nb_signed.'/'.$envelope->nb_signers.')', 0, 1, 'L');
		$pdf->Ln(2);

		// Get all signatures
		$sql = "SELECT * FROM ".MAIN_DB_PREFIX."docsig_signature";
		$sql .= " WHERE fk_envelope = ".(int)$envelope->id;
		$sql .= " ORDER BY signature_date ASC";

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			
			while ($i < $num) {
				$obj = $this->db->fetch_object($resql);
				
				$pdf->SetFont('helvetica', 'B', 10);
				$pdf->SetTextColor(0, 51, 102);
				$pdf->Cell(0, 6, ($i + 1).'. '.$obj->signer_name, 0, 1, 'L');
				
				$pdf->SetFont('helvetica', '', 9);
				$pdf->SetTextColor(0, 0, 0);
				$this->addRow($pdf, '   Email:', $obj->signer_email);
				if (!empty($obj->signer_dni)) {
					$this->addRow($pdf, '   NIF/CIF/NIE:', $obj->signer_dni);
				}
				$this->addRow($pdf, '   Status:', $this->getStatusLabel($obj->status));
				if ($obj->status == 3) { // STATUS_SIGNED
					$this->addRow($pdf, '   Signed:', dol_print_date($this->db->jdate($obj->signature_date), 'dayhour'));
					$this->addRow($pdf, '   IP Address:', $obj->signature_ip);
				}
				$pdf->Ln(3);
				
				$i++;
			}
		}

		// Audit Trail Summary
		$pdf->SetFont('helvetica', 'B', 12);
		$pdf->SetTextColor(0, 51, 102);
		$pdf->Cell(0, 8, 'Audit Trail', 0, 1, 'L');
		$pdf->Ln(2);

		$sql = "SELECT * FROM ".MAIN_DB_PREFIX."docsig_audit_trail";
		$sql .= " WHERE fk_envelope = ".(int)$envelope->id;
		$sql .= " ORDER BY rowid ASC";

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			
			$pdf->SetFont('helvetica', '', 8);
			$pdf->SetTextColor(0, 0, 0);
			
			// Table header
			$pdf->SetFillColor(240, 240, 240);
			$pdf->Cell(40, 6, 'Date/Time', 1, 0, 'C', true);
			$pdf->Cell(50, 6, 'Event', 1, 0, 'C', true);
			$pdf->Cell(30, 6, 'IP Address', 1, 0, 'C', true);
			$pdf->Cell(50, 6, 'Hash', 1, 1, 'C', true);
			
			while ($i < $num) {
				$obj = $this->db->fetch_object($resql);
				
				$pdf->Cell(40, 6, dol_print_date($this->db->jdate($obj->event_date), 'dayhour'), 1, 0, 'L');
				$pdf->Cell(50, 6, $this->formatEventType($obj->event_type), 1, 0, 'L');
				$pdf->Cell(30, 6, $obj->ip_address ?: '-', 1, 0, 'L');
				$pdf->Cell(50, 6, substr($obj->event_hash, 0, 12).'...', 1, 1, 'L');
				
				$i++;
			}
		}
		$pdf->Ln(5);

		// TSA Information (if available)
		if (!empty($envelope->tsa_timestamp)) {
			$pdf->SetFont('helvetica', 'B', 12);
			$pdf->SetTextColor(0, 51, 102);
			$pdf->Cell(0, 8, 'Timestamp Authority (TSA)', 0, 1, 'L');
			$pdf->Ln(2);

			$pdf->SetFont('helvetica', '', 9);
			$pdf->SetTextColor(0, 0, 0);
			$this->addRow($pdf, 'TSA Server:', $conf->global->DOCSIG_TSA_URL ?? 'N/A');
			$this->addRow($pdf, 'Timestamp:', dol_print_date($envelope->tsa_timestamp, 'dayhour'));
			$pdf->Ln(5);
		}

		// System Signature
		$pdf->SetFont('helvetica', 'B', 12);
		$pdf->SetTextColor(0, 51, 102);
		$pdf->Cell(0, 8, 'System Certification', 0, 1, 'L');
		$pdf->Ln(2);

		$pdf->SetFont('helvetica', '', 9);
		$pdf->SetTextColor(0, 0, 0);
		$this->addRow($pdf, 'Certified by:', 'Docsig Document Signature Module');
		$this->addRow($pdf, 'Certificate Date:', dol_print_date(dol_now(), 'dayhour'));
		$this->addRow($pdf, 'System Entity:', $conf->entity);
		$pdf->Ln(5);

		// Compliance Statement
		$pdf->SetFont('helvetica', 'I', 8);
		$pdf->SetTextColor(100, 100, 100);
		$pdf->MultiCell(0, 5, 'This certificate attests that the referenced document was signed electronically in compliance with applicable digital signature standards (PAdES-BES, RFC 3161). All signatures were collected with double authentication (identity document + email OTP) and are recorded in an immutable audit trail. The integrity of this certificate can be verified through the hash chain recorded in the audit trail.', 0, 'L');

		// Footer
		$pdf->Ln(10);
		$pdf->SetDrawColor(200, 200, 200);
		$pdf->SetLineWidth(0.2);
		$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
		$pdf->Ln(3);

		$pdf->SetFont('courier', '', 7);
		$pdf->SetTextColor(150, 150, 150);
		$pdf->Cell(0, 4, 'Certificate ID: CERT-'.$envelope->ref.'-'.date('YmdHis'), 0, 1, 'C');
		$pdf->Cell(0, 4, 'Generated by Docsig v1.0.0 - '.dol_print_date(dol_now(), 'dayhourtext'), 0, 1, 'C');

		// Output PDF
		$pdf->Output($certificatePath, 'F');

		// Store certificate data
		$this->fk_envelope = $envelope->id;
		$this->certificate_path = $certificateRelPath;
		
		$certificateData = array(
			'envelope_ref' => $envelope->ref,
			'document_name' => $envelope->document_name,
			'document_hash' => $envelope->document_hash,
			'signed_hash' => $envelope->signed_document_hash,
			'nb_signatures' => $envelope->nb_signed,
			'generated_date' => dol_now(),
		);
		
		$this->certificate_json = json_encode($certificateData);
		$this->tsa_url = $conf->global->DOCSIG_TSA_URL ?? '';
		
		// Create database record
		global $user;
		$result = $this->create($user);
		
		if ($result > 0) {
			// Update envelope with certificate path
			$envelope->certificate_path = $certificateRelPath;
			$envelope->update($user);
			
			return 1;
		}

		return -1;
	}

	/**
	 * Add a row to PDF
	 */
	private function addRow($pdf, $label, $value)
	{
		$pdf->Cell(60, 5, $label, 0, 0, 'L');
		$pdf->Cell(0, 5, $value, 0, 1, 'L');
	}

	/**
	 * Get status label
	 */
	private function getStatusLabel($status)
	{
		$labels = array(
			0 => 'Pending',
			1 => 'Sent',
			2 => 'Opened',
			3 => 'Signed',
			4 => 'Failed',
			5 => 'Cancelled',
		);
		
		return $labels[$status] ?? 'Unknown';
	}

	/**
	 * Format event type
	 */
	private function formatEventType($type)
	{
		return ucwords(str_replace('_', ' ', $type));
	}
}
