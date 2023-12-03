<?php
session_start();
include 'vendor/autoload.php';
include "src/DotEnv.php";
(new DotEnv(__DIR__ . '/.env'))->load();
include "includes/connexion_db.php";

if(isset($_SESSION['connected']) && isset($_SESSION['userId']) && isset($_GET['idFile']) ){
    if($_SESSION['connected'] === true && !empty($_SESSION['userId'])){
        $id = (int)$_SESSION['userId'];
        $getUser = $connexion->prepare("SELECT * FROM user WHERE id = :id");
        $getUser->bindParam("id",$id); 
        $getUser->execute();
        $user = $getUser->fetch();

    }else{
        header("Location: index.php");
    }
}else{
    header("Location: index.php");
}

$id = $_GET['idFile'];

$selectAllFilesParts = $connexion->prepare("SELECT * FROM files_uploaded fu, files_parts_uploaded fpu WHERE fu.id = fpu.file_id AND fu.id = :id");
$selectAllFilesParts->bindParam(":id", $id);
$selectAllFilesParts->execute();
$infos = $selectAllFilesParts->fetchAll();

$botToken = getenv('BOT_TOKEN');

// Créer un fichier zip temporaire
$zipFile = tempnam(sys_get_temp_dir(), 'download_files');
$zip = new \ZipArchive();
$zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

// Variable pour stocker le contenu concaténé
$concatenatedContent = '';

foreach ($infos as $info) {
    $channelId = $info['channel_id'];
    $messageId = $info['message_id'];

    // Construire l'URL pour récupérer le message
    $url = "https://discord.com/api/v10/channels/{$channelId}/messages/{$messageId}";

    // Effectuer la requête GET
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        "Authorization: Bot $botToken",
    ]);

    $response = curl_exec($ch);

    // Vérifier si la requête a réussi
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
        $messageData = json_decode($response, true);

        // Télécharger l'attachement
        $attachments = $messageData['attachments'];
        if (!empty($attachments)) {
            $attachmentUrl = $attachments[0]['url'];
            $attachmentContents = file_get_contents($attachmentUrl);
            // Concaténer le contenu sans le header
            $concatenatedContent .= $attachmentContents;

            // Ajouter le fichier à l'archive zip
           
        } else {
            echo 'Aucun attachement trouvé dans le message.';
        }
    } else {
        echo 'Échec de la requête : ' . $response;
    }

    curl_close($ch);
}
$concatenatedContentDecode = base64_decode($concatenatedContent);
$zip->addFromString($info['name'] . "_" . $info['id'] . "." . $info['extension'], $concatenatedContent);
// Fermer l'archive zip
$zip->close();

// Envoi des en-têtes HTTP pour indiquer que le contenu est un téléchargement
header('Content-Description: File Transfer');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename=downloaded_files.zip');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($zipFile));

// Sortie du contenu de l'archive zip
readfile($zipFile);

// Supprimer le fichier zip temporaire
unlink($zipFile);

// Utilisez $concatenatedContent comme nécessaire
?>
