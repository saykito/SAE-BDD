<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth.php';
if (is_logged()) { header('Location: dashboard.php'); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $l = $_POST['login'] ?? '';
    $m = $_POST['mdp'] ?? '';
    if ($l === '' || $m === '') {
        $err = 'Veuillez remplir les deux champs.';
    } elseif (login($l, $m)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $err = 'Identifiant ou mot de passe incorrect.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion - Cimetiere</title><link rel="stylesheet" href="assets/style.css"></head>
<body class="page-login">
<div class="login-box">
    <div class="login-header">
        <div class="login-logo">&#9766;</div>
        <h1>Cimetiere communal</h1>
        <p>Gestion des emplacements et concessions</p>
    </div>
    <?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>
    <form method="post">
        <label>Identifiant</label>
        <input type="text" name="login" required autofocus>
        <label>Mot de passe</label>
        <input type="password" name="mdp" required>
        <button type="submit" class="btn btn-primary btn-block" style="margin-top:16px">Se connecter</button>
    </form>
    <div class="login-footer">SAE 2-04 &mdash; BUT Informatique 2026</div>
</div>
</body></html>
