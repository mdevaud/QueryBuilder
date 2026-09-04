<?php

declare(strict_types=1);

namespace QueryBuilder\Form;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\BaseForm;

class RuleForm extends BaseForm
{
    public static function getName(): string
    {
        return 'query_builder_rule';
    }

    protected function buildForm(): void
    {
        $this->formBuilder
            ->add('name', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => 'Nom',
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Description',
            ])
            ->add('context', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => 'Contexte',
            ])
            ->add('condition_tree', HiddenType::class, [
                'required' => false,
            ])
            ->add('activate', CheckboxType::class, [
                'required' => false,
                'label' => 'Active',
            ]);
    }
}
