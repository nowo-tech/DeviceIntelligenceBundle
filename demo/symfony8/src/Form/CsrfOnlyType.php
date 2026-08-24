<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * CSRF-only POST (checkout, trust, coupon, …). Empty block prefix keeps {@code _token} flat.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @extends AbstractType<null>
 */
final class CsrfOnlyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('submit', SubmitType::class, [
            'label' => $options['submit_label'],
            'attr' => $options['submit_attr'],
            'row_attr' => $options['submit_row_attr'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'demo',
            'csrf_field_name' => '_token',
            'submit_label' => 'Submit',
            'submit_attr' => ['class' => 'btn btn-primary'],
            'submit_row_attr' => [],
        ]);
        $resolver->setAllowedTypes('csrf_token_id', 'string');
        $resolver->setAllowedTypes('submit_label', 'string');
        $resolver->setAllowedTypes('submit_attr', 'array');
        $resolver->setAllowedTypes('submit_row_attr', 'array');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
