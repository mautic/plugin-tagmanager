<?php

namespace MauticPlugin\MauticTagManagerBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\FormButtonsType;
use Mautic\LeadBundle\Form\Type\TagType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class TagMergeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'tag_to_merge',
            TagType::class,
            [
                'multiple'        => false,
                'label'           => 'mautic.tagmanager.to.merge.into',
                'required'        => true,
                'add_transformer' => false,
                'constraints'     => [
                    new NotBlank(['message' => 'mautic.tagmanager.tag.choose.notblank']),
                ],
            ]
        );

        $builder->add(
            'buttons',
            FormButtonsType::class,
            [
                'apply_text' => false,
                'save_text'  => 'mautic.lead.merge',
                'save_icon'  => 'ri-hashtag',
            ]
        );

        if (!empty($options['action'])) {
            $builder->setAction($options['action']);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined(['action']);
    }
}
