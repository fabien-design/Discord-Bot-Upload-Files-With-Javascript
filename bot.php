<?php
include 'vendor/autoload.php';

include "src/DotEnv.php";
(new DotEnv(__DIR__ . '/.env'))->load();

include "includes/connexion_db.php";

// // Import classes, install a LSP such as Intelephense to auto complete imports
use Discord\DiscordCommandClient;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Channel;
use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use function React\Async\coroutine;
use Discord\WebSockets\Events;
use Discord\X;


function generateRandomPassword($length = 12) {
    // Définit tous les caractères possibles pour le mot de passe
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ@!';

    // Mélange les caractères
    $shuffledCharacters = str_shuffle($characters);

    // Sélectionne la sous-chaîne de la longueur spécifiée
    $password = substr($shuffledCharacters, 0, $length);

    return $password;
}



// Create a $discord BOT
$discord = new DiscordCommandClient([
    'token' => getenv('BOT_TOKEN'), // Put your Bot token here from https://discord.com/developers/applications/
]);
// Create a $browser with same loop as $discord
$browser = new Browser(null, $discord->getLoop());

$discord->registerCommand('createAccount', function (Message $message, $params) use ($connexion, $browser, $discord) {
    coroutine(function (Message $message, $params) use ($connexion, $browser, $discord) {
        if ($message->author->bot) return;

        try {
            $idAuthor = $message->author->id;

            $CheckUser = $connexion->prepare("SELECT * FROM user WHERE discord_id = :id");
            $CheckUser->bindParam("id",$idAuthor); 
            if($CheckUser->execute()){
                $users = $CheckUser->fetch();
                if(empty($users)){
                    $message->author->sendMessage($message->author . " vous n'avez pas de compte associé" );
                    $password = generateRandomPassword();
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                    $username = $message->author->username;
                    $discordId = $message->author->id;
                    $insertUser = $connexion->prepare('INSERT INTO `user` (`username`, `discord_id`, `password`) VALUES (:user, :discord, :pass)');
                    $insertUser->bindParam(":user", $username);
                    $insertUser->bindParam(":discord", $discordId);
                    $insertUser->bindParam(":pass", $passwordHash);
                    if($insertUser->execute()){

                        $message->author->sendMessage("Username : ".$username. " Password : " .$password);
                        $message->author->sendMessage("**Une fois connecté, changez le mot de passe**");

                    }else{
                        echo "Erreur lors de la création de votre compte.";
                    }

                }else{
                    $message->author->sendMessage($message->author . " vous avez déjà un compte associé sous le nom : ". $users['username']);
                }
            }else{
                $message->channel->sentMessage("Le service de vérification est indisponible");
            }

        } catch (Exception $e) { 
            $message->reply('Unable to acesss the Discord status API :(');
        }
               

    }, $message, $params, $connexion);
});


$discord->registerCommand('discordstatus', function (Message $message, $params) use ($browser) {
    coroutine(function (Message $message, $params) use ($browser) {
        // Ignore messages from any Bots
        
        if ($message->author->bot) return;

        try {

            $message->reply('Discord bot status: ' );

        } catch (Exception $e) { // Request failed
            // Uncomment to debug exceptions
            //var_dump($e);
            
            // Send reply about the discord status
            $message->reply('Unable to acesss the Discord status API :(');
        }
    }, $message, $params);
});


