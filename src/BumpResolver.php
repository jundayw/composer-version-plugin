<?php

declare(strict_types=1);

namespace Jundayw\ComposerVersion;

use Jundayw\ComposerVersion\Exception\InvalidBumpException;
use PHLAK\SemVer\Version;
use Throwable;

/**
 * 版本递增解析器。
 *
 * 职责：
 * - 将 bump 参数（major/minor/... 或显式版本号）解析为新版本字符串
 * - 通过 BumpType 常量校验合法性
 * - 统一处理 preid 默认值与规范化
 *
 * 设计：使用 match 表达式集中分发，便于扩展。
 */
final class BumpResolver
{
    /** 默认预发布标识符 */
    public const DEFAULT_PRE_ID = BumpType::DEFAULT_PRE_ID;

    /** 默认开发版本标识符 */
    public const DEFAULT_DEV_ID = BumpType::DEFAULT_DEV_ID;

    /**
     * 解析 bump 参数并返回新版本字符串。
     *
     * @param string      $bump       递增类型或显式版本号
     * @param string|null $preId      预发布标识符
     * @param string|null $currentVersion
     * @param string|null $gitVersion 从 Git 标签获取的版本号
     *
     * @return string
     */
    public function resolve(string $bump, ?string $preId = null, ?string $currentVersion = null, ?string $gitVersion = null): string
    {
        // 1. from-git：直接使用 Git 标签版本
        if ($bump === BumpType::FROM_GIT) {
            if ($gitVersion === null) {
                throw new InvalidBumpException(
                    'Cannot resolve version from git: no tags found in repository.'
                );
            }

            return $this->normalizeVersionString($gitVersion);
        }

        // 2. 显式版本号：直接规范化返回
        if ($this->looksLikeExplicitVersion($bump)) {
            return $this->normalizeExplicitVersion($bump);
        }

        // 3. bump 类型：校验后分发
        if (!BumpType::isValid($bump)) {
            throw new InvalidBumpException(sprintf(
                'Unsupported bump type "%s". Valid types: %s.',
                $bump,
                implode(', ', BumpType::all())
            ));
        }
        // 4. bump 类型：优先用当前版本，其次 gitVersion，最后 0.0.0
        $baseVersion = $this->pickBaseVersion($currentVersion, $gitVersion);

        return $this->apply(new Version($baseVersion), $bump, $preId);
    }

    private function pickBaseVersion(?string $currentVersion, ?string $gitVersion): string
    {
        // 当前版本合法 SemVer → 用它
        if ($currentVersion !== null && $this->isValidSemVer($currentVersion)) {
            return $this->normalizeVersionString($currentVersion);
        }

        // 回退到 git tag
        if ($gitVersion !== null && $this->isValidSemVer($gitVersion)) {
            return $this->normalizeVersionString($gitVersion);
        }

        // 最后回退
        return '0.0.0';
    }

    private function isValidSemVer(string $version): bool
    {
        $version = $this->normalizeVersionString($version);

        return (bool)preg_match(
            '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)'
            .'(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)'
            .'(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?'
            .'(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/',
            $version
        );
    }

    /**
     * 根据 bump 类型应用对应策略。
     */
    private function apply(Version $version, string $bump, ?string $preId): string
    {
        $preId = $this->normalizePreId($preId);

        return match ($bump) {
            BumpType::MAJOR => $this->major($version),
            BumpType::MINOR => $this->minor($version),
            BumpType::PATCH => $this->patch($version),

            BumpType::PREMAJOR => $this->preMajor($version, $preId),
            BumpType::PREMINOR => $this->preMinor($version, $preId),
            BumpType::PREPATCH => $this->prePatch($version, $preId),

            BumpType::PRERELEASE => $this->preRelease($version, $preId),

            BumpType::DEVMAJOR => $this->devMajor($version),
            BumpType::DEVMINOR => $this->devMinor($version),
            BumpType::DEVPATCH => $this->devPatch($version),

            default => throw new InvalidBumpException(
                sprintf('Unsupported bump type "%s".', $bump)
            ),
        };
    }

