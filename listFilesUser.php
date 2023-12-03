<?php
session_start();
include 'vendor/autoload.php';
include "src/DotEnv.php";
(new DotEnv(__DIR__ . '/.env'))->load();
include "includes/connexion_db.php";

if(isset($_SESSION['connected']) && isset($_SESSION['userId']) ){
    if($_SESSION['connected'] === true && !empty($_SESSION['userId'])){
        $id = (int)$_SESSION['userId'];
        $getUser = $connexion->prepare("SELECT * FROM user WHERE id = :id");
        $getUser->bindParam("id",$id); 
        $getUser->execute();
        $user = $getUser->fetch();

        $getFilesUser = $connexion->prepare("SELECT * FROM files_uploaded WHERE user_id = :id");
        $getFilesUser->bindParam("id",$id); 
        $getFilesUser->execute();
        $allFiles = $getFilesUser->fetchAll();

    }else{
        header("Location: index.php");
    }
}else{
    header("Location: index.php");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes fichiers</title>
    <link rel="stylesheet" href="src/css/style.css">
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
                <li><a href="uploadFile.php">Upload</a></li>
                <li><a href="#">Mes Fichiers</a></li>
                <li><a href="#">Contact</a></li>
            </ul>
        </nav>
    </header>

    <div id="popupMessageBox">
        <p id="contentMessageBox">Téléchargement en cours</p>
    </div>

    <main>
        <table>
            <caption>Vos fichiers</caption>
            <thead>
                <tr>
                <th scope="col">Nom</th>
                <th scope="col">Date d'ajout</th>
                <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $numFile = 0;
                foreach($allFiles as $file){ ?>

                    <tr>
                        <td <?= $numFile === 0 ? "scope='row'" : "" ?>data-label="Name"><?= $file['name'] ?></td>
                        <td data-label="Created at"><?= $file['created_at'] ?></td>
                        <td data-label="Action">
                            <a class="fileBtnDownload" href="downloadFile.php?idFile=<?= $file['id'] ?>" onclick="downloadPopupMessage()">Télécharger</a>
                            <a class="fileBtnDelete" href="deleteFile.php?idFile=<?= $file['id'] ?>">Supprimer</a>
                        </td>
                    </tr>
                <?php 
                }
                ?>
                
            </tbody>
        </table>

    </main>
    <script>
        function downloadPopupMessage(){
            document.getElementById('popupMessageBox').style.display='block';
            setInterval(() => {
                document.getElementById('popupMessageBox').style.display='none';
            }, 5000);
        }
    </script>
</body>
</html>