USE db_cocina;

-- Asigna las fotografías originales sin borrar usuarios, compras ni candidaturas.
UPDATE cocineros SET imagen = 'assets/images/CHEF1.jpg' WHERE nombre = 'Zendaya Campbel';
UPDATE cocineros SET imagen = 'assets/images/CHEF2.jpg' WHERE nombre = 'Mark Rodriguez';
UPDATE cocineros SET imagen = 'assets/images/CHEF3.jpg' WHERE nombre = 'Han Xiwanmu';
UPDATE cocineros SET imagen = 'assets/images/CHEF4.jpg' WHERE nombre = 'Dina Sato';

UPDATE cursos SET imagen = 'assets/images/comida_corea.jpg' WHERE slug = 'cocina-coreana-tradicional';
UPDATE cursos SET imagen = 'assets/images/comida_japo.jpg' WHERE slug = 'fundamentos-cocina-japonesa';
-- El curso mexicano queda sin imagen porque no se ha facilitado una fotografía mexicana.
UPDATE cursos SET imagen = 'assets/images/comida_españa.jpg' WHERE slug = 'clasicos-cocina-espanola';
UPDATE cursos SET imagen = 'assets/images/comida_china.jpg' WHERE slug = 'secretos-de-la-cocina-china';
