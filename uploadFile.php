<?php
session_start();
include 'vendor/autoload.php';
include "src/DotEnv.php";
(new DotEnv(__DIR__ . '/.env'))->load();
include "includes/connexion_db.php";

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;

if(isset($_SESSION['connected']) && isset($_SESSION['userId']) ){
    if($_SESSION['connected'] === true && !empty($_SESSION['userId'])){
        $id = (int)$_SESSION['userId'];
        $getUser = $connexion->prepare("SELECT * FROM user WHERE id = :id");
        $getUser->bindParam("id",$id); 
        $user = $getUser->execute();

    }else{
        header("Location: index.php");
    }
}else{
    header("Location: index.php");
}

function getVideoHeader($filePath, $headerSize = 2048)
{
    // Ouvre le fichier en mode binaire
    $fileHandle = fopen($filePath, 'rb');

    // Lis les premiers octets (header) du fichier
    $header = fread($fileHandle, $headerSize);

    // Ferme le fichier
    fclose($fileHandle);

    // Retourne le header
    return $header;
}

function download_mediafire_file($url) {
    //$url = $_POST['mediafire'];
    $ch = curl_init();

    // Configurez les options de cURL
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //curl_setopt($ch, CURLOPT_USERPWD, "votre_identifiant:votre_mot_de_passe");

    // Exécutez la requête
    $contents = curl_exec($ch);

    // Fermez la connexion cURL
    curl_close($ch);

    // Trouvez le lien `a href` qui correspond au fichier
    if (preg_match('/<a[^>]*href=[\'"]?([^\'" >]+)[\'"]?[^>]*id="downloadButton"[^>]*>/', $contents, $matches)) {
        $downloadLink = $matches[1];
        // Extraire le nom du fichier de l'URL
        $fileName = basename(parse_url($downloadLink, PHP_URL_PATH));
        echo 'Le téléchargement du fichier '.$fileName.' est en cours' ;
        $destination = __DIR__ . "/uploads/" . basename($fileName);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $downloadLink);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $contents = curl_exec($ch);
        curl_close($ch);
        file_put_contents($destination, $contents);
        
        echo "Nous traitons l'upload sur Discord";
        // Split the file into parts
        $file_parts = fsplit($destination);

        // Information about the uploaded file
        $uploadInfo = [
            'user_id' => getUserId(),  // Replace with the actual way to get user ID
            'file_name' => $fileName,
            'file_extension' => pathinfo($fileName, PATHINFO_EXTENSION),
            'file_parts' => $file_parts,
        ];

        // Save the information to a JSON file
        saveUploadInfo($uploadInfo);

        
        interactWithDiscordBot();

        echo "File uploaded successfully!";
        $_POST = array();
        unlink($destination);

    } else {
        echo 'Le lien de téléchargement n\'a pas été trouvé.';
    }

}

function fsplit($inputFile, $outputDirectory = "splits/", $chunkSize = 16 * 1024 * 1024) {
    $fileHandle = fopen($inputFile, 'rb');

    $counter = 1;
    $fileParts = [];

    // Read the header of the input file
    $header = getVideoHeader($inputFile, 2048);

    while (!feof($fileHandle)) {
        $chunk = fread($fileHandle, $chunkSize);

        // Save the current file part
        $filePartPath = $outputDirectory . basename($inputFile) . '.part' . $counter;
        $fileNew = fopen($filePartPath, 'w+');

        fwrite($fileNew, $chunk);
        fclose($fileNew);

        // Add the file part path to the list
        $fileParts[] = $filePartPath;
        $counter++;
    }

    fclose($fileHandle);
    return $fileParts;
}

