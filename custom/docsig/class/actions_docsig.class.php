<?php
/* Copyright (C) 2026 Document Signature Module
 *
 * Hook for UI integration
 */

/**
 * \file       class/actions_docsig.class.php
 * \ingroup    docsig
 * \brief      Hook overload for Docsig module
 */

/**
 * Class ActionsDocsig
 */
class ActionsDocsig
{
	/** @var DoliDB */
	public $db;
	public $error = '';
	public $errors = array();
	public $results = array();
	public $resprints;

	/**
	 * Constructor
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Hook: add icon into documents list (FormFile::showdocuments)
	 * We append to $morepicto by reference, so it is shown inside the right actions cell.
	 */
	public function showDocuments($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		if (empty($conf->docsig->enabled)) return 0;
		if (empty($user->rights->docsig->envelope->write)) return 0;
		if (empty($parameters['modulepart'])) return 0;
		if (!is_object($object) || empty($object->id)) return 0;

		$modulepart = (string) $parameters['modulepart'];
		$elementType = $this->getElementTypeFromModulePart($modulepart);
		if ($elementType === 'unknown') return 0;

		$title = is_object($langs) ? $langs->trans('DocsigRequestSignature') : 'Request signature';
		$title = dol_escape_htmltag($title);

		$icon = '<i class="fa fa-file-signature"></i>';
		$btn = '<a class="marginleftonly reposition docsig-request-btn" href="#"'
			.' data-element-type="'.dol_escape_htmltag($elementType).'"'
			.' data-element-id="'.((int) $object->id).'"'
			.' data-modulepart="'.dol_escape_htmltag($modulepart).'"'
			.' data-document="__FILENAMEURLENCODED__"'
			.' title="'.$title.'">'.$icon.'</a>';

		// Append to existing morepicto (string passed by reference)
		$parameters['morepicto'] .= $btn;

		return 0;
	}

	/**
	 * Add signature button to document lists
	 * Contexts: invoicelist, orderlist, contractlist, propallist, etc.
	 */
	public function printFieldListOption($parameters, &$object, &$action)
	{
		global $conf, $user, $langs;

		if (!$conf->docsig->enabled) return 0;
		if (!$user->rights->docsig->envelope->write) return 0;

		$contexts = $this->extractContexts($parameters);

		$listContexts = array(
			'invoicelist', 'orderlist', 'contractlist', 'propallist',
			'supplierinvoicelist', 'supplierproposallist', 'fileslib'
		);

		foreach ($listContexts as $context) {
			if ($this->contextMatches($contexts, $context)) {
				// Add column header
				echo '<th class="liste_titre"></th>';
				return 0;
			}
		}

		return 0;
	}

	/**
	 * Add signature button to each row
	 */
	public function printFieldListValue($parameters, &$object, &$action)
	{
		global $conf, $user, $langs;

		if (!$conf->docsig->enabled) return 0;
		if (!$user->rights->docsig->envelope->write) return 0;

		$contexts = $this->extractContexts($parameters);
		
		$listContexts = array(
			'invoicelist', 'orderlist', 'contractlist', 'propallist',
			'supplierinvoicelist', 'supplierproposallist', 'fileslib'
		);

		foreach ($listContexts as $context) {
			if ($this->contextMatches($contexts, $context)) {
				$elementType = $this->getElementTypeFromContext($context);
				$elementId = $object->id;

				echo '<td class="center nowraponall">';
				echo '<a href="#" class="docsig-request-btn" data-element-type="'.$elementType.'" data-element-id="'.$elementId.'" title="Request signature">';
				echo '<i class="fa fa-file-signature"></i>';
				echo '</a>';
				echo '</td>';
				
				return 0;
			}
		}

		return 0;
	}

	/**
	 * Add signature section to document card
	 */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		if (!$conf->docsig->enabled) return 0;
		if (!$user->rights->docsig->envelope->read) return 0;

		$contexts = $this->extractContexts($parameters);
		$cardContexts = array(
			'invoicecard', 'ordercard', 'contractcard', 'propalcard',
			'supplierinvoicecard', 'supplierproposalcard'
		);

