<?php

declare(strict_types=1);

namespace Admin\Forms;

use App\Command\CreateUserCommand;
use App\Entity\User;
use Closure;
use Laminas\Filter\StringToLower;
use Laminas\Filter\StringTrim;
use Laminas\Filter\StripNewlines;
use Laminas\Filter\StripTags;
use Laminas\Form\Element\Checkbox;
use Laminas\Form\Element\Hidden;
use Laminas\Form\Element\MultiCheckbox;
use Laminas\Form\Element\Password;
use Laminas\Form\Element\Select;
use Laminas\Form\Element\Submit;
use Laminas\Form\Element\Text;
use Laminas\Form\Exception\ExceptionInterface;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Validator\Callback;
use Laminas\Validator\EmailAddress;
use Laminas\Validator\StringLength;

use function assert;

/**
 * Adds or edits a user. The password is never filled in, so leaving it blank keeps the current one. Viewers are given
 * the sites they can see.
 */
final class UserForm extends Form implements InputFilterProviderInterface
{
    use AdminFormTrait;

    /** @var array{csrf: string, taken: string} */
    public static array $responseMessages = [
        'csrf'  => 'This page has expired. Please try again.',
        'taken' => 'Another user already has this email address.',
    ];

    /** @var Closure(string): bool */
    private Closure $isTaken;

    private bool $passwordRequired = true;

    public function __construct()
    {
        parent::__construct('user');
        $this->isTaken = static fn (): bool => false;
    }

    /**
     * @param Closure(string): bool $isTaken Whether another user has the email address
     */
    public function setTakenCheck(Closure $isTaken): static
    {
        $this->isTaken = $isTaken;

        return $this;
    }

    /**
     * New users need a password; existing ones keep theirs unless a new one is entered
     */
    public function setPasswordRequired(bool $required): static
    {
        $this->passwordRequired = $required;
        $this->get('password')->setAttribute('required', $required);

        return $this;
    }

    /**
     * @param array<int|string, string> $sites Site id => name
     */
    public function setSiteOptions(array $sites): static
    {
        $element = $this->get('sites');
        assert($element instanceof MultiCheckbox);
        $element->setValueOptions($sites);

        return $this;
    }

    /**
     * @throws ExceptionInterface
     */
    public function init(): void
    {
        $this->add([
            'name'       => 'name',
            'type'       => Text::class,
            'options'    => ['label' => 'Name'],
            'attributes' => ['maxlength' => 100, 'required' => true, 'class' => 'form-control', 'id' => 'name'],
        ]);

        $this->add([
            'name'       => 'email',
            'type'       => Text::class,
            'options'    => ['label' => 'Email Address'],
            'attributes' => [
                'type'         => 'email',
                'maxlength'    => 254,
                'required'     => true,
                'autocomplete' => 'off',
                'class'        => 'form-control',
                'id'           => 'email',
            ],
        ]);

        $this->add([
            'name'       => 'role',
            'type'       => Select::class,
            'options'    => ['label' => 'Role', 'value_options' => User::ROLES],
            'attributes' => ['class' => 'form-select', 'id' => 'role', 'value' => User::ROLE_VIEWER],
        ]);

        $this->add([
            'name'       => 'active',
            'type'       => Checkbox::class,
            'options'    => ['label' => 'Can log in', 'use_hidden_element' => true],
            'attributes' => ['class' => 'form-check-input', 'id' => 'active', 'value' => '1'],
        ]);

        $this->add([
            'name'       => 'password',
            'type'       => Password::class,
            'options'    => ['label' => 'Password'],
            'attributes' => [
                'minlength'    => CreateUserCommand::MIN_PASSWORD_LENGTH,
                'autocomplete' => 'new-password',
                'class'        => 'form-control',
                'id'           => 'password',
            ],
        ]);

        $this->add([
            'name'       => 'sites',
            'type'       => MultiCheckbox::class,
            'options'    => [
                'label'                     => 'Sites',
                'value_options'             => [],
                'use_hidden_element'        => true,
                'disable_inarray_validator' => false,
                'label_attributes'          => ['class' => 'form-check-label d-block'],
            ],
            'attributes' => ['class' => 'form-check-input me-2'],
        ]);

        $this->add(['name' => 'csrf', 'type' => Hidden::class]);

        $this->add([
            'name'       => 'submit',
            'type'       => Submit::class,
            'attributes' => ['value' => 'Save User', 'class' => 'btn btn-primary'],
        ]);
    }

    public function getInputFilterSpecification(): array
    {
        $singleLine = [
            ['name' => StripTags::class],
            ['name' => StringTrim::class],
            ['name' => StripNewlines::class],
        ];

        return [
            'name'   => [
                'required'   => true,
                'filters'    => $singleLine,
                'validators' => [$this->notEmpty('Please enter their name.'), $this->maxLength(100)],
            ],
            'email'  => [
                'required'   => true,
                'filters'    => [...$singleLine, ['name' => StringToLower::class]],
                'validators' => [
                    $this->notEmpty('Please enter their email address.'),
                    $this->maxLength(254),
                    [
                        'name'                   => EmailAddress::class,
                        'break_chain_on_failure' => true,
                        'options'                => [
                            'useMxCheck' => false,
                            'message'    => 'Please enter a valid email address.',
                        ],
                    ],
                    [
                        'name'    => Callback::class,
                        'options' => [
                            'callback' => fn (string $value): bool => ! ($this->isTaken)($value),
                            'messages' => [Callback::INVALID_VALUE => self::$responseMessages['taken']],
                        ],
                    ],
                ],
            ],
            'role'   => [
                'required'   => true,
                'validators' => [$this->inOptions('role', 'Please choose a role.')],
            ],
            'active' => ['required' => false],
            // Not filtered, as spaces can be part of a password
            'password' => [
                'required'   => $this->passwordRequired,
                'validators' => [
                    $this->notEmpty('Please enter a password.'),
                    [
                        'name'    => StringLength::class,
                        'options' => [
                            'min'      => CreateUserCommand::MIN_PASSWORD_LENGTH,
                            'max'      => 4096,
                            'messages' => [
                                StringLength::TOO_SHORT => 'The password must be at least %min% characters.',
                            ],
                        ],
                    ],
                ],
            ],
            'sites'    => ['required' => false],
            'csrf'     => $this->csrfSpecification(self::$responseMessages['csrf']),
        ];
    }
}
