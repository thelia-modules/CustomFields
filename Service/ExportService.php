<?php

namespace CustomFields\Service;

use CustomFields\Model\CustomField;
use CustomFields\Model\CustomFieldParentQuery;
use CustomFields\Model\CustomFieldQuery;
use CustomFields\Model\CustomFieldSource;
use CustomFields\Model\CustomFieldSourceQuery;
use Propel\Runtime\Exception\PropelException;

class ExportService
{
    /**
     * @throws PropelException
     */
    public function getExportData(): array
    {
        $export = [];
        $customFields = CustomFieldQuery::create()->find();
        foreach ($customFields as $customField) {
            $parent = 'no_parent';
            if (null !== $customField->getCustomFieldParent()) {
                $parent = $customField->getCustomFieldParent()->getTitle();
            }
            $export[$parent][] = $this->serializeCustomFields($customField);
        }

        return $export;
    }

    private function serializeCustomFields(CustomField $customField)
    {
        return [
            'title' => $customField->getTitle(),
            'code' => $customField->getCode(),
            'type' => $customField->getType(),
            'sources' => $customField->getCustomFieldSources()->getColumnValues('source'),
        ];
    }

    public function importData(array $data)
    {
        foreach ($data as $parent => $fields) {
            $customFieldParent = null;
            if ('no_parent' !== $parent) {
                $customFieldParent = CustomFieldParentQuery::create()->filterByTitle($parent)->findOneOrCreate();
            }
            foreach ($fields as $field) {
                $customField = CustomFieldQuery::create()
                    ->filterByCode($field['code'])
                    ->findOneOrCreate();

                $customField
                    ->setTitle($field['title'])
                    ->setType($field['type'])
                    ->save()
                ;
                CustomFieldSourceQuery::create()
                    ->filterByCustomFieldId($customField->getId())
                    ->delete();

                $sources = $field['sources'];
                if (is_array($sources)) {
                    foreach ($sources as $source) {
                        $customFieldSource = new CustomFieldSource();
                        $customFieldSource
                            ->setCustomFieldId($customField->getId())
                            ->setSource($source)
                            ->save();
                    }
                }
                if ($customFieldParent) {
                    $customField->setCustomFieldParent($customFieldParent);
                }
                $customField->save();
            }
        }
    }
}
