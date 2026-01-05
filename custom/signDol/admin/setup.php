<?php
/* Copyright (C) 2026 DocSig Module
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    htdocs/custom/signDol/admin/setup.php
 * \ingroup docsig
 * \brief   Página de configuración del módulo DocSig
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

global $langs, $user, $conf, $db;

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/docsig.lib.php';

// Translations
$langs->loadLangs(array("admin", "docsig@signDol"));

// Initialize hook manager
$hookmanager->initHooks(array('docsigsetup', 'globalsetup'));

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

$error = 0;
$setupnotempty = 0;

// Access control
if (!$user->admin) {
    accessforbidden();
}

// Form setup class
if (!class_exists('FormSetup')) {
    require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';
}
$formSetup = new FormSetup($db);

/*
 * Configuración TSA (Time Stamping Authority)
 */
$formSetup->newItem('TSASection')->setAsTitle();

$item = $formSetup->newItem('DOCSIG_TSA_URL');
$item->nameText = $langs->trans('DocSigTSAUrl');
$item->helpText = $langs->trans('DocSigTSAUrlHelp');
$item->defaultFieldValue = 'https://freetsa.org/tsr';
$item->cssClass = 'minwidth400';

$item = $formSetup->newItem('DOCSIG_TSA_USER');
$item->nameText = $langs->trans('DocSigTSAUser');
$item->helpText = $langs->trans('DocSigTSAUserHelp');
$item->cssClass = 'minwidth200';

$item = $formSetup->newItem('DOCSIG_TSA_PASS');
$item->nameText = $langs->trans('DocSigTSAPass');
$item->helpText = $langs->trans('DocSigTSAPassHelp');
$item->cssClass = 'minwidth200';
$item->fieldAttr['type'] = 'password';

$item = $formSetup->newItem('DOCSIG_TSA_POLICY');
$item->nameText = $langs->trans('DocSigTSAPolicy');
$item->helpText = $langs->trans('DocSigTSAPolicyHelp');
$item->cssClass = 'minwidth200';

/*
 * Configuración de Tokens y OTP
 */
$formSetup->newItem('TokenOTPSection')->setAsTitle();

$item = $formSetup->newItem('DOCSIG_TOKEN_EXPIRATION_DAYS');
$item->nameText = $langs->trans('DocSigTokenExpirationDays');
$item->helpText = $langs->trans('DocSigTokenExpirationDaysHelp');
$item->defaultFieldValue = '7';
$item->cssClass = 'minwidth100';
$item->fieldAttr['type'] = 'number';
$item->fieldAttr['min'] = '1';
$item->fieldAttr['max'] = '365';

$item = $formSetup->newItem('DOCSIG_OTP_EXPIRATION_MINUTES');
$item->nameText = $langs->trans('DocSigOTPExpirationMinutes');
$item->helpText = $langs->trans('DocSigOTPExpirationMinutesHelp');
$item->defaultFieldValue = '10';
$item->cssClass = 'minwidth100';
$item->fieldAttr['type'] = 'number';
$item->fieldAttr['min'] = '1';
$item->fieldAttr['max'] = '60';

$item = $formSetup->newItem('DOCSIG_OTP_MAX_ATTEMPTS');
$item->nameText = $langs->trans('DocSigOTPMaxAttempts');
$item->helpText = $langs->trans('DocSigOTPMaxAttemptsHelp');
$item->defaultFieldValue = '5';
$item->cssClass = 'minwidth100';
$item->fieldAttr['type'] = 'number';
$item->fieldAttr['min'] = '1';
$item->fieldAttr['max'] = '10';

$item = $formSetup->newItem('DOCSIG_OTP_LENGTH');
$item->nameText = $langs->trans('DocSigOTPLength');
$item->helpText = $langs->trans('DocSigOTPLengthHelp');
$item->defaultFieldValue = '6';
$item->cssClass = 'minwidth100';
$item->fieldAttr['type'] = 'number';
$item->fieldAttr['min'] = '4';
$item->fieldAttr['max'] = '8';

