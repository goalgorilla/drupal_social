<?php

/**
 * @file
 */

declare(strict_types=1);

use Rector\Core\ValueObject\PhpVersion;
use DrupalFinder\DrupalFinder;
use Rector\Core\Configuration\Option;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php74\Rector\Property\TypedPropertyRector;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\FunctionLike\ParamTypeDeclarationRector;
use Rector\TypeDeclaration\Rector\FunctionLike\ReturnTypeDeclarationRector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromReturnNewRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictTypedCallRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictTypedPropertyRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;
return static function (ContainerConfigurator $containerConfigurator): void {
  // @todo find out how to only load the relevant rector rules.
  //   Should we try and load \Drupal::VERSION and check?
  $containerConfigurator->import(__DIR__ . '/vendor/palantirnet/drupal-rector/config/drupal-8/drupal-8-all-deprecations.php');
  $containerConfigurator->import(__DIR__ . '/vendor/palantirnet/drupal-rector/config/drupal-9/drupal-9-all-deprecations.php');
  $containerConfigurator->import(SetList::PHP_74);
  $parameters = $containerConfigurator->parameters();

  $drupalFinder = new DrupalFinder();
  $drupalFinder->locateRoot(__DIR__);
  $drupalRoot = $drupalFinder->getDrupalRoot();
  $parameters->set(Option::AUTOLOAD_PATHS, [
    $drupalRoot . '/core',
    $drupalRoot . '/modules',
    $drupalRoot . '/profiles',
    $drupalRoot . '/themes',
  ]);

  $parameters->set(Option::PHP_VERSION_FEATURES, PhpVersion::PHP_74);
  $parameters->set(Option::SKIP, ['*/upgrade_status/tests/modules/*']);
  $parameters->set(Option::FILE_EXTENSIONS, ['php', 'module', 'theme', 'install', 'profile', 'inc', 'engine']);
  $parameters->set(Option::AUTO_IMPORT_NAMES, TRUE);
  $parameters->set(Option::IMPORT_SHORT_CLASSES, FALSE);
  $parameters->set('drupal_rector_notices_as_comments', TRUE);
  $services = $containerConfigurator->services();
  $services->set(ReturnTypeFromReturnNewRector::class);
  $services->set(ReturnTypeFromStrictTypedPropertyRector::class);
  $services->set(ReturnTypeFromStrictTypedCallRector::class);
  $services->set(TypedPropertyFromStrictConstructorRector::class);
  $services->set(TypedPropertyRector::class);
  $services->set(ReturnTypeDeclarationRector::class);
  $services->set(ParamTypeDeclarationRector::class);
  $containerConfigurator->import(DoctrineSetList::DOCTRINE_CODE_QUALITY);
  // Auto import fully qualified class names? [default: false].
  $parameters->set(Option::AUTO_IMPORT_NAMES, TRUE);
  // Path to phpstan with extensions, that PHPSTan in Rector uses to determine types.
  $parameters->set(Option::PHPSTAN_FOR_RECTOR_PATH, getcwd() . '/html/profiles/contrib/social/phpstan.neon');
};
