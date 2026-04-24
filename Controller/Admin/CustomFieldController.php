<?php

declare(strict_types=1);

namespace CustomFields\Controller\Admin;

use CustomFields\Form\CustomFieldForm;
use CustomFields\Form\CustomFieldImportForm;
use CustomFields\Model\CustomField;
use CustomFields\Model\CustomFieldParent;
use CustomFields\Model\CustomFieldParentQuery;
use CustomFields\Model\CustomFieldOptionPageQuery;
use CustomFields\Model\CustomFieldQuery;
use CustomFields\Model\CustomFieldValueQuery;
use CustomFields\Service\CustomFieldSortingService;
use CustomFields\Service\ExportService;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\TheliaProcessException;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\LangQuery;
use Thelia\Tools\URL;

#[AsController]
#[Route(path: '/admin/module/customfields', name: 'customfields_')]
final class CustomFieldController extends BaseAdminController
{
    public function __construct(
        private readonly CustomFieldSortingService $sortingService
    ) {
    }

    #[Route(path: '/list', name: 'list')]
    public function listCustomFields(): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::VIEW)) {
            return $response;
        }

        // List parents/groups instead of custom fields
        $parents = CustomFieldParentQuery::create()->orderByTitle()->find();

        // Get current tab
        $currentTab = $this->getRequest()->query->get('current_tab', 'groups');


        $generalValues = [];
        $generalValueIds = [];
        $editLanguageId = (int) $this->getRequest()->query->get('edit_language_id', $this->getSession()->getLang()->getId());

        $locale = LangQuery::create()->findOneById($editLanguageId)->getLocale();

        // Get all custom fields with 'general' source (via parent)
        $generalCustomFields = CustomFieldQuery::create()
            ->useCustomFieldParentQuery()
            ->filterBySource('general')
            ->endUse()
            ->find();

        // Get existing values for general fields (source_id = 1 for general)
        foreach ($generalCustomFields as $customField) {
            $value = CustomFieldValueQuery::create()
                ->filterByCustomFieldId($customField->getId())
                ->filterBySource('general')
                ->findOne();

            if (
                $value &&
                (in_array($customField->getType(), CustomFieldValueController::CUSTOM_FIELD_SIMPLE_VALUES)
                || !$customField->getIsInternational())
            ) {
                $generalValues[$customField->getId()] = $value->getSimpleValue() ?? '';
                $generalValueIds[$customField->getId()] = $value->getId();
            } elseif ($value) {
                $value->setLocale($locale);
                $generalValues[$customField->getId()] = $value->getValue();
                $generalValueIds[$customField->getId()] = $value->getId();
            } else {
                $generalValues[$customField->getId()] = '';
                $generalValueIds[$customField->getId()] = null;
            }
        }

        // Group general fields by parent
        $groupedGeneralFields = $this->sortingService->groupByParent($generalCustomFields);

        // Get option pages
        $optionPages = CustomFieldOptionPageQuery::create()->orderByTitle()->find();

        return $this->render('custom-field-list', [
            'parents' => $parents,
            'current_tab' => $currentTab,
            'general_custom_fields' => $generalCustomFields,
            'grouped_general_fields' => $groupedGeneralFields,
            'general_values' => $generalValues,
            'general_value_ids' => $generalValueIds,
            'edit_language_id' => $editLanguageId,
            'option_pages' => $optionPages,
        ]);
    }

    #[Route(path: '/list-fields/{parentId}', name: 'list_fields')]
    public function listFieldsByParent(int $parentId): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::VIEW)) {
            return $response;
        }

        $parent = CustomFieldParentQuery::create()->findPk($parentId);

        if (!$parent) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields/list', [
                    'error' => Translator::getInstance()->trans('Group not found', [], 'customfields'),
                ])
            );
        }

        $customFields = CustomFieldQuery::create()
            ->filterByCustomFieldParentId($parentId)
            ->find();

        return $this->render('custom-fields-by-parent', [
            'parent' => $parent,
            'custom_fields' => $customFields,
        ]);
    }

    #[Route(path: '/create/{parentId}', name: 'create', methods: ['GET', 'POST'])]
    public function createCustomField(?int $parentId = null): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::CREATE)) {
            return $response;
        }

        // Get all parents for the dropdown
        $parents = CustomFieldParentQuery::create()->find();

        $parent = null;
        if ($parentId) {
            $parent = CustomFieldParentQuery::create()->findPk($parentId);
        }

        $form = $this->createForm(CustomFieldForm::getName(), FormType::class, [
            'is_international' => true,
            'custom_field_parent_id' => $parentId,
        ]);
        $this->getParserContext()->addForm($form);
        $error = null;

        try {
            $validatedForm = $this->validateForm($form);

            $this->handleSave($validatedForm);

            $redirectParentId = $validatedForm->get('custom_field_parent_id')->getData();
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields/list-fields/' . $redirectParentId, [
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
            'parent' => $parent,
        ]);
    }

    #[Route(path: '/update/{id}', name: 'update', methods: ['GET', 'POST'])]
    public function updateCustomField(int $id): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::UPDATE)) {
            return $response;
        }

        $customField = CustomFieldQuery::create()->findPk($id);

        if (!$customField) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields/list', [
                    'error' => Translator::getInstance()->trans('Custom field not found', [], 'customfields'),
                ])
            );
        }

        // Get all parents for the dropdown
        $parents = CustomFieldParentQuery::create()->find();

        $form = $this->createForm(CustomFieldForm::getName(), FormType::class, [
            'id' => $customField->getId(),
            'title' => $customField->getTitle(),
            'code' => $customField->getCode(),
            'type' => $customField->getType(),
            'is_international' => $customField->getIsInternational(),
            'custom_field_parent_id' => $customField->getCustomFieldParentId(),
        ]);
        $this->getParserContext()->addForm($form);
        $error = null;

        try {
            $validatedForm = $this->validateForm($form);
            $this->handleSave($validatedForm, $customField);

            $redirectParentId = $customField->getCustomFieldParentId();
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields/list-fields/' . $redirectParentId, [
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

    private function handleSave(Form $validatedForm, ?CustomField $customField = null)
    {
        $parentId = $validatedForm->get('custom_field_parent_id')->getData();

        if (!$customField) {
            $customField = new CustomField();
        }

        $customField
            ->setTitle($validatedForm->get('title')->getData())
            ->setCode($validatedForm->get('code')->getData())
            ->setType($validatedForm->get('type')->getData())
            ->setIsInternational($validatedForm->get('is_international')->getData())
            ->setCustomFieldParentId($parentId)
            ->save();
    }

    #[Route(path: '/delete/{id}', name: 'delete', methods: ['GET'])]
    public function deleteCustomField(int $id): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::DELETE)) {
            return $response;
        }

        $customField = CustomFieldQuery::create()->findPk($id);

        if (!$customField) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields/list', [
                    'error' => Translator::getInstance()->trans('Custom field not found', [], 'customfields'),
                ])
            );
        }

        try {
            $customField->delete();

            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields/list', [
                    'success' => Translator::getInstance()->trans('Custom field deleted successfully', [], 'customfields'),
                ])
            );
        } catch (PropelException $e) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields/list', [
                    'error' => Translator::getInstance()->trans('An error occurred while deleting the custom field', [], 'customfields'),
                ])
            );
        }
    }

    #[Route(path: '/export', name: 'export', methods: ['GET'])]
    public function export(ExportService $exportService) {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::VIEW)) {
            return $response;
        }
        $export = $exportService->getExportData();
        $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new \RuntimeException('Impossible de générer le JSON.');
        }
        $fileName = sprintf('custom-fields-%s.json', date('Y-m-d-H-i-s'));

        $response = new Response($json);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $fileName
            )
        );

        return $response;
    }
    #[Route(path: '/import', name: 'import', methods: ['POST'])]
    public function import(ExportService $exportService) {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::VIEW)) {
            return $response;
        }
        $form = $this->createForm(CustomFieldImportForm::getName());
        try {
            $validatedForm = $this->validateForm($form);

            $jsonFile = $validatedForm->get('json_file')->getData();

            if ($jsonFile) {
                $json = file_get_contents($jsonFile->getPathname());
            } else {
                $json = $validatedForm->get('json')->getData();

                if (empty($json)) {
                    throw new \Exception('Please provide JSON data either via file upload or text input');
                }
            }

            $import = json_decode($json, true);
            $exportService->importData($import);

        } catch (FormValidationException $e) {
            $form->setErrorMessage($this->createStandardFormValidationErrorMessage($e));
        } catch (\Exception $e) {
            Tlog::getInstance()->error(sprintf('Error during import: %s', $e->getMessage()));
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields/list', [
                    'error' => Translator::getInstance()->trans('Error during custom field import', [], 'customfields'),
                ])
            );
        }

        return new RedirectResponse(
            URL::getInstance()->absoluteUrl('/admin/module/customfields/list', [
                'success' => Translator::getInstance()->trans('Custom field import successfully', [], 'customfields'),
            ])
        );
    }
}
