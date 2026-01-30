-- Script SQL pour vérifier pourquoi l'abonnement IZI n'apparaît pas

-- 1. Vérifier le nom de l'opérateur avec l'ID 14
SELECT 
    country_payments_methods_id,
    country_payments_methods_name,
    country_payments_methods_desc
FROM country_payments_methods
WHERE country_payments_methods_id = 14;

-- 2. Vérifier tous les opérateurs contenant IZI ou Privilèges
SELECT 
    country_payments_methods_id,
    country_payments_methods_name
FROM country_payments_methods
WHERE country_payments_methods_name LIKE '%IZI%'
   OR country_payments_methods_name LIKE '%Privil%'
   OR country_payments_methods_name LIKE '%izi%'
   OR country_payments_methods_name LIKE '%privil%'
ORDER BY country_payments_methods_name;

-- 3. Vérifier l'abonnement avec l'ID 256409 et son opérateur
SELECT 
    ca.client_abonnement_id,
    ca.client_abonnement_creation,
    ca.client_abonnement_expiration,
    ca.country_payments_methods_id,
    cpm.country_payments_methods_name,
    cpm.country_payments_methods_id
FROM client_abonnement ca
JOIN country_payments_methods cpm ON ca.country_payments_methods_id = cpm.country_payments_methods_id
WHERE ca.client_abonnement_id = 256409;

-- 4. Compter les abonnements pour l'ID 14 dans la période 26/11/2025 - 09/12/2025
SELECT 
    COUNT(*) as total_abonnements,
    COUNT(CASE WHEN client_abonnement_creation >= '2025-11-26 00:00:00' 
               AND client_abonnement_creation <= '2025-12-09 23:59:59' 
          THEN 1 END) as dans_periode
FROM client_abonnement
WHERE country_payments_methods_id = 14;

-- 5. Lister les abonnements pour l'ID 14 dans la période
SELECT 
    ca.client_abonnement_id,
    ca.client_abonnement_creation,
    ca.client_abonnement_expiration,
    cpm.country_payments_methods_name
FROM client_abonnement ca
JOIN country_payments_methods cpm ON ca.country_payments_methods_id = cpm.country_payments_methods_id
WHERE ca.country_payments_methods_id = 14
  AND ca.client_abonnement_creation >= '2025-11-26 00:00:00'
  AND ca.client_abonnement_creation <= '2025-12-09 23:59:59'
ORDER BY ca.client_abonnement_creation DESC;

-- 6. Vérifier si "S'abonner via IZI" existe exactement
SELECT 
    country_payments_methods_id,
    country_payments_methods_name,
    LENGTH(country_payments_methods_name) as longueur_nom,
    HEX(country_payments_methods_name) as nom_hex
FROM country_payments_methods
WHERE country_payments_methods_name = "S'abonner via IZI"
   OR country_payments_methods_name LIKE "%IZI%";

