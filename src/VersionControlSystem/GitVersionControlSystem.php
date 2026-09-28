<?php

declare(strict_types=1);

namespace Jundayw\ComposerVersion\VersionControlSystem;

use Jundayw\ComposerVersion\Exception\GitException;

/**
 * Git 版本控制系统实现。
 */
final class GitVersionControlSystem extends AbstractVersionControlSystem
{
    /**
     * {@inheritDoc}
     */
    public function getIdentifier(): string
    {
        return 'git';
    }

    /**
     * {@inheritDoc}
     */
    public function isRepository(): bool
    {
        return $this->tryRun([
            'git',
            'rev-parse',
            '--is-inside-work-tree',
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function isWorkingDirectoryClean(): bool
    {
        // git diff-index --quiet HEAD 在干净时退出码为 0
        return $this->tryRun([
            'git',
            'diff-index',
            '--quiet',
            'HEAD',
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function getVersionFromTag(): ?string
    {
        try {
            $tag = $this->run([
                'git',
                'describe',
                '--tags',
                '--abbrev=0',
            ]);

            return $this->normalizeTag($tag);
        } catch (GitException) {
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function hasTag(string $version): bool
    {
        return $this->tryRun([
            'git',
            'rev-parse',
            '--verify',
            '--quiet',
            'refs/tags/v'.$version,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function commitAndTag(string $version, string $message): void
    {
        $this->run(['git', 'add', './']);
        $this->run(['git', 'commit', '-m', $message]);
        $this->run(['git', 'tag', 'v'.$version]);
    }

    /**
     * {@inheritDoc}
     *
     * 回滚策略：
     * 1. 删除本地标签 v{version}
     * 2. 软重置最近一次提交（保留文件改动，便于用户手动处理）
     */
    public function rollbackLastCommitAndTag(string $version): void
    {
        $this->tryRun(['git', 'tag', '-d', 'v'.$version]);
        $this->tryRun(['git', 'reset', '--soft', 'HEAD~1']);
    }

    /**
     * 去除标签前缀 v。
     */
    private function normalizeTag(string $tag): string
    {
        return str_starts_with($tag, 'v') ? substr($tag, 1) : $tag;
    }
}