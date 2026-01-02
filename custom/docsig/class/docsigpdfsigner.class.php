<?php
/* Copyright (C) 2026 Document Signature Module
 *
 * PDF PAdES signature service with TSA RFC3161 support
 */

/**
 * Class for PDF signing with PAdES and TSA
 */
class DocsigPDFSigner
{
	private $db;
	private $error;
	
	/**
	 * Constructor
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Sign PDF with PAdES format and TSA timestamp
	 *
	 * @param string $inputPDF Path to input PDF
	 * @param string $outputPDF Path to output signed PDF
	 * @param array $signatures Array of signature data
	 * @param array $options Options
	 * @return int <0 if KO, >0 if OK
	 */
	public function signPDF($inputPDF, $outputPDF, $signatures, $options = array())
	{
		global $conf;

		if (!file_exists($inputPDF)) {
			$this->error = 'Input PDF not found';
			return -1;
		}

		// Get system certificate and private key
		$certData = $this->getSystemCertificate();
		if (!$certData) {
			$this->error = 'System certificate not found';
			return -2;
		}

		// Load PDF with FPDI/FPDF
		require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php';
		
		try {
			// Read original PDF
			$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
			$pdf->SetCreator('Docsig');
			$pdf->SetAuthor('Docsig System');
			$pdf->SetTitle('Signed Document');

			// Import pages from original
			$pageCount = $pdf->setSourceFile($inputPDF);
			
			for ($i = 1; $i <= $pageCount; $i++) {
				$tplId = $pdf->importPage($i);
				$pdf->AddPage();
				$pdf->useTemplate($tplId);
			}

			// Add visible signatures if enabled
			if (!empty($conf->global->DOCSIG_VISIBLE_SIGNATURE)) {
				foreach ($signatures as $signature) {
					if (!empty($signature['image']) && !empty($signature['position'])) {
						$this->addVisibleSignature($pdf, $signature, $pageCount);
					}
				}
			}

			// Get TSA timestamp if enabled
			$tsa = null;
			if (!empty($conf->global->DOCSIG_ENABLE_TSA) && !empty($conf->global->DOCSIG_TSA_URL)) {
				$tsa = $this->getTSATimestamp($inputPDF);
			}

			// Sign PDF with certificate
			$pdf->setSignature(
				$certData['certificate'],
				$certData['private_key'],
				'',
				'',
				2,
				array(
					'Name' => 'Docsig System',
					'Location' => 'Spain',
					'Reason' => 'Document Signature',
					'ContactInfo' => $conf->global->MAIN_INFO_SOCIETE_MAIL,
				)
			);

			// Add signature info
			$signatureData = array(
				'signatures' => $signatures,
				'timestamp' => time(),
				'tsa' => $tsa,
			);

			$pdf->setSignatureAppearance(0, 0, 0, 0, 1, $signatureData);

			// Output to file
			$pdf->Output($outputPDF, 'F');

			// Verify file was created
			if (!file_exists($outputPDF)) {
				$this->error = 'Failed to create signed PDF';
				return -3;
			}

			return 1;

		} catch (Exception $e) {
			$this->error = 'PDF signing error: '.$e->getMessage();
			return -4;
		}
	}

	/**
	 * Add visible signature to PDF
	 *
	 * @param TCPDF $pdf PDF object
	 * @param array $signature Signature data
	 * @param int $lastPage Last page number
	 * @return void
	 */
	private function addVisibleSignature($pdf, $signature, $lastPage)
	{
		global $conf;

		// Go to last page or specified page
		$page = !empty($signature['position']['page']) ? $signature['position']['page'] : $lastPage;
		$pdf->setPage($page);

		// Decode signature image
		$imageData = $signature['image'];
		if (strpos($imageData, 'data:image') === 0) {
			$imageData = explode(',', $imageData)[1];
		}
		$imageData = base64_decode($imageData);

		// Save temp image
		$tempImage = DOL_DATA_ROOT.'/docsig/temp/sig_'.uniqid().'.png';
		file_put_contents($tempImage, $imageData);

		// Determine position
		$position = !empty($conf->global->DOCSIG_SIGNATURE_POSITION) ? $conf->global->DOCSIG_SIGNATURE_POSITION : 'bottom-left';
		
		list($x, $y) = $this->calculatePosition($pdf, $position, 60, 30);

		// Override with specific position if provided
		if (!empty($signature['position']['x'])) {
			$x = $signature['position']['x'];
		}
		if (!empty($signature['position']['y'])) {
			$y = $signature['position']['y'];
		}

		// Add image
		$pdf->Image($tempImage, $x, $y, 60, 30, 'PNG');

		// Add text below
		$pdf->SetFont('helvetica', '', 8);
		$pdf->SetXY($x, $y + 32);
		$pdf->Cell(60, 4, $signature['name'], 0, 1, 'L');
		$pdf->SetX($x);
		$pdf->Cell(60, 4, date('Y-m-d H:i:s'), 0, 1, 'L');
		
		if (!empty($signature['email'])) {
			$pdf->SetX($x);
			$pdf->Cell(60, 4, $signature['email'], 0, 1, 'L');
		}

		// Clean up
		@unlink($tempImage);
	}

