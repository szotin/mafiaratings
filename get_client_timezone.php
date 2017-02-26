<?php

if (isset($_REQUEST['offset'])) { 
    $minutes = $_REQUEST['offset']; 
    echo 'GMT offset (in minutes, from the browser): '. $minutes .'<br />\n'; 
    echo 'GMT: '. gmdate("Y-m-d H:i:s") .'<br />\n'; 
     
    $local = gmmktime(gmdate("H"),gmdate("i")-$minutes); // adjust GMT by client's offset 
     
    echo 'Calculated client\'s date/time: ' . gmdate("Y-m-d h:i:s a",$local) .'<br />\n'; 
} else { 
    echo "<script>\n"; 
    echo "var d = new Date();\n"; 
    echo "location.href=\"${_SERVER['SCRIPT_NAME']}?offset=\" + d.getTimezoneOffset();\n"; 
    echo "</script>\n"; 
    exit(); 
} 

?>