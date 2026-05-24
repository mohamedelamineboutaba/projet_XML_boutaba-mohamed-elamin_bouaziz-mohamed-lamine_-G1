declare function local:Q1() {
  let $doc := doc("C:/xampp/htdocs/projet_final/club.xml")
  return
  <Q1_membres>{
    for $m in $doc//membres/membre
    let $cat := $doc//categorie[@id = $m/@categorieRef]
    return
      <membre id="{$m/@id}">
        <nomComplet>{$m/prenom/text()} {$m/nom/text()}</nomComplet>
        <email>{$m/email/text()}</email>
        <categorie>{$cat/@libelle/string()}</categorie>
      </membre>
  }</Q1_membres>
};

declare function local:Q2() {
  let $doc := doc("C:/xampp/htdocs/projet_final/club.xml")
  return
  <Q2_concours>{
    for $c in $doc//concours/concours
    let $cat := $doc//categorie[@id = $c/@categorieRef]
    order by xs:date($c/@date) ascending
    return
      <concours id="{$c/@id}">
        <titre>{$c/titre/text()}</titre>
        <date>{$c/@date/string()}</date>
        <coefficient>{$c/@coefficient/string()}</coefficient>
        <categorie>{$cat/@libelle/string()}</categorie>
      </concours>
  }</Q2_concours>
};

declare function local:Q3() {
  let $doc := doc("C:/xampp/htdocs/projet_final/club.xml")
  return
  <Q3_scores>{
    for $c in $doc//concours/concours
    let $coeff := xs:decimal($c/@coefficient)
    return
      <concours titre="{$c/titre/text()}" coefficient="{$coeff}">{
        for $p in $c/participants/participant
        let $cx    := xs:integer($p/complexite)
        let $te    := xs:integer($p/tempsExecution)
        let $score := round-half-to-even(($cx + $te) * $coeff, 2)
        let $m     := $doc//membre[@id = $p/@membreRef]
        return
          <participant membreRef="{$p/@membreRef}">
            <nom>{$m/prenom/text()} {$m/nom/text()}</nom>
            <complexite>{$cx}</complexite>
            <tempsExecution>{$te}</tempsExecution>
            <score>{$score}</score>
          </participant>
      }</concours>
  }</Q3_scores>
};

declare function local:Q4() {
  let $doc := doc("C:/xampp/htdocs/projet_final/club.xml")
  return
  <Q4_vainqueurs>{
    for $c in $doc//concours/concours
    let $coeff := xs:decimal($c/@coefficient)
    let $scores :=
      for $p in $c/participants/participant
      return round-half-to-even(
        (xs:integer($p/complexite) + xs:integer($p/tempsExecution)) * $coeff, 2)
    let $scoreMax := max($scores)
    return
      <concours titre="{$c/titre/text()}" scoreMax="{$scoreMax}">{
        for $p in $c/participants/participant
        let $score := round-half-to-even(
          (xs:integer($p/complexite) + xs:integer($p/tempsExecution)) * $coeff, 2)
        let $m := $doc//membre[@id = $p/@membreRef]
        where $score = $scoreMax
        return
          <vainqueur>
            <nom>{$m/nom/text()}</nom>
            <prenom>{$m/prenom/text()}</prenom>
            <score>{$score}</score>
          </vainqueur>
      }</concours>
  }</Q4_vainqueurs>
};

declare function local:Q5() {
  let $doc       := doc("C:/xampp/htdocs/projet_final/club.xml")
  let $categorie := "Intelligence Artificielle"
  let $catId     := $doc//categorie[@libelle = $categorie]/@id
  return
  <Q5_categorie categorie="{$categorie}">{
    for $m in $doc//membre[@categorieRef = $catId]
    order by $m/nom/text() ascending, $m/prenom/text() ascending
    return
      <membre id="{$m/@id}">
        <nom>{$m/nom/text()}</nom>
        <prenom>{$m/prenom/text()}</prenom>
        <email>{$m/email/text()}</email>
      </membre>
  }</Q5_categorie>
};

<resultats>{
  local:Q1(),
  local:Q2(),
  local:Q3(),
  local:Q4(),
  local:Q5()
}</resultats>