if (isset($_FILES["file"]) || isset($_POST['mediafire'] )) {
    if(!empty($_POST['mediafire'])) {
        // Téléchargez le fichier
        download_mediafire_file($_POST['mediafire']);

    }else if(!empty($_FILES["file"])){

        $file = $_FILES["file"];
    
        if ($file["error"] === UPLOAD_ERR_OK) {
            // Check file size (max 25MB)
            if ($file["size"] <= 2000 * 1024 * 1024) {
                echo "traitement en cours";
                // $file = fopen($file["tmp_name"], "r");
                // $compressedFile = gzcompress(fread($file, filesize($file["tmp_name"])));
                // fclose($file);
    
                
                // Move the uploaded file to a location accessible by your bot
                $destination = __DIR__ . "/uploads/" . basename($file["name"]);
    
                // Enregistrez le fichier compressé
                // file_put_contents($destination, $compressedFile);
                // die;
    
                if (move_uploaded_file($file["tmp_name"], $destination)) {
                    echo "votre fichier a été pris en compte. Nous traitons l'upload sur Discord";
                    // Split the file into parts
                    $file_parts = fsplit($destination);
    
                    // Information about the uploaded file
                    $uploadInfo = [
                        'user_id' => getUserId(),  // Replace with the actual way to get user ID
                        'file_name' => $file['name'],
                        'file_extension' => pathinfo($file['name'], PATHINFO_EXTENSION),
                        'file_parts' => $file_parts,
                    ];
    
                    // Save the information to a JSON file
                    saveUploadInfo($uploadInfo);
    
                    
                    interactWithDiscordBot();
    
                    echo "File uploaded successfully!";
                    $_POST = array();
                    unlink($destination);
    
                    // Rest of your code for sending a message to Discord
                } else {
                    echo "Error moving the file.";
                }
            } else {
                echo "File size exceeds the limit (200MB).";
            }
        } else {
            echo "Error uploading file.";
        }
    }

    $_FILES = array();
    $_POST  = array(); 
    header('Location: uploadFile.php');
} 

function interactWithDiscordBot() {
    $botId = getenv('BOT_ID');
    $message = "<@{$botId}> uploadfile";

    // Replace YOUR_WEBHOOK_URL with the actual webhook URL
    $webhookUrl = getenv('WEBHOOK_URL');
    //Data to be sent in the POST request
    $data = [
        "content" => $message,
    ];

    // Use cURL to send a POST request to the Discord webhook URL
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute the cURL request
    $response = curl_exec($ch);

    // Close cURL session
    curl_close($ch);

    // Check for errors or handle the response as needed
    if ($response === false) {
        echo "Error sending message.";
    } else {
        echo "Message sent successfully!";
    }

}

function getUserId() {
    // Replace this with the actual way to get the user ID from the db
    return '777910706476679228';
}

function saveUploadInfo($uploadInfo) {
    // Specify the path to the JSON file
    $jsonFilePath = __DIR__ . "/upload_info.json";

    // Load existing data from the JSON file if it exists
    $existingData = file_exists($jsonFilePath) ? json_decode(file_get_contents($jsonFilePath), true) : [];

    // Append the new upload information to the existing data
    $existingData[] = $uploadInfo;

    // Save the updated data back to the JSON file
    file_put_contents($jsonFilePath, json_encode($existingData, JSON_PRETTY_PRINT));
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bot Discord</title>
    <link rel="stylesheet" href="src/Css/style.css">
</head>
<body>
    <header>
        <nav class="nav">
            <input type="checkbox" id="nav-check">
            <div class="nav-header">
                <div class="nav-title">
                Discord Storage
                </div>
            </div>
            <div class="nav-btn">
                <label for="nav-check">
                <span></span>
                <span></span>
                <span></span>
                </label>
            </div>
            
            <ul class="nav-list">
                <li><a href="#">Upload</a></li>
                <li><a href="listFilesUser.php">Mes Fichiers</a></li>
                <li><a href="#">Contact</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <h1>Discord Bot File Uploader</h1>
        <form action="" method="post" enctype="multipart/form-data">
            <label for="file">Choose a file (max 60MB):</label>
            <input type="file" name="file" id="file">
            <label for="url">Mediafire Url</label>
            <input type="url" name="mediafire" id="mediafire">
            <button type="submit" name="submit">Upload</button>
        </form>
    </main>
</body>
</html>

