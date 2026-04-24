<?php

declare(strict_types=1);

namespace CustomFields\Controller\Admin;

use CustomFields\Form\CustomFieldValueForm;
use CustomFields\Model\CustomFieldImage;
use CustomFields\Model\CustomFieldImageQuery;
use CustomFields\Model\CustomFieldOptionPageQuery;
use CustomFields\Model\CustomFieldQuery;
use CustomFields\Model\CustomFieldRepeaterRow;
use CustomFields\Model\CustomFieldRepeaterRowQuery;
use CustomFields\Model\CustomFieldValueQuery;
use CustomFields\Model\Map\CustomFieldTableMap;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\LangQuery;
use Thelia\Tools\URL;

#[AsController]
#[Route(path: '/admin/module/customfields', name: 'customfields_')]
final class CustomFieldValueController extends BaseAdminController
{
    // champs qui n'ont pas de traductions
    public const CUSTOM_FIELD_SIMPLE_VALUES = [
        CustomFieldTableMap::COL_TYPE_CONTENT,
        CustomFieldTableMap::COL_TYPE_CATEGORY,
        CustomFieldTableMap::COL_TYPE_FOLDER,
        CustomFieldTableMap::COL_TYPE_PRODUCT,
        CustomFieldTableMap::COL_TYPE_CHECKBOX ?? 'checkbox'
    ];

    #[Route(path: '/values/save', name: 'values_save', methods: ['POST'])]
    public function saveCustomFieldValues(): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(CustomFieldValueForm::getName());

        try {
            $validatedForm = $this->validateForm($form);

            $source = $validatedForm->get('source')->getData();
            $sourceId = ($source === 'general' || str_starts_with($source, 'option_page_')) ? null : (int) $validatedForm->get('source_id')->getData();
            $editLanguageId = (int) $this->getRequest()->request->get('edit_language_id', $this->getSession()->getLang()->getId());
            $locale = LangQuery::create()->findOneById($editLanguageId)->getLocale();

            // Get all custom fields for this source
            $customFields = CustomFieldQuery::create()
                ->useCustomFieldSourceQuery()
                    ->filterBySource($source)
                ->endUse()
                ->find();

            foreach ($customFields as $customField) {
                $fieldKey = 'custom_field_'.$customField->getId();

                // Handle repeater type
                if ($customField->getType() === 'repeater') {
                    $this->handleRepeaterField($customField, $source, $sourceId, $locale);
                    continue;
                }

                // Handle image type separately
                if ($customField->getType() === CustomFieldTableMap::COL_TYPE_IMAGE) {
                    $this->handleImageUpload($fieldKey, $customField->getId(), $source, $sourceId, null);
                } else {
                    $this->handleFieldValue($fieldKey, $customField, $source, $sourceId, $locale, null);
                }
            }

            // Handle save mode (stay or close)
            $saveMode = $this->getRequest()->request->get('save_mode', 'stay');

            // Redirect based on source type and save mode
            $redirectUrls = [
                'product' => '/admin/products/update/?product_id='.$sourceId,
                'content' => '/admin/content/update/'.$sourceId,
                'category' => '/admin/categories/update?category_id='.$sourceId,
                'folder' => '/admin/folders/update/'.$sourceId,
                'general' => '/admin/module/customfields/list',
            ];

            // Handle option page sources
            $isOptionPage = str_starts_with($source, 'option_page_');
            if ($isOptionPage) {
                $optionPageCode = substr($source, strlen('option_page_'));
                $optionPage = CustomFieldOptionPageQuery::create()
                    ->filterByCode($optionPageCode)
                    ->findOne();

                if ($optionPage) {
                    $redirectUrls[$source] = '/admin/module/customfields/option-pages/view/' . $optionPage->getId();
                }
            }

            if ('close' === $saveMode) {
                if ($isOptionPage) {
                    $redirectUrl = '/admin/module/customfields/list';
                } else {
                    $redirectUrl = match ($source) {
                        'product' => '/admin/products',
                        'content' => '/admin/content',
                        'category' => '/admin/categories',
                        'folder' => '/admin/folders',
                        'general' => '/admin/module/customfields/list',
                        default => '/admin/module/customfields/list',
                    };
                }
            } else {
                $redirectUrl = $redirectUrls[$source] ?? '/admin/module/customfields/list';
            }

            $params = [
                'success' => Translator::getInstance()->trans('Custom field values saved successfully', [], 'customfields'),
            ];

            if ('stay' === $saveMode) {
                if ($source === 'general') {
                    $params['current_tab'] = 'general_values';
                } else {
                    $params['current_tab'] = 'custom_fields';
                }
                $params['edit_language_id'] = $editLanguageId;
            }

            return new RedirectResponse(
                URL::getInstance()->absoluteUrl($redirectUrl, $params)
            );
        } catch (FormValidationException $e) {
            $error = $e->getMessage();
        } catch (PropelException $e) {
            $error = Translator::getInstance()->trans('An error occurred while saving custom field values', [], 'customfields');
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        return new RedirectResponse(
            URL::getInstance()->absoluteUrl('/admin/module/customfields/list', [
                'error' => $error ?? 'Unknown error',
            ])
        );
    }

