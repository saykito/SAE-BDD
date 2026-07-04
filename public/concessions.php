<?php
$page_title = 'Concessions';
require_once __DIR__ . '/../includes/header.php';
$pdo = db();

// Construction de la requête avec filtre RGPD pour le rôle client
$sql = "SELECT c.*, e.numero, e.typeemplacement, a.nomallee,
        p.nom AS p_nom, p.prenom AS p_prenom,
        (SELECT COUNT(*) FROM occupant o WHERE o.idemplacement = e.idemplacement) AS nb_occ
        FROM concession c
        JOIN emplacement e ON e.idemplacement = c.idemplacement
        JOIN allee a ON a.idallee = e.idallee
        LEFT JOIN personne p ON p.idconcession = c.idconcession
        WHERE 1=1";
$params = [];

// [C] RGPD : le client ne peut voir que sa propre concession, pas celles des autres familles
if (is_client()) {
    $sql .= " AND c.idconcession = :id_conc";
    $params[':id_conc'] = (int)($_SESSION['user_concession'] ?? 0);
}

$sql .= " ORDER BY c.datedebut DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$concs = $stmt->fetchAll();
?>

<h1>Concessions</h1>

<div class="panel">
    <div class="panel-header"><h2><?= count($concs) ?> concession<?= count($concs) > 1 ? 's' : '' ?></h2></div>
    <div class="table-wrap"><table class="data"><thead><tr>
        <th>N&deg; empl.</th><th>Allee</th><th>Type</th><th>Titulaire</th><th>Duree</th><th>Debut</th><th>Fin</th><th>Cout</th><th>Occupants</th><th></th>
    </tr></thead><tbody>
    <?php foreach ($concs as $c): ?>
    <tr>
        <td><?= $c['numero'] ?></td>
        <td><?= h($c['nomallee']) ?></td>
        <td><span class="tag tag-<?= strtolower($c['typeemplacement']) ?>"><?= h($c['typeemplacement']) ?></span></td>
        <td><?= h($c['p_nom']) ?> <?= h($c['p_prenom']) ?></td>
        <td><?= h($c['duree']) ?></td>
        <td><?= fdate($c['datedebut']) ?></td>
        <td><?= $c['datefinprevu'] ? fdate($c['datefinprevu']) : '<em>Perpetuite</em>' ?></td>
        <td><?= number_format($c['cout'], 2, ',', ' ') ?> &euro;</td>
        <td><?= $c['nb_occ'] ?></td>
        <td><a href="emplacements.php?id=<?= $c['idemplacement'] ?>" class="btn btn-sm">Voir</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
