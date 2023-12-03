<?php 
session_start();
include 'vendor/autoload.php';

include "src/DotEnv.php";
(new DotEnv(__DIR__ . '/.env'))->load();

include "includes/connexion_db.php";

if(isset($_POST['username']) && isset($_POST['password'])){
    $user = htmlspecialchars($_POST['username']);
    $pass = htmlspecialchars($_POST['password']);
    echo $user;
    echo $pass;
    $CheckUser = $connexion->prepare("SELECT COUNT(*) FROM user WHERE username = :user");
    $CheckUser->bindParam("user",$user);
    if ($CheckUser->execute()) {
        $users = $CheckUser->fetch();
        if ($users[0] === 1) { // Check if the count is equal to 1
            $getUser = $connexion->prepare("SELECT * FROM user WHERE username = :user");
            $getUser->bindParam("user",$user); 
            $getUser->execute();
            $user = $getUser->fetch();
            echo $pass."\n<br>";
            echo $user["password"]."\n<br>";
            if(password_verify($pass, $user["password"])){
                $_SESSION["connected"]=true;
                $_SESSION["userId"] = $user['id'];
                header("Location: uploadFile.php");
            } else {
                echo "Invalid username or password";
            }
        } else {
            echo "Invalid username or password";
        }
        $_POST = array();
    }
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <link rel="stylesheet" href="src/css/index.css">
</head>
<body>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <div class="wrapper">
        <form action="#" method="post">
            <h1>Login</h1>
            <div class="input-box">
                <input type="text" placeholder="Username" name="username" required>
                <i class='bx bxs-user'></i>
            </div>
            <div class="input-box">
                <input type="password" placeholder="password" name="password" required>
                <i class='bx bxs-lock-alt'></i>
            </div>
            <div class="remember-forgot">
                <label><input type="checkbox">Remember me
                </label>
                <a href="#">Forgot password?</a>
            </div>
            <button type="submit" class="btn">Login</button>
        </form>
    </div>
</body>
</html>


