<?php

function isLoggedIn(){
    if (!isset($_COOKIE[session_name()])) {
        return false;
    }
    if (!isset($_SESSION)) {
        session_start();
    }
    if (!isset($_SESSION['userId'])){
        return false;
    }
    //Checking the userId still exists
    $pdo = getConnection();
    $statment = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $statment -> execute([$_SESSION['userId']]);
    $user = $statment -> fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return false;
    }
    return true;
}

function isAuth(){
    if(isLoggedIn()) {
        return;
    }

    header('Location: /admin');
    exit;
}

function loginHandler(){
    $pdo = getConnection();

    $statement = $pdo -> prepare('SELECT * FROM `users` WHERE email = ?');
    $statement ->execute([$_POST['email']]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header ('Location: /admin');
        exit;
    }
    if (!password_verify($_POST['password'], $user['password'])) {
        header ('Location: /admin');
        exit;
    }
    session_start();
    $_SESSION['userId'] = $user['id'];
    header ('Location: /admin');
}

function logoutHandler(){

    session_start();
    $params = session_get_cookie_params();

    setcookie(session_name(), '', 0, $params['path'],$params['domain'],$params['secure'],$params['httponly'] );

    session_destroy();

    header ('Location: /');
}
?>