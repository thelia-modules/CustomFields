<?php

declare(strict_types=1);

namespace CustomFields\Service;

use CustomFields\Model\CustomFieldQuery;
use Propel\Runtime\ActiveQuery\Criteria;

class CustomFieldValidationService
{
    /**
     * Valide l'unicité du code d'un custom field
     *
     * @param string $code Le code à valider
     * @param int|null $customFieldId ID du champ en édition (null si création)
     * @param int|null $parentId ID du parent (peut être custom_field_parent ou repeater)
     * @return bool True si le code est unique, False sinon
     */
    public function isCodeUnique(string $code, ?int $customFieldId = null, ?int $parentId = null): bool
    {
        // Vérifier si le parent est un repeater
        $repeaterParentId = $this->getRepeaterParentId($parentId);

        $query = CustomFieldQuery::create()
            ->filterByCode($code);

        // Exclure le champ en cours d'édition
        if ($customFieldId) {
            $query->filterById($customFieldId, Criteria::NOT_EQUAL);
        }

        // Si c'est un sous-champ de repeater : vérifier l'unicité dans ce repeater uniquement
        if ($repeaterParentId) {
            $query->filterByCustomFieldParentId($repeaterParentId);
        }

        return $query->count() === 0;
    }

    /**
     * Vérifie si le parent est un repeater et retourne son ID, sinon null
     *
     * @param int|null $parentId
     * @return int|null
     */
    public function getRepeaterParentId(?int $parentId): ?int
    {
        if (!$parentId) {
            return null;
        }

        $parentField = CustomFieldQuery::create()->findPk($parentId);

        if ($parentField && $parentField->getType() === 'repeater') {
            return $parentId;
        }

        return null;
    }

    /**
     * Retourne un message d'erreur approprié
     *
     * @param int|null $parentId ID du parent (peut être custom_field_parent ou repeater)
     * @return string
     */
    public function getErrorMessage(?int $parentId = null): string
    {
        $repeaterParentId = $this->getRepeaterParentId($parentId);

        if ($repeaterParentId) {
            return 'This code already exists in this repeater. Please choose another code.';
        }

        return 'This code already exists. Please choose another code.';
    }
}