/*
 * Configuración de Firma
 */
$formSetup->newItem('SignatureSection')->setAsTitle();

$signatureModes = array(
    'parallel' => $langs->trans('DocSigModeParallel'),
    'sequential' => $langs->trans('DocSigModeSequential'),
);
$item = $formSetup->newItem('DOCSIG_SIGNATURE_MODE');
$item->nameText = $langs->trans('DocSigSignatureMode');
$item->helpText = $langs->trans('DocSigSignatureModeHelp');
$item->setAsSelect($signatureModes);

$formSetup->newItem('DOCSIG_SIGNATURE_VISIBLE')->setAsYesNo();

$signaturePositions = array(
    'bottom-right' => $langs->trans('DocSigPositionBottomRight'),
    'bottom-left' => $langs->trans('DocSigPositionBottomLeft'),
    'top-right' => $langs->trans('DocSigPositionTopRight'),
    'top-left' => $langs->trans('DocSigPositionTopLeft'),
);
$item = $formSetup->newItem('DOCSIG_SIGNATURE_POSITION');
$item->nameText = $langs->trans('DocSigSignaturePosition');
$item->helpText = $langs->trans('DocSigSignaturePositionHelp');
$item->setAsSelect($signaturePositions);

/*
 * Notificaciones por tipo de objeto
 */
$formSetup->newItem('NotificationSection')->setAsTitle();

$item = $formSetup->newItem('DOCSIG_NOTIFY_FACTURE');
$item->nameText = $langs->trans('DocSigNotifyFacture');
$item->setAsYesNo();

$item = $formSetup->newItem('DOCSIG_NOTIFY_COMMANDE');
$item->nameText = $langs->trans('DocSigNotifyCommande');
$item->setAsYesNo();

$item = $formSetup->newItem('DOCSIG_NOTIFY_PROPAL');
$item->nameText = $langs->trans('DocSigNotifyPropal');
$item->setAsYesNo();

$item = $formSetup->newItem('DOCSIG_NOTIFY_CONTRAT');
$item->nameText = $langs->trans('DocSigNotifyContrat');
$item->setAsYesNo();

$item = $formSetup->newItem('DOCSIG_NOTIFY_FICHINTER');
$item->nameText = $langs->trans('DocSigNotifyFichinter');
$item->setAsYesNo();

/*
 * Plantillas de Email
 */
$formSetup->newItem('EmailTemplatesSection')->setAsTitle();

$item = $formSetup->newItem('DOCSIG_EMAIL_SUBJECT_REQUEST');
$item->nameText = $langs->trans('DocSigEmailSubjectRequest');
$item->helpText = $langs->trans('DocSigEmailSubjectRequestHelp');
$item->defaultFieldValue = 'Solicitud de firma: __REF__';
$item->cssClass = 'minwidth400';

$item = $formSetup->newItem('DOCSIG_EMAIL_SUBJECT_OTP');
$item->nameText = $langs->trans('DocSigEmailSubjectOTP');
$item->helpText = $langs->trans('DocSigEmailSubjectOTPHelp');
$item->defaultFieldValue = 'Código de verificación: __CODE__';
$item->cssClass = 'minwidth400';

$item = $formSetup->newItem('DOCSIG_EMAIL_SUBJECT_COMPLETED');
$item->nameText = $langs->trans('DocSigEmailSubjectCompleted');
$item->helpText = $langs->trans('DocSigEmailSubjectCompletedHelp');
$item->defaultFieldValue = 'Documento firmado: __REF__';
$item->cssClass = 'minwidth400';

$item = $formSetup->newItem('DOCSIG_EMAIL_SUBJECT_REMINDER');
$item->nameText = $langs->trans('DocSigEmailSubjectReminder');
$item->helpText = $langs->trans('DocSigEmailSubjectReminderHelp');
$item->defaultFieldValue = 'Recordatorio de firma: __REF__';
$item->cssClass = 'minwidth400';

