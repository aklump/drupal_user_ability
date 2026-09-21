<?php

namespace Drupal\user_ability;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Answers whether an account has an ability.
 *
 * Type-hint this rather than AbilityChecker, so the service can be decorated
 * or replaced. Registering checks is the implementation's concern, not part
 * of this contract.
 */
interface AbilityCheckerInterface {

  /**
   * Decides whether the given account has the given ability.
   *
   * @param \Drupal\user_ability\AbilityInterface $ability
   *   The ability being asked about.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account asking. Normalized once to \Drupal\user\UserInterface
   *   before being handed to the check.
   * @param \Drupal\user_ability\AbilityContextInterface $context
   *   What is being asked about, beyond the account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The cache-aware access result, with $account's user entity added as a
   *   cacheable dependency whenever a check runs. AccessResult::forbidden()
   *   with no cache metadata when no check is registered for the ability;
   *   an uncacheable (max-age 0) AccessResult::forbidden() when the account
   *   no longer resolves to a real user. Both cases are logged, never
   *   thrown.
   */
  public function check(AbilityInterface $ability, AccountInterface $account, ?AbilityContextInterface $context = NULL): AccessResult;

}
