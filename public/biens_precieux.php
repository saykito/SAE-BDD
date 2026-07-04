<?php
// [H] Page "Biens de plus de 5000€ et leur emplacement" - gestionnaire et admin uniquement
$page_title = 'Biens precieux';
require_once __DIR__ . '/../includes/header.php';
require_gestionnaire();
$pdo = db();

// Requête avec jointures multiples : bien → possession → occupant → emplacement → allee
// Filtre sur b.valeur > 5000 conformément au sujet
$biens = $pdo->query("SELECT b.description, b.valeur, b.photoouinon,
    o.nom AS occ_nom, o.prenom AS occ_prenom, o.datedeces,
    e.idemplacement, e.numero AS num_empl, e.typeemplacement,
    a.nomallee
    FROM bien b
    JOIN possession p ON p.idbien = b.idbien
    JOIN occupant o ON o.idoccupant = p.idoccupant
    JOIN emplacement e ON e.idemplacement = o.idemplacement
    JOIN allee a ON a.idallee = e.idallee
    WHERE b.valeur > 5000
    ORDER BY b.valeur DESC")->fetchAll();
?>

<h1>Biens precieux (&gt; 5 000 &euro;)</h1>

<div class="panel">
    <div class="panel-header">
        <h2><?= count($biens) ?> bien<?= count($biens) > 1 ? 's' : '' ?> recense<?= count($biens) > 1 ? 's' : '' ?></h2>
    </div>

    <?php if (count($biens) === 0): ?>
        <p class="empty">Aucun bien d'une valeur superieure a 5 000 &euro;.</p>
    <?php else: ?>
    <div class="table-wrap"><table class="data"><thead><tr>
        <th>Description</th><th>Valeur</th><th>Photo</th><th>Defunt</th><th>Deces</th><th>Emplacement</th><th>Allee</th><th>Type</th>
    </tr></thead><tbody>
    <?php foreach ($biens as $b): ?>
    <tr>
        <td><?= h($b['description']) ?></td>
        <td><strong><?= number_format($b['valeur'], 2, ',', ' ') ?> &euro;</strong></td>
        <td><?= $b['photoouinon'] ? 'Oui' : 'Non' ?></td>
        <td><?= h($b['occ_nom']) ?> <?= h($b['occ_prenom']) ?></td>
        <td><?= fdate($b['datedeces']) ?></td>
        <td><a href="emplacements.php?id=<?= $b['idemplacement'] ?>">n&deg;<?= $b['num_empl'] ?></a></td>
        <td><?= h($b['nomallee']) ?></td>
        <td><span class="tag tag-<?= strtolower($b['typeemplacement']) ?>"><?= h($b['typeemplacement']) ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
