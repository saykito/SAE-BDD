<?php
require_once __DIR__ . '/auth.php';
require_login();
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title ?? 'Cimetiere') ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header class="topbar">
    <div class="brand">
        <div class="logo">&#9766;</div>
        <span class="title">Cimetiere communal</span>
    </div>
    <div class="user-info">
        <span><?= h($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']) ?></span>
        <span class="badge badge-<?= h($_SESSION['user_role']) ?>"><?= h(ucfirst($_SESSION['user_role'])) ?></span>
        <a href="logout.php" class="btn-link">Deconnexion</a>
    </div>
</header>

<div class="app">
    <nav class="sidebar">
        <ul>
            <li><a href="dashboard.php" class="<?= $current==='dashboard.php'?'active':'' ?>">Tableau de bord</a></li>
            <li><a href="emplacements.php" class="<?= $current==='emplacements.php'?'active':'' ?>">Emplacements</a></li>
            <li><a href="concessions.php" class="<?= $current==='concessions.php'?'active':'' ?>">Concessions</a></li>
            <li><a href="occupants.php" class="<?= $current==='occupants.php'?'active':'' ?>">Occupants</a></li>
            <li><a href="tarifs.php" class="<?= $current==='tarifs.php'?'active':'' ?>">Tarifs</a></li>

            <?php if (is_gestionnaire()): ?>
            <li class="nav-sep">Gestionnaire</li>
            <li><a href="admission.php" class="<?= $current==='admission.php'?'active':'' ?>">Nouvelle inhumation</a></li>
            <li><a href="deplacement.php" class="<?= $current==='deplacement.php'?'active':'' ?>">Deplacement</a></li>
            <li><a href="previsions.php" class="<?= $current==='previsions.php'?'active':'' ?>">Previsions</a></li>
            <li><a href="contacts.php" class="<?= $current==='contacts.php'?'active':'' ?>">Contacts</a></li>
            <li><a href="biens_precieux.php" class="<?= $current==='biens_precieux.php'?'active':'' ?>">Biens precieux</a></li>
            <?php endif; ?>

            <?php if (is_admin()): ?>
            <li class="nav-sep">Administration</li>
            <li><a href="supprimer.php" class="<?= $current==='supprimer.php'?'active':'' ?>">Supprimer</a></li>
            <li><a href="audit.php" class="<?= $current==='audit.php'?'active':'' ?>">Audit</a></li>
            <?php endif; ?>
        </ul>
    </nav>
    <main class="content">
