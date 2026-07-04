<?php
/**
 * Audit - 5 requetes SQL avancees :
 * 1) Requete imbriquee : biens de valeur superieure a la moyenne
 * 2) Requete correlee : nb contacts et biens par occupant
 * 3) Division SQL : contacts lies a tous les occupants
 * 4) Jointures multiples : historique deplacements (6 tables)
 * 5) Sous-requete dans FROM : stats par allee
 */
$page_title = 'Audit';
require_once __DIR__ . '/../includes/header.php';
require_admin();
$pdo = db();

// 1) Requete imbriquee
$r1 = $pdo->query("
    SELECT o.nom, o.prenom, b.description, b.valeur
    FROM occupant o
    JOIN possession p ON p.idoccupant = o.idoccupant
    JOIN bien b ON b.idbien = p.idbien
    WHERE b.valeur > (SELECT AVG(valeur) FROM bien WHERE valeur IS NOT NULL)
    ORDER BY b.valeur DESC")->fetchAll();
$moy = $pdo->query("SELECT ROUND(AVG(valeur), 2) FROM bien WHERE valeur IS NOT NULL")->fetchColumn();

// 2) Requete correlee
$r2 = $pdo->query("
    SELECT o.nom, o.prenom, o.datedeces,
        (SELECT COUNT(*) FROM relation r WHERE r.idoccupant = o.idoccupant) AS nb_contacts,
        (SELECT COUNT(*) FROM possession p WHERE p.idoccupant = o.idoccupant) AS nb_biens
    FROM occupant o
    ORDER BY nb_contacts DESC")->fetchAll();

// 3) Division SQL
$r3 = $pdo->query("
    SELECT pc.nom, pc.prenom
    FROM personne_contact pc
    WHERE NOT EXISTS (
        SELECT o.idoccupant FROM occupant o
        WHERE NOT EXISTS (
            SELECT 1 FROM relation r
            WHERE r.idpersonnecontact = pc.idpersonnecontact
              AND r.idoccupant = o.idoccupant
        )
    )")->fetchAll();
$nb_occ = $pdo->query("SELECT COUNT(*) FROM occupant")->fetchColumn();

// 4) Jointures multiples (6 tables)
$r4 = $pdo->query("
    SELECT d.datedeplacement, d.motif,
        o.nom, o.prenom,
        e1.numero AS num_orig, e2.numero AS num_dest,
        a1.nomallee AS allee_orig, a2.nomallee AS allee_dest
    FROM deplacement d
    JOIN car_deplacement cd ON cd.iddeplacement = d.iddeplacement
    JOIN occupant o ON o.idoccupant = cd.idoccupant
    JOIN emplacement e1 ON e1.idemplacement = cd.idemplacementorigine
    JOIN emplacement e2 ON e2.idemplacement = cd.idemplacementdestination
    JOIN allee a1 ON a1.idallee = e1.idallee
    JOIN allee a2 ON a2.idallee = e2.idallee
    ORDER BY d.datedeplacement DESC")->fetchAll();

// 5) Sous-requete dans FROM
$r5 = $pdo->query("
    SELECT stats.*
    FROM (
        SELECT a.nomallee,
            COUNT(DISTINCT e.idemplacement) AS nb_empl,
            COUNT(DISTINCT e.idemplacement) FILTER (WHERE e.statut = 'Libre') AS nb_libres,
            COUNT(DISTINCT o.idoccupant) AS nb_occ,
            SUM(e.capacite) AS capacite_totale
        FROM allee a
        JOIN emplacement e ON e.idallee = a.idallee
        LEFT JOIN occupant o ON o.idemplacement = e.idemplacement
        GROUP BY a.nomallee
    ) AS stats
    ORDER BY stats.nomallee")->fetchAll();
?>

<h1>Audit et statistiques</h1>

<!-- R1 -->
<div class="panel">
    <div class="panel-header"><h2>Biens de valeur superieure a la moyenne</h2><span class="text-muted">Requete imbriquee &mdash; Moyenne : <?= $moy ?> &euro;</span></div>
    <?php if (!$r1): ?><p class="empty">Aucun resultat.</p>
    <?php else: ?>
    <div class="table-wrap"><table class="data"><thead><tr><th>Defunt</th><th>Bien</th><th>Valeur</th></tr></thead><tbody>
    <?php foreach ($r1 as $r): ?><tr><td><?= h($r['nom']) ?> <?= h($r['prenom']) ?></td><td><?= h($r['description']) ?></td><td><?= number_format($r['valeur'],2,',',' ') ?> &euro;</td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</div>

<!-- R2 -->
<div class="panel">
    <div class="panel-header"><h2>Contacts et biens par defunt</h2><span class="text-muted">Requete correlee</span></div>
    <div class="table-wrap"><table class="data"><thead><tr><th>Defunt</th><th>Deces</th><th>Contacts</th><th>Biens</th></tr></thead><tbody>
    <?php foreach ($r2 as $r): ?><tr><td><?= h($r['nom']) ?> <?= h($r['prenom']) ?></td><td><?= fdate($r['datedeces']) ?></td><td><?= $r['nb_contacts'] ?></td><td><?= $r['nb_biens'] ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>

<!-- R3 -->
<div class="panel">
    <div class="panel-header"><h2>Contacts lies a tous les defunts</h2><span class="text-muted">Division SQL &mdash; <?= $nb_occ ?> defunts</span></div>
    <?php if (!$r3): ?><p class="empty">Aucun contact n'est lie a la totalite des <?= $nb_occ ?> defunts.</p>
    <?php else: ?>
    <div class="table-wrap"><table class="data"><thead><tr><th>Nom</th><th>Prenom</th></tr></thead><tbody>
    <?php foreach ($r3 as $r): ?><tr><td><?= h($r['nom']) ?></td><td><?= h($r['prenom']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</div>

<!-- R4 -->
<div class="panel">
    <div class="panel-header"><h2>Historique des deplacements</h2><span class="text-muted">Jointures multiples (6 tables)</span></div>
    <?php if (!$r4): ?><p class="empty">Aucun deplacement.</p>
    <?php else: ?>
    <div class="table-wrap"><table class="data"><thead><tr><th>Date</th><th>Defunt</th><th>Motif</th><th>De</th><th>Vers</th></tr></thead><tbody>
    <?php foreach ($r4 as $r): ?><tr><td><?= fdate($r['datedeplacement']) ?></td><td><?= h($r['nom']) ?> <?= h($r['prenom']) ?></td><td><?= h($r['motif']) ?></td><td>n°<?= $r['num_orig'] ?> (<?= h($r['allee_orig']) ?>)</td><td>n°<?= $r['num_dest'] ?> (<?= h($r['allee_dest']) ?>)</td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</div>

<!-- R5 -->
<div class="panel">
    <div class="panel-header"><h2>Capacite par allee</h2><span class="text-muted">Sous-requete dans FROM</span></div>
    <div class="table-wrap"><table class="data"><thead><tr><th>Allee</th><th>Emplacements</th><th>Capacite totale</th><th>Defunts</th><th>Libres</th></tr></thead><tbody>
    <?php foreach ($r5 as $r): ?><tr><td><?= h($r['nomallee']) ?></td><td><?= $r['nb_empl'] ?></td><td><?= $r['capacite_totale'] ?></td><td><?= $r['nb_occ'] ?></td><td><?= $r['nb_libres'] ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
