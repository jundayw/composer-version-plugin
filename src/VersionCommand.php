<?php

declare(strict_types=1);

namespace Jundayw\ComposerVersion;

use Composer\Command\BaseCommand;
use Composer\Json\JsonFile;
use Composer\Package\RootPackageInterface;
use Exception;
use Jundayw\ComposerVersion\Exception\GitException;
use Jundayw\ComposerVersion\Exception\InvalidBumpException;
use Jundayw\ComposerVersion\VersionControlSystem\GitVersionControlSystem;
use Jundayw\ComposerVersion\VersionControlSystem\VersionControlSystemInterface;
use Seld\JsonLint\ParsingException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * composer version 命令。
 *
 * 行为：
 * 1. 校验 Git 工作区干净
 * 2. 计算新版本
 * 3. 执行 pre-version 脚本
 * 4. 持久化 composer.json
 * 5. 执行 pre-version-commit 脚本
 * 6. Git 提交与打标签
 * 7. 执行 post-version 脚本
 */
final class VersionCommand extends BaseCommand
{
    /** 脚本钩子名称 */
    private const SCRIPT_PRE_VERSION        = 'pre-version';
    private const SCRIPT_PRE_VERSION_COMMIT = 'pre-version-commit';
    private const SCRIPT_POST_VERSION       = 'post-version';

    private ?VersionControlSystemInterface $vcs      = null;
    private ?BumpResolver                  $resolver = null;

    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('version')
            ->setDescription('Bump package version')
            ->addArgument(
                'bump',
                InputArgument::OPTIONAL,
                'composer version: <newversion>, '.implode(', ', BumpType::all())
            )
            ->addOption(
                'preid',
                'P',
                InputOption::VALUE_REQUIRED,
                'Prerelease identifier (alpha, beta, rc)',
                BumpType::DEFAULT_PRE_ID
            )
            ->addOption(
                'no-git-tag-version',
                'S',
                InputOption::VALUE_NONE,
                'Skip git commit and tag'
            )
            ->addOption(
                'dry-run',
                'T',
                InputOption::VALUE_NONE,
                'Output operations without executing'
            )
            ->addOption(
                'allow-dirty',
                'F',
                InputOption::VALUE_NONE,
                'Allow bumping when the git working directory is not clean'
            );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composer = $this->requireComposer();
        $package  = $composer->getPackage();

        $this->vcs      = new GitVersionControlSystem($output);
        $this->resolver = new BumpResolver();

        $bump       = $input->getArgument('bump');
        $preId      = $input->getOption('preid');
        $isDryRun   = $input->getOption('dry-run');
        $skipGit    = $input->getOption('no-git-tag-version');
        $allowDirty = $input->getOption('allow-dirty');

        // 无参数：输出当前版本
        if ($bump === null) {
            $output->writeln(sprintf(
                'Current version: <info>%s</info>',
                $package->getVersion()
            ));

            return self::SUCCESS;
        }

        // Step 1: Git 工作区安全检查
        if (!$skipGit && $this->vcs->isRepository()) {
            if (!$allowDirty && !$this->vcs->isWorkingDirectoryClean()) {
                $output->writeln(
                    '<error>Git working directory is not clean. Commit or stash changes first, or use --allow-dirty.</error>'
                );

                return self::FAILURE;
            }
        }

        // Step 2: 读取当前版本
        $currentVersion = $package->getPrettyVersion();

        $output->writeln(sprintf(
            'Current version: <comment>%s</comment>',
            $currentVersion
        ));

        // Step 3: 解析新版本
        $gitVersion = null;
        if ($bump === BumpType::FROM_GIT && $this->vcs->isRepository()) {
            $gitVersion = $this->vcs->getVersionFromTag();
        }

        try {
            $newVersionString = $this->resolver->resolve($bump, $preId, $currentVersion, $gitVersion);
        } catch (InvalidBumpException $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));

            return self::FAILURE;
        }

        // 版本未变化时警告
        if ($newVersionString === $currentVersion) {
            $output->writeln(
                '<warning>Version is unchanged. Nothing to do.</warning>'
            );

            return self::SUCCESS;
        }

        // Dry-run：仅输出计划
        if ($isDryRun) {
            $output->writeln(sprintf(
                'Would bump: <info>%s</info> → <info>%s</info>',
                $currentVersion,
                $newVersionString
            ));
            $output->writeln('<comment>Dry run completed. No changes were made.</comment>');

            return self::SUCCESS;
        }

        // Step 4: pre-version 脚本（旧版本可用）
        $this->dispatchScript(self::SCRIPT_PRE_VERSION, $package, $output);

        // Step 5: 持久化新版本到 composer.json（先备份，失败可回滚）
        $composerJsonPath = $this->getComposerJsonPath();
        $backupContent    = $this->readComposerJson($composerJsonPath);
        $this->writeComposerJson($composerJsonPath, $newVersionString);

        $output->writeln(sprintf(
            'Version bumped: <comment>%s</comment> → <info>%s</info>',
            $currentVersion,
            $newVersionString
        ));

        // Step 6: pre-version-commit 脚本（新版本可用）
        $this->dispatchScript(self::SCRIPT_PRE_VERSION_COMMIT, $package, $output);

        // Step 7: Git 提交与打标签（失败时回滚 composer.json）
        if (!$skipGit && $this->vcs->isRepository()) {
            try {
                $this->vcs->commitAndTag($newVersionString, $newVersionString);
                $output->writeln(sprintf(
                    'Created commit and tag: <info>v%s</info>',
                    $newVersionString
                ));
            } catch (GitException $e) {
                // 回滚 composer.json
                $this->restoreComposerJson($composerJsonPath, $backupContent);

                $output->writeln(sprintf(
                    '<error>Git operation failed: %s</error>',
                    $e->getMessage()
                ));
                $output->writeln(
                    '<warning>composer.json has been restored to its previous state.</warning>'
                );

                return self::FAILURE;
            }
        }

        // Step 8: post-version 脚本
        $this->dispatchScript(self::SCRIPT_POST_VERSION, $package, $output);

        return self::SUCCESS;
    }

    /**
     * 执行 Composer 脚本钩子。
     */
    private function dispatchScript(string $scriptName, RootPackageInterface $package, OutputInterface $output): void
    {
        $scripts = $package->getScripts();

        if (!isset($scripts[$scriptName])) {
            return;
        }

        $output->writeln(sprintf('> <comment>%s</comment>', $scriptName));

        $this->requireComposer()->getEventDispatcher()->dispatchScript(
            $scriptName,
            true,
        );
    }

    /**
     * 获取 composer.json 绝对路径。
     *
     * @return string
     */
    private function getComposerJsonPath(): string
    {
        return getcwd().DIRECTORY_SEPARATOR.'composer.json';
    }

    /**
     * 读取 composer.json 内容。
     *
     * @param string $path
     *
     * @return array
     * @throws ParsingException
     */
    private function readComposerJson(string $path): array
    {
        return (new JsonFile($path))->read();
    }

    /**
     * 写入 composer.json（更新 version 字段）。
     *
     * @param string $path
     * @param string $version
     *
     * @return void
     * @throws ParsingException
     */
    private function writeComposerJson(string $path, string $version): void
    {
        $json               = new JsonFile($path);
        $content            = $json->read();
        $content['version'] = $version;
        $json->write($content);
    }

    /**
     * 从备份恢复 composer.json。
     *
     * @param string $path
     * @param array  $content
     *
     * @return void
     * @throws Exception
     */
    private function restoreComposerJson(string $path, array $content): void
    {
        (new JsonFile($path))->write($content);
    }
}