<?php
$GLOBALS['organizrPages'][] = 'plugin_custom_page_here';
function get_page_plugin_custom_page_here($Organizr)
{
    if (!$Organizr) {
        $Organizr = new Organizr();
    }
    if ((!$Organizr->hasDB())) {
        return false;
    }
    if (!$Organizr->qualifyRequest(14, true)) {
        return false;
    }
    $plugin = new Plugin();
    return '';
}
