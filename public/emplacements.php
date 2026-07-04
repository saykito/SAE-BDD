<?php
// === [F] Traitement prolongation AVANT le header pour permettre la redirection ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_prolonger'])) {
    require_once __DIR__ . '/../includes/auth.php';
    require_login();
    require_gestionnaire();
    $pdo = db();

    $id_conc     = (int)($_POST['id_conc']    ?? 0);
    $id_empl_ret = (int)($_POST['id_empl']    ?? 0);
    $duree_ajout =       $_POST['duree_ajout'] ?? '';

    // Correspondance duree → nombre d'années (whitelist, jamais de saisie libre dans la requête)
    $annees_map     = ['5 ans'=>5, '10 ans'=>10, '20 ans'=>20, '30 ans'=>30, '50 ans'=>50];
    $durees_valides = array_merge(array_keys($annees_map), ['Perpetuite']);

    if ($id_conc > 0 && $id_empl_ret > 0 && in_array($duree_ajout, $durees_valides, true)) {
        $pdo->beginTransaction();
        try {
            // Verrouiller la concession et récupérer le type d'emplacement pour la tarification
            $stmt = $pdo->prepare(
                "SELECT c.*, e.typeemplacement FROM concession c
                 JOIN emplacement e ON e.idemplacement = c.idemplacement
                 WHERE c.idconcession = :id FOR UPDATE"
            );
            $stmt->execute([':id' => $id_conc]);
            $conc = $stmt->fetch();

            if (!$conc || $conc['datefinprevu'] === null) {
                // Concession inexistante ou déjà en perpétuité : impossible de prolonger
                $pdo->rollBack();
                header('Location: emplacements.php?id=' . $id_empl_ret . '&err_prolong=1');
                exit;
            }

            // Lire le supplément de tarif dans la base (cahier des charges : prix en BD)
            $stmt = $pdo->prepare("SELECT prix_base FROM tarif WHERE typeemplacement = :t AND duree = :d");
            $stmt->execute([':t' => $conc['typeemplacement'], ':d' => $duree_ajout]);
            $t = $stmt->fetch();
            $prix_ajout = $t ? (float)$t['prix_base'] : 0.0;

            if ($duree_ajout === 'Perpetuite') {
                // Passage en perpétuité : fin prévue devient NULL
                $stmt = $pdo->prepare(
                    "UPDATE concession SET duree = 'Perpetuite', datefinprevu = NULL,
                     cout = cout + :p WHERE idconcession = :id"
                );
                $stmt->execute([':p' => $prix_ajout, ':id' => $id_conc]);
            } else {
                // Prolongation de N années : on passe l'intervalle comme paramètre bindé
                $intervalle = $annees_map[$duree_ajout] . ' years';
                $stmt = $pdo->prepare(
                    "UPDATE concession SET duree = :d,
                     datefinprevu = datefinprevu + CAST(:i AS INTERVAL),
                     cout = cout + :p WHERE idconcession = :id"
                );
                $stmt->execute([':d'=>$duree_ajout, ':i'=>$intervalle, ':p'=>$prix_ajout, ':id'=>$id_conc]);
            }

            $pdo->commit();
            header('Location: emplacements.php?id=' . $id_empl_ret . '&prolongee=1');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            header('Location: emplacements.php?id=' . $id_empl_ret . '&err_prolong=1');
            exit;
        }
    }

    header('Location: emplacements.php?id=' . $id_empl_ret . '&err_prolong=1');
    exit;
}

$page_title = 'Emplacements';
require_once __DIR__ . '/../includes/header.php';
$pdo = db();
$id = (int)($_GET['id'] ?? 0);

// Message flash après une prolongation (transmis par le redirect)
if (isset($_GET['prolongee'])): ?>
    <div class="alert alert-success">Concession prolongee avec succes.</div>
<?php elseif (isset($_GET['err_prolong'])): ?>
    <div class="alert alert-danger">Erreur lors de la prolongation. Verifiez les donnees.</div>
<?php endif;

