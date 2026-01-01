<?php
/* Copyright (C) 2024	PuertaSevilla Team
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * or see https://www.gnu.org/
 */

/**
 *	\file       htdocs/core/modules/contract/doc/pdf_puerta_sevilla.modules.php
 *	\ingroup    contracts
 *	\brief      PuertaSevilla rental contracts template class file
 */
require_once DOL_DOCUMENT_ROOT . '/core/modules/contract/modules_contract.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';


/**
 *	Class to build rental contracts documents with model PuertaSevilla
 */
class pdf_puerta_sevilla extends ModelePDFContract
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var int The environment ID when using a multicompany module
	 */
	public $entity;

	/**
	 * @var string model name
	 */
	public $name;

	/**
	 * @var string model description (short text)
	 */
	public $description;

	/**
	 * @var int     Save the name of generated file as the main doc when generating a doc with this template
	 */
	public $update_main_doc_field;

	/**
	 * @var string document type
	 */
	public $type;

	/**
	 * Dolibarr version of the loaded document
	 * @var string
	 */
	public $version = 'dolibarr';

	/**
	 * Recipient
	 * @var Societe
	 */
	public $recipient;

	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		global $langs, $mysoc;

		$this->db = $db;
		$this->name = 'puerta_sevilla';
		$this->description = $langs->trans("PuertaSevillaRentalContractTemplate");
		$this->update_main_doc_field = 1;

		// Dimension page
		$this->type = 'pdf';
		$formatarray = pdf_getFormat();

		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 5);

		$this->option_logo = 1;
		$this->option_tva = 0;
		$this->option_modereg = 0;
		$this->option_condreg = 0;
		$this->option_multilang = 0;
		$this->option_draft_watermark = 1;

		// Get source company
		$this->emetteur = $mysoc;
		if (empty($this->emetteur->country_code)) {
			$this->emetteur->country_code = substr($langs->defaultlang, -2);
		}

		// Define position of columns
		$this->posxdesc = $this->marge_gauche + 1;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Function to build pdf onto disk
	 *
	 *  @param		Contrat			$object				Object to generate
	 *  @param		Translate		$outputlangs		Lang output object
	 *  @param		string			$srctemplatepath	Full path of source filename for generator using a template file
	 *  @param		int				$hidedetails		Do not show line details
	 *  @param		int				$hidedesc			Do not show desc
	 *  @param		int				$hideref			Do not show ref
	 *  @return		int									1=OK, 0=KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		global $user, $langs, $conf, $mysoc, $db, $hookmanager, $nblines;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}

		if (getDolGlobalString('MAIN_USE_FPDF')) {
			$outputlangs->charset_output = 'ISO-8859-1';
		}

		$outputlangs->loadLangs(array("main", "dict", "companies", "contracts"));

		if ($object->statut == $object::STATUS_DRAFT && (getDolGlobalString('CONTRACT_DRAFT_WATERMARK'))) {
			$this->watermark = getDolGlobalString('CONTRACT_DRAFT_WATERMARK');
		}

		global $outputlangsbis;
		$outputlangsbis = null;
		if (getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE') && $outputlangs->defaultlang != getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE')) {
			$outputlangsbis = new Translate('', $conf);
			$outputlangsbis->setDefaultLang(getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE'));
			$outputlangsbis->loadLangs(array("main", "dict", "companies", "bills", "products", "orders", "deliveries"));
		}

		$nblines = count($object->lines);

		if ($conf->contract->multidir_output[$conf->entity]) {
			$object->fetch_thirdparty();

			// Definition of $dir and $file
			if ($object->specimen) {
				$dir = getMultidirOutput($object);
				$file = $dir . "/SPECIMEN.pdf";
			} else {
				$objectref = dol_sanitizeFileName($object->ref);
				$dir = getMultidirOutput($object) . "/" . $objectref;
				$file = $dir . "/" . $objectref . ".pdf";
			}

			if (!file_exists($dir)) {
				if (dol_mkdir($dir) < 0) {
					$this->error = $langs->transnoentitiesnoconv("ErrorCanNotCreateDir", $dir);
					return 0;
				}
			}

			if (file_exists($dir)) {
				// Add pdfgeneration hook
				if (!is_object($hookmanager)) {
					include_once DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php';
					$hookmanager = new HookManager($this->db);
				}
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
				global $action;
				$reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action);

				$nblines = (is_array($object->lines) ? count($object->lines) : 0);

				// Create pdf instance
				$pdf = pdf_getInstance($this->format);
				$default_font_size = pdf_getPDFFontSize($outputlangs);
				// Important: reserve space for footer to avoid body overlapping it.
				// We'll set the final bottom margin after $heightforfooter is computed below.
				$pdf->SetAutoPageBreak(1, 0);

				$heightforinfotot = 50;
				$heightforfreetext = getDolGlobalInt('MAIN_PDF_FREETEXT_HEIGHT', 5);
				$heightforfooter = $this->marge_basse + 9;
				if (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS')) {
					$heightforfooter += 6;
				}

				// Now that we know the exact footer height, enforce it as the bottom margin
				// for automatic page breaks. This prevents the main content from being printed
				// into the footer area.
				$pdf->SetAutoPageBreak(1, $heightforfooter);

				if (class_exists('TCPDF')) {
					$pdf->setPrintHeader(false);
					$pdf->setPrintFooter(false);
				}
				$pdf->SetFont(pdf_getPDFFont($outputlangs));
				if (getDolGlobalString('MAIN_ADD_PDF_BACKGROUND')) {
					$logodir = $conf->mycompany->dir_output;
					if (!empty($conf->mycompany->multidir_output[$object->entity])) {
						$logodir = $conf->mycompany->multidir_output[$object->entity];
					}
					$pagecount = $pdf->setSourceFile($logodir . '/' . getDolGlobalString('MAIN_ADD_PDF_BACKGROUND'));
					$tplidx = $pdf->importPage(1);
				}

				$pdf->Open();
				$pagenb = 0;
				$pdf->SetDrawColor(128, 128, 128);

				$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
				$pdf->SetSubject($outputlangs->transnoentities("Contract"));
				$pdf->SetCreator("Dolibarr " . DOL_VERSION);
				$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
				$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref) . " " . $outputlangs->transnoentities("Contract") . " " . $outputlangs->convToOutputCharset($object->thirdparty->name));
				if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
					$pdf->SetCompression(false);
				}

				$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);

				// New page
				$pdf->AddPage();
				if (!empty($tplidx)) {
					$pdf->useTemplate($tplidx);
				}
				$pagenb++;
				$top_shift = $this->_pagehead($pdf, $object, 1, $outputlangs, (is_object($outputlangsbis) ? $outputlangsbis : null));
				$pdf->SetFont('', '', $default_font_size - 1);
				$pdf->MultiCell(0, 3, '');
				$pdf->SetTextColor(0, 0, 0);

				$tab_top = 30;
				$tab_top_newpage = (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD') ? 42 : 10);

				// Render contract content
				$tab_top = $this->_renderContractContent($pdf, $object, $outputlangs, $tab_top, $default_font_size);

				// Show signature boxes
				if ($pagenb == 1) {
					$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 0, 0);
					$this->tabSignature($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforinfotot - $heightforfreetext - $heightforfooter, $outputlangs);
					$bottomlasttab = $this->page_hauteur - $heightforfooter - $heightforfooter + 1;
				} else {
					$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 0, 0);
					$this->tabSignature($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforinfotot - $heightforfreetext - $heightforfooter, $outputlangs);
					$bottomlasttab = $this->page_hauteur - $heightforfooter - $heightforfooter + 1;
				}

				// Draw footer on every page (TCPDF footer is disabled above)
				$this->_addFooterToPages($pdf, $object, $outputlangs);
				$this->_addPageNumberRightMarginVertical($pdf);
				if (method_exists($pdf, 'AliasNbPages')) {
					$pdf->AliasNbPages();
				}

				$pdf->Close();

				$pdf->Output($file, 'F');

				// Add pdfgeneration hook
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
				global $action;
				$reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action);
				if ($reshook < 0) {
					$this->error = $hookmanager->error;
					$this->errors = $hookmanager->errors;
				}

				dolChmod($file);

				$this->result = array('fullpath' => $file);

				return 1;
			} else {
				$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
				return 0;
			}
		} else {
			$this->error = $langs->transnoentities("ErrorConstantNotDefined", "CONTRACT_OUTPUTDIR");
			return 0;
		}
	}

	/**
	 * Render the main contract content
	 *
	 * @param TCPDF $pdf PDF object
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Language object
	 * @param int $tab_top Current Y position
	 * @param int $default_font_size Default font size
	 * @return int New Y position
	 */
	protected function _renderContractContent(&$pdf, $object, $outputlangs, $tab_top, $default_font_size)
	{
		global $mysoc;

		$curY = $tab_top;
		$leftmargin = $this->marge_gauche + 5;
		$rightmargin = $this->page_largeur - $this->marge_droite - 5;
		$contentwidth = $rightmargin - $leftmargin;

		// Obtener datos de las líneas del contrato
		$firstLine = !empty($object->lines) && count($object->lines) > 0 ? $object->lines[0] : null;
		$rentAmount = !empty($firstLine) ? $firstLine->subprice : 0;
		$startDate = !empty($firstLine) && !empty($firstLine->date_start) ? $firstLine->date_start : 0;
		$endDate = !empty($firstLine) && !empty($firstLine->date_end) ? $firstLine->date_end : 0;
		
		// Calcular número de meses entre fechas
		$months = 0;
		if ($startDate && $endDate) {
			$dateStart = new DateTime('@' . $startDate);
			$dateEnd = new DateTime('@' . $endDate);
			$interval = $dateStart->diff($dateEnd);
			$months = ($interval->y * 12) + $interval->m;
		}
		
		$sql = "select * from " . MAIN_DB_PREFIX . "projet p LEFT JOIN " . MAIN_DB_PREFIX . "projet_extrafields e on e.fk_object = p.rowid where p.rowid = " . (int) $object->fk_project;
		$resql = $this->db->query($sql);
		$projectData = $this->db->fetch_object($resql);
		// echo json_encode($projectData); // Debug line, can be removed later

		// REUNIDOS (Introducción del contrato)
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', 'B', $default_font_size);
		$pdf->MultiCell($contentwidth, 5, "REUNIDOS", 0, 'C');
		$curY = $pdf->GetY() + 3;

		// DE UNA PARTE (Arrendador)
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', 'B', $default_font_size - 1);
		$pdf->MultiCell($contentwidth, 4, "DE UNA PARTE:", 0, 'L');
		$curY = $pdf->GetY() + 2;

		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', '', $default_font_size - 2);

		// Get contact with role 'propietario firmante' for landlord
		$landlord_name = $mysoc->name;
		$landlord_address = '';
		$landlord_zip = '';
		$landlord_town = '';
		$landlord_siret = '';
		$companyText = "";
		// Get all contacts associated with the contract
		$sql = "SELECT
				s.rowid  AS tercero_id,
				s.nom    AS tercero_nombre,
				s.address AS tercero_direccion,
				s.zip    AS tercero_zip,
				s.town   AS tercero_localidad,
				s.tva_intra AS tercero_tva_intra,

				c.rowid  AS contacto_id,
				CONCAT(c.firstname, ' ', c.lastname) AS contacto_nombre,
				c.address AS contacto_direccion,
				c.zip    AS contacto_zip,
				c.town   AS contacto_localidad,
				s.tva_intra AS contacto_tva_intra,
				ect.libelle AS tipo_asociacion

			FROM vol_element_contact ec
			INNER JOIN vol_socpeople c ON c.rowid = ec.fk_socpeople
			INNER JOIN vol_societe s ON s.rowid = c.fk_soc
			LEFT JOIN vol_c_type_contact ect ON ect.rowid = ec.fk_c_type_contact
			WHERE ec.element_id =" . (int) $object->id;
		$resql = $this->db->query($sql);
		$propietarios = [];
		$inquilinos = [];
		$avalistas = [];
		if ($resql) {
			if ($this->db->num_rows($resql) > 0) {
				$obj = $this->db->fetch_object($resql);
				if ($obj) {
					switch (strtoupper(trim($obj->tipo_asociacion))) {
						case 'PROPIETARIO FIRMANTE':
							$propietarios[] = $obj;;
							break;
						case 'INQUILINO':
							$inquilinos[] = $obj;
							break;
						case 'AVALISTA':
							$avalistas[] = $obj;
							break;
						default:
							// Other types can be handled here
							break;
					}

					// Note: siret is typically a company field, not contact field
					// Store contact for signature box

				}
			}
			$this->db->free($resql);
		}
		if (!empty($propietarios)) {
			$companyText .= "D./Dña./Entidad " . $propietarios[0]->tercero_nombre;
			if (!empty($propietarios[0]->tercero_direccion)) {
				$companyText .= ", con domicilio en " .  $propietarios[0]->tercero_direccion;
			}
			if (!empty($propietarios[0]->tercero_zip) && !empty($propietarios[0]->tercero_localidad)) {
				$companyText .= ", " . $propietarios[0]->tercero_zip . " " . $propietarios[0]->tercero_localidad;
			}
			if (!empty($propietarios[0]->tercero_tva_intra)) {
				$companyText .= ", con NIF/CIF: " . $propietarios[0]->tercero_tva_intra;
			}
			$companyText .= "\nRepresentado por D./Dña. " . $propietarios[0]->contacto_nombre;
			if (!empty($propietarios[0]->contacto_direccion)) {
				$companyText .= ", con domicilio en " .  $propietarios[0]->contacto_direccion;
			}
			if (!empty($propietarios[0]->contacto_zip) && !empty($propietarios[0]->contacto_localidad)) {
				$companyText .= ", " . $propietarios[0]->contacto_zip . " " . $propietarios[0]->contacto_localidad;
			}
			if (!empty($propietarios[0]->contacto_tva_intra)) {
				$companyText .= ", con NIF/CIF: " . $propietarios[0]->contacto_tva_intra;
			}
			if(count($propietarios) > 1) {
				for($i = 1; $i < count($propietarios); $i++) {
					$companyText .= " y D./Dña. " . $propietarios[$i]->contacto_nombre;
					if (!empty($propietarios[$i]->contacto_direccion)) {
						$companyText .= ", con domicilio en " .  $propietarios[$i]->contacto_direccion;
					}
					if (!empty($propietarios[$i]->contacto_zip) && !empty($propietarios[$i]->contacto_localidad)) {
						$companyText .= ", " . $propietarios[$i]->contacto_zip . " " . $propietarios[$i]->contacto_localidad;
					}
					if (!empty($propietarios[$i]->contacto_tva_intra)) {
						$companyText .= ", con NIF/CIF: " . $propietarios[$i]->contacto_tva_intra;
					}
				}
			}
		} else {
			$companyText = "<span style='color:red;'>ERROR: No se ha definido ningún 'PROPIETARIO FIRMANTE' en los contactos del contrato.</span>";
		}


		$companyText .= ", en adelante EL ARRENDADOR.";
		$pdf->MultiCell($contentwidth, 3, $companyText, 0, 'L');
		$curY = $pdf->GetY() + 4;

		// Store recipient for signature box
		$this->recipient = $object->contact;

		// DE OTRA PARTE (Arrendatario)
		if (!empty($object->thirdparty)) {
			$pdf->SetXY($leftmargin, $curY);
			$pdf->SetFont('', 'B', $default_font_size - 1);
			$pdf->MultiCell($contentwidth, 4, "Y DE OTRA PARTE:", 0, 'L');
			$curY = $pdf->GetY() + 2;

			$pdf->SetXY($leftmargin, $curY);
			$pdf->SetFont('', '', $default_font_size - 2);
			$tenantText = "D./Dña. " . $object->thirdparty->name;
			if (!empty($object->thirdparty->address)) {
				$tenantText .= ", con domicilio en " . $object->thirdparty->address;
			}
			if (!empty($object->thirdparty->zip) && !empty($object->thirdparty->town)) {
				$tenantText .= ", " . $object->thirdparty->zip . " " . $object->thirdparty->town;
			}
			if (!empty($object->thirdparty->siret)) {
				$tenantText .= ", con NIF/CIF: " . $object->thirdparty->siret;
			}

			if(count($inquilinos) > 0) {
				$tenantText .= "\n y D./Dña. " . $inquilinos[0]->contacto_nombre;
				if (!empty($inquilinos[0]->contacto_direccion)) {
					$tenantText .= ", con domicilio en " .  $inquilinos[0]->contacto_direccion;
				}
				if (!empty($inquilinos[0]->contacto_zip) && !empty($inquilinos[0]->contacto_localidad)) {
					$tenantText .= ", " . $inquilinos[0]->contacto_zip . " " . $inquilinos[0]->contacto_localidad;
				}
				if (!empty($inquilinos[0]->contacto_tva_intra)) {
					$tenantText .= ", con NIF/CIF: " . $inquilinos[0]->contacto_tva_intra;
				}
				
			}

			$tenantText .= ", en adelante EL ARRENDATARIO./LOS ARRENDATARIOS.";
			$pdf->MultiCell($contentwidth, 3, $tenantText, 0, 'L');
		}

		//AVALISTAS
		if(!empty($avalistas)){
			$curY = $pdf->GetY() + 4;
			$pdf->SetXY($leftmargin, $curY);
			$pdf->SetFont('', 'B', $default_font_size - 1);
			$pdf->MultiCell($contentwidth, 4, "Y AVALISTAS:", 0, 'L');
			$curY = $pdf->GetY() + 2;

			$pdf->SetXY($leftmargin, $curY);
			$pdf->SetFont('', '', $default_font_size - 2);
			$avalistaText = "D./Dña. " . $avalistas[0]->contacto_nombre;
			if (!empty($avalistas[0]->contacto_direccion)) {
				$avalistaText .= ", con domicilio en " .  $avalistas[0]->contacto_direccion;
			}
			if (!empty($avalistas[0]->contacto_zip) && !empty($avalistas[0]->contacto_localidad)) {
				$avalistaText .= ", " . $avalistas[0]->contacto_zip . " " . $avalistas[0]->contacto_localidad;
			}
			if (!empty($avalistas[0]->contacto_tva_intra)) {
				$avalistaText .= ", con NIF/CIF: " . $avalistas[0]->contacto_tva_intra;
			}

			if(count($avalistas) > 1) {
				for($i = 1; $i < count($avalistas); $i++) {
					$avalistaText .= " y D./Dña. " . $avalistas[$i]->contacto_nombre;
					if (!empty($avalistas[$i]->contacto_direccion)) {
						$avalistaText .= ", con domicilio en " .  $avalistas[$i]->contacto_direccion;
					}
					if (!empty($avalistas[$i]->contacto_zip) && !empty($avalistas[$i]->contacto_localidad)) {
						$avalistaText .= ", " . $avalistas[$i]->contacto_zip . " " . $avalistas[$i]->contacto_localidad;
					}
					if (!empty($avalistas[$i]->contacto_tva_intra)) {
						$avalistaText .= ", con NIF/CIF: " . $avalistas[$i]->contacto_tva_intra;	
					}
				}
			}

			$avalistaText .= ", en adelante LOS AVALISTAS.";
			$pdf->MultiCell($contentwidth, 3, $avalistaText, 0, 'L');	
		}

		$curY = $pdf->GetY() + 5;
		// CONSIDERANDO
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', '', $default_font_size - 2);
		$pdf->MultiCell($contentwidth, 3, "El Propietario y el Inquilino serán denominadas conjuntamente como las 'Partes'. Ambas partes en la calidad con la que actúan, se reconocen recíprocamente capacidad jurídica para contratar y obligarse y en especial para el otorgamiento del presente CONTRATO DE ARRENDAMIENTO DE VIVIENDA:
", 0, 'L');
		$curY = $pdf->GetY() + 3;
		
		// EXPONEN
		$pdf->SetXY($leftmargin, $curY);
		// $pdf->SetXY($leftmargin + 5, $curY);
		$pdf->SetFont('', 'B', $default_font_size);
		$pdf->MultiCell($contentwidth, 5, "EXPONEN", 0, 'C');
		$curY = $pdf->GetY() + 3;

		// PRIMERO - Propiedad del inmueble
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', 'B', $default_font_size - 1);
		$pdf->MultiCell($contentwidth, 4, "PRIMERO.- ", 0, 'L');
		$pdf->SetXY($leftmargin + 25, $curY);
		$pdf->SetFont('', '', $default_font_size - 2);
		$primerText = "Que EL ARRENDADOR es propietario de la vivienda sita en " . $projectData->psv_direccion;
		if($projectData->psv_localidad) {
			$primerText .= ", " . $projectData->psv_localidad;
		}
		$primerText .= ", ";
		$primerText .= "REF. CATASTRAL:".$projectData->psv_referencia_catastral.", Certificado de eficiencia energética ".$projectData->psv_certificado.". Se adjunta fotocopia del certificado como anexo al final del presente contrato si hubiese.";
		$primerText .= "El Propietario manifiesta expresamente que el Inmueble cumple con todos los requisitos y condiciones necesarias para ser destinado a satisfacer las necesidades permanentesde vivienda del Inquilino. (En adelante, la vivienda y sus dependencias descritas, conjuntamente, el 'Inmueble').";
		$pdf->MultiCell($contentwidth - 15, 3, $primerText, 0, 'L');
		$curY = $pdf->GetY() + 3;

		// SEGUNDO - Voluntad de arrendar
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', 'B', $default_font_size - 1);
		$pdf->MultiCell($contentwidth, 4, "SEGUNDO.- ", 0, 'L');
		$pdf->SetXY($leftmargin + 25, $curY);
		$pdf->SetFont('', '', $default_font_size - 2);
		$segundoText = "Que el Inquilino, manifiesta su interés en tomar en arrendamiento el citado Inmueble descrito en el Expositivo 1º, para su uso propio (y, en su caso, el de su familia) como vivienda habitual y permanente.";
		$pdf->MultiCell($contentwidth - 15, 3, $segundoText, 0, 'L');
		$curY = $pdf->GetY() + 5;

		// TERCERO - Voluntad de arrendar
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', 'B', $default_font_size - 1);
		$pdf->MultiCell($contentwidth, 4, "TERCERO.- ", 0, 'L');
		$pdf->SetXY($leftmargin + 25, $curY);
		$pdf->SetFont('', '', $default_font_size - 2);
		$segundoText = 'Ambas partes libremente reconocen entender y aceptar el presente CONTRATO DE ARRENDAMIENTO DE VIVIENDA (el "Contrato"), conforme a las disposiciones de la Ley 29/1994 de 24 de noviembre de Arrendamientos Urbanos (la "LAU"), reconociéndose mutuamente capacidad jurídica para suscribirlo, con sujeción a las siguientes:';
		$pdf->MultiCell($contentwidth - 15, 3, $segundoText, 0, 'L');
		$curY = $pdf->GetY() + 5;

		// ESTIPULACIONES
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', 'B', $default_font_size);
		$pdf->MultiCell($contentwidth, 5, "CLÁUSULAS", 0, 'C');
		$curY = $pdf->GetY() + 3;

		// PRIMERA - Objeto del contrato
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', 'B', $default_font_size - 1);
		$pdf->MultiCell($contentwidth, 4, "PRIMERA.- Objeto del contrato.", 0, 'L');
		$curY = $pdf->GetY() + 2;
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', '', $default_font_size - 2);
		$primeraText = "- El Propietario arrienda al Inquilino, que acepta en este acto, el Inmueble descrito en el Expositivo 1º, que el Inquilino acepta en este acto.
- El Inquilino se compromete a usar dicho Inmueble exclusivamente como vivienda del Inquilino y de su familia directa, en su caso.
- En relación con el uso del Inmueble, queda estrictamente prohibido:
	a) Cualquier otro tipo de uso distinto al descrito en el apartado anterior.
	b) El subarrendamiento, total o parcial.
	c) La cesión del contrato sin el consentimiento previo y por escrito del Arrendador.
	d) El uso del Inmueble para comercio, industria ni oficina o despacho profesional.
	e) Destinarla al hospedaje de carácter vacacional.
