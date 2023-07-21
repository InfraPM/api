<?php
#authenticate at different permission levels (read, write, etc.)
#if authentication passes then send through
#    else
#        error
require '../../support/pass.php';
require '../../support/dbcon.php';
require '../../support/user.php';
header('Access-Control-Allow-Origin: *'); 
header("Access-Control-Allow-Credentials: true");
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token , Authorization');
#when request = getfeature, etc. just check for read access to datasets and remove datasets where user does not have permission
#when request = transaction must read through xml elements and compile list of all different transactions (in case of mixed transaction)
#then check for permission of each relevant transaction
#i think we'll do the same for create stored query (set up additional field in database?)
if (strtolower($transactionType) == "insert"){
    #check for insert permission on dataset
}
elseif (strtolower($transactionType) == "delete"){
    #check for delete permission on dataset
}
elseif (strtolower($transactionType) == "modify"){
    #check for modify permission on dataset
}
elseif (strtolower($transactionType) == "getfeature"){
    #check for modify permission on dataset
}
else{
    #
}

?>
