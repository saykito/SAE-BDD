<?php
/**
 * Nouvelle inhumation.
 * TRANSACTION : INSERT occupant + UPDATE statut emplacement.
 * Si un echoue, ROLLBACK annule tout.
 */
$page_title = 'Nouvelle inhumation';
require_once __DIR__ . '/../includes/header.php';
require_gestionnaire();
$pdo = db();
$err = ''; $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom     = trim($_POST['nom'] ?? '');
    $prenom  = trim($_POST['prenom'] ?? '');
    $naiss   = $_POST['datenaissance'] ?? '';
    $deces   = $_POST['datedeces'] ?? '';
    $type    = $_POST['typeoccupation'] ?? 'Cercueil';
    $id_empl = (int)($_POST['id_empl'] ?? 0);

    if ($nom === '' || $prenom === '' || $deces === '' || $id_empl <= 0) {
        $err = 'Tous les champs obligatoires doivent etre remplis.';
    } else {
        $pdo->beginTransaction();
        try {
            // Verifier capacite
            $stmt = $pdo->prepare("SELECT capacite, (SELECT COUNT(*) FROM occupant WHERE idemplacement = :id) AS nb FROM emplacement WHERE idemplacement = :id");
            $stmt->execute([':id' => $id_empl]);
            $empl = $stmt->fetch();

            if (!$empl || $empl['nb'] >= $empl['capacite']) {
                $pdo->rollBack();
                $err = 'Emplacement plein ou introuvable.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO occupant (nom, prenom, datenaissance, datedeces, typeoccupation, idemplacement) VALUES (:n, :p, :dn, :dd, :t, :e)");
                $stmt->execute([':n'=>$nom, ':p'=>$prenom, ':dn'=>$naiss?:null, ':dd'=>$deces, ':t'=>$type, ':e'=>$id_empl]);

                $stmt = $pdo->prepare("UPDATE emplacement SET statut = 'Occupe' WHERE idemplacement = :id");
                $stmt->execute([':id' => $id_empl]);

                $pdo->commit();
                $ok = 'Inhumation enregistree.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $err = 'Erreur : ' . htmlspecialchars($e->getMessage());
        }
    }
}

$empls = $pdo->query("SELECT e.idemplacement, e.numero, e.typeemplacement, e.capacite, a.nomallee,
    (SELECT COUNT(*) FROM occupant WHERE idemplacement = e.idemplacement) AS nb
    FROM emplacement e JOIN allee a ON a.idallee = e.idallee ORDER BY a.nomallee, e.numero")->fetchAll();
$id_get = (int)($_GET['id_empl'] ?? 0);
?>

<h1>Nouvelle inhumation</h1>
<?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?= h($ok) ?></div><?php endif; ?>

<div class="panel">
    <form method="post">
        <div class="form-row">
            <div><label>Nom *</label><input type="text" name="nom" required></div>
            <div><label>Prenom *</label><input type="text" name="prenom" required></div>
        </div>
        <div class="form-row">
            <div><label>Date de naissance</label><input type="date" name="datenaissance"></div>
            <div><label>Date de deces *</label><input type="date" name="datedeces" required></div>
        </div>
        <label>Type d'occupation</label>
        <select name="typeoccupation">
            <option value="Cercueil">Cercueil</option>
            <option value="Urne">Urne</option>
        </select>
        <label>Emplacement *</label>
        <select name="id_empl" required>
            <option value="">-- Choisir un emplacement --</option>
            <?php foreach ($empls as $e): if ($e['nb'] < $e['capacite']): ?>
            <option value="<?= $e['idemplacement'] ?>" <?= $id_get === $e['idemplacement'] ? 'selected' : '' ?>>
                n°<?= $e['numero'] ?> - <?= h($e['nomallee']) ?> (<?= h($e['typeemplacement']) ?>, <?= $e['nb'] ?>/<?= $e['capacite'] ?>)
            </option>
            <?php endif; endforeach; ?>
        </select>
        <div class="btn-bar">
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <a href="dashboard.php" class="btn">Annuler</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
