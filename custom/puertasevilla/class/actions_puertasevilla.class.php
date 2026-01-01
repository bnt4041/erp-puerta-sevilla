<?php
/*
 * Actions hooks for module PuertaSevilla
 */

class ActionsPuertasevilla
{
	/** @var DoliDB */
	public $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Hook executed before showing extrafields (template extrafields_edit.tpl.php)
	 * Injects a datalist to provide account suggestions via AJAX
	 */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;

		if (empty($conf->puertasevilla->enabled)) {
			return 0;
		}

		$contexts = array();
		if (is_object($hookmanager) && !empty($hookmanager->contextarray) && is_array($hookmanager->contextarray)) {
			$contexts = $hookmanager->contextarray;
		}

		if (!in_array('contractcard', $contexts, true)) {
			return 0;
		}

		// Best-effort: resolve contract id from current object
		$resolvedContractId = 0;
		if (is_object($object)) {
			if (!empty($object->element) && $object->element === 'contrat' && !empty($object->id)) {
				$resolvedContractId = (int) $object->id;
			} elseif (!empty($object->fk_contrat)) {
				$resolvedContractId = (int) $object->fk_contrat;
			} elseif (!empty($object->fk_contract)) {
				$resolvedContractId = (int) $object->fk_contract;
			}
		}

		$resolvedContractIdJs = (int) $resolvedContractId;
		$ajaxUrl = dol_buildpath('/custom/puertasevilla/ajax/ribs_by_contract.php', 1);
		$ajaxUrlJs = dol_escape_js($ajaxUrl);

		$this->resprints = <<<JS
<script>
(function() {
	function getParam(name) {
		try {
			var params = new URLSearchParams(window.location.search);
			return params.get(name);
		} catch (e) {
			return null;
		}
	}

	function resolveOldRibIdToIban(ribId) {
		// Resolve old rowid format to IBAN via a simple endpoint
		// For now, we'll try to extract from the actual RIB lookup
		return fetch("{$ajaxUrlJs}?resolve_rib=" + encodeURIComponent(ribId), { credentials: "same-origin" })
			.then(function(r) { return r.json(); })
			.then(function(data) {
				return (data && data.iban) ? data.iban : ribId;
			})
			.catch(function() { return ribId; });
	}

	function setupDatalist(contractId) {
		// Wait for element to exist
		setTimeout(function() {
			var input = document.getElementById("options_psv_ccc");
			if (!input) {
				console.log("psv_ccc not found, retrying...");
				setTimeout(function() { setupDatalist(contractId); }, 500);
				return;
			}

			var currentValue = input.value;

			// Create or get datalist
			var dataListId = "psv_ccc_datalist";
			var datalist = document.getElementById(dataListId);
			if (!datalist) {
				datalist = document.createElement("datalist");
				datalist.id = dataListId;
				document.body.appendChild(datalist);
			}

			// Set input to use datalist
			input.setAttribute("list", dataListId);
			input.setAttribute("autocomplete", "off");

			// Check if current value is an old rowid (numeric) that needs conversion
			if (currentValue && /^\d+$/.test(currentValue)) {
				// It's a numeric rowid, try to resolve it to IBAN
				resolveOldRibIdToIban(currentValue).then(function(ibanValue) {
					input.value = ibanValue;
					currentValue = ibanValue;
					// Continue with datalist population
					populateDatalist(contractId, datalist, currentValue);
				});
			} else {
				populateDatalist(contractId, datalist, currentValue);
			}
		}, 100);
	}

	function populateDatalist(contractId, datalist, currentValue) {
		// Fetch items from AJAX
		if (contractId) {
			fetch("{$ajaxUrlJs}?contractid=" + encodeURIComponent(contractId), { credentials: "same-origin" })
				.then(function(r) { return r.json(); })
				.then(function(data) {
					datalist.innerHTML = "";
					if (data && data.success && data.items && data.items.length > 0) {
						(data.items || []).forEach(function(item) {
							var option = document.createElement("option");
							option.value = String(item.id);
							option.label = item.label || String(item.id);
							datalist.appendChild(option);
						});
					}
				})
				.catch(function(err) {
					console.error("Error fetching RIBs:", err);
				});
		}
	}

	var contractId = {$resolvedContractIdJs} || getParam("id") || getParam("contractid") || getParam("contratid");
	if (!contractId) {
		var hidden = document.querySelector("input[name='id']");
		if (hidden && hidden.value) contractId = hidden.value;
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", function() {
			if (contractId) setupDatalist(contractId);
		});
	} else {
		if (contractId) setupDatalist(contractId);
	}
})();
</script>
JS;

		return 0;
	}
}
