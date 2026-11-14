<?php

declare(strict_types=1);

namespace Admin\Forms;

use Laminas\Form\Element\Select;
use Laminas\Validator\Callback;
use Laminas\Validator\Date as DateValidator;
use Laminas\Validator\InArray;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\StringLength;
use Mezzio\Csrf\CsrfGuardInterface;

use function array_keys;
use function array_map;
use function assert;

/**
 * The CSRF token and validator specifications the admin forms share. A form using it must have a "csrf" Hidden
 * element, and an input specification that removes the inputs of removed elements.
 */
trait AdminFormTrait
{
    private string $csrfKey = '';

    private CsrfGuardInterface $csrfGuard;

    /**
     * @param string $csrfKey A different key for each page, so pages open in two tabs do not replace each other's token
     */
    public function setCsrfGuard(CsrfGuardInterface $csrfGuard, string $csrfKey): static
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

        return $this;
    }

    private function csrfSpecification(string $message): array
    {
        return [
            'required'   => true,
            'validators' => [
                $this->notEmpty($message),
                [
                    'name'    => Callback::class,
                    'options' => [
                        'callback' => fn (string $value): bool => $this->csrfGuard->validateToken(
                            $value,
                            $this->csrfKey
                        ),
                        'messages' => [Callback::INVALID_VALUE => $message],
                    ],
                ],
            ],
        ];
    }

    /**
     * Checks the value is one of a select's options. The element's own check is not used, as the form's input
     * specification replaces it. The specification is built for removed elements too, before they are left out.
     */
    private function inOptions(string $name, string $message): array
    {
        $options = $this->has($name) ? $this->select($name)->getValueOptions() : [];

        return [
            'name'    => InArray::class,
            'options' => [
                'haystack' => array_map('strval', array_keys($options)),
                'strict'   => InArray::COMPARE_STRICT,
                'messages' => [InArray::NOT_IN_ARRAY => $message],
            ],
        ];
    }

    private function select(string $name): Select
    {
        $select = $this->get($name);
        assert($select instanceof Select);

        return $select;
    }

    private function notEmpty(string $message): array
    {
        return [
            'name'                   => NotEmpty::class,
            'break_chain_on_failure' => true,
            'options'                => ['messages' => [NotEmpty::IS_EMPTY => $message]],
        ];
    }

    private function maxLength(int $max, string $message = 'This must be no more than %max% characters.'): array
    {
        return [
            'name'                   => StringLength::class,
            'break_chain_on_failure' => true,
            'options'                => ['max' => $max, 'messages' => [StringLength::TOO_LONG => $message]],
        ];
    }

    private function date(string $message): array
    {
        return [
            'name'    => DateValidator::class,
            'options' => [
                'format'   => 'Y-m-d',
                'messages' => [
                    DateValidator::INVALID      => $message,
                    DateValidator::INVALID_DATE => $message,
                    DateValidator::FALSEFORMAT  => $message,
                ],
            ],
        ];
    }
}