- El incumplimiento por el Inquilino de esta obligación esencial facultará al Propietario aresolver el presente Contrato.
- Por las dimensiones del Inmueble, el número máximo de personas que podrán ocuparlo es de 5, incluyendo al Inquilino.
- El Inquilino se obliga a cumplir y respetar en todo momento los estatutos y normas internas de la comunidad de propietarios a la que pertenece el Inmueble, que declara conocer y aceptar. Además, se compromete a no molestar ni perjudicar la pacífica convivencia del resto de vecinos de la comunidad.
Mascotas: Se permite expresamente al Inquilino tener en el Inmueble cualquier tipo de animal doméstico.";
		$pdf->MultiCell($contentwidth, 3, $primeraText, 0, 'L');
		$curY = $pdf->GetY() + 3;

		// SEGUNDA - Duración
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', 'B', $default_font_size - 1);
		$pdf->MultiCell($contentwidth, 4, "SEGUNDA.- Plazo de Vigencia.", 0, 'L');
		$curY = $pdf->GetY() + 2;
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', '', $default_font_size - 2);
		$segundaDurationText = "El Contrato entrará en vigor en la fecha  ";
		if ($startDate && $endDate) {
			$startDateStr = dol_print_date($startDate, "day", false, $outputlangs, true);
			$endDateStr = dol_print_date($endDate, "day", false, $outputlangs, true);
			$monthsText = $months == 1 ? "mes" : "meses";
			$segundaDurationText .= "con una duración inicial obligatoria de " . $months . " " . $monthsText . ", desde el " . $startDateStr . " hasta el " . $endDateStr;
		} else {
			$segundaDurationText .= "UN AÑO a partir de la fecha de entrada en vigor del Contrato.";
		}
		$segundaDurationText .= "\nEl Contrato se prorrogará tácitamente (sin necesidad de aviso previo) en cada anualidad hasta un máximo legal de cinco 5 años, salvo que el Inquilino manifieste al Propietario, con treinta días de antelación a la fecha de terminación del Contrato o de cualquiera de sus prórrogas, su voluntad de no renovar el Contrato.
