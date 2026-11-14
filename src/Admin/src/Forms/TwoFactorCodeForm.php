<?php

declare(strict_types=1);

namespace Admin\Forms;

use Laminas\Filter\StringTrim;
use Laminas\Form\Element\Hidden;
use Laminas\Form\Element\Submit;
use Laminas\Form\Element\Text;
use Laminas\Form\Exception\ExceptionInterface;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Validator\Callback;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\StringLength;
use Mezzio\Csrf\CsrfGuardInterface;

/**
 * Asks for a code from the user's authenticator app, or one of their recovery codes.
 */
final class TwoFactorCodeForm extends Form implements InputFilterProviderInterface
{
    public static array $responseMessages = [
        'invalid'           => 'That code is incorrect. Please try again.',
        'throttled'         => 'Too many failed login attempts. Please try again in %d minute(s).',
        'throttled-no-time' => 'Too many failed login attempts. Please try again later.',
        'expired'           => 'Your login has expired. Please log in again.',
        'csrf'              => 'This page has expired. Please try again.',
    ];

    protected string $csrfKey = 'admin-two-factor';

    protected CsrfGuardInterface $csrfGuard;

    public function __construct()
    {
        parent::__construct('admin-two-factor');
    }

    /**
     * @param string $csrfKey A different key for each page, so pages open in two tabs do not replace each other's token
     */
    public function setCsrfGuard(CsrfGuardInterface $csrfGuard, string $csrfKey = 'admin-two-factor'): static
    {
        $this->csrfGuard = $csrfGuard;
        $this->csrfKey   = $csrfKey;

        return $this;
    }

    /**
     * Tokens work once, so call this each time the form is shown.
     */
    public function generateCsrfToken(): static
    {
        $this->get('csrf')->setValue($this->csrfGuard->generateToken($this->csrfKey));
        // Codes work once, so never show one again
        $this->get('code')->setValue('');

        return $this;
    }

    /**
     * @throws ExceptionInterface
     */
    public function init(): void
    {
        $this->add([
            'name'       => 'code',
            'type'       => Text::class,
            'options'    => [
                'label' => 'Code',
            ],
            'attributes' => [
                'maxlength'      => 20,
                'placeholder'    => 'Code',
                'required'       => true,
                'autocomplete'   => 'one-time-code',
                'autocapitalize' => 'off',
                'spellcheck'     => 'false',
                'autofocus'      => true,
                'class'          => 'form-control',
                'id'             => 'code',
            ],
        ]);

        $this->add([
            'name' => 'csrf',
            'type' => Hidden::class,
        ]);

        $this->add([
            'name'       => 'submit',
            'type'       => Submit::class,
            'attributes' => [
                'value' => 'Continue',
                'class' => 'btn btn-primary',
            ],
        ]);
    }

    public function getInputFilterSpecification(): array
    {
        return [
            'code' => [
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
                                NotEmpty::IS_EMPTY => 'Please enter the code.',
                            ],
                        ],
                    ],
                    [
                        'name'    => StringLength::class,
                        'options' => [
                            'max'      => 20,
                            'messages' => [
                                StringLength::TOO_LONG => 'Please check the code.',
                            ],
                        ],
                    ],
                ],
            ],
            'csrf' => [
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
