<?php

class ActionsWhatsApp
{
	/**
	 * Overloading the addMoreActionsButtons function
	 *
	 * @param   array   $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (Invoice, Propal, etc...)
	 * @param   string  $action         Current action
	 * @param   HookManager     $hookmanager    Hook manager instance
	 * @return  int                     0 if OK, 1 if we replace the standard code
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf;

		$contexts = explode(':', $parameters['context']);

		// List of contexts where we want to add the button
		$allowed_contexts = array('invoicecard', 'propalcard', 'ordercard', 'thirdpartycard', 'contactcard');
		
		$found = false;
		foreach ($contexts as $context) {
			if (in_array($context, $allowed_contexts)) {
				$found = true;
				break;
			}
		}

		if ($found && $action == '') {
			$langs->load("whatsapp@whatsapp");

			$phoneNumber = '';
			if (isset($object->phone)) $phoneNumber = $object->phone;
			elseif (isset($object->phone_pro)) $phoneNumber = $object->phone_pro;
			elseif (isset($object->phone_mobile)) $phoneNumber = $object->phone_mobile;
			elseif (method_exists($object, 'fetch_thirdparty')) {
				$object->fetch_thirdparty();
				if (isset($object->thirdparty->phone)) $phoneNumber = $object->thirdparty->phone;
			}

			// Add "Send WhatsApp" button
			$url = dol_buildpath('/whatsapp/whatsapp_card.php', 1) . '?id=' . $object->id . '&objecttype=' . $object->element . '&phone=' . urlencode($phoneNumber);
			
			print '<div class="inline-block divButAction"><a class="butAction" href="' . $url . '">' . $langs->trans("SendWhatsApp") . '</a></div>';
		}

		return 0;
	}

	/**
	 * Overloading the doActions function
	 *
	 * @param   array   $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (Invoice, Propal, etc...)
	 * @param   string  $action         Current action
	 * @param   HookManager     $hookmanager    Hook manager instance
	 * @return  int                     0 if OK, 1 if we replace the standard code
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		// You can handle actions here if needed
		return 0;
	}
}
