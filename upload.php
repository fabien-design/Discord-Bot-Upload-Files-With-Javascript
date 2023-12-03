<?php
include 'vendor/autoload.php';
include "src/DotEnv.php";
(new DotEnv(__DIR__ . '/.env'))->load();
include "includes/connexion_db.php";

// Set the target directory for uploaded files
$targetDir = "uploads/";

// Define the chunk size
const CHUNK_SIZE = 10 * 1024 * 1024; // 10 MB

// Handle the upload request
$request = $_SERVER["REQUEST_METHOD"] === "POST" ? $_POST : $_GET;
if ($request["action"] === "upload") {
    // Get the chunk
    $chunk = $_FILES["chunk"]["tmp_name"];

    // Write the chunk to a file
    $fileName = $_POST['filename'];
    move_uploaded_file($chunk, $targetDir . $fileName);

    // Send a success response
    echo json_encode(["success" => true]);
}

if($request["action"] === "sendToBot"){
    $filename = $_POST['filename'];
    $file_parts = json_decode($_POST["file_parts"], true);
    $uploadInfo = [
        'user_id' => "777910706476679228",  // Replace with the actual way to get user ID
        'file_name' => $filename,   
        'file_extension' => pathinfo($filename, PATHINFO_EXTENSION),
        'file_parts' => $file_parts,
    ];

    saveUploadInfo($uploadInfo);

    interactWithDiscordBot();
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
        echo json_encode(["success" => false]);
    } else {
        echo json_encode(["success" => true]);
    }

}