		foreach ($cardContexts as $context) {
			if ($this->contextMatches($contexts, $context)) {
				$elementType = $this->getElementTypeFromContext($context);
				$elementId = $object->id;

				// Check if there are signature envelopes
				require_once __DIR__.'/docsigenvelope.class.php';
				
				$sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."docsig_envelope";
				$sql .= " WHERE element_type = '".$this->db->escape($elementType)."'";
				$sql .= " AND element_id = ".(int)$elementId;
				$sql .= " AND entity IN (".getEntity('docsigenvelope').")";
				
				$resql = $this->db->query($sql);
				$nb = 0;
				if ($resql) {
					$obj = $this->db->fetch_object($resql);
					$nb = $obj->nb;
				}

				if ($nb > 0 || $user->rights->docsig->envelope->write) {
					echo '<div class="div-table-responsive-no-min">';
					echo '<table class="border centpercent">';
					echo '<tr><td class="titlefield">';
					echo '<span class="fa fa-file-signature"></span> '.$langs->trans('Signatures');
					echo '</td><td>';
					
					if ($nb > 0) {
						echo '<a href="'.DOL_URL_ROOT.'/custom/docsig/envelope_list.php?element_type='.$elementType.'&element_id='.$elementId.'">';
						echo $nb.' envelope(s)';
						echo '</a>';
					}
					
					if ($user->rights->docsig->envelope->write) {
						echo ' <a href="#" class="docsig-request-btn" data-element-type="'.$elementType.'" data-element-id="'.$elementId.'">';
						echo '<span class="fa fa-plus-circle"></span> Request signature';
						echo '</a>';
					}
					
					echo '</td></tr>';
					echo '</table>';
					echo '</div>';
				}

				return 0;
			}
		}

		return 0;
	}

	/**
	 * Add signatures tab to contact card
	 */
	 public function printTabsHead($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		if (!$conf->docsig->enabled) return 0;

		$contexts = $this->extractContexts($parameters);
		
		if ($this->contextMatches($contexts, 'contactcard')) {
			if ($user->rights->docsig->envelope->read) {
				require_once __DIR__.'/docsignotification.class.php';
				
				$notification = new DocsigNotification($this->db);
				$notifications = $notification->fetchByContact($object->id);
				
				$head = $parameters['head'];
				$h = count($head);
				
				$head[$h][0] = DOL_URL_ROOT.'/custom/docsig/contact_signatures.php?id='.$object->id;
				$head[$h][1] = '<span class="fa fa-file-signature"></span> '.$langs->trans('Signatures').' <span class="badge">'.count($notifications).'</span>';
				$head[$h][2] = 'docsig';
				
				$parameters['head'] = $head;
			}
		}

		return 0;
	}

	/**
	 * Get element type from context
	 */
	private function getElementTypeFromContext($context)
	{
		$map = array(
			'invoicelist' => 'invoice',
			'invoicecard' => 'invoice',
			'orderlist' => 'order',
			'ordercard' => 'order',
			'contractlist' => 'contract',
			'contractcard' => 'contract',
			'propallist' => 'propal',
			'propalcard' => 'propal',
			'supplierinvoicelist' => 'supplier_invoice',
			'supplierinvoicecard' => 'supplier_invoice',
			'supplierproposallist' => 'supplier_proposal',
			'supplierproposalcard' => 'supplier_proposal',
			'fileslib' => 'fileslib',
		);

		return isset($map[$context]) ? $map[$context] : 'unknown';
	}

	private function getElementTypeFromModulePart($modulepart)
	{
		$modulepart = (string) $modulepart;
		$map = array(
			'contract' => 'contract',
			'commande' => 'order',
			'order' => 'order',
			'propal' => 'propal',
			'proposal' => 'propal',
			'facture' => 'invoice',
			'invoice' => 'invoice',
			'supplier_proposal' => 'supplier_proposal',
			'supplierinvoice' => 'supplier_invoice',
			'supplier_invoice' => 'supplier_invoice',
		);

		return isset($map[$modulepart]) ? $map[$modulepart] : 'unknown';
	}

	private function extractContexts($parameters)
	{
		if (empty($parameters['context'])) return array();

		$contexts = $parameters['context'];
		if (!is_array($contexts)) {
			$contexts = array($contexts);
		}

		return array_map('strval', $contexts);
	}

	private function contextMatches(array $contexts, $needle)
	{
		foreach ($contexts as $context) {
			if ($context === $needle) return true;
			if (strpos($context, $needle.':') === 0) return true;
		}

		return false;
	}

	/**
	 * Include JS and CSS
	 */
	public function addHtmlHeader($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;

		if (!$conf->docsig->enabled) return 0;

		$this->resprints = '';
		$this->resprints .= '<link rel="stylesheet" href="'.DOL_URL_ROOT.'/custom/docsig/css/docsig.css">' . "\n";
		$this->resprints .= '<script src="'.DOL_URL_ROOT.'/custom/docsig/js/docsig.js"></script>' . "\n";
		$this->resprints .= '<script>' . "\n";
		$this->resprints .= 'var docsigAjaxUrl = \''.dol_escape_js(DOL_URL_ROOT.'/custom/docsig/ajax/').'\';' . "\n";
		$this->resprints .= 'var docsigToken = \''.dol_escape_js(newToken()).'\';' . "\n";
		$this->resprints .= '</script>' . "\n";

		return 0;
	}
}
