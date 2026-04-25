<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ImageEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('image', FileType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'data-controller' => 'symfony--ux-cropperjs--cropper',
                    'data-symfony--ux-cropperjs-cropper-public-url-value' => $options['public_url'] ?? '',
                    'data-symfony--ux-cropperjs-cropper-options-value' => json_encode([
                        'aspectRatio' => null,
                        'viewMode' => 1,
                        'dragMode' => 'move',
                        'autoCropArea' => 0.8,
                        'restore' => false,
                        'guides' => true,
                        'center' => true,
                        'highlight' => true,
                        'cropBoxMovable' => true,
                        'cropBoxResizable' => true,
                        'toggleDragModeOnDblclick' => false,
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'public_url' => '',
        ]);
    }
}
