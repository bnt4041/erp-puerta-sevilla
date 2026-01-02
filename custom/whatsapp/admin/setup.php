<?php

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once '../lib/whatsapp.lib.php';
require_once '../class/gowaclient.class.php';
require_once '../class/gowainstaller.class.php';

// Load languages
$langs->loadLangs(array("admin", "whatsapp"));

if (!$user->admin) accessforbidden();

$action = GETPOST('action', 'alpha');
$tab = GETPOST('tab', 'alpha') ? GETPOST('tab', 'alpha') : 'settings';

/*
 * Actions
 */

if ($action == 'update_settings')
{
	$error = 0;
	$db->begin();

	$res1 = dolibarr_set_const($db, "WHATSAPP_GOWA_URL", GETPOST('WHATSAPP_GOWA_URL', 'alpha'), 'chaine', 0, '', $conf->entity);
	$res2 = dolibarr_set_const($db, "WHATSAPP_GOWA_TOKEN", GETPOST('WHATSAPP_GOWA_TOKEN', 'alpha'), 'chaine', 0, '', $conf->entity);
	$res3 = dolibarr_set_const($db, "WHATSAPP_GOWA_INSTANCE", GETPOST('WHATSAPP_GOWA_INSTANCE', 'alpha'), 'chaine', 0, '', $conf->entity);

	if (!$res1 || !$res2 || !$res3) $error++;

	if (!$error)
	{
		$db->commit();
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	}
	else
	{
		$db->rollback();
		setEventMessages($langs->trans("ErrorFailedToSaveEntity"), null, 'errors');
	}
}

if ($action == 'install_gowa') {
	$result = GoWAInstaller::install();
	if ($result['success']) {
		setEventMessages($result['message'], null, 'mesgs');
		// Auto-configure local URL
		if (empty($conf->global->WHATSAPP_GOWA_URL)) {
			dolibarr_set_const($db, "WHATSAPP_GOWA_URL", "http://localhost:3000", 'chaine', 0, '', $conf->entity);
		}
	} else {
		setEventMessages($result['message'], null, 'errors');
	}
}

if ($action == 'start_local') {
	if (!GoWAInstaller::isInstalled()) {
		setEventMessages($langs->trans("GoWANotInstalled"), null, 'errors');
	} else {
		$res = whatsapp_local_start();
		setEventMessages($res, null, 'mesgs');
		if (empty($conf->global->WHATSAPP_GOWA_URL)) {
			dolibarr_set_const($db, "WHATSAPP_GOWA_URL", "http://localhost:3000", 'chaine', 0, '', $conf->entity);
		}
	}
}

if ($action == 'stop_local') {
	whatsapp_local_stop();
	setEventMessages($langs->trans("Stopped"), null, 'mesgs');
}

/*
 * View
 */

$form = new Form($db);

llxHeader('', $langs->trans("WhatsAppSetup"));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("WhatsAppSetup"), $linkback, 'title_setup');

$head = whatsapp_admin_prepare_head();
print dol_get_fiche_head($head, $tab, $langs->trans("WhatsAppSetup"), -1, 'whatsapp');

