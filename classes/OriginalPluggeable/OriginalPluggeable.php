<?php
global $wp_version;
if( $wp_version < '5.5') {
    require_once(__DIR__ . '/OriginalPluggeable-5_4_X.php');
}
else {
    require_once(__DIR__ . '/OriginalPluggeable-5_5_X.php');
}