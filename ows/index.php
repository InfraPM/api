<?php
if (!empty($_GET)) {
    foreach ($_GET as $key => $value) {
        if (strtolower($key) == 'service' && strtolower($value) == 'wms') {
            require '../wms/index.php';
        }
    }
}