Una vez transcurridos como mínimo cinco (5) años de duración del Contrato, si ninguna de las Partes hubiese notificado a la otra, con al menos cuatro meses de antelación en el caso del Propietario, o con al menos con dos meses de antelación en el caso del Inquilino, a la fecha de finalización su voluntad de no renovar el Contrato, el Contrato se prorrogará obligatoriamente por anualidades hasta un máximo de tres (3) años, salvo que el Inquilino manifieste al arrendador con un mes de antelación a la fecha de terminación de cualquiera de las anualidades, su voluntad de no renovar el Contrato.
El Inquilino podrá desistir del Contrato una vez que hayan transcurrido al menos seis (6) meses a contar desde la fecha de entrada en vigor del Contrato, siempre que notifique por escrito con treinta (30) días de antelación al Propietario. El desistimiento dará lugar a una indemnización equivalente a la parte proporcional de la renta arrendaticia de una mensualidad con relación a los meses que falten por cumplir de un año.";
		$pdf->MultiCell($contentwidth, 3, $segundaDurationText, 0, 'L');
		$curY = $pdf->GetY() + 3;

		// TERCERA - Entrega del inmueble
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', 'B', $default_font_size - 1);
		$pdf->MultiCell($contentwidth, 4, "TERCERA.- ENTREGA DEL INMUEBLE.", 0, 'L');
		$curY = $pdf->GetY() + 2;
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', '', $default_font_size - 2);
		$terceraText = "El Propietario entrega al Inquilino el Inmueble en perfectas condiciones de habitabilidad, buen estado de conservación y funcionamiento de sus servicios y a la entera satisfacción de éste.
