<?php

declare(strict_types=1);

namespace Admin\Forms;

use Closure;
use Laminas\Filter\StringToLower;
use Laminas\Filter\StringTrim;
use Laminas\Filter\StripNewlines;
use Laminas\Filter\StripTags;
use Laminas\Form\Element\Checkbox;
use Laminas\Form\Element\Hidden;
use Laminas\Form\Element\Submit;
use Laminas\Form\Element\Text;
use Laminas\Form\Element\Textarea;
use Laminas\Form\Exception\ExceptionInterface;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Validator\Callback;
use Laminas\Validator\Hostname;

use function explode;
use function filter_var;
use function preg_match;
use function preg_replace;
use function preg_split;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function trim;

use const FILTER_VALIDATE_IP;

/**
 * Adds or edits a site, with the settings of Open Web Analytics' site profile
 */
final class SiteForm extends Form implements InputFilterProviderInterface
{
    use AdminFormTrait;

    /** An IPv4 range of excluded addresses, such as 192.168.1.* or 10.* */
    private const IPV4_RANGE = '~^(25[0-5]|2[0-4]\d|1?\d?\d)(\.(25[0-5]|2[0-4]\d|1?\d?\d)){0,2}\.\*$~';

    /** @var array{csrf: string, taken: string} */
    public static array $responseMessages = [
        'csrf'  => 'This page has expired. Please try again.',
        'taken' => 'Another site already has this domain.',
    ];

    /** @var Closure(string): bool */
    private Closure $isTaken;

    public function __construct()
    {
        parent::__construct('site');
        $this->isTaken = static fn (): bool => false;
    }

    /**
     * @param Closure(string): bool $isTaken Whether another site has the domain
     */
    public function setTakenCheck(Closure $isTaken): static
    {
        $this->isTaken = $isTaken;

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
            'name'       => 'domain',
            'type'       => Text::class,
            'options'    => ['label' => 'Domain'],
            'attributes' => [
                'maxlength'   => 255,
                'required'    => true,
                'placeholder' => 'www.example.com',
                'class'       => 'form-control',
                'id'          => 'domain',
            ],
        ]);

        $this->add([
            'name'       => 'description',
            'type'       => Textarea::class,
            'options'    => ['label' => 'Description'],
            'attributes' => ['rows' => 2, 'maxlength' => 1000, 'class' => 'form-control', 'id' => 'description'],
        ]);

        $this->add([
            'name'       => 'excludedIps',
            'type'       => Textarea::class,
            'options'    => ['label' => 'Excluded IP Addresses'],
            'attributes' => [
                'rows'        => 3,
                'maxlength'   => 4000,
                'placeholder' => "203.0.113.7\n192.168.1.*",
                'class'       => 'form-control font-monospace',
                'id'          => 'excludedIps',
            ],
        ]);

        foreach (
            [
                'checkDomain'       => 'Only accept page views from this domain and its subdomains',
                'respectDoNotTrack' => 'Leave out visitors whose browsers ask not to be tracked',
                'active'            => 'Collecting',
            ] as $name => $label
        ) {
            $this->add([
                'name'       => $name,
                'type'       => Checkbox::class,
                'options'    => ['label' => $label, 'use_hidden_element' => true],
                'attributes' => ['class' => 'form-check-input', 'id' => $name, 'value' => '1'],
            ]);
        }

        $this->add(['name' => 'csrf', 'type' => Hidden::class]);

        $this->add([
            'name'       => 'submit',
            'type'       => Submit::class,
            'attributes' => ['value' => 'Save Site', 'class' => 'btn btn-primary'],
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
            'name'              => [
                'required'   => true,
                'filters'    => $singleLine,
                'validators' => [$this->notEmpty('Please enter a name for the site.'), $this->maxLength(100)],
            ],
            'domain'            => [
                'required'   => true,
                'filters'    => [
                    ...$singleLine,
                    ['name' => StringToLower::class],
                    // Accept a pasted address, keeping only the host name and any port
                    [
                        'name'    => \Laminas\Filter\Callback::class,
                        'options' => ['callback' => static fn (mixed $value): string => self::hostOnly((string) $value)],
                    ],
                ],
                'validators' => [
                    $this->notEmpty('Please enter the site\'s domain, such as www.example.com.'),
                    $this->maxLength(255),
                    [
                        'name'                   => Callback::class,
                        'options'                => [
                            'callback' => static fn (string $value): bool => self::isHost($value),
                            'messages' => [
                                Callback::INVALID_VALUE => 'Please enter a domain name, such as www.example.com.',
                            ],
                        ],
                        'break_chain_on_failure' => true,
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
            'description'       => [
                'required'   => false,
                'filters'    => [['name' => StripTags::class], ['name' => StringTrim::class]],
                'validators' => [$this->maxLength(1000)],
            ],
            'excludedIps'       => [
                'required'   => false,
                'filters'    => [['name' => StringTrim::class]],
                'validators' => [
                    $this->maxLength(4000),
                    [
                        'name'    => Callback::class,
                        'options' => [
                            'callback' => static fn (string $value): bool => self::isIpList($value),
                            'messages' => [
                                Callback::INVALID_VALUE => 'Please enter IP addresses, one per line. An IPv4 range can '
                                    . 'end in *, such as 192.168.1.*',
                            ],
                        ],
                    ],
                ],
            ],
            'checkDomain'       => ['required' => false],
            'respectDoNotTrack' => ['required' => false],
            'active'            => ['required' => false],
            'csrf'              => $this->csrfSpecification(self::$responseMessages['csrf']),
        ];
    }

    /**
     * The host name and port of a pasted address, such as https://www.example.com/page
     */
    public static function hostOnly(string $value): string
    {
        $value = (string) preg_replace('~^[a-z][a-z0-9+.-]*://~i', '', trim($value));
        $value = (string) preg_replace('~[/?#].*$~', '', $value);

        return rtrim($value, '.');
    }

    public static function isHost(string $value): bool
    {
        [$host, $port] = str_contains($value, ':') ? explode(':', $value, 2) : [$value, null];
        if ($port !== null && (preg_match('~^\d{1,5}$~', $port) !== 1)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false || $host === 'localhost') {
            return true;
        }

        return (new Hostname(['allow' => Hostname::ALLOW_DNS, 'useTldCheck' => false]))->isValid($host);
    }

    public static function isIpList(string $value): bool
    {
        foreach (preg_split('/[\s,]+/', $value) ?: [] as $entry) {
            if ($entry === '') {
                continue;
            }

            // An IPv4 range such as 192.168.1.* or 10.*
            if (str_ends_with($entry, '*')) {
                if (preg_match(self::IPV4_RANGE, $entry) !== 1) {
                    return false;
                }
                continue;
            }

            if (filter_var($entry, FILTER_VALIDATE_IP) === false) {
                return false;
            }
        }

        return true;
    }
}
