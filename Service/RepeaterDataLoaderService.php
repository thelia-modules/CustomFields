<?php

declare(strict_types=1);

namespace CustomFields\Service;

use CustomFields\Model\CustomFieldQuery;
use CustomFields\Model\CustomFieldRepeaterRowQuery;
use CustomFields\Model\CustomFieldValueQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Service pour charger les données des champs repeater de manière récursive
 */
class RepeaterDataLoaderService
{
    private ImageService $imageService;

    public function __construct(
        private readonly EventDispatcherInterface $dispatcher
    ) {
        $this->imageService = new ImageService($this->dispatcher);
    }

    /**
     * Charge les données des repeaters de manière récursive
     *
     * @param iterable $customFields Liste des custom fields à traiter
     * @param string $source Source des données (product, content, category, folder, general, option_page_*)
     * @param int|null $sourceId ID de la source (null pour general et option pages)
     * @param string $locale Locale pour les champs internationaux
     * @param int|null $parentRepeaterRowId ID de la ligne parent (pour les sous-repeaters)
     * @return array Tableau avec [repeaterValues, repeaterSubfields]
     */
    public function loadRepeaterData(
        iterable $customFields,
        string $source,
        ?int $sourceId,
        string $locale,
        ?int $parentRepeaterRowId = null
    ): array {
        $repeaterValues = [];
        $repeaterSubfields = [];

        foreach ($customFields as $customField) {
            if ($customField->getType() !== 'repeater') {
                continue;
            }

            $repeaterId = $customField->getId();

            // Charger les sous-champs du repeater
            $subFields = CustomFieldQuery::create()
                ->filterByCustomFieldParentId($repeaterId)
                ->orderByPosition()
                ->find();

            $repeaterSubfields[$repeaterId] = $subFields;

            // Charger les sous-repeaters récursivement
            foreach ($subFields as $subField) {
                if ($subField->getType() === 'repeater') {
                    [$subRepeaterValues, $subRepeaterSubfields] = $this->loadRepeaterData(
                        [$subField],
                        $source,
                        $sourceId,
                        $locale,
                        $parentRepeaterRowId
                    );
                    $repeaterValues = $repeaterValues + $subRepeaterValues;
                    $repeaterSubfields = $repeaterSubfields + $subRepeaterSubfields;
                }
            }

            // Charger les lignes du repeater
            $rows = CustomFieldRepeaterRowQuery::create()
                ->filterByCustomFieldId($repeaterId)
                ->filterBySource($source)
                ->filterBySourceId($sourceId)
                ->orderByPosition()
                ->find();

            $rowData = [];
            foreach ($rows as $row) {
                $rowId = $row->getId();
                $rowValues = [];

                foreach ($subFields as $subField) {
                    if ($subField->getType() === 'repeater') {
                        // Pour les sous-repeaters, charger leurs lignes spécifiques
                        $subRepeaterId = $subField->getId();
                        $subRepeaterRows = CustomFieldRepeaterRowQuery::create()
                            ->filterByCustomFieldId($subRepeaterId)
                            ->filterBySource($source)
                            ->filterBySourceId($sourceId)
                            ->orderByPosition()
                            ->find();

                        $subRowData = [];
                        $subSubFields = $repeaterSubfields[$subRepeaterId] ?? [];

                        foreach ($subRepeaterRows as $subRow) {
                            $subRowId = $subRow->getId();
                            $subRowValues = [];

                            foreach ($subSubFields as $subSubField) {
                                $subValue = CustomFieldValueQuery::create()
                                    ->filterByCustomFieldId($subSubField->getId())
                                    ->filterBySource($source)
                                    ->filterBySourceId($sourceId)
                                    ->filterByRepeaterRowId($subRowId)
                                    ->findOne();

                                if ($subValue) {
                                    $subRowValues[$subSubField->getId()] = $this->formatFieldValue(
                                        $subValue,
                                        $subSubField,
                                        $locale
                                    );
                                } else {
                                    $subRowValues[$subSubField->getId()] = '';
                                }
                            }

                            $subRowData[] = ['__row_id' => $subRowId] + $subRowValues;
                        }

                        $rowValues[$subField->getId()] = $subRowData;
                    } else {
                        // Champ simple
                        $value = CustomFieldValueQuery::create()
                            ->filterByCustomFieldId($subField->getId())
                            ->filterBySource($source)
                            ->filterBySourceId($sourceId)
                            ->filterByRepeaterRowId($rowId)
                            ->findOne();

                        if ($value) {
                            $rowValues[$subField->getId()] = $this->formatFieldValue(
                                $value,
                                $subField,
                                $locale
                            );
                        } else {
                            $rowValues[$subField->getId()] = '';
                        }
                    }
                }

                $rowData[] = ['__row_id' => $rowId] + $rowValues;
            }

            $repeaterValues[$repeaterId] = $rowData;
        }

        return [$repeaterValues, $repeaterSubfields];
    }

    /**
     * Formate la valeur d'un champ selon son type
     *
     * @param \CustomFields\Model\CustomFieldValue $value
     * @param \CustomFields\Model\CustomField $field
     * @param string $locale
     * @return mixed
     */
    private function formatFieldValue($value, $field, string $locale)
    {
        if ($field->getType() === 'image') {
            $image = $value->getCustomFieldImages()->getFirst();
            if ($image && $image->getFile()) {
                [$fileUrl] = $this->imageService->imageProcess($image, false, 'none');
                return [
                    'id'  => $image->getId(),
                    'url' => $fileUrl ?? '',
                ];
            }
            return null;
        }

        if (
            in_array($field->getType(), ['content', 'category', 'folder', 'product'])
            || !$field->isInternational()
        ) {
            return $value->getSimpleValue();
        }

        $value->setLocale($locale);
        return $value->getValue();
    }
}
