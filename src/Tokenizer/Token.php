<?php

declare(strict_types=1);

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpCsFixer\Tokenizer;

use PhpCsFixer\Utils;

/**
 * Representation of single token.
 * As a token prototype you should understand a single element generated by token_get_all.
 *
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 */
final class Token
{
    /**
     * Content of token prototype.
     *
     * @var string
     */
    private $content;

    /**
     * ID of token prototype, if available.
     *
     * @var null|int
     */
    private $id;

    /**
     * If token prototype is an array.
     *
     * @var bool
     */
    private $isArray;

    /**
     * Flag is token was changed.
     *
     * @var bool
     */
    private $changed = false;

    /**
     * @param array|string $token token prototype
     */
    public function __construct($token)
    {
        if (\is_array($token)) {
            if (!\is_int($token[0])) {
                throw new \InvalidArgumentException(sprintf(
                    'Id must be an int, got "%s".',
                    \is_object($token[0]) ? \get_class($token[0]) : \gettype($token[0])
                ));
            }

            if (!\is_string($token[1])) {
                throw new \InvalidArgumentException(sprintf(
                    'Content must be a string, got "%s".',
                    \is_object($token[1]) ? \get_class($token[1]) : \gettype($token[1])
                ));
            }

            if ('' === $token[1]) {
                throw new \InvalidArgumentException('Cannot set empty content for id-based Token.');
            }

            $this->isArray = true;
            $this->id = $token[0];
            $this->content = $token[1];

            if ($token[0] && '' === $token[1]) {
                throw new \InvalidArgumentException('Cannot set empty content for id-based Token.');
            }
        } elseif (\is_string($token)) {
            $this->isArray = false;
            $this->content = $token;
        } else {
            throw new \InvalidArgumentException(sprintf(
                'Cannot recognize input value as valid Token prototype, got "%s".',
                \is_object($token) ? \get_class($token) : \gettype($token)
            ));
        }
    }

    /**
     * @return int[]
     */
    public static function getCastTokenKinds(): array
    {
        static $castTokens = [T_ARRAY_CAST, T_BOOL_CAST, T_DOUBLE_CAST, T_INT_CAST, T_OBJECT_CAST, T_STRING_CAST, T_UNSET_CAST];

        return $castTokens;
    }

    /**
     * Get classy tokens kinds: T_CLASS, T_INTERFACE and T_TRAIT.
     *
     * @return int[]
     */
    public static function getClassyTokenKinds(): array
    {
        static $classTokens = [T_CLASS, T_TRAIT, T_INTERFACE];

        return $classTokens;
    }

    /**
     * Get object operator tokens kinds: T_OBJECT_OPERATOR and (if available) T_NULLSAFE_OBJECT_OPERATOR.
     *
     * @return int[]
     */
    public static function getObjectOperatorKinds(): array
    {
        static $objectOperators = null;

        if (null === $objectOperators) {
            $objectOperators = [T_OBJECT_OPERATOR];
            if (\defined('T_NULLSAFE_OBJECT_OPERATOR')) {
                $objectOperators[] = T_NULLSAFE_OBJECT_OPERATOR;
            }
        }

        return $objectOperators;
    }

    /**
     * Check if token is equals to given one.
     *
     * If tokens are arrays, then only keys defined in parameter token are checked.
     *
     * @param array|string|Token $other         token or it's prototype
     * @param bool               $caseSensitive perform a case sensitive comparison
     */
    public function equals($other, bool $caseSensitive = true): bool
    {
        if ($other instanceof self) {
            // Inlined getPrototype() on this very hot path.
            // We access the private properties of $other directly to save function call overhead.
            // This is only possible because $other is of the same class as `self`.
            if (!$other->isArray) {
                $otherPrototype = $other->content;
            } else {
                $otherPrototype = [
                    $other->id,
                    $other->content,
                ];
            }
        } else {
            $otherPrototype = $other;
        }

        if ($this->isArray !== \is_array($otherPrototype)) {
            return false;
        }

        if (!$this->isArray) {
            return $this->content === $otherPrototype;
        }

        if ($this->id !== $otherPrototype[0]) {
            return false;
        }

        if (isset($otherPrototype[1])) {
            if ($caseSensitive) {
                if ($this->content !== $otherPrototype[1]) {
                    return false;
                }
            } elseif (0 !== strcasecmp($this->content, $otherPrototype[1])) {
                return false;
            }
        }

        // detect unknown keys
        unset($otherPrototype[0], $otherPrototype[1]);

        return empty($otherPrototype);
    }

