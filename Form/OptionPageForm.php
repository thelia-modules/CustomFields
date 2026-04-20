<?php

declare(strict_types=1);

namespace CustomFields\Form;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

final class OptionPageForm extends BaseForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add(
                'title',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                        new Length(['max' => 255]),
                    ],
                    'label' => Translator::getInstance()->trans('Title', [], 'customfields'),
                    'label_attr' => ['for' => 'option_page_title'],
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
                        new Regex([
                            'pattern' => '/^[a-z0-9_]+$/',
                            'message' => Translator::getInstance()->trans('The code must contain only lowercase letters, numbers and underscores', [], 'customfields'),
                        ]),
                    ],
                    'label' => Translator::getInstance()->trans('Code', [], 'customfields'),
                    'label_attr' => ['for' => 'option_page_code'],
                    'required' => true,
                ]
            );
    }

    public static function getName(): string
    {
        return 'customfields_form_option_page';
    }
}
