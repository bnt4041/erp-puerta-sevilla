<?php
/* Copyright (C) 2024 PuertaSevilla
 */

// Protection to avoid direct call of template
if (empty($context) || !is_object($context)) {
    print "Error, template page can't be called as URL";
    exit;
}
