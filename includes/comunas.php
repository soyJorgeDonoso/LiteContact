<?php
declare(strict_types=1);

// Fuente: División político-administrativa de Chile (actualizada a 2024)
// Estructura: $COMUNAS_BY_REGION = ['Región' => ['Comuna1','Comuna2',...], ...]
// Además exponemos $COMUNAS (lista plana) para compatibilidad y validación.

$COMUNAS_BY_REGION = [
  'Región de Arica y Parinacota' => [
    'Arica','Camarones','Putre','General Lagos'
  ],
  'Región de Tarapacá' => [
    'Iquique','Alto Hospicio','Pozo Almonte','Camiña','Colchane','Huara','Pica'
  ],
  'Región de Antofagasta' => [
    'Antofagasta','Mejillones','Sierra Gorda','Taltal','Calama','Ollagüe','San Pedro de Atacama','Tocopilla','María Elena'
  ],
  'Región de Atacama' => [
    'Copiapó','Caldera','Tierra Amarilla','Chañaral','Diego de Almagro','Vallenar','Alto del Carmen','Freirina','Huasco'
  ],
  'Región de Coquimbo' => [
    'La Serena','Coquimbo','Andacollo','La Higuera','Paihuano','Vicuña','Illapel','Canela','Los Vilos','Salamanca','Ovalle','Combarbalá','Monte Patria','Punitaqui','Río Hurtado'
  ],
  'Región de Valparaíso' => [
    'Valparaíso','Casablanca','Concón','Juan Fernández','Puchuncaví','Quintero','Viña del Mar','Isla de Pascua','Los Andes','Calle Larga','Rinconada','San Esteban','La Ligua','Cabildo','Papudo','Petorca','Zapallar','Quillota','Calera','Hijuelas','La Cruz','Nogales','San Antonio','Algarrobo','Cartagena','El Quisco','El Tabo','Santo Domingo','San Felipe','Catemu','Llaillay','Panquehue','Putaendo','Santa María','Quilpué','Limache','Olmué','Villa Alemana'
  ],
  'Región Metropolitana de Santiago' => [
    'Santiago','Cerrillos','Cerro Navia','Conchalí','El Bosque','Estación Central','Huechuraba','Independencia','La Cisterna','La Florida','La Granja','La Pintana','La Reina','Las Condes','Lo Barnechea','Lo Espejo','Lo Prado','Macul','Maipú','Ñuñoa','Pedro Aguirre Cerda','Peñalolén','Providencia','Pudahuel','Quilicura','Quinta Normal','Recoleta','Renca','San Joaquín','San Miguel','San Ramón','Vitacura','Puente Alto','Pirque','San José de Maipo','Colina','Lampa','Tiltil','San Bernardo','Buin','Calera de Tango','Paine','Melipilla','Alhué','Curacaví','María Pinto','San Pedro','Talagante','El Monte','Isla de Maipo','Padre Hurtado','Peñaflor'
  ],
  'Región del Libertador General Bernardo O’Higgins' => [
    'Rancagua','Codegua','Coinco','Coltauco','Doñihue','Graneros','Las Cabras','Machalí','Malloa','Mostazal','Olivar','Peumo','Pichidegua','Quinta de Tilcoco','Requínoa','Rengo','San Vicente','San Fernando','Chépica','Chimbarongo','Lolol','Nancagua','Palmilla','Peralillo','Placilla','Pumanque','Santa Cruz','Pichilemu','La Estrella','Litueche','Marchigüe','Navidad','Paredones'
  ],
  'Región del Maule' => [
    'Talca','Constitución','Curepto','Empedrado','Maule','Pelarco','Pencahue','Río Claro','San Clemente','San Rafael','Cauquenes','Chanco','Pelluhue','Curicó','Hualañé','Licantén','Molina','Rauco','Romeral','Sagrada Familia','Teno','Vichuquén','Linares','Colbún','Longaví','Parral','Retiro','San Javier','Villa Alegre','Yerbas Buenas'
  ],
  'Región de Ñuble' => [
    'Chillán','Chillán Viejo','Cobquecura','Coelemu','Ninhue','Portezuelo','Quirihue','Ránquil','Treguaco','Bulnes','Quillón','San Ignacio','El Carmen','Pemuco','Yungay','San Carlos','Coihueco','Ñiquén','San Fabián','San Nicolás'
  ],
  'Región del Biobío' => [
    'Concepción','Coronel','Chiguayante','Florida','Hualqui','Lota','Penco','San Pedro de la Paz','Santa Juana','Talcahuano','Tomé','Hualpén','Lebu','Arauco','Cañete','Contulmo','Curanilahue','Los Álamos','Tirúa','Los Ángeles','Antuco','Cabrero','Laja','Mulchén','Nacimiento','Negrete','Quilaco','Quilleco','San Rosendo','Santa Bárbara','Tucapel','Yumbel','Alto Biobío'
  ],
  'Región de La Araucanía' => [
    'Temuco','Carahue','Cholchol','Cunco','Curarrehue','Freire','Galvarino','Gorbea','Lautaro','Loncoche','Melipeuco','Nueva Imperial','Padre Las Casas','Perquenco','Pitrufquén','Pucón','Saavedra','Teodoro Schmidt','Toltén','Vilcún','Villarrica','Angol','Collipulli','Curacautín','Ercilla','Lonquimay','Los Sauces','Lumaco','Purén','Renaico','Traiguén','Victoria'
  ],
  'Región de Los Ríos' => [
    'Valdivia','Corral','Lanco','Los Lagos','Máfil','Mariquina','Paillaco','Panguipulli','La Unión','Futrono','Lago Ranco','Río Bueno'
  ],
  'Región de Los Lagos' => [
    'Puerto Montt','Calbuco','Cochamó','Fresia','Frutillar','Los Muermos','Llanquihue','Maullín','Puerto Varas','Castro','Ancud','Chonchi','Curaco de Vélez','Dalcahue','Puqueldón','Queilén','Quellón','Quemchi','Quinchao','Osorno','Puerto Octay','Purranque','Puyehue','Río Negro','San Juan de la Costa','San Pablo','Chaitén','Futaleufú','Hualaihué','Palena'
  ],
  'Región de Aysén del General Carlos Ibáñez del Campo' => [
    'Coyhaique','Lago Verde','Aysén','Cisnes','Guaitecas','Cochrane','O’Higgins','Tortel','Chile Chico','Río Ibáñez'
  ],
  'Región de Magallanes y de la Antártica Chilena' => [
    'Punta Arenas','Laguna Blanca','Río Verde','San Gregorio','Cabo de Hornos','Antártica','Porvenir','Primavera','Timaukel','Natales','Torres del Paine'
  ],
  'Región de Tarapacá (ex Provincia del Tamarugal)' => [
    // Ya incluidas en Tarapacá
  ],
  'Región de Atacama (complemento)' => [
    // Ya incluidas
  ],
];

// Lista plana ordenada naturalmente (case-insensitive)
$COMUNAS = [];
foreach ($COMUNAS_BY_REGION as $region => $comunas) {
  foreach ($comunas as $c) { $COMUNAS[] = $c; }
}
$COMUNAS = array_values(array_unique(array_map('strval', $COMUNAS)));
natcasesort($COMUNAS);

// Para validación rápida
function comuna_exists(string $name): bool {
  global $COMUNAS;
  return in_array($name, $COMUNAS, true);
}

?>

