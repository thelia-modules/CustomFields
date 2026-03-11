<?php

declare(strict_types=1);

namespace CustomFields\Form;

use CustomFields\Model\CustomFieldParentQuery;
use CustomFields\Model\Map\CustomFieldTableMap;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

final class CustomFieldForm extends BaseForm
{
    private function getParentChoices()
    {
        $parents = CustomFieldParentQuery::create()->find();
        $parentChoices = [];
        foreach ($parents as $parent) {
            $parentChoices[$parent->getTitle()] = $parent->getId();
        }
        return $parentChoices;
    }
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add(
                'id',
                TextType::class,
                [
                    'required' => false,
                    'label' => 'ID',
                    'label_attr' => ['for' => 'custom_field_id'],
                ]
            )
            ->add(
                'title',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                        new Length(['max' => 255]),
                    ],
                    'label' => Translator::getInstance()->trans('Title', [], 'customfields'),
                    'label_attr' => ['for' => 'custom_field_title'],
                    'required' => true,
                ]
            )
            ->add(
                'code',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                        new Length(['max' => 100]),
                    ],
                    'label' => Translator::getInstance()->trans('Code', [], 'customfields'),
                    'label_attr' => ['for' => 'custom_field_code'],
                    'required' => true,
                ]
            )
            ->add(
                'type',
                ChoiceType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'choices' => [
                        Translator::getInstance()->trans('Text', [], 'customfields') => CustomFieldTableMap::COL_TYPE_TEXT,
                        Translator::getInstance()->trans('Textarea', [], 'customfields') => CustomFieldTableMap::COL_TYPE_TEXTAREA,
                        Translator::getInstance()->trans('Wysiwyg', [], 'customfields') => CustomFieldTableMap::COL_TYPE_WYSIWYG,
                        Translator::getInstance()->trans('Content', [], 'customfields') => CustomFieldTableMap::COL_TYPE_CONTENT,
                        Translator::getInstance()->trans('Category', [], 'customfields') => CustomFieldTableMap::COL_TYPE_CATEGORY,
                        Translator::getInstance()->trans('Folder', [], 'customfields') => CustomFieldTableMap::COL_TYPE_FOLDER,
                        Translator::getInstance()->trans('Product', [], 'customfields') => CustomFieldTableMap::COL_TYPE_PRODUCT,
                    ],
                    'label' => Translator::getInstance()->trans('Type', [], 'customfields'),
                    'label_attr' => ['for' => 'custom_field_type'],
                    'required' => true,
                ]
            )
            ->add(
                'sources',
                ChoiceType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'choices' => [
                        Translator::getInstance()->trans('Content', [], 'customfields') => 'content',
                        Translator::getInstance()->trans('Product', [], 'customfields') => 'product',
                        Translator::getInstance()->trans('Category', [], 'customfields') => 'category',
                        Translator::getInstance()->trans('Folder', [], 'customfields') => 'folder',
                        Translator::getInstance()->trans('General', [], 'customfields') => 'general',
                    ],
                    'label' => Translator::getInstance()->trans('Sources', [], 'customfields'),
                    'label_attr' => ['for' => 'custom_field_sources'],
                    'required' => true,
                    'multiple' => true,
                    'expanded' => false,
                ]
            )
            ->add(
                'custom_field_parent_id',
                ChoiceType::class,
                [
                    'choices' => $this->getParentChoices(),
                    'label' => Translator::getInstance()->trans('Parent Group', [], 'customfields'),
                    'label_attr' => ['for' => 'custom_field_parent_id'],
                    'required' => false,
                    'placeholder' => Translator::getInstance()->trans('No parent', [], 'customfields'),
                ]
            )
            ->add(
                'new_parent_title',
                TextType::class,
                [
                    'label' => Translator::getInstance()->trans('Or create new parent', [], 'customfields'),
                    'label_attr' => ['for' => 'new_parent_title'],
                    'required' => false,
                ]
            );
    }

    public static function getName(): string
    {
        return 'customfields_form_custom_field';
    }
}
