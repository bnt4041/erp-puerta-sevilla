<?php
/**
 * ZonaJob Diagnostic - Verify VAT configuration and autocomplete setup
 * Access: http://localhost/dolpuerta/custom/zonajob/diagnostic.php
 */

// Load Dolibarr
$res = 0;
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}

if (!$res) {
    die("Cannot load Dolibarr. Ensure this file is in /custom/zonajob/");
}

// Security: require admin or user with order rights
if (empty($user) || empty($user->id)) {
    die("Access denied: no user session");
}

if (empty($user->rights->commande->lire) && empty($user->rights->zonajob->order->read) && !$user->admin) {
    die("Access denied: insufficient permissions");
}

// Output
header('Content-Type: text/html; charset=UTF-8');

$langs->loadLangs(array('admin'));

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ZonaJob Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #0066cc; padding-bottom: 0.5rem; }
        h2 { color: #0066cc; margin-top: 2rem; }
        .section { margin-bottom: 2rem; padding: 1rem; background: #f9f9f9; border-left: 4px solid #0066cc; }
        .ok { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .code { background: #f0f0f0; padding: 0.5rem; border-radius: 4px; font-family: monospace; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        table th, table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #ddd; }
        table th { background: #f0f0f0; font-weight: bold; }
        .btn { display: inline-block; padding: 0.5rem 1rem; background: #0066cc; color: white; text-decoration: none; border-radius: 4px; margin: 0.5rem 0.5rem 0.5rem 0; }
        .btn:hover { background: #005ca8; }
    </style>
</head>
<body>
<div class="container">
    <h1>ZonaJob Diagnostic Report</h1>
    <p>User: <strong><?php echo dol_escape_htmltag($user->login); ?></strong> | Date: <strong><?php echo date('Y-m-d H:i:s'); ?></strong></p>

    <!-- Company Info -->
    <div class="section">
        <h2>1. Company Configuration</h2>
        <?php
        if (is_object($mysoc)) {
            echo '<table>';
            echo '<tr><th>Property</th><th>Value</th></tr>';
            echo '<tr><td>Company Name</td><td>' . dol_escape_htmltag($mysoc->name) . '</td></tr>';
            echo '<tr><td>Country ID</td><td>' . (int) $mysoc->country_id . '</td></tr>';
            echo '<tr><td>Country Code</td><td>' . dol_escape_htmltag($mysoc->country_code) . '</td></tr>';
            echo '<tr><td>Country</td><td>' . dol_escape_htmltag($mysoc->country) . '</td></tr>';
            echo '<tr><td>MAIN_INFO_SOCIETE_COUNTRY</td><td class="code">' . dol_escape_htmltag(getDolGlobalString('MAIN_INFO_SOCIETE_COUNTRY')) . '</td></tr>';
            echo '</table>';

            $countryId = 0;
            if (!empty($mysoc->country_id)) {
                $countryId = (int) $mysoc->country_id;
                echo '<p class="ok">✓ Country ID detected: ' . $countryId . '</p>';
            } else {
                echo '<p class="warning">⚠ Country ID is empty in $mysoc</p>';
            }
        } else {
            echo '<p class="error">✗ $mysoc object not available</p>';
        }
        ?>
    </div>

    <!-- VAT Dictionary -->
    <div class="section">
        <h2>2. VAT Dictionary (c_tva)</h2>
        <?php
        $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '".MAIN_DB_PREFIX."c_tva'";
        $resql = $db->query($sql);
        
        $hasCountryColumn = false;
        if ($resql) {
            $columns = array();
            while ($obj = $db->fetch_object($resql)) {
                $columns[] = $obj->COLUMN_NAME;
                if ($obj->COLUMN_NAME === 'fk_pays') {
                    $hasCountryColumn = true;
                }
            }
            echo '<p><strong>Columns in ' . MAIN_DB_PREFIX . 'c_tva:</strong></p>';
            echo '<p class="code">' . implode(', ', $columns) . '</p>';

            if ($hasCountryColumn) {
                echo '<p class="ok">✓ Column <code>fk_pays</code> exists - country filtering available</p>';
            } else {
                echo '<p class="warning">⚠ Column <code>fk_pays</code> NOT found - will use all active VAT rates</p>';
            }
        } else {
            echo '<p class="error">✗ Cannot query table structure</p>';
        }
        ?>
    </div>

    <!-- Active VAT Rates -->
    <div class="section">
        <h2>3. Active VAT Rates</h2>
        <?php
        $countryId = (int) ($mysoc->country_id ?? 0);
        
        $sql = "SELECT rowid, taux, code, active";
        $sql .= " FROM ".MAIN_DB_PREFIX."c_tva";
        $sql .= " WHERE active = 1";
        if ($countryId > 0 && $hasCountryColumn) {
            $sql .= " AND (fk_pays IS NULL OR fk_pays = 0 OR fk_pays = " . $countryId . ")";
        }
        $sql .= " ORDER BY taux ASC";

        $resql = $db->query($sql);
        if ($resql && $db->num_rows($resql) > 0) {
            echo '<table>';
            echo '<tr><th>ID</th><th>Rate (%)</th><th>Code</th><th>Active</th></tr>';
            while ($obj = $db->fetch_object($resql)) {
                echo '<tr>';
                echo '<td>' . (int) $obj->rowid . '</td>';
                echo '<td><strong>' . number_format((float) $obj->taux, 2) . '%</strong></td>';
                echo '<td>' . dol_escape_htmltag($obj->code) . '</td>';
                echo '<td>' . ((int) $obj->active ? '<span class="ok">✓</span>' : '<span class="error">✗</span>') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p class="error">✗ No active VAT rates found</p>';
        }
        ?>
    </div>

    <!-- AJAX Endpoint Test -->
    <div class="section">
        <h2>4. AJAX Endpoint Test</h2>
        <?php
        $ajaxUrl = dol_buildpath('/zonajob/ajax_product_search.php', 1);
        $testUrl = DOL_URL_ROOT . '/custom/zonajob/ajax_product_search.php?search=test&limit=5';
        
        echo '<p>Endpoint URL: <span class="code">' . dol_escape_htmltag($ajaxUrl) . '</span></p>';
        echo '<p>Test URL: <span class="code">' . dol_escape_htmltag($testUrl) . '</span></p>';
        echo '<p><a href="' . $testUrl . '" class="btn" target="_blank">Test Endpoint (JSON)</a></p>';
        ?>
    </div>

    <!-- Product Search -->
    <div class="section">
        <h2>5. Product Autocomplete Test</h2>
        <?php
        $sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."product WHERE tosell = 1 AND entity IN (".getEntity('product').")";
        $resql = $db->query($sql);
        if ($resql) {
            $obj = $db->fetch_object($resql);
            $productCount = (int) $obj->cnt;
            
            if ($productCount > 0) {
                echo '<p class="ok">✓ ' . $productCount . ' active products found</p>';
            } else {
                echo '<p class="warning">⚠ No active products found</p>';
            }
        } else {
            echo '<p class="error">✗ Cannot query products</p>';
        }
        ?>
    </div>

    <!-- Permissions -->
    <div class="section">
        <h2>6. User Permissions</h2>
        <?php
        echo '<table>';
        echo '<tr><th>Permission</th><th>Granted</th></tr>';
        $perms = array(
            'commande->lire' => !empty($user->rights->commande->lire),
            'commande->creer' => !empty($user->rights->commande->creer),
            'zonajob->order->read' => !empty($user->rights->zonajob->order->read),
            'zonajob->order->create' => !empty($user->rights->zonajob->order->create),
            'produit->lire' => !empty($user->rights->produit->lire),
            'product->lire' => !empty($user->rights->product->lire),
        );
        
        foreach ($perms as $perm => $granted) {
            echo '<tr><td>' . dol_escape_htmltag($perm) . '</td>';
            echo '<td>' . ($granted ? '<span class="ok">✓</span>' : '<span class="error">✗</span>') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        ?>
    </div>

    <!-- Summary -->
    <div class="section" style="background: #e8f5e9;">
        <h2 style="color: green;">Summary</h2>
        <?php
        $allOk = true;
        
        if (empty($mysoc->country_id)) {
            echo '<p class="error">✗ Company country not configured</p>';
            $allOk = false;
        } else {
            echo '<p class="ok">✓ Company country: ' . dol_escape_htmltag($mysoc->country) . '</p>';
        }
        
        if ($productCount > 0) {
            echo '<p class="ok">✓ Products available for autocomplete</p>';
        } else {
            echo '<p class="warning">⚠ No products to search</p>';
        }
        
        if ($hasCountryColumn) {
            echo '<p class="ok">✓ VAT rates filtered by country</p>';
        } else {
            echo '<p class="warning">⚠ VAT rates not filtered (fk_pays column missing)</p>';
        }

        if (!empty($user->rights->commande->creer)) {
            echo '<p class="ok">✓ User can create orders</p>';
        } else {
            echo '<p class="error">✗ User cannot create orders</p>';
            $allOk = false;
        }

        if ($allOk) {
            echo '<p style="font-size: 1.1rem; margin-top: 1rem;"><strong class="ok">✓ All systems ready!</strong></p>';
        } else {
            echo '<p style="font-size: 1.1rem; margin-top: 1rem;"><strong class="warning">⚠ Please fix issues above</strong></p>';
        }
        ?>
    </div>

    <hr style="margin-top: 2rem; opacity: 0.3;">
    <p style="font-size: 0.9rem; color: #666;">
        ZonaJob Diagnostic | Generated at <?php echo date('Y-m-d H:i:s'); ?> | 
        <a href="<?php echo $_SERVER['PHP_SELF']; ?>">Refresh</a>
    </p>
</div>
</body>
</html>