if ($tab == 'settings') {
    // Instructions & Requirements
    print '<div style="background: #fdfdfd; border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 5px;">';
    print '<h3 style="margin-top:0;">' . $langs->trans("RequirementsAndHelp") . '</h3>';
    print '<ul>';
    print '<li><strong>' . $langs->trans("PortRequirement") . ':</strong> ' . $langs->trans("PortRequirementDesc") . '</li>';
    print '<li><strong>' . $langs->trans("ArchRequirement") . ':</strong> Linux (AMD64/ARM64), macOS, Windows.</li>';
    print '<li><strong>' . $langs->trans("WebhookRequirement") . ':</strong> ' . $langs->trans("WebhookRequirementDesc") . '</li>';
    print '</ul>';
    print '<p class="info">' . $langs->trans("CorsHelp") . '</p>';
    print '</div>';

	print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update_settings">';

	print '<table class="noborder centertable" width="100%">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Parameter").'</td>';
	print '<td>'.$langs->trans("Value").'</td>';
	print '</tr>';

	// goWA API URL
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("WHATSAPP_GOWA_URL").'</td>';
	print '<td><input type="text" name="WHATSAPP_GOWA_URL" value="'.dol_escape_htmltag($conf->global->WHATSAPP_GOWA_URL).'" size="50"></td>';
	print '</tr>';

	// goWA API Token
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("WHATSAPP_GOWA_TOKEN").'</td>';
	print '<td><input type="password" name="WHATSAPP_GOWA_TOKEN" value="'.dol_escape_htmltag($conf->global->WHATSAPP_GOWA_TOKEN).'" size="50"></td>';
	print '</tr>';

	// goWA Instance ID
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("WHATSAPP_GOWA_INSTANCE").'</td>';
	print '<td><input type="text" name="WHATSAPP_GOWA_INSTANCE" value="'.dol_escape_htmltag($conf->global->WHATSAPP_GOWA_INSTANCE).'" size="20"></td>';
	print '</tr>';

	print '</table>';

	print '<div class="center">';
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print '</div>';
	print '</form>';
}

if ($tab == 'local') {
	$isInstalled = GoWAInstaller::isInstalled();
	$isRunning = whatsapp_local_is_running();

	print '<div class="center">';
	
	// Installation status
	print '<h4>' . $langs->trans("InstallationStatus") . '</h4>';
	if ($isInstalled) {
		print '<span class="badge badge-status4">' . $langs->trans("Installed") . '</span>';
	} else {
		print '<span class="badge badge-status8">' . $langs->trans("NotInstalled") . '</span>';
		print '<br><br>';
		print '<p>' . $langs->trans("GoWAInstallInfo") . '</p>';
		print '<p><strong>' . $langs->trans("DetectedArch") . ':</strong> ' . GoWAInstaller::getOS() . ' / ' . GoWAInstaller::getArch() . '</p>';
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?tab=local&action=install_gowa&token='.newToken().'">'.$langs->trans("InstallGoWA").'</a>';
	}

	print '<br><br>';

	// Service status
	if ($isInstalled) {
		print '<h4>' . $langs->trans("ServiceStatus") . '</h4>';
		if ($isRunning) {
			print '<span class="fa fa-circle text-success"></span> ' . $langs->trans("ServiceRunning") . '<br><br>';
			print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?tab=local&action=stop_local&token='.newToken().'">'.$langs->trans("Stop").'</a>';
		} else {
			print '<span class="fa fa-circle text-danger"></span> ' . $langs->trans("ServiceNotRunning") . '<br><br>';
			print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?tab=local&action=start_local&token='.newToken().'">'.$langs->trans("Start").'</a>';
		}
	}
	
	print '<br><br>';
	print '<h4>' . $langs->trans("LocalServiceInfo") . '</h4>';
	print '<p>Binary: ' . dol_buildpath('/whatsapp/bin/gowa', 1) . '</p>';
	print '<p>Storage: ' . dol_buildpath('/whatsapp/storages/', 1) . '</p>';
	print '</div>';
}

