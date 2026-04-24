<?php

namespace CustomFields\Service;

use CustomFields\Model\CustomField;
use CustomFields\Model\CustomFieldParent;
use CustomFields\Model\CustomFieldParentQuery;
use CustomFields\Model\CustomFieldQuery;
use CustomFields\Model\CustomFieldSource;
use CustomFields\Model\CustomFieldSourceQuery;
use CustomFields\Model\CustomFieldOptionPage;
use CustomFields\Model\CustomFieldOptionPageQuery;
use Propel\Runtime\Exception\PropelException;

class ExportService
{
    /**
     * @throws PropelException
     */
    public function getExportData(): array
    {
        return [
            'option_pages' => $this->getOptionPagesData(),
            'custom_fields' => $this->getCustomFieldsData(),
        ];
    }

    /**
     * @throws PropelException
     */
    private function getOptionPagesData(): array
    {
        $optionPages = [];
        $pages = CustomFieldOptionPageQuery::create()->find();
        foreach ($pages as $page) {
            $optionPages[] = [
                'title' => $page->getTitle(),
                'code' => $page->getCode(),
            ];
        }

        return $optionPages;
    }

    /**
     * @throws PropelException
     */
    private function getCustomFieldsData(): array
    {
        $export = [];

        // Build set of sub-field IDs (fields whose parent_id points to a repeater)
        $repeaterIds = CustomFieldQuery::create()
            ->filterByType('repeater')
            ->select(['Id'])
            ->find()
            ->toArray();

        $subFieldIds = [];
        if (!empty($repeaterIds)) {
            $subFields = CustomFieldQuery::create()
                ->filterByCustomFieldParentId($repeaterIds)
                ->select(['Id'])
                ->find()
                ->toArray();
            $subFieldIds = array_flip($subFields);
        }

        $customFields = CustomFieldQuery::create()->find();
        foreach ($customFields as $customField) {
            // Sub-fields are exported nested under their parent repeater
            if (isset($subFieldIds[$customField->getId()])) {
                continue;
            }

            $parent = 'no_parent';
            if (null !== $customField->getCustomFieldParent()) {
                $parent = $customField->getCustomFieldParent()->getTitle();
            }

            $export[$parent][] = $this->serializeCustomField($customField, $subFieldIds);
        }

        return $export;
    }

    private function serializeCustomField(CustomField $customField, array $subFieldIds = []): array
    {
        $data = [
            'title' => $customField->getTitle(),
            'code' => $customField->getCode(),
            'type' => $customField->getType(),
            'is_international' => $customField->getIsInternational(),
            'sources' => $customField->getCustomFieldSources()->getColumnValues('source'),
        ];

        if ($customField->getType() === 'repeater') {
            $subFields = CustomFieldQuery::create()
                ->filterByCustomFieldParentId($customField->getId())
                ->orderByPosition()
                ->find();

            $data['sub_fields'] = [];
            foreach ($subFields as $subField) {
                $data['sub_fields'][] = [
                    'title' => $subField->getTitle(),
                    'code' => $subField->getCode(),
                    'type' => $subField->getType(),
                    'is_international' => $subField->getIsInternational(),
                ];
            }
        }

        return $data;
    }

    public function importData(array $data): void
    {
        if (isset($data['option_pages']) && is_array($data['option_pages'])) {
            $this->importOptionPages($data['option_pages']);
        }

        if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
            $this->importCustomFields($data['custom_fields']);
        }

        if (!isset($data['option_pages']) && !isset($data['custom_fields'])) {
            $this->importCustomFields($data);
        }
    }

    /**
     * @throws PropelException
     */
    private function importOptionPages(array $pages): void
    {
        foreach ($pages as $page) {
            $optionPage = CustomFieldOptionPageQuery::create()
                ->filterByCode($page['code'])
                ->findOneOrCreate();

            $optionPage
                ->setTitle($page['title'])
                ->save();
        }
    }

    /**
     * @throws PropelException
     */
    private function importCustomFields(array $data): void
    {
        foreach ($data as $parent => $fields) {
            $customFieldParent = null;
            if ('no_parent' !== $parent) {
                $customFieldParent = CustomFieldParentQuery::create()->filterByTitle($parent)->findOneOrCreate();
                $customFieldParent->setTitle($parent)->save();
            }

            foreach ($fields as $field) {
                $customField = CustomFieldQuery::create()
                    ->filterByCode($field['code'])
                    ->findOneOrCreate();

                $customField
                    ->setTitle($field['title'])
                    ->setType($field['type'])
                    ->setIsInternational($field['is_international'] ?? true)
                    ->setCustomFieldParentId($customFieldParent ? $customFieldParent->getId() : null)
                    ->save();

                CustomFieldSourceQuery::create()
                    ->filterByCustomFieldId($customField->getId())
                    ->delete();

                $sources = $field['sources'] ?? [];
                if (is_array($sources)) {
                    foreach ($sources as $source) {
                        $customFieldSource = new CustomFieldSource();
                        $customFieldSource
                            ->setCustomFieldId($customField->getId())
                            ->setSource($source)
                            ->save();
                    }
                }

                // Import sub-fields for repeaters
                if ($customField->getType() === 'repeater' && !empty($field['sub_fields'])) {
                    foreach ($field['sub_fields'] as $position => $subFieldData) {
                        $subField = CustomFieldQuery::create()
                            ->filterByCode($subFieldData['code'])
                            ->findOneOrCreate();

                        $subField
                            ->setTitle($subFieldData['title'])
                            ->setType($subFieldData['type'])
                            ->setIsInternational($subFieldData['is_international'] ?? true)
                            ->setCustomFieldParentId($customField->getId())
                            ->setPosition($position)
                            ->save();

                        // Sub-fields share the same sources as the parent repeater
                        CustomFieldSourceQuery::create()
                            ->filterByCustomFieldId($subField->getId())
                            ->delete();

                        foreach ($sources as $source) {
                            $customFieldSource = new CustomFieldSource();
                            $customFieldSource
                                ->setCustomFieldId($subField->getId())
                                ->setSource($source)
                                ->save();
                        }
                    }
                }
            }
        }
    }
}
