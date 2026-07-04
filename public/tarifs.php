<?php
$page_title = 'Tarifs';
require_once __DIR__ . '/../includes/header.php';
$pdo = db();

// [B] Lecture de la grille tarifaire depuis la table (cahier des charges : prix en BD, pas en PHP)
$tarifs_db = $pdo->query("SELECT typeemplacement, duree, prix_base FROM tarif ORDER BY typeemplacement, duree")->fetchAll();
$tarifs_ref = [];
foreach ($tarifs_db as $row) {
    $tarifs_ref[$row['typeemplacement']][$row['duree']] = (float)$row['prix_base'];
}

// Fallback PHP si la table tarif est vide (ne devrait pas arriver après un install normal)
if (empty($tarifs_ref)) {
    $tarifs_ref = [
        'Tombe'       => ['5 ans'=>250, '10 ans'=>450, '20 ans'=>800, '30 ans'=>1200, '50 ans'=>2000, 'Perpetuite'=>3500],
        'Caveau'      => ['5 ans'=>500, '10 ans'=>900, '20 ans'=>1600, '30 ans'=>2200, '50 ans'=>3500, 'Perpetuite'=>5000],
        'Columbarium' => ['5 ans'=>150, '10 ans'=>280, '20 ans'=>500, '30 ans'=>700,   '50 ans'=>1100, 'Perpetuite'=>1500],
        'Ossuaire'    => ['5 ans'=>100, '10 ans'=>180, '20 ans'=>320, '30 ans'=>400,   '50 ans'=>600,  'Perpetuite'=>800],
    ];
}

// Recherche tarif d'un emplacement précis
$id_empl = (int)($_GET['id'] ?? 0);
$empl_info = null;
$conc_info = null;
if ($id_empl > 0) {
    $stmt = $pdo->prepare("SELECT e.*, a.nomallee FROM emplacement e JOIN allee a ON a.idallee = e.idallee WHERE e.idemplacement = :id");
    $stmt->execute([':id' => $id_empl]);
    $empl_info = $stmt->fetch();
    if ($empl_info) {
        $stmt = $pdo->prepare("SELECT c.*, p.nom AS p_nom, p.prenom AS p_prenom FROM concession c LEFT JOIN personne p ON p.idconcession = c.idconcession WHERE c.idemplacement = :id");
        $stmt->execute([':id' => $id_empl]);
        $conc_info = $stmt->fetch();
    }
}

