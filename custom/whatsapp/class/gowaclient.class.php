<?php

/**
 * Class to interact with goWA API
 */
class GoWAClient
{
	private $db;
	private $url;
	private $token;
	private $instance;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;
		$this->db = $db;
		// Usar helpers de Dolibarr para evitar warnings si la constante no existe
		$this->url = function_exists('getDolGlobalString') ? getDolGlobalString('WHATSAPP_GOWA_URL', '') : (!empty($conf->global->WHATSAPP_GOWA_URL) ? $conf->global->WHATSAPP_GOWA_URL : '');
		$this->token = function_exists('getDolGlobalString') ? getDolGlobalString('WHATSAPP_GOWA_TOKEN', '') : (!empty($conf->global->WHATSAPP_GOWA_TOKEN) ? $conf->global->WHATSAPP_GOWA_TOKEN : '');
		$this->instance = function_exists('getDolGlobalString') ? getDolGlobalString('WHATSAPP_GOWA_INSTANCE', '') : (!empty($conf->global->WHATSAPP_GOWA_INSTANCE) ? $conf->global->WHATSAPP_GOWA_INSTANCE : '');
	}

	/**
	 * Send a text message
	 *
	 * @param string $to      Recipient phone number (with country code)
	 * @param string $message Message content
	 * @return array          Array with 'error' and 'message'
	 */
	public function sendMessage($to, $message)
	{
		if (empty($this->url)) {
			return array('error' => 1, 'message' => 'GoWA URL not configured');
		}

		// WhatsApp no interpreta HTML. Si llega contenido HTML desde otros módulos, convertir a texto plano.
		if (is_string($message) && $message !== '') {
			$message = html_entity_decode($message, ENT_QUOTES, 'UTF-8');

			// Algunos sistemas envuelven URLs en <...> (no es HTML real) y strip_tags() las borraría.
			// Convertir <https://...> o <www...> a texto plano antes de limpiar.
			$message = preg_replace('/<((?:https?:\/\/|www\.)[^>\s]+)>/i', '$1', $message);

			if (preg_match('/<[^>]+>/', $message)) {
				// Links
				$message = preg_replace('/<a[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', '$2: $1', $message);
				// Saltos
				$message = preg_replace('/<br\s*\/?\s*>/i', "\n", $message);
				$message = preg_replace('/<\/(p|div|h[1-6]|tr)>/i', "\n\n", $message);
				// Listas
				$message = preg_replace('/<li[^>]*>/i', "\n• ", $message);
				$message = preg_replace('/<\/(li|ul|ol)>/i', "\n", $message);

				$message = strip_tags($message);
				$message = preg_replace("/\n{3,}/", "\n\n", $message);
				$message = trim($message);
			}
		}

		// Clean phone number (remove +, spaces, etc.)
		$to = preg_replace('/[^0-9]/', '', $to);
		if (strlen($to) < 10) {
			return array('error' => 1, 'message' => 'Invalid phone number (too short)');
		}

		// Verified endpoint
		$endpoint = rtrim($this->url, '/') . '/send/message';
		
		$data = array(
			// 'instance' => $this->instance, // Not required for this version? Or passed as header?
            // Note: The verified payload uses 'phone' not 'to'
			'phone' => $to,
			'message' => $message
		);
        
        // If instance is needed (multi-device), it might need to be passed differently or checked.
        // For now, based on successful test, simple payload works.

		return $this->callAPI($endpoint, $data, 'POST');
	}

	/**
	 * Get QR Code for authentication
	 *
	 * @return array Response with QR data
	 */
	public function getQR()
	{
		if (empty($this->url)) {
			return array('error' => 1, 'message' => 'GoWA URL not configured');
		}

		// goWA uses /app/login endpoint for QR
		$endpoint = rtrim($this->url, '/') . '/app/login';
		$result = $this->callAPI($endpoint, array(), 'GET');
		
		// Handle "Already Logged In" error which comes as 400
		if ($result['error'] == 1 && strpos($result['message'], 'ALREADY_LOGGED_IN') !== false) {
			return array('error' => 0, 'data' => array('logged_in' => true, 'message' => 'Already logged in'));
		}

		if ($result['error'] == 0 && isset($result['data']['results']['qr_link'])) {
			$qrUrl = $result['data']['results']['qr_link'];
			
			// Download the QR image and convert to base64
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $qrUrl);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			$imageData = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			
			if ($httpCode == 200 && $imageData) {
				$base64 = 'data:image/png;base64,' . base64_encode($imageData);
				return array('error' => 0, 'data' => array('qr' => $base64, 'duration' => $result['data']['results']['qr_duration']));
			} else {
				return array('error' => 1, 'message' => 'Failed to download QR image');
			}
		}
		
		// Check if already logged in (case where API returns 200 but message says it)
		if ($result['error'] == 0 && isset($result['data']['message']) && strpos($result['data']['message'], 'already') !== false) {
			return array('error' => 0, 'data' => array('logged_in' => true, 'message' => $result['data']['message']));
		}
		
		return $result;
	}



	/**
	 * Get session status
	 *
	 * @return array Response with status
	 */
	public function getStatus()
	{
		if (empty($this->url)) {
			return array('error' => 1, 'message' => 'GoWA URL not configured');
		}

		$endpoint = rtrim($this->url, '/') . '/app/status';
		$data = array('instance' => $this->instance); // Keep passing instance just in case

		return $this->callAPI($endpoint, $data);
	}

	/**
	 * Get connected devices/user info
	 *
	 * @return array Response with devices info
	 */
	public function getDevices()
	{
		if (empty($this->url)) {
			return array('error' => 1, 'message' => 'GoWA URL not configured');
		}

		$endpoint = rtrim($this->url, '/') . '/app/devices';
		return $this->callAPI($endpoint, array(), 'GET');
	}

	/**
	 * Call the goWA API using native cURL (allows localhost)
	 *
	 * @param string $url      Endpoint URL
	 * @param array  $data     Data to send (for POST)
	 * @param string $method   HTTP method (GET or POST)
	 * @return array           Response
	 */
	private function callAPI($url, $data = array(), $method = 'GET')
	{
		$ch = curl_init();
		
		if ($method == 'POST' && !empty($data)) {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}
		
		$headers = array(
			'Content-Type: application/json',
			'Accept: application/json'
		);
		
		if (!empty($this->token)) {
			$headers[] = 'Authorization: Bearer ' . $this->token;
		}
		
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);
		
		if ($error) {
			return array('error' => 1, 'message' => 'cURL Error: ' . $error);
		}
		
		if ($httpCode >= 200 && $httpCode < 300) {
			return array('error' => 0, 'data' => json_decode($response, true));
		} else {
			return array('error' => 1, 'message' => 'API Error: ' . $httpCode . ' - ' . $response);
		}
	}
}

