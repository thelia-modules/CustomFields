<?php

declare(strict_types=1);

namespace CustomFields\Form;

use CustomFields\Model\CustomFieldOptionPageQuery;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

final class CustomFieldParentForm extends BaseForm
{
    private function getSourceChoices(): array
    {
        $choices = [
            Translator::getInstance()->trans('Content', [], 'customfields') => 'content',
            Translator::getInstance()->trans('Product', [], 'customfields') => 'product',
            Translator::getInstance()->trans('Category', [], 'customfields') => 'category',
            Translator::getInstance()->trans('Folder', [], 'customfields') => 'folder',
            Translator::getInstance()->trans('General', [], 'customfields') => 'general',
        ];

        // Add option pages as sources
        $optionPages = CustomFieldOptionPageQuery::create()->orderByTitle()->find();
        foreach ($optionPages as $optionPage) {
            $label = Translator::getInstance()->trans('Option Page', [], 'customfields') . ': ' . $optionPage->getTitle();
            $choices[$label] = 'option_page_' . $optionPage->getCode();
        }

        return $choices;
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
                    'label_attr' => ['for' => 'custom_field_parent_id'],
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
                    'label_attr' => ['for' => 'custom_field_parent_title'],
                    'required' => true,
                ]
            )
            ->add(
                'source',
                ChoiceType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'choices' => $this->getSourceChoices(),
                    'label' => Translator::getInstance()->trans('Source', [], 'customfields'),
                    'label_attr' => ['for' => 'custom_field_parent_source'],
                    'required' => true,
                ]
            );
    }

    public static function getName(): string
    {
        return 'customfields_form_custom_field_parent';
    }
}