$discord->registerCommand('uploadfile', function (Message $message, $params) use ($connexion, $browser, $discord) {
    coroutine(function () use ($message, $browser, $discord, $connexion) {


        // Get the channel where the command was received
        $channel = $message->channel;

        // Read the JSON file  
        $json = file_get_contents('upload_info.json'); 
        
        // Decode the JSON file 
        $json_data = json_decode($json,true); 
        $storedMessageIds = [];
        foreach ($json_data as $fileInfo) {
            echo "Processing file: {$fileInfo['file_name']}\n";
            $allfileParts = [];
            $i = 1;
            $selectIdUser = $connexion->prepare('SELECT id, username FROM user WHERE discord_id = :id');
            $selectIdUser->bindParam(":id", $fileInfo['user_id']);
            print_r($selectIdUser->execute());
            $idUser = $selectIdUser->fetch();
            $date = new \DateTime();
            $insertUser = $connexion->prepare('INSERT INTO `files_uploaded` (`name`, `extension`, `user_id`, `channel_id`,`created_at`) VALUES (:name, :ext, :user, :channel, :created_at)');
            $insertUser->bindParam(":name", $fileInfo['file_name']);
            $insertUser->bindParam(":ext", $fileInfo['file_extension']);
            $insertUser->bindParam(":user", $idUser['id']);
            $insertUser->bindParam(":channel", $channel->id);
            $insertUser->bindParam(":created_at", $date);

            if($insertUser->execute()){

                $lastInsertedFile = $connexion->lastInsertId();

                foreach ($fileInfo["file_parts"] as $filePart) {
                    echo "Processing file part: $filePart\n";

                    $oldFilePath = $filePart;
                    
                    // Rename the file on the filesystem
                    $newFilePath = preg_replace('/\.part\d+$/', '', $oldFilePath);
                    rename($oldFilePath, $newFilePath);

                    $filePart = $newFilePath;
    
                    echo $filePart."\n".$oldFilePath;
                    array_push($allfileParts, $filePart);
    
                    // Read the contents of the file
                    $fileContents = file_get_contents($filePart);

                    // Create a temporary file
                    $tempFile = tempnam(sys_get_temp_dir(), 'discord_temp');
                    
                    // Write the contents to the temporary file
                    file_put_contents($tempFile, $fileContents);
                    
                    // Envoyer le fichier en tant que pièce jointe binaire
                    $channel->sendFile($tempFile, $fileInfo['file_name']."_part".$i.".txt" )
                    ->done(function (Message $message) use ($connexion, $lastInsertedFile) {
                        var_dump("\nMessage ID: $message \n");
                        $sentMessageId = $message->id;
                        var_dump("\nMessage ID: $sentMessageId \n");
                        $insertFilePart = $connexion->prepare('INSERT INTO `files_parts_uploaded` (`message_id`, `file_id`) VALUES (:messageId, :fileId)');
                        $insertFilePart->bindParam(":messageId", $sentMessageId);
                        $insertFilePart->bindParam(":fileId", $lastInsertedFile);
                        $insertFilePart->execute();
                    });
                    
                    
    
                    // Incremental counter for differentiating messages
                    $i++;
                    echo "Finished processing file: {$fileInfo['file_name']}\n";
                    
                }
            }else{
                $channel->sendMessage("Erreur lors du stockage du fichier");
            }

        }

        $fileHandle = fopen("upload_info.json", 'w');
        // Vérifie si le fichier est ouvert avec succès
        if ($fileHandle) {
            // Écrit une chaîne vide dans le fichier
            fwrite($fileHandle, '');
            // Ferme le fichier
            fclose($fileHandle);
        }

        // Chemin vers le dossier
        $folderPath = 'uploads';

        // Vérifie si le dossier existe
        if (is_dir($folderPath)) {
            // Obtient la liste des fichiers et dossiers dans le dossier
            $files = scandir($folderPath);

            // Boucle à travers les fichiers et dossiers
            foreach ($files as $file) {
                // Vérifie si le chemin est un fichier (et non un dossier)
                if (is_file($folderPath . '/' . $file)) {
                    // Supprime le fichier
                    unlink($folderPath . '/' . $file);
                }
            }

            echo "Contenu du dossier supprimé avec succès.";
        } else {
            echo "Le dossier n'existe pas.";
        }
        // Chemin vers le dossier
        $folderPath = 'splits';

        // Vérifie si le dossier existe
        if (is_dir($folderPath)) {
            // Obtient la liste des fichiers et dossiers dans le dossier
            $files = scandir($folderPath);

            // Boucle à travers les fichiers et dossiers
            foreach ($files as $file) {
                // Vérifie si le chemin est un fichier (et non un dossier)
                if (is_file($folderPath . '/' . $file)) {
                    // Supprime le fichier
                    unlink($folderPath . '/' . $file);
                }
            }

            echo "Contenu du dossier supprimé avec succès.";
        } else {
            echo "Le dossier n'existe pas.";
        }

        // $builder = MessageBuilder::new();
        // $builder->addFile($filePath);
        

        // // Send the message with the file attached
        // $channel->sendMessage($builder);

    }, $message, $params, $connexion);
});

  



  

// Start the Bot (must be at the bottom)
$discord->run(); 


