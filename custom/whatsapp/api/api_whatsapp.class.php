<?php

use \Restler\Lib\Scope;

/**
 * API for WhatsApp module
 */
class WhatsAppApi extends DolibarrApi
{
	/**
	 * @var array   $FIELDS     Fields for WhatsApp
	 */
	static public $FIELDS = array(
		'to',
		'message'
	);

	/**
	 * Constructor
	 */
	public function __construct()
	{
		global $db;
		$this->db = $db;
	}

	/**
	 * Send a WhatsApp message
	 *
	 * @param string $to      Recipient (phone number)
	 * @param string $message Message content
	 * @return array          Status
	 *
	 * @url POST /send
	 */
	public function postSend($to, $message)
	{
		if (!DolibarrApiAccess::$user->admin) {
			throw new DolibarrApiError('Forbidden', 403);
		}

		require_once dol_buildpath('/whatsapp/lib/whatsapp.lib.php');
		$result = whatsapp_send($to, $message);

		if ($result['error']) {
			throw new DolibarrApiError($result['message'], 400);
		}

		return $result;
	}

	/**
	 * Get WhatsApp connection status / QR code
	 *
	 * @return array Status and QR (if available)
	 *
	 * @url GET /status
	 */
	public function getStatus()
	{
		if (!DolibarrApiAccess::$user->admin) {
			throw new DolibarrApiError('Forbidden', 403);
		}

		require_once dol_buildpath('/whatsapp/class/gowaclient.class.php');
		$waClient = new GoWAClient($this->db);
		
		// This will depend on goWA API
		// return $waClient->getStatus(); 
		return array('status' => 'Feature coming soon', 'info' => 'Integrated with goWA');
	}
}
