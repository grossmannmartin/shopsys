<?php

namespace Shopsys\FrameworkBundle\Form\Admin\TransportAndPayment;

use Shopsys\FrameworkBundle\Component\Domain\Domain;
use Shopsys\FrameworkBundle\Form\Constraints\NotNegativeMoneyAmount;
use Shopsys\FrameworkBundle\Form\ValidationGroup;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class FreeTransportAndPaymentPriceLimitsFormType extends AbstractType
{
    public const DOMAINS_SUBFORM_NAME = 'priceLimits';
    public const FIELD_ENABLED = 'enabled';
    public const FIELD_PRICE_LIMIT = 'priceLimit';
    public const VALIDATION_GROUP_PRICE_LIMIT_ENABLED = 'priceLimitEnabled';

    /**
     * @var \Shopsys\FrameworkBundle\Component\Domain\Domain
     */
    private $domain;

    /**
     * @param \Shopsys\FrameworkBundle\Component\Domain\Domain $domain
     */
    public function __construct(Domain $domain)
    {
        $this->domain = $domain;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add($this->getPriceLimitsBuilder($builder))
            ->add('save', SubmitType::class);
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @return \Symfony\Component\Form\FormBuilderInterface
     */
    private function getPriceLimitsBuilder(FormBuilderInterface $builder)
    {
        $formBuilderForDomains = $builder->create(self::DOMAINS_SUBFORM_NAME, null, ['compound' => true]);

        foreach ($this->domain->getAll() as $domainConfig) {
            $formBuilderForDomain = $builder->create((string)$domainConfig->getId(), null, [
                'compound' => true,
                'validation_groups' => function (FormInterface $form) {
                    $validationGroups = [ValidationGroup::VALIDATION_GROUP_DEFAULT];
                    $formData = $form->getData();

                    if ($formData[self::FIELD_ENABLED]) {
                        $validationGroups[] = static::VALIDATION_GROUP_PRICE_LIMIT_ENABLED;
                    }

                    return $validationGroups;
                },
            ])
                ->add(self::FIELD_ENABLED, CheckboxType::class, [
                    'required' => false,
                ])
                ->add(self::FIELD_PRICE_LIMIT, MoneyType::class, [
                    'required' => true,
                    'constraints' => [
                        new NotNegativeMoneyAmount([
                            'message' => 'Price must be greater or equal to zero',
                            'groups' => [static::VALIDATION_GROUP_PRICE_LIMIT_ENABLED],
                        ]),
                        new Constraints\NotBlank([
                            'message' => 'Please enter price',
                            'groups' => [static::VALIDATION_GROUP_PRICE_LIMIT_ENABLED],
                        ]),
                    ],
                ]);

            $formBuilderForDomains->add($formBuilderForDomain);
        }

        return $formBuilderForDomains;
    }

    /**
     * @param \Symfony\Component\OptionsResolver\OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'attr' => ['novalidate' => 'novalidate'],
        ]);
    }
}
