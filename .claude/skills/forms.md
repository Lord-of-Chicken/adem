# Forms Skill

## Core rules

- Forms bind to a **DTO / plain input object**, never directly to a Doctrine entity that carries invariants.
- Form submission → thin controller validates → delegates work to a **Service**.
- `$form->getData()` returns the input object; hand it to the relevant Service.

## Form Type pattern

```php
final class ContactType extends AbstractType {
    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Votre nom',
                'attr'  => ['placeholder' => 'Prénom Nom'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'attr'  => ['rows' => 5, 'maxlength' => 2000],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void {
        $resolver->setDefaults([
            'data_class'      => ContactInput::class,
            'csrf_protection' => true,
            'csrf_token_id'   => 'contact',
        ]);
    }
}
```

## Input DTO (validated)

```php
final class ContactInput {
    #[NotBlank]
    #[Length(min: 2, max: 100)]
    public string $name = '';

    #[NotBlank]
    #[Email]
    public string $email = '';

    #[NotBlank]
    #[Length(min: 10, max: 2000)]
    public string $message = '';
}
```

## Controller (thin)

```php
#[Route('/{_locale}/contact', name: 'contact', requirements: ['_locale' => 'fr|en|nl'], methods: ['GET', 'POST'])]
public function contact(Request $request, ContactMailer $mailer): Response {
    $input = new ContactInput();
    $form  = $this->createForm(ContactType::class, $input);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $mailer->send($input); // logic lives in the Service, not here
        $this->addFlash('success', 'Votre message a bien été envoyé.');
        return $this->redirectToRoute('contact');
    }

    return $this->render('contact/index.html.twig', ['form' => $form]);
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

## Data Transformer (value normalisation)

```php
// Normalise an amount entered in euros to integer cents (Order.totalCents)
final class EurosToCentsTransformer implements DataTransformerInterface {
    public function transform(mixed $value): string {
        return $value === null ? '' : number_format($value / 100, 2, '.', '');
    }

    public function reverseTransform(mixed $value): ?int {
        if (empty($value)) return null;
        return (int) round(((float) $value) * 100);
    }
}
```

## Conditional fields (Form Events)

```php
$builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
    $data = $event->getData();
    if ($data?->tierGroup === 'vip') {
        // VIP tiers let the contributor display a custom name
        $event->getForm()->add('displayName', TextType::class, ['label' => 'Nom à afficher']);
    }
});
```

## Rules

- `data_class` points to a DTO/input object, never an entity carrying invariants
- CSRF always enabled on state-mutating forms
- Validation constraints on the DTO, not inside the form type
- `mapped: false` for file inputs — handle uploads separately in the controller
- One shared form theme for consistent TailwindCSS styling across the app
