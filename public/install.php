<?php
echo "VERSION 2 - " . date('H:i:s');
require_once __DIR__ . '/../includes/db.php';
$pdo = db();

// Liste de toutes les tables du projet, dans l'ordre inverse des dependances
$tables = [
    'car_deplacement',
    'deplacement',
    'bien',
    'personne_contact',
    'occupant',
    'personne',
    'concession',
    'emplacement',
    'allee',
    'tarif',
    'utilisateur_site',
];

// 1. On supprime tout proprement (CASCADE pour effacer aussi les sequences/contraintes)
try {
    foreach ($tables as $t) {
        $pdo->exec("DROP TABLE IF EXISTS $t CASCADE");
    }
    echo "<p style='color:blue'>Anciennes tables supprimees.</p>";
} catch (PDOException $e) {
    echo "<h2 style='color:red'>Erreur DROP : " . htmlspecialchars($e->getMessage()) . "</h2>";
    exit;
}

// 2. On rejoue le dump complet
$sql = file_get_contents(__DIR__ . '/../sql/dump.sql');
try {
    $pdo->exec($sql);
    echo "<h2 style='color:green'>Installation OK - toutes les tables et donnees sont en place.</h2>";
    echo "<p><a href='login.php'>Aller au site</a></p>";
    echo "<p>Comptes : admin01/admin01 - gestionnaire01/gestionnaire01 - client01/client01</p>";
} catch (PDOException $e) {
    echo "<h2 style='color:red'>Erreur : " . htmlspecialchars($e->getMessage()) . "</h2>";
}
