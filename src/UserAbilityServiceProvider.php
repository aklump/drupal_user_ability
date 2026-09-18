<?php

namespace Drupal\user_ability;

use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\user_ability\DependencyInjection\Compiler\AbilityCheckCollectorPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Registers the ability_check-collecting compiler pass.
 */
class UserAbilityServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $container->addCompilerPass(new AbilityCheckCollectorPass());
  }

}
