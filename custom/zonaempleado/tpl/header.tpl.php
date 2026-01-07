<?php
/* Copyright (C) 2025 Zona Empleado Dev
 *
 * Template for employee zone header
 */

// Don't re-include main.inc.php - it's already loaded by calling page
// Just ensure we have access to global variables
global $langs, $user, $conf, $db, $mysoc, $hookmanager;

// Load translations for this template
$langs->loadLangs(array("zonaempleado@zonaempleado"));

// Security check should be done in calling page, not here
// This template is just for rendering

$title = isset($title) ? $title : $langs->trans('ZonaEmpleadoArea');

// Intentar construir menú superior vía hook si no fue proporcionado
if (!isset($menu_items) || !is_array($menu_items)) {
    $menu_items = array();
}
if (empty($menu_items) && !empty($hookmanager)) {
    $params = array('menu' => &$menu_items, 'user' => $user);
    $hookmanager->executeHooks('getEmployeeZoneMenu', $params);
}

$headerQuickLinks = array();
if (isset($zonaempleado_quick_links) && is_array($zonaempleado_quick_links)) {
    $headerQuickLinks = $zonaempleado_quick_links;
}
if (empty($headerQuickLinks) && function_exists('zonaempleado_get_quick_links')) {
    $headerQuickLinks = zonaempleado_get_quick_links();
}

?>
<!DOCTYPE html>
<html lang="<?php echo $langs->defaultlang; ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo $title; ?> - <?php echo $mysoc->name; ?></title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo DOL_URL_ROOT; ?>/theme/<?php echo $conf->theme; ?>/style.css.php?lang=<?php echo $langs->defaultlang; ?>">
    <link rel="stylesheet" href="<?php echo DOL_URL_ROOT; ?>/custom/zonaempleado/css/zonaempleado.css.php">

    <?php
    // Allow pages to inject additional CSS into the employee zone header
    // Expected format: $GLOBALS['zonaempleado_extra_css'] = array('/custom/xxx/css/file.css.php', ...)
    if (!empty($GLOBALS['zonaempleado_extra_css']) && is_array($GLOBALS['zonaempleado_extra_css'])) {
        foreach ($GLOBALS['zonaempleado_extra_css'] as $cssurl) {
            if (empty($cssurl)) continue;
            $cssurl = (string) $cssurl;
            if ($cssurl[0] !== '/') {
                $cssurl = '/'.$cssurl;
            }
            print '<link rel="stylesheet" href="'.DOL_URL_ROOT.$cssurl.'">';
        }
    }
    ?>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo DOL_URL_ROOT; ?>/theme/common/fontawesome-5/css/all.min.css">
    
    <!-- Favicon -->
    <?php if ($mysoc->logo_squarred_mini) { ?>
        <link rel="shortcut icon" type="image/x-icon" href="<?php echo DOL_URL_ROOT.'/viewimage.php?modulepart=mycompany&file=logos%2Fthumbs%2F'.$mysoc->logo_squarred_mini; ?>"/>
    <?php } ?>

    <!-- JavaScript -->
    <?php if (file_exists(DOL_DOCUMENT_ROOT.'/includes/jquery/js/jquery.min.js')) { ?>
    <script src="<?php echo DOL_URL_ROOT; ?>/includes/jquery/js/jquery.min.js"></script>
    <?php } ?>
    <?php if (file_exists(DOL_DOCUMENT_ROOT.'/includes/jquery/css/base/jquery-ui.min.css')) { ?>
    <link rel="stylesheet" href="<?php echo DOL_URL_ROOT; ?>/includes/jquery/css/base/jquery-ui.min.css">
    <?php } ?>
    <?php if (file_exists(DOL_DOCUMENT_ROOT.'/includes/jquery/js/jquery-ui.min.js')) { ?>
    <script src="<?php echo DOL_URL_ROOT; ?>/includes/jquery/js/jquery-ui.min.js"></script>
    <?php } ?>
    <script src="<?php echo DOL_URL_ROOT; ?>/custom/zonaempleado/js/zonaempleado.js.php"></script>

    <?php
    // Allow pages to inject additional JS into the employee zone header
    // Expected format: $GLOBALS['zonaempleado_extra_js'] = array('/custom/xxx/js/file.js.php', ...)
    if (!empty($GLOBALS['zonaempleado_extra_js']) && is_array($GLOBALS['zonaempleado_extra_js'])) {
        foreach ($GLOBALS['zonaempleado_extra_js'] as $jsurl) {
            if (empty($jsurl)) continue;
            $jsurl = (string) $jsurl;
            if ($jsurl[0] !== '/') {
                $jsurl = '/'.$jsurl;
            }
            print '<script src="'.DOL_URL_ROOT.$jsurl.'"></script>';
        }
    }
    ?>