if ($tab == 'qr') {
	$waClient = new GoWAClient($db);
	$result = $waClient->getQR();

	print '<div class="center">';
	
	if ($result['error'] == 0 && !empty($result['data']['logged_in'])) {
		// Already logged in
		print '<h3><span class="fa fa-check-circle text-success"></span> ' . $langs->trans("WhatsAppConnected") . '</h3>';
        
        // Fetch connected device info
        $devices = $waClient->getDevices();
        if ($devices['error'] == 0 && !empty($devices['data']['results'])) {
            foreach ($devices['data']['results'] as $device) {
                if (isset($device['device'])) {
                     $phone = explode(':', $device['device'])[0];
                     print '<h4>' . $langs->trans("PhoneNumber") . ': +' . $phone . '</h4>';
                }
            }
        }
		// print '<p>' . $result['data']['message'] . '</p>';
	} elseif ($result['error'] == 0 && !empty($result['data']['qr'])) {
		// Show QR code
		print '<h3>' . $langs->trans("ScanToLink") . '</h3>';
		print '<p>' . $langs->trans("QRExpires") . ': ' . $result['data']['duration'] . 's</p>';
		print '<img src="' . $result['data']['qr'] . '" alt="QR Code" style="border: 10px solid #fff; border-radius: 8px; max-width: 300px;">';
		print '<br><br><a class="butAction" href="'.$_SERVER["PHP_SELF"].'?tab=qr">'.$langs->trans("Refresh").'</a>';
	} else {
		print '<div class="warning">' . $langs->trans("ErrorFetchingQR") . ': ' . ($result['message'] ? $result['message'] : 'Check your settings') . '</div>';
		print '<br><a class="butAction" href="'.$_SERVER["PHP_SELF"].'?tab=qr">'.$langs->trans("Refresh").'</a>';
	}
	print '</div>';
}


if ($tab == 'test') {
	$waClient = new GoWAClient($db);
	$action = GETPOST('action', 'alpha');
	
	if ($action == 'send_test_msg') {
		$to = GETPOST('test_phone', 'alpha');
		$msg = GETPOST('test_msg', 'alpha');
		if ($to && $msg) {
			$res = $waClient->sendMessage($to, $msg);
			if ($res['error']) {
				setEventMessages($res['message'], null, 'errors');
			} else {
				setEventMessages("Message sent!", null, 'mesgs');
			}
		} else {
			setEventMessages("Missing phone or message", null, 'errors');
		}
	}

	print '<div class="center">';
	print '<h3>Test Sending</h3>';
	print '<form method="post" action="'.$_SERVER["PHP_SELF"].'?tab=test">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="send_test_msg">';
	print '<table class="noborder centertable">';
	print '<tr><td>Phone (with code):</td><td><input type="text" name="test_phone" placeholder="34600123456"></td></tr>';
	print '<tr><td>Message:</td><td><input type="text" name="test_msg" placeholder="Hello World"></td></tr>';
	print '<tr><td colspan="2" class="center"><input type="submit" class="button" value="Send Test"></td></tr>';
	print '</table>';
	print '</form>';
	
	print '<br><hr><br>';
    
    // Webhook Log viewer
    $logFile = dol_buildpath('/whatsapp/storages/gowa.log', 0);
    if (file_exists($logFile)) {
        print '<h3>Service Log (Last 20 lines)</h3>';
        print '<pre style="text-align: left; background: #eee; padding: 10px; overflow: auto; max-height: 300px;">';
        $lines = array_slice(file($logFile), -20);
        foreach ($lines as $line) {
            echo htmlspecialchars($line);
        }
        print '</pre>';
    }
    
    // Database Log viewer (ActionComm)
    print '<h3>Recent WhatsApp Events (Database)</h3>';
    $sql = "SELECT id, label, datep, note FROM ".MAIN_DB_PREFIX."actioncomm WHERE type_code='AC_WA' ORDER BY datep DESC LIMIT 5";
    $resql = $db->query($sql);
    if ($resql) {
        print '<table class="noborder centertable" width="80%">';
        print '<tr class="liste_titre"><td>ID</td><td>Label</td><td>Date</td><td>Note</td></tr>';
        while ($obj = $db->fetch_object($resql)) {
            print '<tr class="oddeven">';
            print '<td>'.$obj->id.'</td>';
            print '<td>'.$obj->label.'</td>';
            print '<td>'.dol_print_date($obj->datep, 'dayhour').'</td>';
            print '<td>'.dol_trunc($obj->note, 50).'</td>';
            print '</tr>';
        }
        print '</table>';
    }

	print '</div>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();