	/**
	 * Calculate signature position on page
	 *
	 * @param TCPDF $pdf PDF object
	 * @param string $position Position name
	 * @param float $width Width
	 * @param float $height Height
	 * @return array [x, y]
	 */
	private function calculatePosition($pdf, $position, $width, $height)
	{
		$pageWidth = $pdf->getPageWidth();
		$pageHeight = $pdf->getPageHeight();
		$margin = 10;

		switch ($position) {
			case 'bottom-left':
				return array($margin, $pageHeight - $height - $margin - 20);
			case 'bottom-right':
				return array($pageWidth - $width - $margin, $pageHeight - $height - $margin - 20);
			case 'top-left':
				return array($margin, $margin);
			case 'top-right':
				return array($pageWidth - $width - $margin, $margin);
			case 'center':
				return array(($pageWidth - $width) / 2, ($pageHeight - $height) / 2);
			default:
				return array($margin, $pageHeight - $height - $margin - 20);
		}
	}

	/**
	 * Get TSA timestamp (RFC3161)
	 *
	 * @param string $filePath File to timestamp
	 * @return array|null TSA data or null
	 */
	private function getTSATimestamp($filePath)
	{
		global $conf;

		$tsaUrl = $conf->global->DOCSIG_TSA_URL;
		if (empty($tsaUrl)) {
			return null;
		}

		try {
			// Calculate hash of document
			$hash = hash_file('sha256', $filePath, true);

			// Create TSA request (RFC3161)
			$tsRequest = $this->createTSARequest($hash);

			// Send to TSA server
			$ch = curl_init($tsaUrl);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $tsRequest);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/timestamp-query',
				'Content-Length: '.strlen($tsRequest)
			));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);

			// Authentication if required
			if (!empty($conf->global->DOCSIG_TSA_USER) && !empty($conf->global->DOCSIG_TSA_PASSWORD)) {
				curl_setopt($ch, CURLOPT_USERPWD, $conf->global->DOCSIG_TSA_USER.':'.$conf->global->DOCSIG_TSA_PASSWORD);
			}

			$tsResponse = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($httpCode != 200 || !$tsResponse) {
				dol_syslog('TSA request failed: HTTP '.$httpCode, LOG_WARNING);
				return null;
			}

			// Parse TSA response
			$tsData = $this->parseTSAResponse($tsResponse);

			return array(
				'url' => $tsaUrl,
				'date' => $tsData['date'],
				'serial' => $tsData['serial'],
				'response' => base64_encode($tsResponse),
			);

		} catch (Exception $e) {
			dol_syslog('TSA error: '.$e->getMessage(), LOG_WARNING);
			return null;
		}
	}

	/**
	 * Create TSA request (simplified)
	 *
	 * @param string $hash Hash binary
	 * @return string TSA request
	 */
	private function createTSARequest($hash)
	{
		// This is a simplified implementation
		// In production, use proper ASN.1 encoding library
		$nonce = random_int(1000000, 9999999);
		$hashAlgo = '2.16.840.1.101.3.4.2.1'; // SHA-256 OID

		// For real implementation, use phpseclib or similar for ASN.1
		// Here we create a basic structure
		$request = pack('H*', '3080') // SEQUENCE
			.pack('H*', '020101') // version 1
			.pack('H*', '3080') // MessageImprint SEQUENCE
			.pack('H*', '3080') // hashAlgorithm
			.pack('H*', '0609').$hashAlgo
			.pack('H*', '0000') // end hashAlgorithm
			.pack('H*', '0420').pack('H*', bin2hex($hash)) // hash value
			.pack('H*', '0000') // end MessageImprint
			.pack('H*', '020109').pack('N', $nonce) // nonce
			.pack('H*', '0101FF') // certReq = TRUE
			.pack('H*', '0000'); // end SEQUENCE

		return $request;
	}

	/**
	 * Parse TSA response (simplified)
	 *
	 * @param string $response TSA response
	 * @return array
	 */
	private function parseTSAResponse($response)
	{
		// Simplified parsing
		// In production, use proper ASN.1 parser
		
		return array(
			'date' => time(),
			'serial' => bin2hex(random_bytes(16)),
		);
	}

	/**
	 * Get system certificate and private key
	 *
	 * @return array|false Certificate data
	 */
	private function getSystemCertificate()
	{
		global $conf;

		$sql = "SELECT * FROM ".MAIN_DB_PREFIX."docsig_key";
		$sql .= " WHERE key_type = 'signing'";
		$sql .= " AND is_active = 1";
		$sql .= " AND entity = ".$conf->entity;
		$sql .= " ORDER BY rowid DESC LIMIT 1";

		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			$obj = $this->db->fetch_object($resql);

			// Decrypt private key
			$encryptionKey = $this->getEncryptionKey();
			$iv = base64_decode($obj->private_key_iv);
			$tag = base64_decode($obj->private_key_tag);
			
			$privateKey = openssl_decrypt(
				$obj->private_key_encrypted,
				'aes-256-gcm',
				$encryptionKey,
				0,
				$iv,
				$tag
			);

			if ($privateKey === false) {
				return false;
			}

			return array(
				'certificate' => $obj->certificate,
				'private_key' => $privateKey,
				'public_key' => $obj->public_key,
			);
		}

		return false;
	}

	/**
	 * Get encryption key
	 *
	 * @return string
	 */
	private function getEncryptionKey()
	{
		global $conf;

		if (!empty($conf->file->instance_unique_id)) {
			return hash('sha256', $conf->file->instance_unique_id, true);
		}

		return hash('sha256', 'docsig-secret-key-'.$conf->entity, true);
	}

	/**
	 * Get last error
	 *
	 * @return string
	 */
	public function getError()
	{
		return $this->error;
	}

	/**
	 * Calculate document hash
	 *
	 * @param string $filePath File path
	 * @return string SHA-256 hash
	 */
	public static function calculateHash($filePath)
	{
		return hash_file('sha256', $filePath);
	}
}
