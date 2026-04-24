-- Migration 1.1.0: Refactoring parents (groupes) avec source

-- 1. Ajouter la colonne source aux parents
ALTER TABLE custom_field_parent ADD COLUMN source VARCHAR(100);

-- 2. Créer un groupe par défaut pour chaque source existante
INSERT INTO custom_field_parent (title, source)
SELECT DISTINCT
    CASE cfs.source
        WHEN 'content' THEN 'Groupe Content'
        WHEN 'product' THEN 'Groupe Product'
        WHEN 'category' THEN 'Groupe Category'
        WHEN 'folder' THEN 'Groupe Folder'
        WHEN 'general' THEN 'Groupe General'
        ELSE CONCAT('Groupe ', UPPER(SUBSTRING(cfs.source, 1, 1)), SUBSTRING(cfs.source, 2))
    END as title,
    cfs.source
FROM custom_field_source cfs
WHERE cfs.source NOT IN (SELECT COALESCE(source, '') FROM custom_field_parent WHERE source IS NOT NULL)
GROUP BY cfs.source;

-- 3. Associer les custom fields existants à leur groupe correspondant selon leur source
-- Pour les champs qui ont déjà un parent, on les laisse
-- Pour les champs sans parent, on leur assigne le groupe de leur première source
UPDATE custom_field cf
INNER JOIN (
    SELECT cfs.custom_field_id, MIN(cfs.source) as first_source
    FROM custom_field_source cfs
    GROUP BY cfs.custom_field_id
) as field_sources ON cf.id = field_sources.custom_field_id
INNER JOIN custom_field_parent cfp ON cfp.source = field_sources.first_source
SET cf.custom_field_parent_id = cfp.id
WHERE cf.custom_field_parent_id IS NULL;

-- 4. Pour les champs ayant plusieurs sources, créer des duplicatas
-- Note: Cette partie est commentée car elle pourrait créer des doublons

-- 5. Supprimer la table custom_field_source
DROP TABLE custom_field_source;

-- 6. Rendre la colonne source obligatoire (NOT NULL)
ALTER TABLE custom_field_parent MODIFY COLUMN source VARCHAR(100) NOT NULL;

-- 7. Ajouter une contrainte d'unicité sur la source
ALTER TABLE custom_field_parent ADD UNIQUE KEY unique_source (source);
