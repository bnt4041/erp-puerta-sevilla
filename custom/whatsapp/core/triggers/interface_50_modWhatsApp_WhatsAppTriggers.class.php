<?php

require_once DOL_DOCUMENT_ROOT.'/core/class/interfaces.class.php';

/**
 *  Trigger class for WhatsApp module
 */
class InterfaceWhatsAppTriggers extends DolibarrTriggers
{
	public $family = 'whatsapp';
	public $description = "Triggers for WhatsApp module";
	public $version = '1.0.0';
	public $picto = 'whatsapp';

	/**
	 * Function called when a Dolibarr business event occurs.
	 *
	 * @param string        $action     Event code
	 * @param DoliDB        $db         Database handler
	 * @param CommonObject  $object     Object concerned
	 * @param User          $user       User concerned
	 * @param array         $langs      Language library
	 * @param array         $conf       Configuration library
	 * @return int                      0 if OK, <0 if KO
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if ($action == 'WHATSAPP_SENT') {
			dol_syslog("Trigger WHATSAPP_SENT fired for object " . $object->id);
			// You can add logic here if the module itself needs to react to its own event
		}

		return 0;
	}

    /**
     * @deprecated
     */
	public function run_trigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
        return $this->runTrigger($action, $object, $user, $langs, $conf);
	}
}
