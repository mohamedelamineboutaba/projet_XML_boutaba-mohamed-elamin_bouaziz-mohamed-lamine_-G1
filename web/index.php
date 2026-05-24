<?php
/**
 * index.php — Club Info_Tech  |  Application Web
 */

define('BASEX_HOST', 'http://localhost:8984');
define('BASEX_DB',   'TP1');
define('BASEX_USER', 'admin');
define('BASEX_PASS', 'admin');
define('XML_PATH',   __DIR__ . '/../club.xml');

function basexQuery(string $xquery): string|false {
    $url = BASEX_HOST . '/rest/' . BASEX_DB . '?query=' . urlencode($xquery);
    $ctx = stream_context_create(['http' => [
        'header'  => 'Authorization: Basic ' . base64_encode(BASEX_USER . ':' . BASEX_PASS),
        'timeout' => 5,
    ]]);
    return @file_get_contents($url, false, $ctx);
}
function basexAvailable(): bool { return basexQuery("1") !== false; }

$xml      = file_exists(XML_PATH) ? simplexml_load_file(XML_PATH) : null;
$useBaseX = basexAvailable();

function calcScore(int $c, int $t, float $coeff): float {
    return round(($c + $t) * $coeff, 2);
}

// ═══ TRAITEMENT INSCRIPTION (POST) ═══════════════════════════════
$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'inscription') {
    $concoursId = trim($_POST['concours']    ?? '');
    $membreId   = trim($_POST['membre']      ?? '');
    $complexite = (int)($_POST['complexite'] ?? -1);
    $temps      = (int)($_POST['temps']      ?? 0);

    if (!$concoursId || !$membreId || $complexite < 0 || $complexite > 100 || $temps <= 0) {
        $msg = "Tous les champs sont obligatoires et doivent être valides.";
        $msgType = 'error';
    } elseif (!$xml) {
        $msg = "Impossible d'accéder à club.xml.";
        $msgType = 'error';
    } else {
        $membreCat = null;
        foreach ($xml->membres->membre as $m)
            if ((string)$m['id'] === $membreId) { $membreCat = (string)$m['categorieRef']; break; }
        $concoursCat = null;
        foreach ($xml->concours->concours as $c)
            if ((string)$c['id'] === $concoursId) { $concoursCat = (string)$c['categorieRef']; break; }

        if ($membreCat !== $concoursCat) {
            $msg = "Ce membre n'appartient pas à la catégorie de ce concours."; $msgType = 'error';
        } else {
            $dejaInscrit = false;
            foreach ($xml->concours->concours as $c)
                if ((string)$c['id'] === $concoursId)
                    foreach ($c->participants->participant as $p)
                        if ((string)$p['membreRef'] === $membreId) { $dejaInscrit = true; break 2; }

            if ($dejaInscrit) {
                $msg = "Ce membre est déjà inscrit à ce concours."; $msgType = 'error';
            } else {
                foreach ($xml->concours->concours as $c) {
                    if ((string)$c['id'] === $concoursId) {
                        $p = $c->participants->addChild('participant');
                        $p->addAttribute('membreRef', $membreId);
                        $p->addChild('complexite',     (string)$complexite);
                        $p->addChild('tempsExecution', (string)$temps);
                        break;
                    }
                }
                $dom = dom_import_simplexml($xml)->ownerDocument;
                $dom->formatOutput = true;
                $dom->save(XML_PATH);
                $xml = simplexml_load_file(XML_PATH);
                $msg = "Inscription enregistrée avec succès !"; $msgType = 'success';
            }
        }
    }
}

$viewConcours = $_GET['view_concours'] ?? '';

// Build concours array properly (avoid iterator_to_array bug)
$allConcours = [];
if ($xml) {
    foreach ($xml->concours->concours as $c) {
        $allConcours[] = $c;
    }
}
?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Club Info_Tech — Gestion des Concours</title>
  <link rel="stylesheet" href="style.css"/>
</head>
<body>

<header>
  <h1>🏆 Club Info_Tech - Gestion des Concours</h1>
</header>

<main>
<?php if (!$xml): ?>
  <div class="card">
    <div class="alert alert-error">⚠️ Fichier <code>club.xml</code> introuvable : <code><?= htmlspecialchars(XML_PATH) ?></code></div>
  </div>
<?php else: ?>

