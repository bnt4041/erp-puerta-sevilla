<?php

/**
 * Prepare WhatsApp settings
 */
function whatsapp_admin_prepare_head()
{
	global $langs, $conf;

	return array(
		array(dol_buildpath('/whatsapp/admin/setup.php', 1).'?tab=settings', $langs->trans("Settings"), 'settings'),
		array(dol_buildpath('/whatsapp/admin/setup.php', 1).'?tab=local', $langs->trans("LocalService"), 'local'),
		array(dol_buildpath('/whatsapp/admin/setup.php', 1).'?tab=qr', $langs->trans("WhatsAppQR"), 'qr'),
        array(dol_buildpath('/whatsapp/admin/setup.php', 1).'?tab=test', "Test & Debug", 'test')
	);
}

/**
 * Check if local goWA service is running
 * 
 * @return bool
 */
function whatsapp_local_is_running()
{
    // Check if port 3000 is open locally
    $connection = @fsockopen('127.0.0.1', 3000, $errno, $errstr, 1);
    
    if (is_resource($connection)) {
        fclose($connection);
        return true;
    }
    
    return false;
}

/**
 * Start local goWA service
 * 
 * @return string Process output or error
 */
function whatsapp_local_start()
{
	if (whatsapp_local_is_running()) return "Already running";
	
	$module_path = dol_buildpath('/whatsapp/', 0);
	$bin = $module_path . "bin/gowa";
	$storage = $module_path . "storages/whatsapp.db";
	$log = $module_path . "storages/gowa.log";
	
	// Ensure storages directory exists and is writable
	if (!is_dir($module_path . "storages")) {
		mkdir($module_path . "storages", 0777, true);
	}
	
	// Determine Webhook URL (localhost logic)
    // Assuming standard layout. If not, user can change it in settings later if we make it configurable.
    // For local service, we point to our own webhook.
    $webhookUrl = "http://localhost/custom/whatsapp/public/webhook.php";
	
	$cmd = "cd " . escapeshellarg($module_path) . " && nohup " . escapeshellarg($bin) . " rest --db-uri=\"file:" . $storage . "?_foreign_keys=on\" --webhook=\"" . $webhookUrl . "\" > " . escapeshellarg($log) . " 2>&1 &";
	exec($cmd);
	
	sleep(2);
	
	if (whatsapp_local_is_running()) {
		return "Started with webhook: " . $webhookUrl;
	} else {
		$logContent = file_exists($log) ? file_get_contents($log) : "Log file not found";
		return "Failed to start. Log: " . substr($logContent, 0, 500);
	}
}

/**
 * Stop local goWA service
 */
/**
 * Stop local goWA service
 */
function whatsapp_local_stop()
{
	// Find PIDs safely
	$pids = array();
	// Try finding processes associated with our specific binary
    $cmd_pgrep = "pgrep -f 'bin/gowa'";
	exec($cmd_pgrep, $pids);
	
	if (!empty($pids)) {
		foreach ($pids as $pid) {
			if (is_numeric($pid)) {
                // Try soft kill first
				exec("kill " . $pid); 
			}
		}
        sleep(1);
        
        // Check if still alive and force kill
        $pids_check = array();
        exec($cmd_pgrep, $pids_check);
        if (!empty($pids_check)) {
            foreach ($pids_check as $pid) {
                if (is_numeric($pid)) {
                    exec("kill -9 " . $pid); // Force kill
                }
            }
        }
        
        // Backup: use pkill as a catch-all
        exec("pkill -9 -f 'bin/gowa rest' 2>/dev/null");
	}
	
	sleep(1);
}

/**
 * Send WhatsApp message (Helper for other modules)
 * 
 * @param string $to      Recipient phone number
 * @param string $message Message content
 * @return array          Array with 'error' and 'message'
 */
function whatsapp_send($to, $message) 
{
	global $db;
	require_once dol_buildpath('/whatsapp/class/gowaclient.class.php');
	$waClient = new GoWAClient($db);
	return $waClient->sendMessage($to, $message);
}
