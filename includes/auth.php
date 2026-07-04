<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

function is_logged() { return isset($_SESSION['user_id']); }
function is_admin() { return ($_SESSION['user_role'] ?? '') === 'admin'; }
function is_gestionnaire() { return in_array($_SESSION['user_role'] ?? '', ['admin', 'gestionnaire']); }
function is_client() { return ($_SESSION['user_role'] ?? '') === 'client'; }

function require_login() { if (!is_logged()) { header('Location: login.php'); exit; } }
function require_gestionnaire() { require_login(); if (!is_gestionnaire()) { header('Location: dashboard.php'); exit; } }
function require_admin() { require_login(); if (!is_admin()) { header('Location: dashboard.php'); exit; } }

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function fdate($d) { return $d ? date('d/m/Y', strtotime($d)) : '-'; }

// Retourne [classe_css, libelle] selon le taux de remplissage reel d'un emplacement.
// Evite les incohérences du champ statut (ex. caveau 2/6 affiché "Occupé" au lieu de "Partiel").
function statut_reel($capacite, $nb_occ) {
    if ((int)$nb_occ === 0)            return ['libre',   'Libre'];
    if ((int)$nb_occ < (int)$capacite) return ['partiel', 'Partiellement occupe'];
    return ['occupe', 'Complet'];
}

function login($login, $mdp) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM utilisateur_site WHERE login = :l AND mdp = :m");
    $stmt->execute([':l' => $login, ':m' => $mdp]);
    $u = $stmt->fetch();
    if ($u) {
        $_SESSION['user_id']         = $u['id_utilisateur'];
        $_SESSION['user_login']      = $u['login'];
        $_SESSION['user_nom']        = $u['nom'];
        $_SESSION['user_prenom']     = $u['prenom'];
        $_SESSION['user_role']       = $u['role'];
        $_SESSION['user_concession'] = $u['idconcession'];
        return true;
    }
    return false;
}
