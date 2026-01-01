<?php
/* Copyright (C) 2024 PuertaSevilla
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       puertasevilla/lib/puertasevilla.lib.php
 * \ingroup    puertasevilla
 * \brief      Library files with common functions for PuertaSevilla
 */

/**
 * Prepare admin pages header
 *
 * @return array
 */
function puertasevillaAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("puertasevilla@puertasevilla");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/puertasevilla/admin/setup.php", 1);
    $head[$h][1] = $langs->trans("Settings");
    $head[$h][2] = 'settings';
    $h++;

    $head[$h][0] = dol_buildpath("/puertasevilla/admin/about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    complete_head_from_modules($conf, $langs, null, $head, $h, 'puertasevilla');

    complete_head_from_modules($conf, $langs, null, $head, $h, 'puertasevilla', 'remove');

    return $head;
}
