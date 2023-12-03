<?php

try{
    $dbname = getenv('DATABASE_NAME');
    $host = getenv('DATABASE_HOST');
    $user = getenv('DATABASE_USER');
    $mdp = getenv('DATABASE_PASSWORD');

    $connexion= new PDO('mysql:host='.$host.';dbname='.$dbname.'', $user, $mdp);
}catch(Exception $e){
    echo "Erreur de connexion.";
    echo $e;
    echo "\n";
}