<?php

declare(strict_types=1);

namespace Jundayw\ComposerVersion\VersionControlSystem;

use Jundayw\ComposerVersion\Exception\GitException;

/**
 * 版本控制系统抽象接口。
 *
 * 便于后期扩展 SVN、Mercurial 等其他 VCS。
 */
interface VersionControlSystemInterface
{
    /**
     * 返回 VCS 标识（如 git、svn）。
     */
    public function getIdentifier(): string;

    /**
     * 判断当前目录是否为该 VCS 的仓库。
     */
    public function isRepository(): bool;

    /**
     * 判断工作区是否干净（无未提交更改）。
     */
    public function isWorkingDirectoryClean(): bool;

    /**
     * 从 VCS 获取最近一次标签对应的版本号。
     *
     * @return string|null 无标签时返回 null
     */
    public function getVersionFromTag(): ?string;

    /**
     * 判断指定版本是否已存在对应标签。
     */
    public function hasTag(string $version): bool;

    /**
     * 提交 composer.json 并打标签。
     *
     * @param string $version 版本号（不含 v 前缀）
     * @param string $message 提交信息
     *
     * @throws GitException 操作失败时抛出
     */
    public function commitAndTag(string $version, string $message): void;

    /**
     * 回滚最近一次提交与标签（用于失败恢复）。
     */
    public function rollbackLastCommitAndTag(string $version): void;
}