/*
 * Seguridad
 */
$formSetup->newItem('SecuritySection')->setAsTitle();

$item = $formSetup->newItem('DOCSIG_PUBLIC_DOWNLOAD_ENABLED');
$item->nameText = $langs->trans('DocSigPublicDownloadEnabled');
$item->helpText = $langs->trans('DocSigPublicDownloadEnabledHelp');
$item->setAsYesNo();

$item = $formSetup->newItem('DOCSIG_RATE_LIMIT_OTP');
$item->nameText = $langs->trans('DocSigRateLimitOTP');
$item->helpText = $langs->trans('DocSigRateLimitOTPHelp');
$item->defaultFieldValue = '3';
$item->cssClass = 'minwidth100';
$item->fieldAttr['type'] = 'number';
$item->fieldAttr['min'] = '1';
$item->fieldAttr['max'] = '10';

/*
 * Certificado Interno
 */
$formSetup->newItem('CertificateSection')->setAsTitle();

$item = $formSetup->newItem('DOCSIG_CERT_KEY_SIZE');
$item->nameText = $langs->trans('DocSigCertKeySize');
$item->helpText = $langs->trans('DocSigCertKeySizeHelp');
$keySizes = array(
    '2048' => '2048 bits',
    '3072' => '3072 bits',
    '4096' => '4096 bits',
);
$item->setAsSelect($keySizes);

$item = $formSetup->newItem('DOCSIG_CERT_VALIDITY_DAYS');
$item->nameText = $langs->trans('DocSigCertValidityDays');
$item->helpText = $langs->trans('DocSigCertValidityDaysHelp');
$item->defaultFieldValue = '3650';
$item->cssClass = 'minwidth100';
$item->fieldAttr['type'] = 'number';
$item->fieldAttr['min'] = '365';
$item->fieldAttr['max'] = '7300';

$item = $formSetup->newItem('DOCSIG_CERT_CN');
$item->nameText = $langs->trans('DocSigCertCN');
$item->helpText = $langs->trans('DocSigCertCNHelp');
$item->defaultFieldValue = 'DocSig Internal CA';
$item->cssClass = 'minwidth300';

$item = $formSetup->newItem('DOCSIG_CERT_ORG');
$item->nameText = $langs->trans('DocSigCertOrg');
$item->helpText = $langs->trans('DocSigCertOrgHelp');
$item->cssClass = 'minwidth300';

$setupnotempty += count($formSetup->items);

/*
 * Actions
 */

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

// Acción para regenerar certificado
if ($action == 'regenerate_cert') {
    $token = GETPOST('token', 'alpha');
    if (!$user->admin) {
        accessforbidden();
    }
    if ($token != newToken()) {
        accessforbidden('Bad CSRF token');
    }
    
    // Eliminar certificado existente
    $certPath = $conf->docsig->dir_output.'/certificates/docsig_internal.crt';
    $keyPath = $conf->docsig->dir_output.'/certificates/docsig_internal.key';
    
    if (file_exists($certPath)) {
        unlink($certPath);
    }
    if (file_exists($keyPath)) {
        unlink($keyPath);
    }
    
    // Regenerar usando la función del módulo
    dol_include_once('/signDol/core/modules/modDocSig.class.php');
    $module = new modDocSig($db);
    $reflectionMethod = new ReflectionMethod('modDocSig', 'generateInternalCertificate');
    $reflectionMethod->setAccessible(true);
    $result = $reflectionMethod->invoke($module);
    
    if ($result) {
        setEventMessages($langs->trans('DocSigCertRegenerated'), null, 'mesgs');
    } else {
        setEventMessages($langs->trans('DocSigCertRegenerateError'), null, 'errors');
    }
}