<!-- ════ SECTION 1 — LISTE DES CONCOURS ════ -->
<div class="card">
  <h2>📅 Liste des Concours Disponibles</h2>
  <?php
  // Sort by date
  usort($allConcours, fn($a,$b) => strcmp((string)$a['date'], (string)$b['date']));
  $rows = [];
  foreach ($allConcours as $c) {
      $catLib = '';
      foreach ($xml->categories->categorie as $cat)
          if ((string)$cat['id'] === (string)$c['categorieRef']) { $catLib = (string)$cat['libelle']; break; }
      $rows[] = [
          'id'    => (string)$c['id'],
          'titre' => (string)$c->titre,
          'date'  => (string)$c['date'],
          'coeff' => (string)$c['coefficient'],
          'cat'   => $catLib,
      ];
  }
  ?>
  <table>
    <thead>
      <tr><th>Titre</th><th>Date</th><th>Catégorie</th><th>Coefficient</th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['titre']) ?></td>
        <td><?= htmlspecialchars($r['date'])  ?></td>
        <td><span class="badge"><?= htmlspecialchars($r['cat']) ?></span></td>
        <td><?= htmlspecialchars($r['coeff']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>


<!-- ════ SECTION 2 — RÉSULTATS ════ -->
<div class="card">
  <h2>🥇 Résultats des Concours</h2>

  <div class="filter-row">
    <div class="form-group">
      <select id="concours-select">
        <option value="">-- Choisir --</option>
        <?php foreach ($allConcours as $c): ?>
          <option value="<?= htmlspecialchars((string)$c['id']) ?>"
            <?= $viewConcours === (string)$c['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars((string)$c->titre) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary" onclick="
      var v = document.getElementById('concours-select').value;
      if(v) location.href='?view_concours='+v+'#results-anchor';
    ">Afficher résultats</button>
  </div>

  <a name="results-anchor"></a>

  <?php if ($viewConcours): ?>
  <?php
  $titre = ''; $lignes = [];
  foreach ($allConcours as $c) {
      if ((string)$c['id'] !== $viewConcours) continue;
      $titre = (string)$c->titre;
      $coeff = (float)$c['coefficient'];
      foreach ($c->participants->participant as $p) {
          $cx = (int)$p->complexite;
          $te = (int)$p->tempsExecution;
          $nom = '';
          foreach ($xml->membres->membre as $m)
              if ((string)$m['id'] === (string)$p['membreRef']) { $nom = $m->prenom . ' ' . $m->nom; break; }
          $lignes[] = ['nom' => $nom, 'cx' => $cx, 'te' => $te, 'score' => calcScore($cx, $te, $coeff)];
      }
      usort($lignes, fn($a,$b) => $b['score'] <=> $a['score']);
      break;
  }
  $scoreMax = !empty($lignes) ? $lignes[0]['score'] : 0;
  ?>
  <?php if ($lignes): ?>
  <table>
    <thead>
      <tr><th>Rang</th><th>Participant</th><th>Complexité</th><th>Temps (ms)</th><th>Score</th></tr>
    </thead>
    <tbody>
      <?php foreach ($lignes as $i => $l): ?>
      <tr<?= $l['score'] === $scoreMax ? ' class="winner"' : '' ?>>
        <td class="rang"><?= ($i+1) ?> <?= $l['score'] === $scoreMax ? '🏆' : '' ?></td>
        <td><?= htmlspecialchars($l['nom']) ?></td>
        <td><?= $l['cx'] ?></td>
        <td><?= $l['te'] ?></td>
        <td class="score"><?= number_format($l['score'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <?php endif; ?>
</div>


<!-- ════ SECTION 3 — NOUVELLE INSCRIPTION ════ -->
<div class="card">
  <h2>✏️ Nouvelle Inscription</h2>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <form method="post" action="?view_concours=<?= htmlspecialchars($viewConcours) ?>">
    <input type="hidden" name="action" value="inscription"/>

    <div class="form-group">
      <label>Concours:</label>
      <select name="concours" required>
        <option value="">Sélectionnez...</option>
        <?php foreach ($allConcours as $c): ?>
          <option value="<?= htmlspecialchars((string)$c['id']) ?>"
            <?= ($_POST['concours'] ?? '') === (string)$c['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars((string)$c->titre) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label>Membre:</label>
      <select name="membre" required>
        <option value="">Sélectionnez...</option>
        <?php foreach ($xml->membres->membre as $m):
            $catLib = '';
            foreach ($xml->categories->categorie as $cat)
                if ((string)$cat['id'] === (string)$m['categorieRef']) { $catLib = (string)$cat['libelle']; break; }
        ?>
          <option value="<?= htmlspecialchars((string)$m['id']) ?>"
            <?= ($_POST['membre'] ?? '') === (string)$m['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($m->prenom . ' ' . $m->nom) ?> — <?= htmlspecialchars($catLib) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Complexité de l'algo (0-100):</label>
        <input type="number" name="complexite" min="0" max="100" required
               value="<?= htmlspecialchars((string)($_POST['complexite'] ?? '')) ?>"/>
      </div>
      <div class="form-group">
        <label>Temps exécution (ms):</label>
        <input type="number" name="temps" min="1" required
               value="<?= htmlspecialchars((string)($_POST['temps'] ?? '')) ?>"/>
      </div>
    </div>

    <button type="submit" class="btn btn-primary btn-full">S'inscrire</button>
  </form>
</div>

<?php endif; ?>
</main>
</body>
</html>
