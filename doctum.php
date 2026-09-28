<?php

use Doctum\Doctum;
use Doctum\RemoteRepository\GitHubRemoteRepository;
use Doctum\Version\GitVersionCollection;
use Symfony\Component\Finder\Finder;

$dir      = realpath('src');
$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->exclude('Resources')
    ->exclude('Tests')
    ->in($dir);
$versions = GitVersionCollection::create($dir)
    // In a non case-sensitive way, tags containing "PR", "RC", "BETA" and "ALPHA" will be filtered out
    // To change this, use: `$versions->setFilter(static function (string $version): bool { // ... });`
    // ->addFromTags('v1.0.0')
    // ->add('1.0', '1.0')
    ->add('main', 'main');

return new Doctum($iterator, [
    'versions'             => $versions,
    'title'                => 'Jundayw ComposerVersionPlugin Documentation',
    'language'             => 'en', // Could be 'fr'
    'build_dir'            => __DIR__ . '/build/docs/%version%',
    'cache_dir'            => __DIR__ . '/cache/docs/%version%',
    'source_dir'           => dirname($dir) . '/',
    'remote_repository'    => new GitHubRemoteRepository('jundayw/composer-version-plugin', dirname($dir)),
    // 'footer_link'          => [
    //     'href'        => 'https://github.com/jundayw/composer-version-plugin',
    //     'rel'         => 'noreferrer noopener',
    //     'target'      => '_blank',
    //     'before_text' => 'You can edit the configuration',
    //     'link_text'   => 'on this', // Required if the href key is set
    //     'after_text'  => 'repository',
    // ],
    'default_opened_level' => 2, // optional, 2 is the default value
]);
