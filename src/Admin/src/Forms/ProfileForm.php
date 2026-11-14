<?php

declare(strict_types=1);

namespace Admin\Forms;

use App\Command\CreateUserCommand;
use Closure;
use Laminas\Filter\StringTrim;
use Laminas\Filter\StripNewlines;
use Laminas\Filter\StripTags;
use Laminas\Form\Element\Hidden;
use Laminas\Form\Element\Password;
use Laminas\Form\Element\Submit;
use Laminas\Form\Element\Text;
use Laminas\Form\Exception\ExceptionInterface;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Validator\Callback;
use Laminas\Validator\Identical;
use Laminas\Validator\StringLength;

/**
 * The logged-in user's own name and password. Changing the password needs the current one, so someone at a computer
 * left logged in cannot take the account over.
 */
final class ProfileForm extends Form implements InputFilterProviderInterface
{
    use AdminFormTrait;

    /** @var array{csrf: string, wrong-password: string} */
    public static array $responseMessages = [
        'csrf'           => 'This page has expired. Please try again.',
        'wrong-password' => 'That is not your current password.',
    ];

    /** @var Closure(string): bool */
    private Closure $checkPassword;

    public function __construct()
    {
        parent::__construct('profile');
        $this->checkPassword = static fn (): bool => false;
    }

    /**
     * @param Closure(string): bool $checkPassword Whether the user's current password is this
     */
    public function setPasswordCheck(Closure $checkPassword): static
    {
        $this->checkPassword = $checkPassword;

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

        foreach (
            [
                'currentPassword' => ['Current Password', 'current-password'],
                'newPassword'     => ['New Password', 'new-password'],
                'confirmPassword' => ['New Password Again', 'new-password'],
            ] as $name => [$label, $autocomplete]
        ) {
            $this->add([
                'name'       => $name,
                'type'       => Password::class,
                'options'    => ['label' => $label],
                'attributes' => ['autocomplete' => $autocomplete, 'class' => 'form-control', 'id' => $name],
            ]);
        }

        $this->add(['name' => 'csrf', 'type' => Hidden::class]);

        $this->add([
            'name'       => 'submit',
            'type'       => Submit::class,
            'attributes' => ['value' => 'Save', 'class' => 'btn btn-primary'],
        ]);
    }

    public function getInputFilterSpecification(): array
    {
        $changing = ($this->data['newPassword'] ?? '') !== '';

        return [
            'name'            => [
                'required'   => true,
                'filters'    => [
                    ['name' => StripTags::class],
                    ['name' => StringTrim::class],
                    ['name' => StripNewlines::class],
                ],
                'validators' => [$this->notEmpty('Please enter your name.'), $this->maxLength(100)],
            ],
            'currentPassword' => [
                'required'   => $changing,
                'validators' => [
                    $this->notEmpty('Please enter your current password.'),
                    [
                        'name'    => Callback::class,
                        'options' => [
                            'callback' => fn (string $value): bool => ($this->checkPassword)($value),
                            'messages' => [Callback::INVALID_VALUE => self::$responseMessages['wrong-password']],
                        ],
                    ],
                ],
            ],
            'newPassword'     => [
                'required'   => false,
                'validators' => [
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
            'confirmPassword' => [
                'required'   => $changing,
                'validators' => [
                    $this->notEmpty('Please enter the new password again.'),
                    [
                        'name'    => Identical::class,
                        'options' => [
                            'token'    => 'newPassword',
                            'messages' => [Identical::NOT_SAME => 'The new passwords do not match.'],
                        ],
                    ],
                ],
            ],
            'csrf'            => $this->csrfSpecification(self::$responseMessages['csrf']),
        ];
    }
}
