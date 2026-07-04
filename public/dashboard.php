<?php
$page_title = 'Tableau de bord';
require_once __DIR__ . '/../includes/header.php';
$pdo = db();

$nb_empl = $pdo->query("SELECT COUNT(*) FROM emplacement")->fetchColumn();
$nb_occ  = $pdo->query("SELECT COUNT(*) FROM occupant")->fetchColumn();
$nb_conc = $pdo->query("SELECT COUNT(*) FROM concession")->fetchColumn();

// [D] Comptages basés sur les occupants réels (pas sur le champ statut qui peut être désynchronisé)
$nb_libres   = $pdo->query("SELECT COUNT(*) FROM emplacement e
    WHERE (SELECT COUNT(*) FROM occupant WHERE idemplacement = e.idemplacement) = 0")->fetchColumn();
$nb_partiels = $pdo->query("SELECT COUNT(*) FROM emplacement e
    WHERE (SELECT COUNT(*) FROM occupant WHERE idemplacement = e.idemplacement) > 0
    AND (SELECT COUNT(*) FROM occupant WHERE idemplacement = e.idemplacement) < e.capacite")->fetchColumn();
$nb_complets = $pdo->query("SELECT COUNT(*) FROM emplacement e
    WHERE (SELECT COUNT(*) FROM occupant WHERE idemplacement = e.idemplacement) >= e.capacite")->fetchColumn();

$nb_expirent = $pdo->query("SELECT COUNT(*) FROM concession
    WHERE datefinprevu IS NOT NULL AND datefinprevu < CURRENT_DATE + INTERVAL '2 year'")->fetchColumn();
?>

<h1>Tableau de bord</h1>

<div class="cards">
    <div class="card"><div class="label">Emplacements</div><div class="value"><?= $nb_empl ?></div></div>
    <div class="card good"><div class="label">Libres</div><div class="value"><?= $nb_libres ?></div></div>
    <div class="card warn"><div class="label">Partiels</div><div class="value"><?= $nb_partiels ?></div></div>
    <div class="card danger"><div class="label">Complets</div><div class="value"><?= $nb_complets ?></div></div>
    <div class="card"><div class="label">Defunts</div><div class="value"><?= $nb_occ ?></div></div>
    <div class="card"><div class="label">Concessions</div><div class="value"><?= $nb_conc ?></div></div>
    <div class="card <?= $nb_expirent > 0 ? 'warn' : '' ?>"><div class="label">Expirent sous 2 ans</div><div class="value"><?= $nb_expirent ?></div></div>
</div>

<?php if ($nb_expirent > 0): ?>
<div class="panel">
    <div class="panel-header"><h2>Concessions bientot expirantes</h2></div>
    <?php
    $exp = $pdo->query("SELECT c.idconcession, c.datefinprevu, c.duree,
        e.idemplacement, e.numero, a.nomallee,
        p.nom AS p_nom, p.prenom AS p_prenom
        FROM concession c
        JOIN emplacement e ON e.idemplacement = c.idemplacement
        JOIN allee a ON a.idallee = e.idallee
        LEFT JOIN personne p ON p.idconcession = c.idconcession
        WHERE c.datefinprevu IS NOT NULL AND c.datefinprevu < CURRENT_DATE + INTERVAL '2 year'
        ORDER BY c.datefinprevu")->fetchAll();
    ?>
    <div class="table-wrap"><table class="data"><thead><tr>
        <th>Emplacement</th><th>Allee</th><th>Titulaire</th><th>Duree</th><th>Expiration</th>
    </tr></thead><tbody>
    <?php foreach ($exp as $e): ?>
    <tr>
        <td><a href="emplacements.php?id=<?= $e['idemplacement'] ?>"><?= $e['numero'] ?></a></td>
        <td><?= h($e['nomallee']) ?></td>
        <td><?= h($e['p_nom']) ?> <?= h($e['p_prenom']) ?></td>
        <td><?= h($e['duree']) ?></td>
        <td><span class="tag tag-occupe"><?= fdate($e['datefinprevu']) ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header"><h2>Repartition par allee</h2></div>
    <?php
    // [D] Comptages réels par statut + [E] noms d'allée cliquables vers emplacements.php?allee=
    $allees = $pdo->query("SELECT a.nomallee,
        COUNT(DISTINCT e.idemplacement) AS nb_empl,
        COUNT(DISTINCT e.idemplacement) FILTER (WHERE
            (SELECT COUNT(*) FROM occupant WHERE idemplacement = e.idemplacement) = 0
        ) AS nb_libres,
        COUNT(DISTINCT e.idemplacement) FILTER (WHERE
            (SELECT COUNT(*) FROM occupant WHERE idemplacement = e.idemplacement) > 0
            AND (SELECT COUNT(*) FROM occupant WHERE idemplacement = e.idemplacement) < e.capacite
        ) AS nb_partiels,
        COUNT(DISTINCT e.idemplacement) FILTER (WHERE
            (SELECT COUNT(*) FROM occupant WHERE idemplacement = e.idemplacement) >= e.capacite
        ) AS nb_complets,
        COUNT(DISTINCT o.idoccupant) AS nb_occ
        FROM allee a
        JOIN emplacement e ON e.idallee = a.idallee
        LEFT JOIN occupant o ON o.idemplacement = e.idemplacement
        GROUP BY a.nomallee ORDER BY a.nomallee")->fetchAll();
    ?>
    <div class="table-wrap"><table class="data"><thead><tr>
        <th>Allee</th><th>Emplacements</th><th>Libres</th><th>Partiels</th><th>Complets</th><th>Defunts</th>
    </tr></thead><tbody>
    <?php foreach ($allees as $a): ?>
    <tr>
        <td><a href="emplacements.php?allee=<?= urlencode($a['nomallee']) ?>"><?= h($a['nomallee']) ?></a></td>
        <td><?= $a['nb_empl'] ?></td>
        <td><?= $a['nb_libres'] ?></td>
        <td><?= $a['nb_partiels'] ?></td>
        <td><?= $a['nb_complets'] ?></td>
        <td><?= $a['nb_occ'] ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
