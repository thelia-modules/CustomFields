<?php

declare(strict_types=1);

namespace CustomFields\Controller\Admin;

use CustomFields\Form\CustomFieldValueForm;
use CustomFields\Model\CustomFieldQuery;
use CustomFields\Model\CustomFieldValueQuery;
use CustomFields\Model\Map\CustomFieldTableMap;
use Propel\Runtime\Exception\PropelException;
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
        CustomFieldTableMap::COL_TYPE_PRODUCT
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
            $sourceId = $source === 'general' ? null : (int) $validatedForm->get('source_id')->getData();
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
                $value = $this->getRequest()->request->get($fieldKey);

                if (null !== $value) {
                    // Find or create custom field value
                    $customFieldValue = CustomFieldValueQuery::create()
                        ->filterByCustomFieldId($customField->getId())
                        ->filterBySource($source)
                        ->filterBySourceId($sourceId)
                        ->findOneOrCreate();

                    if (in_array($customField->getType(), self::CUSTOM_FIELD_SIMPLE_VALUES)) {
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

            if ('close' === $saveMode) {
                $redirectUrl = match ($source) {
                    'product' => '/admin/products',
                    'content' => '/admin/content',
                    'category' => '/admin/categories',
                    'folder' => '/admin/folders',
                    'general' => '/admin/module/customfields/list',
                    default => '/admin/module/customfields/list',
                };
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
}