    /**
     * Check if token is equals to one of given.
     *
     * @param array $others        array of tokens or token prototypes
     * @param bool  $caseSensitive perform a case sensitive comparison
     */
    public function equalsAny(array $others, bool $caseSensitive = true): bool
    {
        foreach ($others as $other) {
            if ($this->equals($other, $caseSensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A helper method used to find out whether or not a certain input token has to be case-sensitively matched.
     *
     * @param array<int, bool>|bool $caseSensitive global case sensitiveness or an array of booleans, whose keys should match
     *                                             the ones used in $others. If any is missing, the default case-sensitive
     *                                             comparison is used
     * @param int                   $key           the key of the token that has to be looked up
     */
    public static function isKeyCaseSensitive($caseSensitive, int $key): bool
    {
        if (\is_array($caseSensitive)) {
            return isset($caseSensitive[$key]) ? $caseSensitive[$key] : true;
        }

        return $caseSensitive;
    }

    /**
     * @return array|string token prototype
     */
    public function getPrototype()
    {
        if (!$this->isArray) {
            return $this->content;
        }

        return [
            $this->id,
            $this->content,
        ];
    }

    /**
     * Get token's content.
     *
     * It shall be used only for getting the content of token, not for checking it against excepted value.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get token's id.
     *
     * It shall be used only for getting the internal id of token, not for checking it against excepted value.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get token's name.
     *
     * It shall be used only for getting the name of token, not for checking it against excepted value.
     *
     * @return null|string token name
     */
    public function getName(): ?string
    {
        if (null === $this->id) {
            return null;
        }

        return self::getNameForId($this->id);
    }

    /**
     * Get token's name.
     *
     * It shall be used only for getting the name of token, not for checking it against excepted value.
     *
     * @return null|string token name
     */
    public static function getNameForId(int $id): ?string
    {
        if (CT::has($id)) {
            return CT::getName($id);
        }

        $name = token_name($id);

        return 'UNKNOWN' === $name ? null : $name;
    }

    /**
     * Generate array containing all keywords that exists in PHP version in use.
     *
     * @return array<int, int>
     */
    public static function getKeywords(): array
    {
        static $keywords = null;

        if (null === $keywords) {
            $keywords = self::getTokenKindsForNames(['T_ABSTRACT', 'T_ARRAY', 'T_AS', 'T_BREAK', 'T_CALLABLE', 'T_CASE',
                'T_CATCH', 'T_CLASS', 'T_CLONE', 'T_CONST', 'T_CONTINUE', 'T_DECLARE', 'T_DEFAULT', 'T_DO',
                'T_ECHO', 'T_ELSE', 'T_ELSEIF', 'T_EMPTY', 'T_ENDDECLARE', 'T_ENDFOR', 'T_ENDFOREACH',
                'T_ENDIF', 'T_ENDSWITCH', 'T_ENDWHILE', 'T_EVAL', 'T_EXIT', 'T_EXTENDS', 'T_FINAL',
                'T_FINALLY', 'T_FN', 'T_FOR', 'T_FOREACH', 'T_FUNCTION', 'T_GLOBAL', 'T_GOTO', 'T_HALT_COMPILER',
                'T_IF', 'T_IMPLEMENTS', 'T_INCLUDE', 'T_INCLUDE_ONCE', 'T_INSTANCEOF', 'T_INSTEADOF',
                'T_INTERFACE', 'T_ISSET', 'T_LIST', 'T_LOGICAL_AND', 'T_LOGICAL_OR', 'T_LOGICAL_XOR',
                'T_NAMESPACE', 'T_MATCH', 'T_NEW', 'T_PRINT', 'T_PRIVATE', 'T_PROTECTED', 'T_PUBLIC', 'T_REQUIRE',
                'T_REQUIRE_ONCE', 'T_RETURN', 'T_STATIC', 'T_SWITCH', 'T_THROW', 'T_TRAIT', 'T_TRY',
                'T_UNSET', 'T_USE', 'T_VAR', 'T_WHILE', 'T_YIELD', 'T_YIELD_FROM',
            ]) + [
                CT::T_ARRAY_TYPEHINT => CT::T_ARRAY_TYPEHINT,
                CT::T_CLASS_CONSTANT => CT::T_CLASS_CONSTANT,
                CT::T_CONST_IMPORT => CT::T_CONST_IMPORT,
                CT::T_CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE => CT::T_CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE,
                CT::T_CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED => CT::T_CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED,
                CT::T_CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC => CT::T_CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC,
                CT::T_FUNCTION_IMPORT => CT::T_FUNCTION_IMPORT,
                CT::T_NAMESPACE_OPERATOR => CT::T_NAMESPACE_OPERATOR,
                CT::T_USE_LAMBDA => CT::T_USE_LAMBDA,
                CT::T_USE_TRAIT => CT::T_USE_TRAIT,
            ];
        }

        return $keywords;
    }

    /**
     * Generate array containing all predefined constants that exists in PHP version in use.
     *
     * @see https://php.net/manual/en/language.constants.predefined.php
     *
     * @return array<int, int>
     */
    public static function getMagicConstants(): array
    {
        static $magicConstants = null;

        if (null === $magicConstants) {
            $magicConstants = self::getTokenKindsForNames(['T_CLASS_C', 'T_DIR', 'T_FILE', 'T_FUNC_C', 'T_LINE', 'T_METHOD_C', 'T_NS_C', 'T_TRAIT_C']);
        }

        return $magicConstants;
    }

    /**
     * Check if token prototype is an array.
     *
     * @return bool is array
     */
    public function isArray(): bool
    {
        return $this->isArray;
    }

    /**
     * Check if token is one of type cast tokens.
     */
    public function isCast(): bool
    {
        return $this->isGivenKind(self::getCastTokenKinds());
    }

    /**
     * Check if token is one of classy tokens: T_CLASS, T_INTERFACE or T_TRAIT.
     */
    public function isClassy(): bool
    {
        return $this->isGivenKind(self::getClassyTokenKinds());
    }

    /**
     * Check if token is one of comment tokens: T_COMMENT or T_DOC_COMMENT.
     */
    public function isComment(): bool
    {
        static $commentTokens = [T_COMMENT, T_DOC_COMMENT];

        return $this->isGivenKind($commentTokens);
    }

    /**
     * Check if token is one of object operator tokens: T_OBJECT_OPERATOR or T_NULLSAFE_OBJECT_OPERATOR.
     */
    public function isObjectOperator(): bool
    {
        return $this->isGivenKind(self::getObjectOperatorKinds());
    }

    /**
     * Check if token is one of given kind.
     *
     * @param int|int[] $possibleKind kind or array of kinds
     */
    public function isGivenKind($possibleKind): bool
    {
        return $this->isArray && (\is_array($possibleKind) ? \in_array($this->id, $possibleKind, true) : $this->id === $possibleKind);
    }

    /**
     * Check if token is a keyword.
     */
    public function isKeyword(): bool
    {
        $keywords = static::getKeywords();

        return $this->isArray && isset($keywords[$this->id]);
    }

    /**
     * Check if token is a native PHP constant: true, false or null.
     */
    public function isNativeConstant(): bool
    {
        static $nativeConstantStrings = ['true', 'false', 'null'];

        return $this->isArray && \in_array(strtolower($this->content), $nativeConstantStrings, true);
    }

    /**
     * Returns if the token is of a Magic constants type.
     *
     * @see https://php.net/manual/en/language.constants.predefined.php
     */
    public function isMagicConstant(): bool
    {
        $magicConstants = static::getMagicConstants();

        return $this->isArray && isset($magicConstants[$this->id]);
    }

    /**
     * Check if token is whitespace.
     *
     * @param null|string $whitespaces whitespace characters, default is " \t\n\r\0\x0B"
     */
    public function isWhitespace(?string $whitespaces = " \t\n\r\0\x0B"): bool
    {
        if (null === $whitespaces) {
            $whitespaces = " \t\n\r\0\x0B";
        }

        if ($this->isArray && !$this->isGivenKind(T_WHITESPACE)) {
            return false;
        }

        return '' === trim($this->content, $whitespaces);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getName(),
            'content' => $this->content,
            'isArray' => $this->isArray,
            'changed' => $this->changed,
        ];
    }

    /**
     * @param null|string[] $options JSON encode option
     */
    public function toJson(?array $options = null): string
    {
        static $defaultOptions = null;

        if (null === $options) {
            if (null === $defaultOptions) {
                $defaultOptions = Utils::calculateBitmask(['JSON_PRETTY_PRINT', 'JSON_NUMERIC_CHECK']);
            }

            $options = $defaultOptions;
        } else {
            $options = Utils::calculateBitmask($options);
        }

        $jsonResult = json_encode($this->toArray(), $options);

        if (JSON_ERROR_NONE !== json_last_error()) {
            $jsonResult = json_encode(
                [
                    'errorDescription' => 'Can not encode Tokens to JSON.',
                    'rawErrorMessage' => json_last_error_msg(),
                ],
                $options
            );
        }

        return $jsonResult;
    }

    /**
     * @param string[] $tokenNames
     *
     * @return array<int, int>
     */
    private static function getTokenKindsForNames(array $tokenNames): array
    {
        $keywords = [];
        foreach ($tokenNames as $keywordName) {
            if (\defined($keywordName)) {
                $keyword = \constant($keywordName);
                $keywords[$keyword] = $keyword;
            }
        }

        return $keywords;
    }
}
