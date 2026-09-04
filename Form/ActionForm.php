<?php

declare(strict_types=1);

namespace QueryBuilder\Form;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Thelia\Form\BaseForm;

class ActionForm extends BaseForm
{
    public static function getName(): string
    {
        return 'query_builder_action';
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
            ->add('code', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => 'Action',
            ])
            ->add('limit', IntegerType::class, [
                'required' => false,
                'label' => 'Nombre de produits',
                'constraints' => [new Range(min: 1, max: 50)],
            ])
            ->add('persist_days', IntegerType::class, [
                'required' => false,
                'label' => 'Persistance des suggestions (jours)',
                'constraints' => [new Range(min: 1, max: 365)],
            ])
            ->add('discount_rate', NumberType::class, [
                'required' => false,
                'label' => 'Taux de remise (%)',
                'constraints' => [new Range(min: 0.01, max: 99.99)],
            ])
            ->add('discount_label', TextType::class, [
                'required' => false,
                'label' => 'Libellé de la remise',
            ])
            ->add('discount_cumulative', CheckboxType::class, [
                'required' => false,
                'label' => 'Cumulable avec une promotion déjà appliquée',
            ])
            ->add('cart_discount_rate', NumberType::class, [
                'required' => false,
                'label' => 'Taux de remise panier (%)',
                'constraints' => [new Range(min: 0.01, max: 99.99)],
            ])
            ->add('cart_discount_free_shipping', CheckboxType::class, [
                'required' => false,
                'label' => 'Frais de port offerts',
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
