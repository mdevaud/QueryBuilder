<?php

declare(strict_types=1);

namespace QueryBuilder\Form;

use OpenStudio\QueryBuilderBundle\Enum\QueryBuilderProcessor;
use OpenStudio\QueryBuilderBundle\Form\QueryBuilderType;
use QueryBuilder\Service\FieldsBuilder;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\BaseForm;

class RuleForm extends BaseForm
{
    public function __construct(
        private readonly FieldsBuilder $fieldsBuilder,
    ) {
    }

    public static function getName(): string
    {
        return 'query_builder_rule';
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
            ->add('context', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => 'Context',
            ])
            ->add('condition_tree', QueryBuilderType::class, [
                'required' => false,
                'label' => false,
                'processor' => QueryBuilderProcessor::Native,
                //Every dictionary field: the editor may switch context before submitting, the
                //context restriction is enforced by SqlBuilder::validateTree() on save
                'fields' => $this->fieldsBuilder->buildForContext(null, $locale),
                'lang' => $locale,
            ])
            ->add('activate', CheckboxType::class, [
                'required' => false,
                'label' => 'Active',
            ]);
    }
}
