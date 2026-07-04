<?php
/**
 * Page de suppression - Admin uniquement.
 * Permet de retirer un occupant d'un emplacement
 * ou de liberer un emplacement entier.
 *
 * TRANSACTION : suppression des liens (possession, relation, car_deplacement)
 * puis de l'occupant, puis mise a jour du statut emplacement.
 */
$page_title = 'Supprimer';
require_once __DIR__ . '/../includes/header.php';
require_admin();
$pdo = db();
$err = ''; $ok = '';

// Suppression d'un occupant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['suppr_occupant'])) {
    $id_occ = (int)($_POST['id_occupant'] ?? 0);
    if ($id_occ <= 0) { $err = 'Occupant invalide.'; }
    else {
        $pdo->beginTransaction();
        try {
            // Recuperer l'emplacement avant suppression
            $stmt = $pdo->prepare("SELECT idemplacement FROM occupant WHERE idoccupant = :id FOR UPDATE");
            $stmt->execute([':id' => $id_occ]);
            $occ = $stmt->fetch();

            if (!$occ) { $pdo->rollBack(); $err = 'Occupant introuvable.'; }
            else {
                $id_empl = $occ['idemplacement'];

                // Supprimer les liens
                $pdo->prepare("DELETE FROM possession WHERE idoccupant = :id")->execute([':id' => $id_occ]);
                $pdo->prepare("DELETE FROM relation WHERE idoccupant = :id")->execute([':id' => $id_occ]);
                $pdo->prepare("DELETE FROM car_deplacement WHERE idoccupant = :id")->execute([':id' => $id_occ]);

                // Supprimer l'occupant
                $pdo->prepare("DELETE FROM occupant WHERE idoccupant = :id")->execute([':id' => $id_occ]);

                // Verifier si l'emplacement est vide
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM occupant WHERE idemplacement = :id");
                $stmt->execute([':id' => $id_empl]);
                if ($stmt->fetchColumn() == 0) {
                    $pdo->prepare("UPDATE emplacement SET statut = 'Libre' WHERE idemplacement = :id")->execute([':id' => $id_empl]);
                }

                $pdo->commit();
                $ok = 'Occupant supprime et emplacement mis a jour.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $err = 'Erreur : ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Liste des occupants
$occupants = $pdo->query("
    SELECT o.idoccupant, o.nom, o.prenom, o.datedeces, o.typeoccupation,
           e.numero, a.nomallee
    FROM occupant o
    JOIN emplacement e ON e.idemplacement = o.idemplacement
    JOIN allee a ON a.idallee = e.idallee
    ORDER BY a.nomallee, e.numero, o.nom")->fetchAll();

// Emplacements occupes
$empls_occ = $pdo->query("
    SELECT e.idemplacement, e.numero, a.nomallee, e.typeemplacement,
           (SELECT COUNT(*) FROM occupant o WHERE o.idemplacement = e.idemplacement) AS nb_occ
    FROM emplacement e
    JOIN allee a ON a.idallee = e.idallee
    WHERE e.statut = 'Occupe'
    ORDER BY a.nomallee, e.numero")->fetchAll();
?>

<h1>Gestion des suppressions</h1>

<?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?= h($ok) ?></div><?php endif; ?>

<div class="alert alert-warning">
    Attention : les suppressions sont definitives. Seul l'administrateur a acces a cette page.
</div>

<!-- Supprimer un occupant -->
<div class="panel">
    <div class="panel-header"><h2>Retirer un defunt d'un emplacement</h2></div>
    <form method="post">
        <label>Defunt a retirer</label>
        <select name="id_occupant" required>
            <option value="">-- Choisir un defunt --</option>
            <?php foreach ($occupants as $o): ?>
            <option value="<?= $o['idoccupant'] ?>">
                <?= h($o['nom']) ?> <?= h($o['prenom']) ?> (<?= fdate($o['datedeces']) ?>) — empl. n°<?= $o['numero'] ?>, <?= h($o['nomallee']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <div class="btn-bar">
            <button type="submit" name="suppr_occupant" value="1" class="btn btn-danger"
                    onclick="return confirm('Confirmer la suppression de ce defunt ?')">Supprimer le defunt</button>
        </div>
    </form>
</div>

<!-- Vue des emplacements occupes -->
<div class="panel">
    <div class="panel-header"><h2>Emplacements occupes (<?= count($empls_occ) ?>)</h2></div>
    <div class="table-wrap"><table class="data"><thead><tr>
        <th>N°</th><th>Allee</th><th>Type</th><th>Occupants</th><th></th>
    </tr></thead><tbody>
    <?php foreach ($empls_occ as $e): ?>
    <tr>
        <td><?= $e['numero'] ?></td>
        <td><?= h($e['nomallee']) ?></td>
        <td><span class="tag tag-<?= strtolower($e['typeemplacement']) ?>"><?= h($e['typeemplacement']) ?></span></td>
        <td><?= $e['nb_occ'] ?></td>
        <td><a href="emplacements.php?id=<?= $e['idemplacement'] ?>" class="btn btn-sm">Voir</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
