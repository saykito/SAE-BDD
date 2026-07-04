<?php
$page_title = 'Contacts';
require_once __DIR__ . '/../includes/header.php';
require_gestionnaire();
$pdo = db();

// [H] Filtre par période pour "personnes à contacter pour les décédés récents"
$periode = $_GET['periode'] ?? 'tous';
$periodes_valides = ['semaine', 'mois', 'tous'];
// Validation whitelist : évite toute injection via le paramètre GET
if (!in_array($periode, $periodes_valides, true)) $periode = 'tous';

$sql_recents = "SELECT pc.nom, pc.prenom, pc.telephone, pc.lienavecoccupant,
    o.nom AS occ_nom, o.prenom AS occ_prenom, o.datedeces
    FROM personne_contact pc
    JOIN relation r ON r.idpersonnecontact = pc.idpersonnecontact
    JOIN occupant o ON o.idoccupant = r.idoccupant";

if ($periode === 'semaine') {
    $sql_recents .= " WHERE o.datedeces >= CURRENT_DATE - INTERVAL '7 days'";
} elseif ($periode === 'mois') {
    $sql_recents .= " WHERE o.datedeces >= CURRENT_DATE - INTERVAL '30 days'";
}
// 'tous' : aucun filtre de date, tous les contacts avec leurs defunts

$sql_recents .= " ORDER BY o.datedeces DESC, pc.nom";
$contacts_recents = $pdo->query($sql_recents)->fetchAll();

// Liste globale de tous les contacts
$contacts_tous = $pdo->query("SELECT pc.*,
    STRING_AGG(o.prenom || ' ' || o.nom, ', ' ORDER BY o.nom) AS occupants
    FROM personne_contact pc
    LEFT JOIN relation r ON r.idpersonnecontact = pc.idpersonnecontact
    LEFT JOIN occupant o ON o.idoccupant = r.idoccupant
    GROUP BY pc.idpersonnecontact ORDER BY pc.nom")->fetchAll();
?>

<h1>Personnes de contact</h1>

<!-- [H] Section "décédés récents" avec filtre de période (sujet : personnes à contacter pour décédés de la semaine) -->
<div class="panel">
    <div class="panel-header">
        <h2>Contacts des defunts recents</h2>
    </div>
    <div class="flex mb-2">
        <span style="font-size:13px; color:#374151; font-weight:600">Periode :</span>
        <a href="contacts.php?periode=semaine" class="btn btn-sm <?= $periode==='semaine'?'btn-primary':'' ?>">Semaine</a>
        <a href="contacts.php?periode=mois"    class="btn btn-sm <?= $periode==='mois'   ?'btn-primary':'' ?>">Mois (30 j.)</a>
        <a href="contacts.php?periode=tous"    class="btn btn-sm <?= $periode==='tous'   ?'btn-primary':'' ?>">Tous</a>
    </div>

    <?php if (count($contacts_recents) === 0): ?>
        <p class="empty">Aucun contact trouve pour cette periode.</p>
    <?php else: ?>
    <div class="table-wrap"><table class="data"><thead><tr>
        <th>Contact</th><th>Telephone</th><th>Lien</th><th>Defunt concerne</th><th>Date de deces</th>
    </tr></thead><tbody>
    <?php foreach ($contacts_recents as $c): ?>
    <tr>
        <td><?= h($c['nom']) ?> <?= h($c['prenom']) ?></td>
        <td><?= h($c['telephone']) ?></td>
        <td><?= h($c['lienavecoccupant']) ?></td>
        <td><?= h($c['occ_nom']) ?> <?= h($c['occ_prenom']) ?></td>
        <td><?= fdate($c['datedeces']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</div>

<!-- Liste complète de tous les contacts (section originale) -->
<div class="panel">
    <div class="panel-header"><h2><?= count($contacts_tous) ?> contacts enregistres</h2></div>
    <div class="table-wrap"><table class="data"><thead><tr>
        <th>Nom</th><th>Prenom</th><th>Telephone</th><th>Lien</th><th>Defunts lies</th>
    </tr></thead><tbody>
    <?php foreach ($contacts_tous as $c): ?>
    <tr>
        <td><?= h($c['nom']) ?></td>
        <td><?= h($c['prenom']) ?></td>
        <td><?= h($c['telephone']) ?></td>
        <td><?= h($c['lienavecoccupant']) ?></td>
        <td><?= h($c['occupants']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
