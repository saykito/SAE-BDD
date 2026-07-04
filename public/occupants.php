<?php
$page_title = 'Occupants';
require_once __DIR__ . '/../includes/header.php';
$pdo = db();
$id = (int)($_GET['id'] ?? 0);

// [C] RGPD : pré-calculer l'emplacement de la concession du client pour filtrer les accès
$id_empl_client = 0;
if (is_client()) {
    $id_conc_client = (int)($_SESSION['user_concession'] ?? 0);
    if ($id_conc_client > 0) {
        $s = $pdo->prepare("SELECT idemplacement FROM concession WHERE idconcession = :id");
        $s->execute([':id' => $id_conc_client]);
        $row = $s->fetch();
        $id_empl_client = $row ? (int)$row['idemplacement'] : 0;
    }
}

if ($id > 0):
    // ===== VUE DETAIL =====
    $stmt = $pdo->prepare("SELECT o.*, e.numero, e.typeemplacement, a.nomallee
        FROM occupant o JOIN emplacement e ON e.idemplacement = o.idemplacement
        JOIN allee a ON a.idallee = e.idallee WHERE o.idoccupant = :id");
    $stmt->execute([':id' => $id]);
    $occ = $stmt->fetch();
    if (!$occ) { echo '<div class="alert alert-danger">Occupant introuvable.</div>'; require_once __DIR__.'/../includes/footer.php'; exit; }

    // [C] Le client ne peut voir que les occupants de son propre emplacement
    if (is_client() && (int)$occ['idemplacement'] !== $id_empl_client) {
        echo '<div class="alert alert-danger">Acces refuse : cet occupant n\'est pas lie a votre concession.</div>';
        require_once __DIR__.'/../includes/footer.php';
        exit;
    }

    // Contacts liés à cet occupant
    $stmt = $pdo->prepare("SELECT pc.* FROM personne_contact pc
        JOIN relation r ON r.idpersonnecontact = pc.idpersonnecontact WHERE r.idoccupant = :id");
    $stmt->execute([':id' => $id]);
    $contacts = $stmt->fetchAll();

    // Biens conservés
    $stmt = $pdo->prepare("SELECT b.* FROM bien b JOIN possession p ON p.idbien = b.idbien WHERE p.idoccupant = :id");
    $stmt->execute([':id' => $id]);
    $biens = $stmt->fetchAll();

    // Historique des déplacements
    $stmt = $pdo->prepare("SELECT d.*, cd.idemplacementorigine, cd.idemplacementdestination,
        e1.numero AS num_orig, e2.numero AS num_dest
        FROM deplacement d
        JOIN car_deplacement cd ON cd.iddeplacement = d.iddeplacement
        JOIN emplacement e1 ON e1.idemplacement = cd.idemplacementorigine
        JOIN emplacement e2 ON e2.idemplacement = cd.idemplacementdestination
        WHERE cd.idoccupant = :id ORDER BY d.datedeplacement DESC");
    $stmt->execute([':id' => $id]);
    $depls = $stmt->fetchAll();
?>

<div class="flex-between mb-2">
    <h1><?= h($occ['prenom']) ?> <?= h($occ['nom']) ?></h1>
    <a href="occupants.php" class="btn btn-sm">&larr; Retour</a>
</div>

<div class="panel">
    <div class="panel-header"><h2>Informations</h2>
        <span class="tag tag-<?= strtolower($occ['typeoccupation']) ?>"><?= h($occ['typeoccupation']) ?></span>
    </div>
    <div class="form-row">
        <div><span class="text-muted">Ne(e) le :</span> <?= fdate($occ['datenaissance']) ?></div>
        <div><span class="text-muted">Decede(e) le :</span> <?= fdate($occ['datedeces']) ?></div>
    </div>
    <div class="form-row mt-1">
        <div><span class="text-muted">Emplacement :</span> <a href="emplacements.php?id=<?= $occ['idemplacement'] ?>">n&deg;<?= $occ['numero'] ?></a></div>
        <div><span class="text-muted">Allee :</span> <?= h($occ['nomallee']) ?></div>
    </div>
    <div class="mt-1"><span class="text-muted">Type :</span> <?= h($occ['typeemplacement']) ?></div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Personnes de contact (<?= count($contacts) ?>)</h2></div>
    <?php if (count($contacts) === 0): ?><p class="empty">Aucun contact enregistre.</p>
    <?php else: ?>
    <div class="table-wrap"><table class="data"><thead><tr><th>Nom</th><th>Prenom</th><th>Telephone</th><th>Lien</th></tr></thead><tbody>
    <?php foreach ($contacts as $c): ?>
    <tr><td><?= h($c['nom']) ?></td><td><?= h($c['prenom']) ?></td><td><?= h($c['telephone']) ?></td><td><?= h($c['lienavecoccupant']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</div>

<div class="panel">
    <div class="panel-header"><h2>Biens conserves (<?= count($biens) ?>)</h2></div>
    <?php if (count($biens) === 0): ?><p class="empty">Aucun bien.</p>
    <?php else: ?>
    <div class="table-wrap"><table class="data"><thead><tr><th>Description</th><th>Valeur</th><th>Photo</th></tr></thead><tbody>
    <?php foreach ($biens as $b): ?>
    <tr><td><?= h($b['description']) ?></td><td><?= number_format($b['valeur'],2,',',' ') ?> &euro;</td><td><?= $b['photoouinon'] ? 'Oui' : 'Non' ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</div>

<?php if (count($depls) > 0): ?>
<div class="panel">
    <div class="panel-header"><h2>Historique des deplacements</h2></div>
    <div class="table-wrap"><table class="data"><thead><tr><th>Date</th><th>Motif</th><th>De</th><th>Vers</th></tr></thead><tbody>
    <?php foreach ($depls as $d): ?>
    <tr><td><?= fdate($d['datedeplacement']) ?></td><td><?= h($d['motif']) ?></td>
    <td>n&deg;<?= $d['num_orig'] ?></td><td>n&deg;<?= $d['num_dest'] ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>

<?php else:
    // ===== VUE LISTE =====
    $q = trim($_GET['q'] ?? '');
    $sql = "SELECT o.*, e.numero, a.nomallee FROM occupant o
        JOIN emplacement e ON e.idemplacement = o.idemplacement
        JOIN allee a ON a.idallee = e.idallee WHERE 1=1";
    $params = [];

    // [C] RGPD : le client ne voit que les occupants de sa propre concession
    if (is_client()) {
        if ($id_empl_client > 0) {
            $sql .= " AND o.idemplacement = :empl";
            $params[':empl'] = $id_empl_client;
        } else {
            // Client sans concession valide : aucun résultat
            $sql .= " AND FALSE";
        }
    }

    if ($q !== '') {
        $sql .= " AND (LOWER(o.nom) LIKE :q OR LOWER(o.prenom) LIKE :q)";
        $params[':q'] = '%' . strtolower($q) . '%';
    }
    $sql .= " ORDER BY o.nom, o.prenom";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $occs = $stmt->fetchAll();
?>

<h1>Occupants</h1>

<div class="panel">
    <form method="get" class="flex">
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="Rechercher par nom ou prenom..." style="max-width:320px">
        <button type="submit" class="btn btn-primary btn-sm">Rechercher</button>
        <?php if ($q): ?><a href="occupants.php" class="btn btn-sm">Effacer</a><?php endif; ?>
    </form>
</div>

<div class="panel">
    <div class="panel-header"><h2><?= count($occs) ?> occupant<?= count($occs) > 1 ? 's' : '' ?></h2></div>
    <div class="table-wrap"><table class="data"><thead><tr>
        <th>Nom</th><th>Prenom</th><th>Naissance</th><th>Deces</th><th>Type</th><th>Empl.</th><th>Allee</th><th></th>
    </tr></thead><tbody>
    <?php foreach ($occs as $o): ?>
    <tr>
        <td><?= h($o['nom']) ?></td><td><?= h($o['prenom']) ?></td>
        <td><?= fdate($o['datenaissance']) ?></td><td><?= fdate($o['datedeces']) ?></td>
        <td><span class="tag tag-<?= strtolower($o['typeoccupation']) ?>"><?= h($o['typeoccupation']) ?></span></td>
        <td><a href="emplacements.php?id=<?= $o['idemplacement'] ?>">n&deg;<?= $o['numero'] ?></a></td>
        <td><?= h($o['nomallee']) ?></td>
        <td><a href="occupants.php?id=<?= $o['idoccupant'] ?>" class="btn btn-sm">Dossier</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
