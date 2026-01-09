<?php
/**
 * \file    custom/zonajob/scripts/migrate_photos_to_dolibarr.php
 * \ingroup zonajob
 * \brief   Migration script to move photos from ZonaJob storage to standard Dolibarr documents
 *
 * This script helps migrate existing photos stored in ZonaJob's custom location
 * to the standard Dolibarr document directory for commandes.
 *
 * Usage: php migrate_photos_to_dolibarr.php [--dry-run] [--verbose]
 */

// Prevent direct call
if (!defined('NOREQUIREUSER')) {
    define('NOREQUIREUSER', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREDB')) {
    define('NOREQUIREDB', '1');
}

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists(__DIR__."/../../main.inc.php")) {
    $res = @include __DIR__."/../../main.inc.php";
}
if (!$res && file_exists(__DIR__."/../../../main.inc.php")) {
    $res = @include __DIR__."/../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails\n");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/zonajob/class/zonajobphoto.class.php';

// Get parameters
$dry_run = in_array('--dry-run', $argv);
$verbose = in_array('--verbose', $argv);

// Statistics
$stats = array(
    'total' => 0,
    'migrated' => 0,
    'errors' => 0,
    'skipped' => 0,
);

if (php_sapi_name() != 'cli') {
    die("This script must be run from command line\n");
}

echo "\n";
echo "========================================\n";
echo "ZonaJob Photos Migration Script\n";
echo "========================================\n";
echo ($dry_run ? "[DRY RUN MODE]\n" : "[PRODUCTION MODE]\n");
echo "\n";

// Fetch all photos from zonajob_photo table
$sql = "SELECT p.rowid, p.fk_commande, p.filename, p.filepath, p.photo_type";
$sql .= " FROM ".MAIN_DB_PREFIX."zonajob_photo p";
$sql .= " ORDER BY p.fk_commande, p.rowid";

$result = $db->query($sql);

if (!$result) {
    echo "Error: Cannot query photos table\n";
    exit(1);
}

$num = $db->num_rows($result);
echo "Found $num photos to process\n\n";

$stats['total'] = $num;

if ($num == 0) {
    echo "No photos to migrate.\n";
    exit(0);
}

// Process each photo
$i = 0;
while ($i < $num) {
    $obj = $db->fetch_object($result);
    
    $photo_id = $obj->rowid;
    $fk_commande = $obj->fk_commande;
    $old_filename = $obj->filename;
    $old_filepath = $obj->filepath;
    $photo_type = $obj->photo_type;
    
    if ($verbose) {
        echo "Processing photo ID $photo_id for order ID $fk_commande...\n";
    }
    
    // Load order to get reference
    $order = new Commande($db);
    if ($order->fetch($fk_commande) < 0) {
        echo "  ERROR: Cannot load order $fk_commande\n";
        $stats['errors']++;
        $i++;
        continue;
    }
    
    // Determine new path
    $new_dir = $conf->commande->dir_output . '/' . $order->ref;
    
    // Check if old file exists
    if (!file_exists($old_filepath)) {
        if ($verbose) {
            echo "  SKIPPED: Old file does not exist: $old_filepath\n";
        }
        $stats['skipped']++;
        $i++;
        continue;
    }
    
    // Create new directory if needed
    if (!is_dir($new_dir)) {
        if (!$dry_run) {
            dol_mkdir($new_dir);
        }
        if ($verbose) {
            echo "  Created directory: $new_dir\n";
        }
    }
    
    // Generate new filename
    $ext = pathinfo($old_filename, PATHINFO_EXTENSION);
    $new_filename = 'photo_' . $photo_type . '_' . dechex(filemtime($old_filepath)) . '.' . $ext;
    $new_filepath = $new_dir . '/' . $new_filename;
    
    // Check if already exists at destination
    if (file_exists($new_filepath)) {
        if ($verbose) {
            echo "  SKIPPED: File already exists at: $new_filepath\n";
        }
        $stats['skipped']++;
        $i++;
        continue;
    }
    
    // Copy file to new location
    if (!$dry_run) {
        if (!copy($old_filepath, $new_filepath)) {
            echo "  ERROR: Failed to copy file to $new_filepath\n";
            $stats['errors']++;
            $i++;
            continue;
        }
    }
    
    // Update database with new path
    if (!$dry_run) {
        $sql_update = "UPDATE ".MAIN_DB_PREFIX."zonajob_photo";
        $sql_update .= " SET filepath = '".$db->escape($new_filepath)."'";
        $sql_update .= " WHERE rowid = $photo_id";
        
        if (!$db->query($sql_update)) {
            echo "  ERROR: Failed to update database for photo $photo_id\n";
            $stats['errors']++;
            $i++;
            continue;
        }
    }
    
    echo "  MIGRATED: $old_filename → $new_filename\n";
    $stats['migrated']++;
    
    $i++;
}

// Summary
echo "\n";
echo "========================================\n";
echo "Migration Summary\n";
echo "========================================\n";
echo "Total photos: " . $stats['total'] . "\n";
echo "Migrated: " . $stats['migrated'] . "\n";
echo "Skipped: " . $stats['skipped'] . "\n";
echo "Errors: " . $stats['errors'] . "\n";
echo "========================================\n";

if ($dry_run) {
    echo "\nDry run completed. No changes made.\n";
    echo "Run without --dry-run to apply changes.\n";
} else {
    echo "\nMigration completed.\n";
}

echo "\n";

exit($stats['errors'] > 0 ? 1 : 0);
