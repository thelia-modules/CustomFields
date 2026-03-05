<?php

declare(strict_types=1);

namespace CustomFields\Service;

use CustomFields\Model\CustomFieldQuery;
use CustomFields\Model\CustomFieldValueQuery;
use Thelia\Model\LangQuery;

class CustomFieldService
{
    /**
     * Get custom field value by code, source, source_id and locale
     *
     * @param string $code The custom field code
     * @param string $source The source type (product, content, category, folder)
     * @param int $sourceId The source entity ID
     * @param string|null $locale The locale (e.g., 'en_US'). If null, uses the default locale.
     * @return string|null The custom field value or null if not found
     */
    public function getCustomFieldValue(string $code, string $source, int $sourceId, ?string $locale = null): ?string
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
        $customFieldValue = CustomFieldValueQuery::create()
            ->filterByCustomFieldId($customField->getId())
            ->filterBySource($source)
            ->filterBySourceId($sourceId)
            ->findOne();

        if (!$customFieldValue) {
            return null;
        }

        // Set locale if provided
        if ($locale !== null) {
            $customFieldValue->setLocale($locale);
        }

        return $customFieldValue->getValue();
    }

    /**
     * Get custom field value by code, source, source_id and language ID
     *
     * @param string $code The custom field code
     * @param string $source The source type (product, content, category, folder)
     * @param int $sourceId The source entity ID
     * @param int $languageId The language ID
     * @return string|null The custom field value or null if not found
     */
    public function getCustomFieldValueByLangId(string $code, string $source, int $sourceId, int $languageId): ?string
    {
        $lang = LangQuery::create()->findOneById($languageId);

        if (!$lang) {
            return null;
        }

        return $this->getCustomFieldValue($code, $source, $sourceId, $lang->getLocale());
    }
}