$empls_libres = $pdo->query("SELECT e.idemplacement, e.numero, e.typeemplacement, a.nomallee
    FROM emplacement e JOIN allee a ON a.idallee = e.idallee ORDER BY a.nomallee, e.numero")->fetchAll();

$stats = $pdo->query("SELECT e.typeemplacement,
    COUNT(*) AS nb_concessions,
    ROUND(AVG(c.cout), 2) AS cout_moyen,
    MIN(c.cout) AS cout_min,
    MAX(c.cout) AS cout_max
    FROM concession c
    JOIN emplacement e ON e.idemplacement = c.idemplacement
    GROUP BY e.typeemplacement
    ORDER BY cout_moyen DESC")->fetchAll();

// Ordre d'affichage des durées (clés de la grille)
$ordre_durees = ['5 ans', '10 ans', '20 ans', '30 ans', '50 ans', 'Perpetuite'];
?>

<h1>Tarifs des concessions</h1>

<!-- Recherche par emplacement -->
<div class="panel">
    <div class="panel-header"><h2>Consulter le tarif d'un emplacement</h2></div>
    <form method="get" class="flex">
        <select name="id" onchange="this.form.submit()" style="max-width:400px">
            <option value="">-- Choisir un emplacement --</option>
            <?php foreach ($empls_libres as $el): ?>
            <option value="<?= $el['idemplacement'] ?>" <?= $id_empl === $el['idemplacement'] ? 'selected' : '' ?>>
                n&deg;<?= $el['numero'] ?> - <?= h($el['nomallee']) ?> (<?= h($el['typeemplacement']) ?>)
            </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($empl_info): ?>
    <div class="mt-2">
        <div class="form-row">
            <div><span class="text-muted">Emplacement :</span> n&deg;<?= $empl_info['numero'] ?></div>
            <div><span class="text-muted">Allee :</span> <?= h($empl_info['nomallee']) ?></div>
        </div>
        <div class="form-row mt-1">
            <div><span class="text-muted">Type :</span> <span class="tag tag-<?= strtolower($empl_info['typeemplacement']) ?>"><?= h($empl_info['typeemplacement']) ?></span></div>
            <div><span class="text-muted">Statut :</span> <span class="tag tag-<?= strtolower($empl_info['statut']) ?>"><?= h($empl_info['statut']) ?></span></div>
        </div>

        <?php if ($conc_info): ?>
        <div class="alert alert-info mt-2">
            Concession existante : <strong><?= h($conc_info['duree']) ?></strong> par <?= h($conc_info['p_nom']) ?> <?= h($conc_info['p_prenom']) ?>
            &mdash; Cout : <strong><?= number_format($conc_info['cout'], 2, ',', ' ') ?> &euro;</strong>
            &mdash; Fin : <?= $conc_info['datefinprevu'] ? fdate($conc_info['datefinprevu']) : 'Perpetuite' ?>
        </div>
        <?php else: ?>
        <div class="alert alert-success mt-2">Emplacement disponible. Tarifs applicables :</div>
        <?php endif; ?>

        <div class="tarif-grid mt-1">
        <?php
        $type = $empl_info['typeemplacement'];
        if (isset($tarifs_ref[$type])):
            foreach ($ordre_durees as $duree):
                if (!isset($tarifs_ref[$type][$duree])) continue;
                $prix = $tarifs_ref[$type][$duree]; ?>
            <div class="tarif-card">
                <div class="type"><?= h($duree) ?></div>
                <div class="prix"><?= number_format($prix, 0, ',', ' ') ?> &euro;</div>
                <div class="detail"><?= h($type) ?> &mdash; <?= $empl_info['capacite'] ?> places</div>
            </div>
        <?php endforeach; endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Grille tarifaire complète (durées corrigées : 20 ans et 50 ans) -->
<div class="panel">
    <div class="panel-header"><h2>Grille tarifaire</h2></div>
    <div class="table-wrap"><table class="data"><thead><tr>
        <th>Type</th>
        <?php foreach ($ordre_durees as $d): ?>
        <th><?= h($d) ?></th>
        <?php endforeach; ?>
    </tr></thead><tbody>
    <?php foreach ($tarifs_ref as $type => $prix): ?>
    <tr>
        <td><span class="tag tag-<?= strtolower($type) ?>"><?= h($type) ?></span></td>
        <?php foreach ($ordre_durees as $d): ?>
        <td><?= isset($prix[$d]) ? number_format($prix[$d], 0, ',', ' ') . ' &euro;' : '-' ?></td>
        <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<!-- Stats réelles des concessions existantes -->
<div class="panel">
    <div class="panel-header"><h2>Statistiques des concessions existantes</h2></div>
    <div class="table-wrap"><table class="data"><thead><tr>
        <th>Type</th><th>Nb concessions</th><th>Cout moyen</th><th>Min</th><th>Max</th>
    </tr></thead><tbody>
    <?php foreach ($stats as $s): ?>
    <tr>
        <td><span class="tag tag-<?= strtolower($s['typeemplacement']) ?>"><?= h($s['typeemplacement']) ?></span></td>
        <td><?= $s['nb_concessions'] ?></td>
        <td><?= number_format($s['cout_moyen'], 2, ',', ' ') ?> &euro;</td>
        <td><?= number_format($s['cout_min'], 2, ',', ' ') ?> &euro;</td>
        <td><?= number_format($s['cout_max'], 2, ',', ' ') ?> &euro;</td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
