<?php

declare(strict_types=1);

namespace Jundayw\ComposerVersion;

/**
 * 版本递增类型枚举（使用类常量模拟枚举，兼容 PHP 8.0+）。
 *
 * 提供类型校验与合法值列表，供命令参数提示与 BumpResolver 使用。
 */
final class BumpType
{
    /** 主版本号递增：1.2.3 → 2.0.0 */
    public const MAJOR = 'major';

    /** 次版本号递增：1.2.3 → 1.3.0 */
    public const MINOR = 'minor';

    /** 补丁号递增：1.2.3 → 1.2.4 */
    public const PATCH = 'patch';

    /** 预发布主版本：1.2.3 → 2.0.0-alpha.0 */
    public const PREMAJOR = 'premajor';

    /** 预发布次版本：1.2.3 → 1.3.0-alpha.0 */
    public const PREMINOR = 'preminor';

    /** 预发布补丁：1.2.3 → 1.2.4-alpha.0 */
    public const PREPATCH = 'prepatch';

    /** 预发布递增：1.2.3-alpha.0 → 1.2.3-alpha.1 */
    public const PRERELEASE = 'prerelease';

    /** 开发主版本：1.2.3 → 2.0.0-dev */
    public const DEVMAJOR = 'devmajor';

    /** 开发次版本：1.2.3 → 1.3.0-dev */
    public const DEVMINOR = 'devminor';

    /** 开发补丁：1.2.3 → 1.2.4-dev */
    public const DEVPATCH = 'devpatch';

    /** 从 Git 标签推导版本（不递增） */
    public const FROM_GIT = 'from-git';

    /** 默认预发布标识符 */
    public const DEFAULT_PRE_ID = 'alpha';

    /** 开发版本默认标识符 */
    public const DEFAULT_DEV_ID = 'dev';

    /**
     * 所有合法的 bump 类型列表。
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::MAJOR,
            self::MINOR,
            self::PATCH,
            self::PREMAJOR,
            self::PREMINOR,
            self::PREPATCH,
            self::PRERELEASE,
            self::DEVMAJOR,
            self::DEVMINOR,
            self::DEVPATCH,
            self::FROM_GIT,
        ];
    }

    /**
     * 判断给定类型是否为合法的 bump 类型。
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }

    /**
     * 判断给定类型是否为预发布类型（pre* 或 prerelease）。
     */
    public static function isPreRelease(string $type): bool
    {
        return in_array($type, [
            self::PREMAJOR,
            self::PREMINOR,
            self::PREPATCH,
            self::PRERELEASE,
        ], true);
    }

    /**
     * 判断给定类型是否为开发版本类型（dev*）。
     */
    public static function isDevRelease(string $type): bool
    {
        return in_array($type, [
            self::DEVMAJOR,
            self::DEVMINOR,
            self::DEVPATCH,
        ], true);
    }
}