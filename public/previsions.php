<?php
// [G] Page "Déplacements prévus" - gestionnaire et admin uniquement
$page_title = 'Previsions de deplacements';
require_once __DIR__ . '/../includes/header.php';
require_gestionnaire();
$pdo = db();

// Filtre de période (whitelist obligatoire pour éviter une injection dans la requête SQL)
$periode = $_GET['periode'] ?? 'mois_courant';
$periodes_valides = ['mois_courant', 'mois_suivant', 'annee'];
if (!in_array($periode, $periodes_valides, true)) $periode = 'mois_courant';

// Construction du filtre SQL selon la période choisie
if ($periode === 'mois_courant') {
    $where = "WHERE d.datedeplacement >= DATE_TRUNC('month', CURRENT_DATE)
              AND d.datedeplacement < DATE_TRUNC('month', CURRENT_DATE) + INTERVAL '1 month'";
    $lib_periode = 'Mois en cours';
} elseif ($periode === 'mois_suivant') {
    $where = "WHERE d.datedeplacement >= DATE_TRUNC('month', CURRENT_DATE) + INTERVAL '1 month'
              AND d.datedeplacement < DATE_TRUNC('month', CURRENT_DATE) + INTERVAL '2 months'";
    $lib_periode = 'Mois suivant';
} else {
    // Année à venir : les 12 prochains mois
    $where = "WHERE d.datedeplacement >= CURRENT_DATE
              AND d.datedeplacement < CURRENT_DATE + INTERVAL '1 year'";
    $lib_periode = 'Annee a venir (12 mois)';
}

$sql = "SELECT d.datedeplacement, d.motif,
        o.nom AS occ_nom, o.prenom AS occ_prenom,
        e1.numero AS num_orig, a1.nomallee AS allee_orig,
        e2.numero AS num_dest, a2.nomallee AS allee_dest
        FROM deplacement d
        JOIN car_deplacement cd ON cd.iddeplacement = d.iddeplacement
        JOIN occupant o ON o.idoccupant = cd.idoccupant
        JOIN emplacement e1 ON e1.idemplacement = cd.idemplacementorigine
        JOIN allee a1 ON a1.idallee = e1.idallee
        JOIN emplacement e2 ON e2.idemplacement = cd.idemplacementdestination
        JOIN allee a2 ON a2.idallee = e2.idallee
        $where
        ORDER BY d.datedeplacement";

$depls = $pdo->query($sql)->fetchAll();
?>

<h1>Previsions de deplacements</h1>

<div class="panel">
    <div class="flex mb-2">
        <span style="font-size:13px; color:#374151; font-weight:600">Periode :</span>
        <a href="previsions.php?periode=mois_courant" class="btn btn-sm <?= $periode==='mois_courant'?'btn-primary':'' ?>">Mois en cours</a>
        <a href="previsions.php?periode=mois_suivant" class="btn btn-sm <?= $periode==='mois_suivant'?'btn-primary':'' ?>">Mois suivant</a>
        <a href="previsions.php?periode=annee"        class="btn btn-sm <?= $periode==='annee'       ?'btn-primary':'' ?>">Annee a venir</a>
    </div>

    <div class="panel-header">
        <h2><?= count($depls) ?> deplacement<?= count($depls) > 1 ? 's' : '' ?> &mdash; <?= h($lib_periode) ?></h2>
    </div>

    <?php if (count($depls) === 0): ?>
        <p class="empty">Aucun deplacement prevu pour cette periode.</p>
    <?php else: ?>
    <div class="table-wrap"><table class="data"><thead><tr>
        <th>Date</th><th>Occupant</th><th>Motif</th><th>Origine</th><th>Destination</th>
    </tr></thead><tbody>
    <?php foreach ($depls as $d): ?>
    <tr>
        <td><?= fdate($d['datedeplacement']) ?></td>
        <td><?= h($d['occ_nom']) ?> <?= h($d['occ_prenom']) ?></td>
        <td><?= h($d['motif']) ?></td>
        <td>n&deg;<?= $d['num_orig'] ?> &mdash; <?= h($d['allee_orig']) ?></td>
        <td>n&deg;<?= $d['num_dest'] ?> &mdash; <?= h($d['allee_dest']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</div>

<div class="panel">
    <div class="panel-header"><h2>Enregistrer un nouveau deplacement</h2></div>
    <p style="font-size:13px; color:#64748b">
        Pour planifier un deplacement, utilisez la page
        <a href="deplacement.php">Deplacement</a>.
    </p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
