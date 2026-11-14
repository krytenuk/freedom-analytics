<?php

declare(strict_types=1);

namespace Admin\Forms;

use Laminas\Filter\StringTrim;
use Laminas\Form\Element\Hidden;
use Laminas\Form\Element\Password;
use Laminas\Form\Element\Submit;
use Laminas\Form\Element\Text;
use Laminas\Form\Exception\ExceptionInterface;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Validator\Callback;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\StringLength;
use Mezzio\Csrf\CsrfGuardInterface;

final class LoginForm extends Form implements InputFilterProviderInterface
{
    public static array $responseMessages = [
        // The same message for every failure, so the page does not reveal which accounts exist
        'invalid'           => 'The email address or password is incorrect.',
        'throttled'         => 'Too many failed login attempts. Please try again in %d minute(s).',
        'throttled-no-time' => 'Too many failed login attempts. Please try again later.',
        'csrf'              => 'This page has expired. Please try logging in again.',
        'login-required'    => 'You are not allowed to access this resource. Please log in.',
    ];

    protected string $csrfKey = 'login';

    protected CsrfGuardInterface $csrfGuard;

    public function __construct()
    {
        parent::__construct('login');
    }

    public function setCsrfGuard(CsrfGuardInterface $csrfGuard): static
    {
        $this->csrfGuard = $csrfGuard;

        return $this;
    }

    /**
     * Tokens work once, so call this each time the form is shown.
     */
    public function generateCsrfToken(): static
    {
        $this->get('csrf')->setValue($this->csrfGuard->generateToken($this->csrfKey));

        return $this;
    }

    /**
     * @throws ExceptionInterface
     */
    public function init(): void
    {
        // Not an Email element, as its validator shows its regular expression to the user. A malformed address simply
        // fails to log in, with the same message as any other failure.
        $this->add([
            'name'       => 'email',
            'type'       => Text::class,
            'options'    => [
                'label' => 'Email Address',
            ],
            'attributes' => [
                'maxlength'    => 254,
                'placeholder'  => 'Email Address',
                'required'     => true,
                'autocomplete' => 'username',
                'class'        => 'form-control',
                'id'           => 'email',
            ],
        ]);

        $this->add([
            'name'       => 'password',
            'type'       => Password::class,
            'options'    => [
                'label' => 'Password',
            ],
            'attributes' => [
                'placeholder'  => 'Password',
                'required'     => true,
                'autocomplete' => 'current-password',
                'class'        => 'form-control',
                'id'           => 'password',
            ],
        ]);

        $this->add([
            'name' => 'csrf',
            'type' => Hidden::class,
        ]);

        $this->add([
            'name'       => 'login',
            'type'       => Submit::class,
            'attributes' => [
                'value' => 'Log In',
                'class' => 'btn btn-primary',
            ],
        ]);
    }

    public function getInputFilterSpecification(): array
    {
        return [
            'email' => [
                'required'   => true,
                'filters'    => [
                    ['name' => StringTrim::class],
                ],
                'validators' => [
                    [
                        'name'                   => NotEmpty::class,
                        'break_chain_on_failure' => true,
                        'options'                => [
                            'messages' => [
                                NotEmpty::IS_EMPTY => 'Please enter your email address.',
                            ],
                        ],
                    ],
                    [
                        'name'    => StringLength::class,
                        'options' => [
                            'max'      => 254,
                            'messages' => [
                                StringLength::TOO_LONG => 'Please check your email address.',
                            ],
                        ],
                    ],
                ],
            ],
            // No filters, and only an empty string counts as empty, as spaces can be part of a password
            'password' => [
                'required'   => true,
                'validators' => [
                    [
                        'name'    => NotEmpty::class,
                        'options' => [
                            'type'     => NotEmpty::STRING,
                            'messages' => [
                                NotEmpty::IS_EMPTY => 'Please enter your password.',
                            ],
                        ],
                    ],
                ],
            ],
            'csrf'     => [
                'required'   => true,
                'validators' => [
                    [
                        'name'                   => NotEmpty::class,
                        'break_chain_on_failure' => true,
                        'options'                => [
                            'messages' => [
                                NotEmpty::IS_EMPTY => self::$responseMessages['csrf'],
                            ],
                        ],
                    ],
                    [
                        'name'    => Callback::class,
                        'options' => [
                            'callback' => fn (string $value): bool => $this->csrfGuard->validateToken(
                                $value,
                                $this->csrfKey
                            ),
                            'messages' => [
                                Callback::INVALID_VALUE => self::$responseMessages['csrf'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