    #[Route(path: '/image/delete/{id}', name: 'image_delete', methods: ['POST', 'GET'])]
    public function deleteCustomFieldImage(int $id): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::DELETE)) {
            return $response;
        }

        $customFieldImage = CustomFieldImageQuery::create()->findPk($id);

        if (!$customFieldImage) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl($this->getRequest()->headers->get('referer', '/admin/module/customfields/list'), [
                    'error' => Translator::getInstance()->trans('Image not found', [], 'customfields'),
                ])
            );
        }

        try {
            // Delete physical file
            $uploadDir = $customFieldImage->getUploadDir();
            $file = $uploadDir . DS . $customFieldImage->getFile();
            if (file_exists($file)) {
                unlink($file);
            }

            // Delete database entry
            $customFieldImage->delete();

            return new RedirectResponse(
                URL::getInstance()->absoluteUrl($this->getRequest()->headers->get('referer', '/admin/module/customfields/list'), [
                    'success' => Translator::getInstance()->trans('Image deleted successfully', [], 'customfields'),
                ])
            );
        } catch (PropelException $e) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl($this->getRequest()->headers->get('referer', '/admin/module/customfields/list'), [
                    'error' => Translator::getInstance()->trans('An error occurred while deleting the image', [], 'customfields'),
                ])
            );
        }
    }

    private function handleRepeaterField(
        \CustomFields\Model\CustomField $customField,
        string $source,
        ?int $sourceId,
        string $locale,
        string $parentPath = 'custom_field',
        ?int $parentRepeaterRowId = null
    ): void {
        $repeaterId = $customField->getId();
        $fieldKey = $parentPath . '_' . $repeaterId;
        $rowsKey = $fieldKey . '_rows';

        if (!$this->getRequest()->request->has($rowsKey)) {
            return;
        }

        $rowCount = (int) $this->getRequest()->request->get($rowsKey, 0);

        // Récupérer les lignes existantes
        $existingRowsQuery = CustomFieldRepeaterRowQuery::create()
            ->filterByCustomFieldId($repeaterId)
            ->filterBySource($source)
            ->filterBySourceId($sourceId)
            ->filterByParentRepeaterRowId($parentRepeaterRowId);

        $existingRows = $existingRowsQuery->find();

        $existingRowIds = [];
        foreach ($existingRows as $row) {
            $existingRowIds[] = $row->getId();
        }

        $newRowIds = [];

        // Créer/mettre à jour les lignes
        for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++) {
            $existingRowId = (int) $this->getRequest()->request->get(
                $fieldKey . '_' . $rowIndex . '_row_id',
                0
            );

            $repeaterRow = null;
            if ($existingRowId > 0) {
                $repeaterRow = CustomFieldRepeaterRowQuery::create()->findPk($existingRowId);
            }

            if (!$repeaterRow) {
                $repeaterRow = new CustomFieldRepeaterRow();
                $repeaterRow->setCustomFieldId($repeaterId);
                $repeaterRow->setSource($source);
                $repeaterRow->setSourceId($sourceId);
                $repeaterRow->setParentRepeaterRowId($parentRepeaterRowId);
            }

            $repeaterRow->setPosition($rowIndex);
            $repeaterRow->save();

            $newRowIds[] = $repeaterRow->getId();
        }

        // Supprimer les lignes qui ne sont plus présentes
        $rowsToDelete = array_diff($existingRowIds, $newRowIds);
        if (!empty($rowsToDelete)) {
            CustomFieldRepeaterRowQuery::create()
                ->filterById($rowsToDelete)
                ->delete();
        }

        // Charger les sous-champs
        $subFields = CustomFieldQuery::create()
            ->filterByCustomFieldParentId($repeaterId)
            ->orderByPosition()
            ->find();

        // Traiter chaque ligne
        for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++) {
            $repeaterRowId = $newRowIds[$rowIndex] ?? null;
            if (!$repeaterRowId) {
                continue;
            }

            $currentRowPath = $fieldKey . '_' . $rowIndex;

            foreach ($subFields as $subField) {
                // Si c'est un sous-repeater, le traiter récursivement
                if ($subField->getType() === 'repeater') {
                    $this->handleRepeaterField(
                        $subField,
                        $source,
                        $sourceId,
                        $locale,
                        $currentRowPath,
                        $repeaterRowId
                    );
                    continue;
                }

                $subFieldKey = $currentRowPath . '_' . $subField->getId();

                // Gérer les images
                if ($subField->getType() === CustomFieldTableMap::COL_TYPE_IMAGE) {
                    $this->handleImageUpload($subFieldKey, $subField->getId(), $source, $sourceId, $repeaterRowId);
                    continue;
                }

                // Gérer les autres types de champs
                $this->handleFieldValue($subFieldKey, $subField, $source, $sourceId, $locale, $repeaterRowId);
            }
        }
    }

    private function handleFieldValue(
        string $fieldKey,
        \CustomFields\Model\CustomField $customField,
        string $source,
        ?int $sourceId,
        string $locale,
        ?int $repeaterRowId
    ): void {
        $value = $this->getRequest()->request->get($fieldKey);

        if (null === $value) {
            return;
        }

        $customFieldValue = CustomFieldValueQuery::create()
            ->filterByCustomFieldId($customField->getId())
            ->filterBySource($source)
            ->filterBySourceId($sourceId)
            ->filterByRepeaterRowId($repeaterRowId)
            ->findOne();

        if (!$customFieldValue) {
            $customFieldValue = new \CustomFields\Model\CustomFieldValue();
            $customFieldValue->setCustomFieldId($customField->getId());
            $customFieldValue->setSource($source);
            $customFieldValue->setSourceId($sourceId);
            $customFieldValue->setRepeaterRowId($repeaterRowId);
        }

        if (
            in_array($customField->getType(), self::CUSTOM_FIELD_SIMPLE_VALUES) ||
            !$customField->isInternational()
        ) {
            $customFieldValue
                ->setSimpleValue($value)
                ->save();
        } else {
            $customFieldValue
                ->setLocale($locale)
                ->setValue($value)
                ->save();
        }
    }

    private function handleImageUpload(
        string $fieldKey,
        int $customFieldId,
        string $source,
        ?int $sourceId,
        ?int $repeaterRowId
    ): void {
        // Skip if no file field exists in the request
        if (!$this->getRequest()->files->has($fieldKey)) {
            return;
        }

        try {
            /** @var UploadedFile|null $uploadedFile */
            $uploadedFile = $this->getRequest()->files->get($fieldKey);
 
            // Skip if no valid file was uploaded (empty file input)
            if (!$uploadedFile instanceof UploadedFile || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
                return;
            }

            // Create upload directory if it doesn't exist
            $uploadDir = THELIA_LOCAL_DIR . 'media' . DS . 'images' . DS . 'customField';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Generate unique filename
            $fileName = uniqid() . '_' . $uploadedFile->getClientOriginalName();
            $uploadedFile->move($uploadDir, $fileName);

            // Find or create custom field value first
            $customFieldValue = CustomFieldValueQuery::create()
                ->filterByCustomFieldId($customFieldId)
                ->filterBySource($source)
                ->filterBySourceId($sourceId)
                ->filterByRepeaterRowId($repeaterRowId)
                ->findOneOrCreate();

            $customFieldValue->save();

            // Check if image already exists for this custom field value
            $customFieldImage = CustomFieldImageQuery::create()
                ->filterByCustomFieldValueId($customFieldValue->getId())
                ->findOne();

            if (!$customFieldImage) {
                $customFieldImage = new CustomFieldImage();
                $customFieldImage->setCustomFieldValueId($customFieldValue->getId());
            } else {
                // Delete old file if exists
                $oldFile = $uploadDir . DS . $customFieldImage->getFile();
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }

            $customFieldImage->setFile($fileName);
            $customFieldImage->save();
        } catch (\Exception $e) {
            // Skip this field if there's an error with the file
            return;
        }
    }
}