    // -----------------------------------------------------------------
    // 稳定版本递增
    // -----------------------------------------------------------------

    private function major(Version $version): string
    {
        $version->incrementMajor();

        return (string)$version;
    }

    private function minor(Version $version): string
    {
        $version->incrementMinor();

        return (string)$version;
    }

    private function patch(Version $version): string
    {
        $version->incrementPatch();

        return (string)$version;
    }

    // -----------------------------------------------------------------
    // 预发布版本递增
    // -----------------------------------------------------------------

    private function preMajor(Version $version, string $preId): string
    {
        $version->incrementMajor();
        $version->setPreRelease($preId.'.0');

        return (string)$version;
    }

    private function preMinor(Version $version, string $preId): string
    {
        $version->incrementMinor();
        $version->setPreRelease($preId.'.0');

        return (string)$version;
    }

    private function prePatch(Version $version, string $preId): string
    {
        $version->incrementPatch();
        $version->setPreRelease($preId.'.0');

        return (string)$version;
    }

    /**
     * 预发布号递增：
     * - 无预发布：设置 preId.0
     * - 有预发布：递增最后的数字部分
     */
    private function preRelease(Version $version, string $preId): string
    {
        $preRelease = $version->preRelease;

        if ($preRelease === null) {
            $version->setPreRelease("{$preId}.0");

            return (string)$version;
        }

        // alpha.0 / alpha.1 / beta.2 ...
        if (str_starts_with(strtolower($preRelease), strtolower("{$preId}.")) && preg_match('/^(.+)\.(\d+)$/', $preRelease, $matches)) {
            $version->setPreRelease(
                $matches[1].'.'.((int)$matches[2] + 1)
            );

            return (string)$version;
        }

        // alpha → alpha.0
        $version->setPreRelease($preId.'.0');

        return (string)$version;
    }

    // -----------------------------------------------------------------
    // 开发版本递增
    // -----------------------------------------------------------------

    private function devMajor(Version $version): string
    {
        $version->incrementMajor();
        $version->setPreRelease(self::DEFAULT_DEV_ID);

        return (string)$version;
    }

    private function devMinor(Version $version): string
    {
        $version->incrementMinor();
        $version->setPreRelease(self::DEFAULT_DEV_ID);

        return (string)$version;
    }

    private function devPatch(Version $version): string
    {
        $version->incrementPatch();
        $version->setPreRelease(self::DEFAULT_DEV_ID);

        return (string)$version;
    }

    // -----------------------------------------------------------------
    // 工具方法
    // -----------------------------------------------------------------

    /**
     * 判断输入是否形如显式版本号（如 v1.2.3、1.2.3、1.2.3-alpha）。
     */
    private function looksLikeExplicitVersion(string $input): bool
    {
        return (bool)preg_match('/^v?\d+\.\d+\.\d+/', $input);
    }

    /**
     * 规范化显式版本号（去除 v 前缀，校验 semver）。
     *
     * @throws InvalidBumpException
     */
    private function normalizeExplicitVersion(string $input): string
    {
        try {
            return (string)new Version($this->normalizeVersionString($input));
        } catch (Throwable $e) {
            throw new InvalidBumpException(
                sprintf('Invalid explicit version "%s": %s', $input, $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * 去除版本字符串前缀 v。
     */
    private function normalizeVersionString(string $version): string
    {
        return str_starts_with($version, 'v') ? substr($version, 1) : $version;
    }

    /**
     * 规范化预发布标识符（空值回退默认值）。
     */
    private function normalizePreId(?string $preId): string
    {
        $preId = $preId !== null ? trim($preId) : '';

        return $preId !== '' ? $preId : self::DEFAULT_PRE_ID;
    }
}