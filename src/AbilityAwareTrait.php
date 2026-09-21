<?php

namespace Drupal\user_ability;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Generic can()/abilityTo() delegation to an AbilityCheckerInterface.
 *
 * A consumer supplies the checker and the account via the two abstract
 * methods below — this trait never assumes how either is obtained, so it
 * stays free of any dependency beyond Drupal core and this module.
 */
trait AbilityAwareTrait {

  /**
   * Describes this account's ability to perform an ability, cache-aware.
   *
   * @see \Drupal\user_ability\AbilityCheckerInterface::check()
   */
  public function abilityTo(AbilityInterface $ability, ?AbilityContextInterface $context = NULL): AccessResult {
    return $this->getAbilityChecker()->check($ability, $this->getAbilityAccount(), $context);
  }

  /**
   * Checks if this account can perform an ability (returns TRUE/FALSE).
   *
   * Discards cache metadata — see abilityTo() for anything render-layer.
   */
  public function can(AbilityInterface $ability, ?AbilityContextInterface $context = NULL): bool {
    return $this->abilityTo($ability, $context)->isAllowed();
  }

  /**
   * @return \Drupal\user_ability\AbilityCheckerInterface
   *   The checker to dispatch to.
   */
  abstract protected function getAbilityChecker(): AbilityCheckerInterface;

  /**
   * @return \Drupal\Core\Session\AccountInterface
   *   The account being asked about.
   */
  abstract protected function getAbilityAccount(): AccountInterface;

}