</head>
<body class="employee-zone-body">

<!-- Employee Zone Header -->
<header class="employee-header">
    <div class="container-fluid">
        <div class="header-content">
            <!-- Logo and Company Name -->
            <div class="header-brand">
                <?php if ($mysoc->logo) { ?>
                    <img src="<?php echo DOL_URL_ROOT.'/viewimage.php?modulepart=mycompany&file=logos%2F'.$mysoc->logo; ?>" alt="<?php echo $mysoc->name; ?>" class="company-logo">
                <?php } else { ?>
                    <span class="company-name"><?php echo $mysoc->name; ?></span>
                <?php } ?>
                <span class="zone-title"><?php echo $langs->trans('ZonaEmpleadoArea'); ?></span>
            </div>

            <!-- Main Navigation -->
            <nav class="main-nav">
                <?php
                if (!empty($menu_items)) {
                    echo '<ul class="nav-list">';
                    foreach ($menu_items as $key => $item) {
                        $active_class = (!empty($item['active'])) ? ' active' : '';
                        $icon = !empty($item['icon']) ? '<i class="'.dol_escape_htmltag($item['icon']).'"></i> ' : '';
                        $label = !empty($item['label']) ? $item['label'] : '';
                        $url = !empty($item['url']) ? (DOL_URL_ROOT.$item['url']) : '#';
                        echo '<li class="nav-item'.$active_class.'">';
                        echo '<a href="'.$url.'" class="nav-link">'.$icon.$label.'</a>';
                        echo '</li>';
                    }
                    echo '</ul>';
                }
                ?>
            </nav>

            <!-- User Menu -->
            <div class="user-menu">
                <div class="dropdown">
                    <button class="user-menu-btn dropdown-toggle" type="button" data-toggle="dropdown">
                        <div class="user-avatar">
                            <?php
                            // Generate user initials
                            $initials = '';
                            if ($user->firstname) $initials .= strtoupper(substr($user->firstname, 0, 1));
                            if ($user->lastname) $initials .= strtoupper(substr($user->lastname, 0, 1));
                            if (empty($initials)) $initials = strtoupper(substr($user->login, 0, 2));
                            echo $initials;
                            ?>
                        </div>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right user-dropdown-menu">
                        <!-- User Info Card -->
                        <div class="user-info-card">
                            <div class="user-info-avatar">
                                <?php echo $initials; ?>
                            </div>
                            <div class="user-info-details">
                                <div class="user-info-name">
                                    <i class="fas fa-star"></i> <?php echo $user->getFullName($langs); ?>
                                    <?php if ($user->admin) { ?>
                                        <span class="user-role">(<?php echo $langs->trans('Administrator'); ?>)</span>
                                    <?php } ?>
                                </div>
                                <div class="user-info-dates">
                                    <div class="user-info-date">
                                        <i class="fas fa-user"></i> <?php echo dol_print_date($user->datec, 'day'); ?>
                                    </div>
                                    <div class="user-info-date">
                                        <i class="fas fa-calendar"></i> <?php echo dol_print_date($user->datec, 'dayhour'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="dropdown-divider" style="border-top: 1px solid #eee; margin: 0.5rem 0;"></div>

                        <?php if (!empty($headerQuickLinks)) { ?>
                        <div class="dropdown-menu-section dropdown-quick-links">
                            <div class="dropdown-section-title">
                                <i class="fas fa-th-large"></i> Accesos Rápidos
                            </div>
                            <div class="dropdown-divider" style="margin: 4px 0; opacity: 0.5; border-top: 1px solid #eee;"></div>
                            <div class="dropdown-section-links">
                                <?php foreach ($headerQuickLinks as $quickLink) {
                                    $linkUrl = !empty($quickLink['url']) ? $quickLink['url'] : '#';
                                    $linkLabel = !empty($quickLink['label']) ? $quickLink['label'] : '';
                                    ?>
                                    <a class="dropdown-item" href="<?php echo dol_escape_htmltag($linkUrl); ?>"<?php echo isset($quickLink['target']) ? ' target="'.dol_escape_htmltag($quickLink['target']).'"' : ''; ?>>
                                        <?php
                                        if (!empty($quickLink['icon'])) {
                                            echo zonaempleado_render_icon($quickLink['icon']);
                                        }
                                        echo $linkLabel;
                                        ?>
                                    </a>
                                <?php } ?>
                            </div>
                            <div class="dropdown-divider" style="border-top: 1px solid #eee; margin: 0.5rem 0;"></div>
                        </div>
                        <?php } ?>

                        <!-- Menu Options -->
                        <div class="dropdown-menu-options">
                            <?php if (!empty($GLOBALS['show_home_link'])) { ?>
                            <a class="dropdown-item" href="<?php echo DOL_URL_ROOT; ?>/custom/zonaempleado/index.php">
                                <i class="fas fa-home"></i> <?php echo $langs->trans('Home'); ?>
                            </a>
                            <div class="dropdown-divider" style="border-top: 1px solid #eee; margin: 0.5rem 0;"></div>
                            <?php } ?>
                            <a class="dropdown-item" href="<?php echo DOL_URL_ROOT; ?>/custom/zonaempleado/usercard.php">
                                <i class="fas fa-id-card"></i> Perfil
                            </a>
                            <a class="dropdown-item" href="<?php echo DOL_URL_ROOT; ?>/custom/zonaempleado/companyinfo.php">
                                <i class="fas fa-building"></i> Información de la empresa
                            </a>
                            <div class="dropdown-divider" style="border-top: 1px solid #eee; margin: 0.5rem 0;"></div>
                            <a class="dropdown-item" href="<?php echo DOL_URL_ROOT; ?>/user/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Desconexión
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Navigation -->
    <div class="mobile-nav d-md-none">
        <?php
        if (isset($menu_items) && !empty($menu_items)) {
            echo '<ul class="mobile-nav-list">';
            foreach ($menu_items as $key => $item) {
                $active_class = !empty($item['active']) ? ' active' : '';
                echo '<li class="mobile-nav-item' . $active_class . '">';
                echo '<a href="' . DOL_URL_ROOT . $item['url'] . '" class="mobile-nav-link">';
                if (isset($item['icon'])) {
                    echo '<i class="' . $item['icon'] . '"></i> ';
                }
                echo $item['label'];
                echo '</a>';
                echo '</li>';
            }
            echo '</ul>';
        }
        ?>
    </div>
</header>

<script>
// Inicialización inmediata de dropdowns (fallback sólo si no cargó ZonaEmpleado.js)
(function() {
    if (window.ZonaEmpleado && typeof window.ZonaEmpleado.initDropdowns === 'function') {
        // Ya hay inicialización centralizada, no duplicar listeners
        return;
    }
    function initDropdowns() {
        console.log('Inicializando dropdowns...');
        
        // Buscar todos los botones de dropdown
        const dropdownBtns = document.querySelectorAll('[data-toggle="dropdown"], .dropdown-toggle, .user-menu-btn');
        console.log('Botones encontrados:', dropdownBtns.length);
        
        dropdownBtns.forEach(function(btn) {
            const dropdown = btn.nextElementSibling;
            
            if (dropdown && dropdown.classList.contains('dropdown-menu')) {
                console.log('Dropdown menu encontrado para botón');
                
                btn.addEventListener('click', function(e) {
                    console.log('Click en dropdown button');
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Cerrar otros dropdowns
                    document.querySelectorAll('.dropdown-menu.show').forEach(function(other) {
                        if (other !== dropdown) {
                            other.classList.remove('show');
                        }
                    });
                    
                    // Toggle dropdown actual
                    dropdown.classList.toggle('show');
                    console.log('Dropdown toggled, show:', dropdown.classList.contains('show'));
                });
            }
        });
        
        // Cerrar dropdowns al hacer click fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu.show').forEach(function(dropdown) {
                    dropdown.classList.remove('show');
                });
            }
        });
        
        // Menú móvil
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        const mobileNav = document.querySelector('.mobile-nav');
        
        if (mobileToggle && mobileNav) {
            console.log('Menú móvil encontrado');
            mobileToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                mobileNav.classList.toggle('show');
                
                const icon = this.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-bars');
                    icon.classList.toggle('fa-times');
                }
            });
        }
    }
    
    // Ejecutar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDropdowns);
    } else {
        initDropdowns();
    }
})();
</script>

<!-- Main Content Area -->
<main class="employee-main">
    <div class="container-fluid">
        <?php
        // Show any messages
        if (function_exists('dol_htmloutput_mesg')) {
            dol_htmloutput_mesg('', '', 'ok');
            dol_htmloutput_mesg('', '', 'warning');
            dol_htmloutput_mesg('', '', 'error');
        }
        ?>