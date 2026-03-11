<?php

declare(strict_types=1);

namespace CustomFields\Controller\Admin;

use CustomFields\CustomFields;
use CustomFields\Form\CustomFieldForm;
use CustomFields\Model\CustomField;
use CustomFields\Model\CustomFieldParent;
use CustomFields\Model\CustomFieldParentQuery;
use CustomFields\Model\CustomFieldQuery;
use CustomFields\Model\CustomFieldSource;
use CustomFields\Model\CustomFieldSourceQuery;
use CustomFields\Model\CustomFieldValueQuery;
use CustomFields\Service\CustomFieldSortingService;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\LangQuery;
use Thelia\Tools\URL;

final class CustomFieldController extends BaseAdminController
{
    public function __construct(
        private readonly CustomFieldSortingService $sortingService
    ) {
    }

    #[Route(path: '/admin/module/customfields', name: 'customfields-list')]
    public function listCustomFields(): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::VIEW)) {
            return $response;
        }

        $customFields = CustomFieldQuery::create()->find();

        // Get current tab
        $currentTab = $this->getRequest()->query->get('current_tab', 'fields');


        $generalValues = [];
        $editLanguageId = (int) $this->getRequest()->query->get('edit_language_id', $this->getSession()->getLang()->getId());

        $locale = LangQuery::create()->findOneById($editLanguageId)->getLocale();

        // Get all custom fields with 'general' source
        $generalCustomFields = CustomFieldQuery::create()
            ->useCustomFieldSourceQuery()
            ->filterBySource('general')
            ->endUse()
            ->find();

        // Get existing values for general fields (source_id = 1 for general)
        foreach ($generalCustomFields as $customField) {
            $value = CustomFieldValueQuery::create()
                ->filterByCustomFieldId($customField->getId())
                ->filterBySource('general')
                ->findOne();

            if ($value && in_array($customField->getType(), CustomFields::CUSTOM_FIELD_SIMPLE_VALUES)) {
                $generalValues[$customField->getId()] = $value->getSimpleValue() ?? '';
            } elseif ($value) {
                $value->setLocale($locale);
                $generalValues[$customField->getId()] = $value->getValue();
            } else {
                $generalValues[$customField->getId()] = '';
            }
        }

        // Group general fields by parent
        $groupedGeneralFields = $this->sortingService->groupByParent($generalCustomFields);

        return $this->render('custom-field-list', [
            'custom_fields' => $customFields,
            'current_tab' => $currentTab,
            'general_custom_fields' => $generalCustomFields,
            'grouped_general_fields' => $groupedGeneralFields,
            'general_values' => $generalValues,
            'edit_language_id' => $editLanguageId,
        ]);
    }

    #[Route(path: '/admin/module/customfields/create', name: 'customfields-create', methods: ['GET', 'POST'])]
    public function createCustomField(): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::CREATE)) {
            return $response;
        }

        // Get all parents for the dropdown
        $parents = CustomFieldParentQuery::create()->find();

        $form = $this->createForm(CustomFieldForm::getName());
        $this->getParserContext()->addForm($form);
        $error = null;

        try {
            $validatedForm = $this->validateForm($form);

            // Handle new parent creation
            $parentId = $validatedForm->get('custom_field_parent_id')->getData();
            $newParentTitle = $validatedForm->get('new_parent_title')->getData();

            if (!empty($newParentTitle)) {
                $newParent = new CustomFieldParent();
                $newParent
                    ->setTitle($newParentTitle)
                    ->save();
                $parentId = $newParent->getId();
            }

            $customField = new CustomField();
            $customField
                ->setTitle($validatedForm->get('title')->getData())
                ->setCode($validatedForm->get('code')->getData())
                ->setType($validatedForm->get('type')->getData())
                ->setCustomFieldParentId($parentId ?: null)
                ->save();

            // Save sources
            $sources = $validatedForm->get('sources')->getData();
            if (is_array($sources)) {
                foreach ($sources as $source) {
                    $customFieldSource = new CustomFieldSource();
                    $customFieldSource
                        ->setCustomFieldId($customField->getId())
                        ->setSource($source)
                        ->save();
                }
            }

            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields', [
                    'success' => Translator::getInstance()->trans('Custom field created successfully', [], 'customfields'),
                ])
            );
        } catch (FormValidationException $e) {
            $error = $e->getMessage();
        } catch (PropelException $e) {
            $error = Translator::getInstance()->trans('An error occurred while saving the custom field', [], 'customfields');
        } catch (\Exception $e) {
            // Form not submitted, display empty form
        }

        return $this->render('custom-field-form', [
            'error' => $error,
            'custom_field' => null,
            'parents' => $parents,
        ]);
    }

    #[Route(path: '/admin/module/customfields/update/{id}', name: 'customfields-update', methods: ['GET', 'POST'])]
    public function updateCustomField(int $id): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::UPDATE)) {
            return $response;
        }

        $customField = CustomFieldQuery::create()->findPk($id);

        if (!$customField) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields', [
                    'error' => Translator::getInstance()->trans('Custom field not found', [], 'customfields'),
                ])
            );
        }

        // Get existing sources
        $existingSources = CustomFieldSourceQuery::create()
            ->filterByCustomFieldId($id)
            ->find();
        $sourcesArray = [];
        foreach ($existingSources as $existingSource) {
            $sourcesArray[] = $existingSource->getSource();
        }

        // Get all parents for the dropdown
        $parents = CustomFieldParentQuery::create()->find();

        $form = $this->createForm(CustomFieldForm::getName(), FormType::class, [
            'id' => $customField->getId(),
            'title' => $customField->getTitle(),
            'code' => $customField->getCode(),
            'type' => $customField->getType(),
            'sources' => $sourcesArray,
            'custom_field_parent_id' => $customField->getCustomFieldParentId(),
        ]);
        $this->getParserContext()->addForm($form);
        $error = null;

        try {
            $validatedForm = $this->validateForm($form);

            // Handle new parent creation
            $parentId = $validatedForm->get('custom_field_parent_id')->getData();
            $newParentTitle = $validatedForm->get('new_parent_title')->getData();

            if (!empty($newParentTitle)) {
                $newParent = new CustomFieldParent();
                $newParent
                    ->setTitle($newParentTitle)
                    ->save();
                $parentId = $newParent->getId();
            }

            $customField
                ->setTitle($validatedForm->get('title')->getData())
                ->setCode($validatedForm->get('code')->getData())
                ->setType($validatedForm->get('type')->getData())
                ->setCustomFieldParentId($parentId ?: null)
                ->save();

            // Delete existing sources and save new ones
            CustomFieldSourceQuery::create()
                ->filterByCustomFieldId($customField->getId())
                ->delete();

            $sources = $validatedForm->get('sources')->getData();
            if (is_array($sources)) {
                foreach ($sources as $source) {
                    $customFieldSource = new CustomFieldSource();
                    $customFieldSource
                        ->setCustomFieldId($customField->getId())
                        ->setSource($source)
                        ->save();
                }
            }

            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields', [
                    'success' => Translator::getInstance()->trans('Custom field updated successfully', [], 'customfields'),
                ])
            );
        } catch (FormValidationException $e) {
            $error = $e->getMessage();
        } catch (PropelException $e) {
            $error = Translator::getInstance()->trans('An error occurred while updating the custom field', [], 'customfields');
        } catch (\Exception $e) {
            // Form not submitted, display form with current values
        }

        return $this->render('custom-field-form', [
            'error' => $error,
            'custom_field' => $customField,
            'parents' => $parents,
        ]);
    }

    #[Route(path: '/admin/module/customfields/delete/{id}', name: 'customfields-delete', methods: ['GET'])]
    public function deleteCustomField(int $id): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::DELETE)) {
            return $response;
        }

        $customField = CustomFieldQuery::create()->findPk($id);

        if (!$customField) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields', [
                    'error' => Translator::getInstance()->trans('Custom field not found', [], 'customfields'),
                ])
            );
        }

        try {
            $customField->delete();

            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields', [
                    'success' => Translator::getInstance()->trans('Custom field deleted successfully', [], 'customfields'),
                ])
            );
        } catch (PropelException $e) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields', [
                    'error' => Translator::getInstance()->trans('An error occurred while deleting the custom field', [], 'customfields'),
                ])
            );
        }
    }
}
