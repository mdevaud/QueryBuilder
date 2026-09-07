<?php

declare(strict_types=1);

namespace QueryBuilder\Form;

use OpenStudio\QueryBuilderBundle\Enum\QueryBuilderProcessor;
use OpenStudio\QueryBuilderBundle\Form\QueryBuilderType;
use QueryBuilder\Service\FieldsBuilder;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Thelia\Form\BaseForm;

class ActionForm extends BaseForm
{
    public function __construct(
        private readonly FieldsBuilder $fieldsBuilder,
    ) {
    }

    public static function getName(): string
    {
        return 'query_builder_action';
    }

    protected function buildForm(): void
    {
        $locale = $this->getRequest()->getLocale();

        $this->formBuilder
            ->add('name', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => 'Name',
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
                'label' => 'Number of products',
                'constraints' => [new Range(min: 1, max: 50)],
            ])
            ->add('persist_days', IntegerType::class, [
                'required' => false,
                'label' => 'Suggestions persistence (days)',
                'constraints' => [new Range(min: 1, max: 365)],
            ])
            ->add('discount_rate', NumberType::class, [
                'required' => false,
                'label' => 'Discount rate (%)',
                'constraints' => [new Range(min: 0.01, max: 99.99)],
            ])
            ->add('discount_label', TextType::class, [
                'required' => false,
                'label' => 'Discount label',
            ])
            ->add('discount_cumulative', CheckboxType::class, [
                'required' => false,
                'label' => 'Stackable with an existing promotion',
            ])
            ->add('cart_discount_rate', NumberType::class, [
                'required' => false,
                'label' => 'Cart discount rate (%)',
                'constraints' => [new Range(min: 0.01, max: 99.99)],
            ])
            ->add('cart_discount_free_shipping', CheckboxType::class, [
                'required' => false,
                'label' => 'Free shipping',
            ])
            ->add('condition_tree', QueryBuilderType::class, [
                'required' => false,
                'label' => false,
                'processor' => QueryBuilderProcessor::Native,
                //Every dictionary field, the rule context is enforced by SqlBuilder::validateTree() on save
                'fields' => $this->fieldsBuilder->buildForContext(null, $locale),
                'lang' => $locale,
            ])
            ->add('activate', CheckboxType::class, [
                'required' => false,
                'label' => 'Active',
            ]);
    }
}
