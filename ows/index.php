<?php
if (!empty($_GET)){
    if(!empty($_GET['service'])){
        if(strtolower($_GET['service'])=='wms'){
            require '../wms/index.php';
        }
    }
    elseif(!empty($_GET['SERVICE'])){
        if(strtolower($_GET['SERVICE'])=='wms'){
            require '../wms/index.php';
        }
    }    
}
?>
