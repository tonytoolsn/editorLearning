<?php

namespace App\Form;

use App\Entity\Upload;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('uuid')
            ->add('storedFilename')
            ->add('originalFilename')
            ->add('mimeType')
            ->add('createdAt', null, [
                'widget' => 'single_text',
            ])
            ->add('deletedAt', null, [
                'widget' => 'single_text',
            ])
            ->add('fieldName')
            ->add('entityId')
            ->add('createdByUserId')
            ->add('createdByUnitId')
            ->add('entityType')
            ->add('entityUuid')
            ->add('status')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Upload::class,
        ]);
    }
}
