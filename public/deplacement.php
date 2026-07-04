<?php
/**
 * Deplacement d'un occupant.
 * TRANSACTION avec SELECT FOR UPDATE pour verrouiller l'occupant.
 */
$page_title = 'Deplacement';
require_once __DIR__ . '/../includes/header.php';
require_gestionnaire();
$pdo = db();
$err = ''; $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_occ  = (int)($_POST['id_occupant'] ?? 0);
    $id_dest = (int)($_POST['id_destination'] ?? 0);
    $motif   = trim($_POST['motif'] ?? '');

    if ($id_occ <= 0 || $id_dest <= 0 || $motif === '') {
        $err = 'Tous les champs sont obligatoires.';
    } else {
        $pdo->beginTransaction();
        try {
            // Verrouiller l'occupant
            $stmt = $pdo->prepare("SELECT idoccupant, idemplacement FROM occupant WHERE idoccupant = :id FOR UPDATE");
            $stmt->execute([':id' => $id_occ]);
            $occ = $stmt->fetch();

            if (!$occ) { $pdo->rollBack(); $err = 'Occupant introuvable.'; }
            elseif ($occ['idemplacement'] == $id_dest) { $pdo->rollBack(); $err = 'Meme emplacement.'; }
            else {
                $origine = $occ['idemplacement'];

                // Creer le deplacement
                $stmt = $pdo->prepare("INSERT INTO deplacement (datedeplacement, motif) VALUES (CURRENT_DATE, :m) RETURNING iddeplacement");
                $stmt->execute([':m' => $motif]);
                $id_depl = $stmt->fetchColumn();

                // Lier
                $stmt = $pdo->prepare("INSERT INTO car_deplacement (idemplacementorigine, idemplacementdestination, iddeplacement, idoccupant) VALUES (:o, :d, :dp, :oc)");
                $stmt->execute([':o'=>$origine, ':d'=>$id_dest, ':dp'=>$id_depl, ':oc'=>$id_occ]);

                // Deplacer
                $stmt = $pdo->prepare("UPDATE occupant SET idemplacement = :d WHERE idoccupant = :id");
                $stmt->execute([':d'=>$id_dest, ':id'=>$id_occ]);

                // Statut destination
                $pdo->prepare("UPDATE emplacement SET statut = 'Occupe' WHERE idemplacement = :id")->execute([':id'=>$id_dest]);

                // Statut origine (libre si vide)
                $nb = $pdo->prepare("SELECT COUNT(*) FROM occupant WHERE idemplacement = :id");
                $nb->execute([':id' => $origine]);
                if ($nb->fetchColumn() == 0) {
                    $pdo->prepare("UPDATE emplacement SET statut = 'Libre' WHERE idemplacement = :id")->execute([':id'=>$origine]);
                }

                $pdo->commit();
                $ok = 'Deplacement effectue.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $err = 'Erreur : ' . htmlspecialchars($e->getMessage());
        }
    }
}

$occupants = $pdo->query("SELECT o.idoccupant, o.nom, o.prenom, e.numero FROM occupant o JOIN emplacement e ON e.idemplacement = o.idemplacement ORDER BY o.nom")->fetchAll();
$empls = $pdo->query("SELECT e.idemplacement, e.numero, e.typeemplacement, a.nomallee FROM emplacement e JOIN allee a ON a.idallee = e.idallee ORDER BY a.nomallee, e.numero")->fetchAll();
?>

<h1>Deplacement d'un occupant</h1>
<?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?= h($ok) ?></div><?php endif; ?>

<div class="panel">
    <form method="post">
        <label>Occupant *</label>
        <select name="id_occupant" required><option value="">-- Choisir --</option>
        <?php foreach ($occupants as $o): ?><option value="<?= $o['idoccupant'] ?>"><?= h($o['nom']) ?> <?= h($o['prenom']) ?> (empl. n°<?= $o['numero'] ?>)</option><?php endforeach; ?></select>
        <label>Destination *</label>
        <select name="id_destination" required><option value="">-- Choisir --</option>
        <?php foreach ($empls as $e): ?><option value="<?= $e['idemplacement'] ?>">n°<?= $e['numero'] ?> - <?= h($e['nomallee']) ?> (<?= h($e['typeemplacement']) ?>)</option><?php endforeach; ?></select>
        <label>Motif *</label>
        <input type="text" name="motif" required placeholder="Ex: Transfert ossuaire, Regroupement familial...">
        <div class="btn-bar">
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <a href="dashboard.php" class="btn">Annuler</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
