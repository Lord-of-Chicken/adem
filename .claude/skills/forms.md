# Forms Skill

## Core rules

- Forms bind to **DTOs**, never to Doctrine entities
- Form submission → Command dispatched → Application handler does the work
- `$form->getData()` returns the DTO, dispatch it as a Command

## Form Type pattern

```php
final class ReportLostAnimalType extends AbstractType {
    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $builder
            ->add('animalName', TextType::class, [
                'label' => 'Nom de l\'animal',
                'attr'  => ['placeholder' => 'Rex, Mimi...'],
            ])
            ->add('species', EnumType::class, [
                'class'        => Species::class,
                'label'        => 'Espèce',
                'choice_label' => fn(Species $s) => $s->label(),
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description (couleur, signes distinctifs...)',
                'required' => false,
                'attr'     => ['rows' => 4, 'maxlength' => 1000],
            ])
            ->add('city', TextType::class, ['label' => 'Ville de disparition'])
            ->add('photos', FileType::class, [
                'label'    => 'Photos',
                'multiple' => true,
                'required' => false,
                'mapped'   => false, // handled separately in controller
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void {
        $resolver->setDefaults([
            'data_class'      => ReportLostAnimalInput::class,
            'csrf_protection' => true,
            'csrf_token_id'   => 'report_lost_animal',
        ]);
    }
}
```

## Input DTO (validated)

```php
final class ReportLostAnimalInput {
    #[NotBlank]
    #[Length(min: 2, max: 100)]
    public string $animalName = '';

    #[NotNull]
    public ?Species $species = null;

    #[Length(max: 1000)]
    public ?string $description = null;

    #[NotBlank]
    #[Length(min: 2, max: 100)]
    public string $city = '';
}
```

## Controller (thin)

```php
#[Route('/reports/lost/new', name: 'report_lost_create', methods: ['GET', 'POST'])]
#[IsGranted('ROLE_USER')]
public function create(Request $request): Response {
    $input = new ReportLostAnimalInput();
    $form  = $this->createForm(ReportLostAnimalType::class, $input);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $photos = $form->get('photos')->getData();
        $this->commandBus->dispatch(new ReportLostAnimal($input, $photos));
        $this->addFlash('success', 'Votre signalement a été publié.');
        return $this->redirectToRoute('reports_index');
    }

    return $this->render('animal_report/create.html.twig', ['form' => $form]);
}
```

## Form theming (TailwindCSS)

```yaml
# config/packages/twig.yaml
twig:
    form_themes: ['forms/tailwind_theme.html.twig']
```

```twig
{# templates/forms/tailwind_theme.html.twig #}
{% block form_row %}
    <div class="mb-4">
        {{ form_label(form, null, {label_attr: {class: 'block text-sm font-medium text-gray-700 mb-1'}}) }}
        {{ form_widget(form, {attr: {class: 'w-full rounded-md border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500'}}) }}
        {{ form_errors(form) }}
    </div>
{% endblock %}

{% block form_errors %}
    {% if errors|length > 0 %}
        {% for error in errors %}
            <p class="mt-1 text-sm text-red-600">{{ error.message }}</p>
        {% endfor %}
    {% endif %}
{% endblock %}
```

## Data Transformer (for Value Objects)

```php
final class LocationTransformer implements DataTransformerInterface {
    public function transform(mixed $value): string {
        return $value instanceof Location ? $value->city : '';
    }

    public function reverseTransform(mixed $value): ?Location {
        if (empty($value)) return null;
        return Location::fromCity($value);
    }
}
```

## Conditional fields (Form Events)

```php
$builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
    $data = $event->getData();
    if ($data?->type === ReportType::LOST) {
        $event->getForm()->add('lastSeenAt', DateType::class, [...]);
    }
});
```

## Rules

- `data_class` always points to a DTO, never an entity
- CSRF always enabled on state-mutating forms
- Validation constraints on the DTO, not inside the form type
- `mapped: false` for file inputs — handle uploads separately in the controller
- One shared form theme for consistent TailwindCSS styling across the app