Ambas Partes confirman que el Inmueble se entrega con cocina equipada y vivienda amueblada.
Se adjunta como Anexo el inventario, recogiendo el detalle del mobiliario del Inmueble.
En este acto el Propietario hace entrega al Inquilino de un juego de llaves completos de acceso al Inmueble.";
		$pdf->MultiCell($contentwidth, 3, $terceraText, 0, 'L');
		$curY = $pdf->GetY() + 3;

		// CUARTA -RENTA - 
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', 'B', $default_font_size - 1);
		$pdf->MultiCell($contentwidth, 4, "CUARTA.- RENTA.", 0, 'L');
		$curY = $pdf->GetY() + 2;
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', '', $default_font_size - 2);
		
		$terceraText = "Renta arrendaticia
4.1 Ambas Partes acuerdan fijar una renta anual de ".price($rentAmount * $months)." € (".$this->convertNumberToSpanishText($rentAmount * $months)." Euros), que será pagada por el Inquilino en doce (12) mensualidades iguales de 500 € (Quinientos) cada una de ellas.
4.2 La falta de pago de una 1 mensualidad de renta será causa suficiente para que el Propietario pueda dar por resuelto este Contrato y ejercite la acción de desahucio.
Inicio del devengo de la renta
4.3 Se establece que la renta se devengará a partir de la fecha de entrada en vigor del presente Contrato. El Inquilino paga al Propietario el importe de la renta correspondiente a los días que quedan para finalizar el mes en curso, que el Propietario declara haber recibido a su entera satisfacción, sirviendo el presente Contrato como recibo de pago.
Pago de la renta
4.4 El Inquilino abonará la renta por mensualidades anticipadas, dentro de los cinco (5) primeros días laborables de cada mes, mediante transferencia bancaria a la siguiente cuenta titularidad del Propietario: 
• Titular: ".$propietarios[0]->tercero_nombre.".
• Entidad: .
• Nº de Cuenta/IBAN: BBVA-ES51.
• Concepto:114 -- C/ CANDELON 14 2º 16 y mes
Actualización de la renta
4.5 La renta pactada será actualizada anualmente y de manera acumulativa, en cada
[día y mes de entrada en vigor de este contrato], conforme a las variaciones que
experimente el índice General Nacional del Sistema de Precios al Consumo ('I.P.C.'),
publicado por el Instituto Nacional de Estadística teniendo en consideración las
variaciones en los doce (12) meses inmediatamente anteriores.
Si la variación experimentada por el I.P.C. fuera negativa, la renta permanecerá igual,
sin actualizarse.";
		$pdf->MultiCell($contentwidth, 3, $terceraText, 0, 'L');
		$curY = $pdf->GetY() + 3;

		// QUINTA - Fianza
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', 'B', $default_font_size - 1);
		$pdf->MultiCell($contentwidth, 4, "CUARTA.- Fianza.", 0, 'L');
		$curY = $pdf->GetY() + 2;
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', '', $default_font_size - 2);
		$fianzaAmount = $rentAmount * 1; // 1 mensualidad
		$cuartaText = "El ARRENDATARIO entrega en este acto al ARRENDADOR, en concepto de fianza, la cantidad de " . price($fianzaAmount) . " € (una mensualidad), que le será devuelta a la finalización del contrato, previo descuento de los gastos que pudieran corresponder.";
		$pdf->MultiCell($contentwidth, 3, $cuartaText, 0, 'L');
		$curY = $pdf->GetY() + 3;

		// Check if we need a new page
		if ($curY > $this->page_hauteur - 80) {
			$pdf->AddPage();
			$curY = 20;
		}

		// QUINTA - Gastos
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', 'B', $default_font_size - 1);
		$pdf->MultiCell($contentwidth, 4, "QUINTA.- Gastos y suministros.", 0, 'L');
		$curY = $pdf->GetY() + 2;
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', '', $default_font_size - 2);
		$quintaText = "Los gastos de suministros (agua, luz, gas, teléfono, internet, etc.) serán por cuenta del ARRENDATARIO. Los gastos de comunidad, IBI y seguros serán por cuenta del ARRENDADOR.";
		$pdf->MultiCell($contentwidth, 3, $quintaText, 0, 'L');
		$curY = $pdf->GetY() + 3;

		// SEXTA - Conservación
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', 'B', $default_font_size - 1);
		$pdf->MultiCell($contentwidth, 4, "SEXTA.- Conservación y reparaciones.", 0, 'L');
		$curY = $pdf->GetY() + 2;
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', '', $default_font_size - 2);
		$sextaText = "El ARRENDATARIO se compromete a mantener la vivienda en buen estado de conservación y uso, realizando las pequeñas reparaciones que exija el desgaste por el uso ordinario de la vivienda. Las reparaciones por deterioros de mayor importancia corresponderán al ARRENDADOR.";
		$pdf->MultiCell($contentwidth, 3, $sextaText, 0, 'L');
		$curY = $pdf->GetY() + 3;

		// SÉPTIMA - Obras
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', 'B', $default_font_size - 1);
		$pdf->MultiCell($contentwidth, 4, "SÉPTIMA.- Obras.", 0, 'L');
		$curY = $pdf->GetY() + 2;
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', '', $default_font_size - 2);
		$septimaText = "El ARRENDATARIO no podrá realizar obras en el inmueble sin el consentimiento previo y por escrito del ARRENDADOR.";
		$pdf->MultiCell($contentwidth, 3, $septimaText, 0, 'L');
		$curY = $pdf->GetY() + 3;

		// OCTAVA - Cesión y subarriendo
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', 'B', $default_font_size - 1);
		$pdf->MultiCell($contentwidth, 4, "OCTAVA.- Cesión y subarriendo.", 0, 'L');
		$curY = $pdf->GetY() + 2;
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', '', $default_font_size - 2);
		$octavaText = "El ARRENDATARIO no podrá subarrendar ni ceder el contrato sin el consentimiento previo y por escrito del ARRENDADOR.";
		$pdf->MultiCell($contentwidth, 3, $octavaText, 0, 'L');
		$curY = $pdf->GetY() + 3;

		// NOVENA - Resolución
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', 'B', $default_font_size - 1);
		$pdf->MultiCell($contentwidth, 4, "NOVENA.- Causas de resolución.", 0, 'L');
		$curY = $pdf->GetY() + 2;
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', '', $default_font_size - 2);
		$novenaText = "Serán causas de resolución del contrato:\n";
		$novenaText .= "a) El impago de la renta o de las cantidades asimiladas a la misma.\n";
		$novenaText .= "b) El subarriendo o cesión inconsentidos.\n";
		$novenaText .= "c) Los daños causados en la vivienda o las incomodidades ocasionadas a los vecinos.\n";
		$novenaText .= "d) La realización de obras no consentidas por el ARRENDADOR.";
		$pdf->MultiCell($contentwidth, 3, $novenaText, 0, 'L');
		$curY = $pdf->GetY() + 3;

		// DÉCIMA - Legislación
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', 'B', $default_font_size - 1);
		$pdf->MultiCell($contentwidth, 4, "DÉCIMA.- Legislación aplicable.", 0, 'L');
		$curY = $pdf->GetY() + 2;
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', '', $default_font_size - 2);
		$decimaText = "En lo no previsto en el presente contrato se estará a lo dispuesto en la Ley 29/1994, de 24 de noviembre, de Arrendamientos Urbanos, y disposiciones complementarias.";
		$pdf->MultiCell($contentwidth, 3, $decimaText, 0, 'L');
		$curY = $pdf->GetY() + 5;

		// Notas adicionales si existen
		if (!empty($object->note_public)) {
			if ($curY > $this->page_hauteur - 60) {
				$pdf->AddPage();
				$curY = 20;
			}
			$pdf->SetXY($leftmargin, $curY);
			$pdf->SetFont('', 'B', $default_font_size - 1);
			$pdf->MultiCell($contentwidth, 4, "CONDICIONES PARTICULARES:", 0, 'L');
			$curY = $pdf->GetY() + 2;

			$pdf->SetXY($leftmargin, $curY);
			$pdf->SetFont('', '', $default_font_size - 2);
			$noteText = dol_htmlentitiesbr($object->note_public, 1);
			$pdf->MultiCell($contentwidth, 3, $noteText, 0, 'L');
			$curY = $pdf->GetY() + 5;
		}

		// Firma y conformidad
		if ($curY > $this->page_hauteur - 80) {
			$pdf->AddPage();
			$curY = 20;
		}
		$pdf->SetXY($leftmargin, $curY);
		$pdf->SetFont('', '', $default_font_size - 2);
		$firmaText = "Y en prueba de conformidad con cuanto antecede, firman el presente contrato por duplicado y a un solo efecto en ";
		if (!empty($mysoc->town)) {
			$firmaText .= $mysoc->town;
		}
		$firmaText .= ", a " . dol_print_date($object->date_contrat, "daytext", false, $outputlangs, true) . ".";
		$pdf->MultiCell($contentwidth, 3, $firmaText, 0, 'L');
		$curY = $pdf->GetY() + 5;

		return $curY;
	}

	/**
	 * Get property description from contract
	 *
	 * @param Contrat $object
	 * @return string
	 */
	protected function _getPropertyDescription($object)
	{
		$desc = "";

		// Try to get from first line description
		if (!empty($object->lines) && count($object->lines) > 0) {
			$firstLine = $object->lines[0];
			if (!empty($firstLine->desc)) {
				$desc = $firstLine->desc;
			} elseif (!empty($firstLine->product_label)) {
				$desc = $firstLine->product_label;
			}
		}

		// Fallback to contract description
		if (empty($desc) && !empty($object->description)) {
			$desc = $object->description;
		}

		return !empty($desc) ? $desc : "Residential property for rent";
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *   Show table for lines
	 *
	 *   @param		TCPDF		$pdf     		Object PDF
	 *   @param		float|int	$tab_top		Top position of table
	 *   @param		float|int	$tab_height		Height of table (rectangle)
	 *   @param		int			$nexY			Y
	 *   @param		Translate	$outputlangs	Langs object
	 *   @param		int			$hidetop		Hide top bar of array
	 *   @param		int			$hidebottom		Hide bottom bar of array
	 *   @return	void
	 */
	protected function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop = 0, $hidebottom = 0)
	{
		global $conf;

		$hidebottom = 0;
		if ($hidetop) {
			$hidetop = -1;
		}

		// Output Rect
		$this->printRect($pdf, $this->marge_gauche, $tab_top, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $tab_height + 3);
	}

	/**
	 * Show footer signature of page
	 * @param   TCPDF       $pdf            Object PDF
	 * @param   int         $tab_top        tab height position
	 * @param   int         $tab_height     tab height
	 * @param   Translate   $outputlangs    Object language for output
	 * @return void
	 */
	protected function tabSignature(&$pdf, $tab_top, $tab_height, $outputlangs)
	{
		$pdf->SetDrawColor(128, 128, 128);
		$posmiddle = $this->marge_gauche + round(($this->page_largeur - $this->marge_gauche - $this->marge_droite) / 2);
		$posy = $tab_top + $tab_height + 3 + 3;

		$pdf->SetXY($this->marge_gauche, $posy);
		$pdf->MultiCell($posmiddle - $this->marge_gauche - 5, 5, $outputlangs->transnoentities("ContactNameAndSignature", $this->emetteur->name), 0, 'L', 0);

		$pdf->SetXY($this->marge_gauche, $posy + 5);
		$pdf->MultiCell($posmiddle - $this->marge_gauche - 5, 20, '', 1);

		$pdf->SetXY($posmiddle + 5, $posy);
		$pdf->MultiCell($this->page_largeur - $this->marge_droite - $posmiddle - 5, 5, $outputlangs->transnoentities("ContactNameAndSignature", $this->recipient->name), 0, 'L', 0);

		$pdf->SetXY($posmiddle + 5, $posy + 5);
		$pdf->MultiCell($this->page_largeur - $this->marge_droite - $posmiddle - 5, 20, '', 1);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *  Show top header of page.
	 *
	 *  @param	TCPDF		$pdf     		Object PDF
	 *  @param  Contrat		$object     	Object to show
	 *  @param  int	    	$showaddress    0=no, 1=yes
	 *  @param  Translate	$outputlangs	Object lang for output
	 *  @param  Translate	$outputlangsbis	Object lang for output bis
	 *  @param	string		$titlekey		Translation key to show as title of document
	 *  @return	float|int                   Return topshift value
	 */
	protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs, $outputlangsbis = null, $titlekey = "Contract")
	{
		// phpcs:enable
		global $conf;

		$top_shift = 0;

		$ltrdirection = 'L';
		if ($outputlangs->trans("DIRECTION") == 'rtl') {
			$ltrdirection = 'R';
		}

		$outputlangs->loadLangs(array("main", "dict", "contract", "companies"));

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $default_font_size + 3);

		$w = 100;

		$posy = $this->marge_haute;
		$posx = $this->page_largeur - $this->marge_droite - $w;

		$pdf->SetXY($this->marge_gauche, $posy);

		// Logo
		// if (!getDolGlobalString('PDF_DISABLE_MYCOMPANY_LOGO')) {
		// 	$logo = $conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
		// 	if ($mysoc->logo && is_readable($logo)) {
		// 		$height = pdf_getPDFHeightForImage($pdf, $logo);
		// 		$pdf->Image($logo, $this->marge_gauche, $posy, 0, $height); // width=0 (auto)
		// 	} else {
		// 		$pdf->MultiCell(100, 4, $outputlangs->transnoentities($this->emetteur->name), 0, 'L');
		// 	}
		// }
		$pdf->MultiCell(100, 4, $outputlangs->transnoentities("CONTRATO DE ARRENDAMIENTO DE VIVIENDA"), 0, 'L');

		$pdf->SetFont('', 'B', $default_font_size + 3);
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);
		$title = $outputlangs->transnoentities($titlekey);
		$title .= ' ' . $outputlangs->convToOutputCharset($object->ref);
		if ($object->statut == $object::STATUS_DRAFT) {
			$title .= ' (' . $outputlangs->transnoentities("Draft") . ')';
		}
		$pdf->MultiCell($w, 3, $title, '', 'R');

		$pdf->SetFont('', 'B', $default_font_size);

		$posy += 3;
		$pdf->SetFont('', '', $default_font_size - 1);

		$posy += 4;
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->MultiCell($w, 3, $outputlangs->transnoentities("Date") . " : " . dol_print_date($object->date_contrat, "day", false, $outputlangs, true), '', 'R');

		// if (!getDolGlobalString('MAIN_PDF_HIDE_CUSTOMER_CODE') && $object->thirdparty->code_client) {
		// 	$posy += 4;
		// 	$pdf->SetXY($posx, $posy);
		// 	$pdf->MultiCell($w, 3, $outputlangs->transnoentities("CustomerCode")." : ".dol_htmlentitiesbr($object->thirdparty->code_client), '', 'R');
		// }

		// if ($showaddress) {
		// 	// Sender properties
		// 	$carac_emetteur = '';
		// 	// Add internal contact of proposal if defined
		// 	$arrayidcontact = $object->getIdContact('internal', 'SALESREPFOLL');
		// 	if (count($arrayidcontact) > 0) {
		// 		/*
		// 		$object->fetch_user($arrayidcontact[0]);
		// 		$carac_emetteur .= ($carac_emetteur ? "\n" : '') . $outputlangs->transnoentities("Name") . ": " . $object->user->getFullName($outputlangs);
		// 		if ($object->user->office_phone) $carac_emetteur .= "\n" . $outputlangs->transnoentities("Phone") . ": " . $object->user->office_phone;
		// 		if ($object->user->email) $carac_emetteur .= "\n" . $outputlangs->transnoentities("Email") . ": " . $object->user->email;
		// 		*/
		// 	}
		// 	$carac_emetteur .= ($carac_emetteur ? "\n" : '');

		// 	$posy += 5;
		// 	$pdf->SetXY($this->marge_gauche, $posy);
		// 	$pdf->SetFont('', 'B', $default_font_size - 2);
		// 	$pdf->MultiCell(100, 3, $outputlangs->transnoentities("BillFrom"), 0, 'L');
		// 	$posy = $pdf->GetY();

		// 	$pdf->SetXY($this->marge_gauche, $posy);
		// 	$pdf->SetFont('', '', $default_font_size - 2);
		// 	$pdf->MultiCell(100, 4, dol_htmlentitiesbr($this->emetteur->name, 1) . "\n" . dol_htmlentitiesbr($carac_emetteur, 1), 0, 'L');
		// 	$posy = $pdf->GetY();

		// 	// Recipient properties
		// 	$posy += 2;
		// 	$pdf->SetXY($this->marge_gauche + 100, $posy - 5);
		// 	$pdf->SetFont('', 'B', $default_font_size - 2);
		// 	$pdf->MultiCell(100, 3, $outputlangs->transnoentities("BillTo"), 0, 'L');
		// 	$posy = $pdf->GetY();

		// 	$pdf->SetXY($this->marge_gauche + 100, $posy);
		// 	$pdf->SetFont('', '', $default_font_size - 2);

		// 	// If CUSTOMER contact defined on proposal, we use it
		// 	$arrayidcontact = $object->getIdContact('external', 'CUSTOMER');
		// 	if (count($arrayidcontact) > 0) {
		// 		$object->fetch_contact($arrayidcontact[0]);
		// 		$carac_client = $object->contact->getFullName($outputlangs) . "\n";
		// 	}

		// 	if ($object->thirdparty->code_client) {
		// 		$carac_client .= $outputlangs->transnoentities("CustomerCode")." : ".$object->thirdparty->code_client."\n";
		// 	}
		// 	$carac_client .= $object->thirdparty->name . "\n";
		// 	$carac_client .= $object->thirdparty->getFullAddress(true, "\n", true);

		// 	if ($object->thirdparty->phone) {
		// 		$carac_client .= "\n" . $outputlangs->transnoentities("Phone") . ": " . $object->thirdparty->phone;
		// 	}
		// 	if ($object->thirdparty->email) {
		// 		$carac_client .= "\n" . $outputlangs->transnoentities("Email") . ": " . $object->thirdparty->email;
		// 	}

		// 	$pdf->MultiCell(100, 4, $carac_client, 0, 'L');
		// }

		$pdf->SetTextColor(0, 0, 0);

		return $top_shift;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *   	Show footer of page. Need this->emetteur object
	 *
	 *   	@param	TCPDF		$pdf     			PDF
	 * 		@param	Contrat		$object				Object to show
	 *      @param	Translate	$outputlangs		Object lang for output
	 *      @param	int			$hidefreetext		1=Hide free text
	 *      @return	integer
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		$showdetails = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS', 0);
		
		return pdf_pagefoot($pdf, $outputlangs, 'CONTRACT_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext, $this->page_largeur, $this->watermark);
	}

	/**
	 * Draw footer (line only) on all pages.
	 * TCPDF built-in footer is disabled, so we post-process pages.
	 *
	 * @param TCPDF      $pdf
	 * @param Contrat    $object
	 * @param Translate  $outputlangs
	 * @return void
	 */
	protected function _addFooterToPages(&$pdf, $object, $outputlangs)
	{
		$numpages = (int) $pdf->getPage();

		// Avoid any auto page break side effects while drawing the footer
		$pdf->SetAutoPageBreak(false, 0);

		$pdf->SetDrawColor(128, 128, 128);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '', 8);

		for ($i = 1; $i <= $numpages; $i++) {
			$pdf->setPage($i);

			// Use real page dimensions and margins (avoid unit mismatches)
			$pageW = (float) $pdf->getPageWidth();
			$pageH = (float) $pdf->getPageHeight();
			$margins = $pdf->getMargins();
			$left = isset($margins['left']) ? (float) $margins['left'] : (float) $this->marge_gauche;
			$right = isset($margins['right']) ? (float) $margins['right'] : (float) $this->marge_droite;
			$breakMargin = (float) $pdf->getBreakMargin();
			if ($breakMargin <= 0) {
				$breakMargin = 20;
			}
			$footerTopY = $pageH - $breakMargin;
			$lineY = $footerTopY + 1.5;
			$textY = $footerTopY + 3.5;

			$pdf->Line(
				$left,
				$lineY,
				$pageW - $right,
				$lineY
			);
		}

		// Restore current page to the last one
		$pdf->setPage($numpages);
	}

	/**
	 * Draw page number on the right margin with vertical orientation.
	 *
	 * @param TCPDF $pdf
	 * @return void
	 */
	protected function _addPageNumberRightMarginVertical(&$pdf)
	{
		$numpages = (int) $pdf->getPage();

		// Avoid any auto page break side effects while drawing
		$pdf->SetAutoPageBreak(false, 0);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '', 8);

		for ($i = 1; $i <= $numpages; $i++) {
			$pdf->setPage($i);

			$pageW = (float) $pdf->getPageWidth();
			$pageH = (float) $pdf->getPageHeight();
			$margins = $pdf->getMargins();
			$right = isset($margins['right']) ? (float) $margins['right'] : 10;

			// Position 5cm (50mm) from bottom
			$distFromBottom = 15; // 5cm in mm
			$posY = $pageH - $distFromBottom;

			$pageText = 'Página ' . $i;
			$textW = $pdf->GetStringWidth($pageText);
			$y = $posY - ($textW / 2);

			// Place inside the right margin (at least 2mm from the edge)
			$pad = 2;
			$x = $pageW - max($pad, ($right / 2));

			if (method_exists($pdf, 'StartTransform') && method_exists($pdf, 'Rotate') && method_exists($pdf, 'StopTransform')) {
				$pdf->StartTransform();
				$pdf->Rotate(90, $x, $y);
				$pdf->Text($x, $y, $pageText);
				$pdf->StopTransform();
			} else {
				// Fallback (non-rotated) if transforms are not available
				$pdf->Text($x - 3, $posY, $pageText);
			}
		}

		$pdf->setPage($numpages);
	}

	/**
	 * Convert a number to Spanish text representation
	 *
	 * @param float $number Number to convert
	 * @return string Spanish text representation of the number
	 */
	protected function convertNumberToSpanishText($number)
	{
		$number = (int) $number;
		
		$unidades = array(
			0 => 'cero',
			1 => 'uno',
			2 => 'dos',
			3 => 'tres',
			4 => 'cuatro',
			5 => 'cinco',
			6 => 'seis',
			7 => 'siete',
			8 => 'ocho',
			9 => 'nueve',
			10 => 'diez',
			11 => 'once',
			12 => 'doce',
			13 => 'trece',
			14 => 'catorce',
			15 => 'quince',
			16 => 'dieciséis',
			17 => 'diecisiete',
			18 => 'dieciocho',
			19 => 'diecinueve',
			20 => 'veinte'
		);

		$decenas = array(
			2 => 'veinti',
			3 => 'treinta',
			4 => 'cuarenta',
			5 => 'cincuenta',
			6 => 'sesenta',
			7 => 'setenta',
			8 => 'ochenta',
			9 => 'noventa'
		);

		$centenas = array(
			1 => 'ciento',
			2 => 'doscientos',
			3 => 'trescientos',
			4 => 'cuatrocientos',
			5 => 'quinientos',
			6 => 'seiscientos',
			7 => 'setecientos',
			8 => 'ochocientos',
			9 => 'novecientos'
		);

		if ($number == 0) {
			return $unidades[0];
		}

		if ($number < 0) {
			return 'menos ' . $this->convertNumberToSpanishText(abs($number));
		}

		$texto = '';

		if ($number >= 1000000) {
			$millones = (int) ($number / 1000000);
			$texto .= $this->convertNumberToSpanishText($millones) . ' millón' . ($millones > 1 ? 'es' : '');
			$number %= 1000000;
			if ($number > 0) {
				$texto .= ' ';
			}
		}

		if ($number >= 1000) {
			$miles = (int) ($number / 1000);
			$texto .= $this->convertNumberToSpanishText($miles) . ' mil';
			$number %= 1000;
			if ($number > 0) {
				$texto .= ' ';
			}
		}

		if ($number >= 100) {
			$cent = (int) ($number / 100);
			$texto .= $centenas[$cent];
			$number %= 100;
			if ($number > 0) {
				$texto .= ' ';
			}
		}

		if ($number >= 20) {
			$dec = (int) ($number / 10);
			$texto .= $decenas[$dec];
			$number %= 10;
			if ($number > 0) {
				$texto .= ' y ' . $unidades[$number];
			}
		} elseif ($number > 0) {
			$texto .= $unidades[$number];
		}

		return trim($texto);
	}
}