if ($id > 0):
    // ===== VUE DETAIL =====
    $stmt = $pdo->prepare("SELECT e.*, a.nomallee FROM emplacement e JOIN allee a ON a.idallee = e.idallee WHERE e.idemplacement = :id");
    $stmt->execute([':id' => $id]);
    $empl = $stmt->fetch();
    if (!$empl) { echo '<div class="alert alert-danger">Emplacement introuvable.</div>'; require_once __DIR__.'/../includes/footer.php'; exit; }

    // Concession liée
    $stmt = $pdo->prepare("SELECT c.*, p.nom AS p_nom, p.prenom AS p_prenom, p.telephone AS p_tel, p.adresse AS p_adr
        FROM concession c LEFT JOIN personne p ON p.idconcession = c.idconcession
        WHERE c.idemplacement = :id");
    $stmt->execute([':id' => $id]);
    $conc = $stmt->fetch();

    // Occupants de cet emplacement
    $stmt = $pdo->prepare("SELECT * FROM occupant WHERE idemplacement = :id ORDER BY datedeces DESC");
    $stmt->execute([':id' => $id]);
    $occupants = $stmt->fetchAll();

    // Statut réel calculé (tâche D) : caveau avec 2/6 places → "Partiellement occupé"
    [$css_statut, $lib_statut] = statut_reel($empl['capacite'], count($occupants));

    // [C] Le client ne voit le bloc concession QUE si c'est la sienne (protection RGPD)
    $conc_visible = $conc && (
        is_gestionnaire()
        || (is_client() && (int)($conc['idconcession'] ?? 0) === (int)($_SESSION['user_concession'] ?? -1))
    );
?>

<div class="flex-between mb-2">
    <h1>Emplacement n°<?= $empl['numero'] ?></h1>
    <a href="emplacements.php" class="btn btn-sm">&larr; Retour</a>
</div>

<div class="panel">
    <div class="panel-header"><h2>Informations</h2>
        <span class="tag tag-<?= strtolower($empl['typeemplacement']) ?>"><?= h($empl['typeemplacement']) ?></span>
    </div>
    <div class="form-row">
        <div><span class="text-muted">Allee :</span> <?= h($empl['nomallee']) ?></div>
        <div><span class="text-muted">Statut :</span>
            <span class="tag tag-<?= $css_statut ?>"><?= h($lib_statut) ?></span>
        </div>
    </div>
    <div class="form-row mt-1">
        <div><span class="text-muted">Capacite :</span> <?= $empl['capacite'] ?> places</div>
        <div><span class="text-muted">Occupes :</span> <?= count($occupants) ?> / <?= $empl['capacite'] ?></div>
    </div>
    <div class="form-row mt-1">
        <div><span class="text-muted">Orientation :</span> <?= h($empl['orientation']) ?></div>
        <div><span class="text-muted">Exposition vent :</span> <?= h($empl['expositionvent']) ?></div>
    </div>
    <div class="form-row mt-1">
        <div><span class="text-muted">Accessibilite :</span> <?= h($empl['accessibilite']) ?></div>
        <div><span class="text-muted">Proche entree :</span> <?= $empl['proximiteentree'] ? 'Oui' : 'Non' ?></div>
    </div>
</div>

<?php if ($conc_visible): ?>
<div class="panel">
    <div class="panel-header">
        <h2>Concession</h2>
    </div>
    <div class="form-row">
        <div><span class="text-muted">Titulaire :</span> <?= h($conc['p_nom']) ?> <?= h($conc['p_prenom']) ?></div>
        <div><span class="text-muted">Telephone :</span> <?= h($conc['p_tel']) ?></div>
    </div>
    <div class="form-row mt-1">
        <div><span class="text-muted">Adresse :</span> <?= h($conc['p_adr']) ?></div>
        <div><span class="text-muted">Duree :</span> <?= h($conc['duree']) ?></div>
    </div>
    <div class="form-row mt-1">
        <div><span class="text-muted">Debut :</span> <?= fdate($conc['datedebut']) ?></div>
        <div><span class="text-muted">Fin prevue :</span> <?= $conc['datefinprevu'] ? fdate($conc['datefinprevu']) : 'Perpetuite' ?></div>
    </div>
    <div class="mt-1"><span class="text-muted">Cout :</span> <strong><?= number_format($conc['cout'], 2, ',', ' ') ?> &euro;</strong></div>

    <?php if (is_gestionnaire() && $conc['datefinprevu'] !== null): ?>
    <!-- [F] Formulaire de prolongation : visible gestionnaire + admin, seulement si pas en perpétuité -->
    <div class="mt-2" style="border-top:1px solid #f1f5f9; padding-top:12px">
        <form method="post" class="flex" style="align-items:flex-end; gap:10px; flex-wrap:wrap">
            <input type="hidden" name="action_prolonger" value="1">
            <input type="hidden" name="id_conc" value="<?= $conc['idconcession'] ?>">
            <input type="hidden" name="id_empl" value="<?= $id ?>">
            <div>
                <label style="margin-top:0">Prolonger de</label>
                <select name="duree_ajout" required style="width:auto">
                    <option value="">-- Choisir --</option>
                    <option value="5 ans">5 ans</option>
                    <option value="10 ans">10 ans</option>
                    <option value="20 ans">20 ans</option>
                    <option value="30 ans">30 ans</option>
                    <option value="50 ans">50 ans</option>
                    <option value="Perpetuite">Perpetuite</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Prolonger</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php elseif ($conc && is_client()): ?>
<!-- [C] Client sur un autre emplacement : on masque toutes les données de la concession -->
<div class="panel">
    <p class="empty">Informations de concession non disponibles.</p>
</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <h2>Occupants (<?= count($occupants) ?> / <?= $empl['capacite'] ?>)</h2>
        <?php if (is_gestionnaire() && count($occupants) < $empl['capacite']): ?>
            <a href="admission.php?id_empl=<?= $id ?>" class="btn btn-primary btn-sm">Inhumer</a>
        <?php endif; ?>
    </div>
    <?php if (count($occupants) === 0): ?>
        <p class="empty">Aucun occupant.</p>
    <?php else: ?>
    <div class="table-wrap"><table class="data"><thead><tr>
        <th>Nom</th><th>Prenom</th><th>Naissance</th><th>Deces</th><th>Type</th><th></th>
    </tr></thead><tbody>
    <?php foreach ($occupants as $o): ?>
    <tr>
        <td><?= h($o['nom']) ?></td>
        <td><?= h($o['prenom']) ?></td>
        <td><?= fdate($o['datenaissance']) ?></td>
        <td><?= fdate($o['datedeces']) ?></td>
        <td><span class="tag tag-<?= strtolower($o['typeoccupation']) ?>"><?= h($o['typeoccupation']) ?></span></td>
        <td><a href="occupants.php?id=<?= $o['idoccupant'] ?>" class="btn btn-sm">Dossier</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</div>

<?php else:
    // ===== VUE LISTE =====
    $filtre = $_GET['filtre'] ?? 'tous';
    $type   = $_GET['type']   ?? '';
    // [E] Filtre par allée (depuis le dashboard ou directement)
    $allee  = trim($_GET['allee'] ?? '');

    $sql = "SELECT e.*, a.nomallee,
            (SELECT COUNT(*) FROM occupant o WHERE o.idemplacement = e.idemplacement) AS nb_occ
            FROM emplacement e JOIN allee a ON a.idallee = e.idallee WHERE 1=1";
    $params = [];

    if ($filtre === 'libres')  $sql .= " AND e.statut = 'Libre'";
    elseif ($filtre === 'occupes') $sql .= " AND e.statut = 'Occupe'";
    if ($type !== '') $sql .= " AND e.typeemplacement = " . $pdo->quote($type);

    // Filtre allée : utilise un paramètre préparé (valeur venant de l'URL)
    if ($allee !== '') {
        $sql .= " AND a.nomallee = :allee";
        $params[':allee'] = $allee;
    }

    $sql .= " ORDER BY a.nomallee, e.numero";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $empls = $stmt->fetchAll();
?>

<h1>Emplacements</h1>

<!-- [E] Bandeau quand un filtre allée est actif -->
<?php if ($allee !== ''): ?>
<div class="alert alert-info">
    Allee : <strong><?= h($allee) ?></strong> &mdash;
    <a href="emplacements.php">Voir toutes les allees</a>
</div>
<?php endif; ?>

<div class="panel">
    <form method="get" class="flex">
        <?php if ($allee !== ''): ?>
        <input type="hidden" name="allee" value="<?= h($allee) ?>">
        <?php endif; ?>
        <select name="filtre" onchange="this.form.submit()">
            <option value="tous"   <?= $filtre==='tous'   ?'selected':'' ?>>Tous les statuts</option>
            <option value="libres" <?= $filtre==='libres' ?'selected':'' ?>>Libres</option>
            <option value="occupes"<?= $filtre==='occupes'?'selected':'' ?>>Occupes</option>
        </select>
        <select name="type" onchange="this.form.submit()">
            <option value=""            <?= $type===''            ?'selected':'' ?>>Tous les types</option>
            <option value="Tombe"       <?= $type==='Tombe'       ?'selected':'' ?>>Tombe</option>
            <option value="Caveau"      <?= $type==='Caveau'      ?'selected':'' ?>>Caveau</option>
            <option value="Ossuaire"    <?= $type==='Ossuaire'    ?'selected':'' ?>>Ossuaire</option>
            <option value="Columbarium" <?= $type==='Columbarium' ?'selected':'' ?>>Columbarium</option>
        </select>
    </form>
</div>

<div class="panel">
    <div class="panel-header"><h2><?= count($empls) ?> emplacements</h2></div>
    <div class="table-wrap"><table class="data"><thead><tr>
        <th>N&deg;</th><th>Allee</th><th>Type</th><th>Capacite</th><th>Occupes</th><th>Statut</th><th></th>
    </tr></thead><tbody>
    <?php foreach ($empls as $e):
        // [D] Statut calculé en temps réel : évite les désynchronisations
        [$css, $lib] = statut_reel($e['capacite'], $e['nb_occ']);
    ?>
    <tr>
        <td><?= $e['numero'] ?></td>
        <td><?= h($e['nomallee']) ?></td>
        <td><span class="tag tag-<?= strtolower($e['typeemplacement']) ?>"><?= h($e['typeemplacement']) ?></span></td>
        <td><?= $e['capacite'] ?></td>
        <td><?= $e['nb_occ'] ?></td>
        <td><span class="tag tag-<?= $css ?>"><?= h($lib) ?></span></td>
        <td><a href="emplacements.php?id=<?= $e['idemplacement'] ?>" class="btn btn-sm">Voir</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