// Acción para probar TSA
if ($action == 'test_tsa') {
    $token = GETPOST('token', 'alpha');
    if (!$user->admin) {
        accessforbidden();
    }
    if ($token != newToken()) {
        accessforbidden('Bad CSRF token');
    }
    
    dol_include_once('/signDol/class/docsigtsaclient.class.php');
    
    $tsaClient = new DocSigTSAClient($db);
    $testData = hash('sha256', 'DocSig TSA Test - ' . dol_now(), true);
    $result = $tsaClient->getTimestamp($testData);
    
    if ($result['success']) {
        setEventMessages($langs->trans('DocSigTSATestSuccess', $result['timestamp']), null, 'mesgs');
    } else {
        setEventMessages($langs->trans('DocSigTSATestError', $result['error']), null, 'errors');
    }
}

/*
 * View
 */

$page_name = "DocSigSetup";
llxHeader('', $langs->trans($page_name), '', '', 0, 0, '', '', '', 'mod-docsig page-admin-setup');

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Tabs
$head = docsigAdminPrepareHead();

print dol_get_fiche_head($head, 'settings', $langs->trans("ModuleDocSigName"), -1, 'fa-file-signature');

// Setup page
if ($action == 'edit') {
    print $formSetup->generateOutput(true);
    print '<br>';
} elseif (!empty($formSetup->items)) {
    print $formSetup->generateOutput();
    print '<div class="tabsAction">';
    print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit&token='.newToken().'">'.$langs->trans("Modify").'</a>';
    print '</div>';
} else {
    print '<br>'.$langs->trans("NothingToSetup");
}

// Sección de información del certificado
print '<br>';
print load_fiche_titre($langs->trans('DocSigCertificateInfo'), '', '');

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Parameter').'</td>';
print '<td>'.$langs->trans('Value').'</td>';
print '</tr>';

$certPath = $conf->docsig->dir_output.'/certificates/docsig_internal.crt';
$keyPath = $conf->docsig->dir_output.'/certificates/docsig_internal.key';

if (file_exists($certPath)) {
    $certContent = file_get_contents($certPath);
    $certData = openssl_x509_parse($certContent);
    
    print '<tr class="oddeven">';
    print '<td>'.$langs->trans('DocSigCertStatus').'</td>';
    print '<td><span class="badge badge-status4">'.$langs->trans('Active').'</span></td>';
    print '</tr>';
    
    print '<tr class="oddeven">';
    print '<td>'.$langs->trans('DocSigCertSubject').'</td>';
    print '<td>'.htmlspecialchars($certData['subject']['CN'] ?? 'N/A').'</td>';
    print '</tr>';
    
    print '<tr class="oddeven">';
    print '<td>'.$langs->trans('DocSigCertValidFrom').'</td>';
    print '<td>'.dol_print_date($certData['validFrom_time_t'], 'dayhour').'</td>';
    print '</tr>';
    
    print '<tr class="oddeven">';
    print '<td>'.$langs->trans('DocSigCertValidTo').'</td>';
    print '<td>'.dol_print_date($certData['validTo_time_t'], 'dayhour').'</td>';
    print '</tr>';
    
    print '<tr class="oddeven">';
    print '<td>'.$langs->trans('DocSigCertSerial').'</td>';
    print '<td>'.htmlspecialchars($certData['serialNumber'] ?? 'N/A').'</td>';
    print '</tr>';
} else {
    print '<tr class="oddeven">';
    print '<td>'.$langs->trans('DocSigCertStatus').'</td>';
    print '<td><span class="badge badge-status8">'.$langs->trans('NotGenerated').'</span></td>';
    print '</tr>';
}

print '</table>';
print '</div>';

// Botones de acción
print '<div class="tabsAction">';
print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=regenerate_cert&token='.newToken().'" onclick="return confirm(\''.$langs->trans('DocSigConfirmRegenerateCert').'\');">'.$langs->trans("DocSigRegenerateCert").'</a>';
print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=test_tsa&token='.newToken().'">'.$langs->trans("DocSigTestTSA").'</a>';
print '</div>';

// Footer
print dol_get_fiche_end();

llxFooter();
$db->close();
