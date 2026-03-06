<?php

declare(strict_types=1);

namespace CustomFields\Service;

use CustomFields\Model\CustomField;
use CustomFields\Model\CustomFieldParentQuery;
use Propel\Runtime\Collection\ObjectCollection;

class CustomFieldSortingService
{
    /**
     * Group custom fields by parent
     * Returns an array with:
     * - 'no_parent' => array of fields without parent
     * - 'parents' => array of parent objects, each containing 'parent' (CustomFieldParent) and 'children' (array of CustomField)
     *
     * @param ObjectCollection $customFields
     * @return array
     */
    public function groupByParent(ObjectCollection $customFields): array
    {
        $noParent = [];
        $withParent = [];
        $parents = [];

        // First pass: separate fields by parent
        foreach ($customFields as $field) {
            if ($field->getCustomFieldParentId() === null) {
                $noParent[] = $field;
            } else {
                if (!isset($withParent[$field->getCustomFieldParentId()])) {
                    $withParent[$field->getCustomFieldParentId()] = [];
                }
                $withParent[$field->getCustomFieldParentId()][] = $field;
            }
        }

        // Second pass: build parent groups with actual CustomFieldParent objects
        $parentIds = array_keys($withParent);
        if (!empty($parentIds)) {
            $parentObjects = CustomFieldParentQuery::create()
                ->filterById($parentIds)
                ->find();

            foreach ($parentObjects as $parentObject) {
                $parents[] = [
                    'parent' => $parentObject,
                    'children' => $withParent[$parentObject->getId()],
                ];
            }
        }

        return [
            'no_parent' => array_values($noParent),
            'parents' => $parents,
        ];
    }
}
