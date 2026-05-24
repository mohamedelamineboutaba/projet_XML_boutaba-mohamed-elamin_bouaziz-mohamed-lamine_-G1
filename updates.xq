declare updating function local:runAll() {
  insert node
    <membre id="M010" categorieRef="C2">
      <nom>Bensaid</nom>
      <prenom>Yasmine</prenom>
      <email>y.bensaid@club.dz</email>
    </membre>
  into doc("C:/xampp/htdocs/projet_final/club.xml")//membres
  ,
  replace value of node
    doc("C:/xampp/htdocs/projet_final/club.xml")//concours[@id="CO2"]/@coefficient
  with "2.0"
  ,
  delete node
    doc("C:/xampp/htdocs/projet_final/club.xml")//concours[@id="CO1"]//participant[@membreRef="M001"]
};

local:runAll()
