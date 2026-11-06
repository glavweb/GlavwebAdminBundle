<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\Naming;

use Symfony\Bundle\MakerBundle\Str;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Class NamingStrategy.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
#[Exclude]
readonly class NamingStrategy
{
    /**
     * @param class-string $className
     */
    public static function entityClassToSnakeCase(string $className, bool $withRelativeNamespace = false): string
    {
        if ($withRelativeNamespace) {
            [, $result] = self::splitEntityClassName($className);
            $result = self::removeClassNameDuplication($result);
            $result = str_replace('\\', '_', $result);
        } else {
            $result = self::trimNamespace($className);
        }

        return Str::asSnakeCase($result);
    }

    /**
     * @param class-string $className
     */
    public static function entityClassToKebabCase(string $className, bool $withRelativeNamespace = false): string
    {
        if ($withRelativeNamespace) {
            [, $result] = self::splitEntityClassName($className);
            $result = self::removeClassNameDuplication($result);
            $result = str_replace('\\', '-', $result);
        } else {
            $result = self::trimNamespace($className);
        }

        return Str::asCommand($result);
    }

    /**
     * @param class-string $className
     */
    public static function entityClassToResourceFilePath(
        string $className,
        string $fileName,
        string $fileExtension = '',
    ): string {
        $dirPath = self::entityClassToResourceDirPath($className);

        return \sprintf('%s/%s%s', $dirPath, Str::asSnakeCase($fileName), $fileExtension);
    }

    /**
     * @param class-string $className
     */
    public static function entityClassToResourceDirPath(string $className): string
    {
        [,$result] = self::splitEntityClassName($className);

        return implode('/', array_map(Str::asSnakeCase(...), explode('\\', $result)));
    }

    public static function entityPropertyToSnakeCase(string $propertyName): string
    {
        return Str::asSnakeCase($propertyName);
    }

    /**
     * @param class-string $className
     */
    public static function enumClassToSnakeCase(string $className): string
    {
        return Str::asSnakeCase(self::trimNamespace($className));
    }

    public static function adminLabel(string $label): string
    {
        return Str::asSnakeCase($label);
    }

    /**
     * @param class-string $className
     *
     * @return string[]
     */
    private static function splitEntityClassName(string $className): array
    {
        $parts = array_map(static fn ($v): string => ltrim($v, '\\'), explode('\\Entity', $className));

        if (1 === \count($parts)) {
            return ['', $parts[0]];
        }

        return $parts;
    }

    private static function trimNamespace(string $className): string
    {
        return array_last(explode('\\', $className));
    }

    private static function removeClassNameDuplication(string $className): string
    {
        $parts = explode('\\', $className);
        $partsCount = \count($parts);
        if ($partsCount > 1 && str_starts_with($parts[$partsCount - 1], $parts[$partsCount - 2])) {
            array_splice($parts, $partsCount - 2, 1);
        }

        return implode('\\', $parts);
    }
}
