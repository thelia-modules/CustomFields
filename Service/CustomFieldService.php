<?php

declare(strict_types=1);

namespace CustomFields\Service;

use CustomFields\Controller\Admin\CustomFieldValueController;
use CustomFields\Model\CustomFieldQuery;
use CustomFields\Model\CustomFieldRepeaterRowQuery;
use CustomFields\Model\CustomFieldValueQuery;
use CustomFields\Model\Map\CustomFieldTableMap;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\LangQuery;

class CustomFieldService
{
    private ImageService $imageService;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->imageService = new ImageService($dispatcher);
    }
    /**
     * Get custom field value by code, source, source_id and locale.
     *
     * @param string      $code     The custom field code
     * @param string|null $source   The source type (product, content, category, folder, general)
     * @param int|null    $sourceId The source entity ID (no ID if general)
     * @param string|null $locale   The locale (e.g., 'en_US'). If null, uses the default locale.
     *
     * @return string|null The custom field value or null if not found
     */
    public function getCustomFieldValue(string $code, ?string $source = 'general', ?int $sourceId = null, ?string $locale = null): string|int|null
    {
        // Get the custom field by code
        $customField = CustomFieldQuery::create()
            ->filterByCode($code)
            ->findOne();

        if (!$customField) {
            return null;
        }

        // Check if the custom field has this source
        $hasSource = CustomFieldQuery::create()
            ->filterById($customField->getId())
            ->useCustomFieldSourceQuery()
                ->filterBySource($source)
            ->endUse()
            ->count() > 0;

        if (!$hasSource) {
            return null;
        }

        // Get the value
        $customFieldValueQuery = CustomFieldValueQuery::create()
            ->filterByCustomFieldId($customField->getId())
            ->filterBySource($source);

        if (null !== $sourceId) {
            $customFieldValueQuery->filterBySourceId($sourceId);
        }

        $customFieldValue = $customFieldValueQuery->findOne();

        if (!$customFieldValue) {
            return null;
        }

        if (
            in_array($customField->getType(), CustomFieldValueController::CUSTOM_FIELD_SIMPLE_VALUES)
            || !$customField->isInternational()
        ) {
            return $customFieldValue->getSimpleValue();
        }
        if ($customField->getType() === CustomFieldTableMap::COL_TYPE_IMAGE) {
            return $customFieldValue->getCustomFieldImages()->getFirst()->getId();
        }

        // Set locale if provided
        if (null !== $locale) {
            $customFieldValue->setLocale($locale);
        }

        return $customFieldValue->getValue();
    }

    /**
     * Get custom field value by code, source, source_id and language ID.
     *
     * @param string $code       The custom field code
     * @param string $source     The source type (product, content, category, folder)
     * @param int    $sourceId   The source entity ID
     * @param int    $languageId The language ID
     *
     * @return string|null The custom field value or null if not found
     */
    public function getCustomFieldValueByLangId(string $code, ?string $source = 'general', int $sourceId, int $languageId): ?string
    {
        $lang = LangQuery::create()->findOneById($languageId);

        if (!$lang) {
            return null;
        }

        return $this->getCustomFieldValue($code, $source, $sourceId, $lang->getLocale());
    }

    public function getRepeaterValues(string $code, ?string $source = 'general', ?int $sourceId = null, ?string $locale = null): array
    {
        $customField = CustomFieldQuery::create()
            ->filterByCode($code)
            ->filterByType('repeater')
            ->findOne();

        if (!$customField) {
            return [];
        }

        if (null === $locale) {
            $locale = LangQuery::create()->findOneById(1)?->getLocale() ?? 'en_US';
        }

        $hasSource = CustomFieldQuery::create()
            ->filterById($customField->getId())
            ->useCustomFieldSourceQuery()
                ->filterBySource($source)
            ->endUse()
            ->count() > 0;

        if (!$hasSource) {
            return [];
        }

        $subFields = CustomFieldQuery::create()
            ->filterByCustomFieldParentId($customField->getId())
            ->orderByPosition()
            ->find();

        if ($subFields->isEmpty()) {
            return [];
        }

        $rows = CustomFieldRepeaterRowQuery::create()
            ->filterByCustomFieldId($customField->getId())
            ->filterBySource($source)
            ->filterBySourceId($sourceId)
            ->orderByPosition()
            ->find();

        $result = [];
        foreach ($rows as $row) {
            $rowId = $row->getId();
            $rowData = [];

            foreach ($subFields as $subField) {
                $rowData[$subField->getCode()] = $this->getFieldValue($subField, $source, $sourceId, $rowId, $locale);
            }

            $result[] = $rowData;
        }

        return $result;
    }

    private function getFieldValue($subField, string $source, ?int $sourceId, int $rowId, string $locale)
    {
        // Si c'est un repeater, traiter récursivement
        if ($subField->getType() === 'repeater') {
            return $this->getNestedRepeaterValues($subField, $source, $sourceId, $rowId, $locale);
        }

        $value = CustomFieldValueQuery::create()
            ->filterByCustomFieldId($subField->getId())
            ->filterBySource($source)
            ->filterBySourceId($sourceId)
            ->filterByRepeaterRowId($rowId)
            ->findOne();

        if (!$value) {
            return null;
        }

        if ($subField->getType() === CustomFieldTableMap::COL_TYPE_IMAGE) {
            $image = $value->getCustomFieldImages()->getFirst();
            if ($image && $image->getFile()) {
                [$fileUrl] = $this->imageService->imageProcess($image, false, 'none');
                return $fileUrl ?? null;
            }
            return null;
        }

        if (
            in_array($subField->getType(), CustomFieldValueController::CUSTOM_FIELD_SIMPLE_VALUES)
            || !$subField->isInternational()
        ) {
            return $value->getSimpleValue();
        }

        $value->setLocale($locale);
        return $value->getValue();
    }

    private function getNestedRepeaterValues($parentField, string $source, ?int $sourceId, int $parentRowId, string $locale): array
    {
        $subFields = CustomFieldQuery::create()
            ->filterByCustomFieldParentId($parentField->getId())
            ->orderByPosition()
            ->find();

        if ($subFields->isEmpty()) {
            return [];
        }

        $rows = CustomFieldRepeaterRowQuery::create()
            ->filterByCustomFieldId($parentField->getId())
            ->filterBySource($source)
            ->filterBySourceId($sourceId)
            ->orderByPosition()
            ->find();

        $result = [];
        foreach ($rows as $row) {
            $rowId = $row->getId();
            $rowData = [];

            foreach ($subFields as $subField) {
                $rowData[$subField->getCode()] = $this->getFieldValue($subField, $source, $sourceId, $rowId, $locale);
            }

            $result[] = $rowData;
        }

        return $result;
    }

}
