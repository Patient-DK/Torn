<?php

    require_once('../../userdatabase.php');
    $dbconnection = new Database();
    
    $text = mysqli_real_escape_string($dbconnection->openConnection(),$_GET['term']);
     
    $sql = "SELECT factionName FROM tornfactions WHERE concat(factionID,factionName) LIKE '%$text%' LIMIT 15;";
        
    $results = $dbconnection->query($sql);

    while($faction = mysqli_fetch_assoc($results))
    {
        $displayed[] = $faction['factionName'];
    }
    
    //return json data
    echo json_encode($displayed);